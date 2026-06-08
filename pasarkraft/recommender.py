import argparse
import json
import os
from datetime import datetime
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import Request, urlopen

import numpy as np
import pandas as pd
import mysql.connector
from sklearn.decomposition import TruncatedSVD
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity


def get_connection(args):
    return mysql.connector.connect(
        host=args.host,
        user=args.user,
        password=args.password,
        database=args.database,
    )


def fetch_dataframe(cursor, query, params=None):
    cursor.execute(query, params or ())
    rows = cursor.fetchall()
    cols = [c[0] for c in cursor.description]
    return pd.DataFrame(rows, columns=cols)


def fetch_api_data(api_base, api_key):
    params = {'key': api_key} if api_key else {}
    url = api_base.rstrip('/') + '/api/recommender_data.php'
    if params:
        url = url + '?' + urlencode(params)

    # Add a lightweight bypass parameter some hosts use to avoid JS challenge
    if 'i=' not in url:
        sep = '&' if '?' in url else '?'
        url = url + sep + 'i=1'
    # Use a browser-like User-Agent and common headers to avoid hosting provider bot protections
    headers = {
        'Accept': 'application/json, text/javascript, */*; q=0.01',
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36',
        'Accept-Language': 'en-US,en;q=0.9',
        'Referer': api_base.rstrip('/') + '/',
        'X-Requested-With': 'XMLHttpRequest'
    }
    req = Request(url, headers=headers)
    with urlopen(req) as resp:
        raw = resp.read().decode('utf-8', errors='replace')
    try:
        payload = json.loads(raw)
    except json.JSONDecodeError as exc:
        snippet = raw[:300].strip()
        raise SystemExit(f"API returned non-JSON response: {snippet}") from exc
    products_df = pd.DataFrame(payload.get('products', []))
    interactions_df = pd.DataFrame(payload.get('interactions', []))
    return products_df, interactions_df


def post_api_results(api_base, api_key, payload):
    url = api_base.rstrip('/') + '/api/recommender_write.php'
    if api_key:
        url = url + '?' + urlencode({'key': api_key})
    data = json.dumps(payload).encode('utf-8')
    req = Request(url, data=data, headers={'Content-Type': 'application/json'})
    with urlopen(req) as resp:
        return json.loads(resp.read().decode('utf-8'))


def load_input_json(path):
    with open(path, 'r', encoding='utf-8') as handle:
        payload = json.load(handle)
    products_df = pd.DataFrame(payload.get('products', []))
    interactions_df = pd.DataFrame(payload.get('interactions', []))
    return products_df, interactions_df


def build_product_metadata(products_df):
    columns = [
        "title",
        "description",
        "category",
        "subcategory",
        "technique",
        "color",
        "material",
        "style",
        "tags",
    ]
    products_df = products_df.copy()
    for col in columns:
        if col not in products_df.columns:
            products_df[col] = ""
    products_df[columns] = products_df[columns].fillna("")
    products_df["metadata"] = products_df[columns].agg(" ".join, axis=1)
    return products_df


def content_based_scores(products_df, interactions_df, user_id):
    if products_df.empty:
        return pd.Series(dtype=float)

    vectorizer = TfidfVectorizer(stop_words="english", max_features=5000)
    tfidf_matrix = vectorizer.fit_transform(products_df["metadata"].values)

    user_rows = interactions_df[interactions_df["user_id"] == user_id]
    if user_rows.empty:
        return pd.Series(np.zeros(len(products_df)), index=products_df["id"])

    weight_map = user_rows.set_index("product_id")["interaction_value"].to_dict()
    weights = np.array([weight_map.get(pid, 0.0) for pid in products_df["id"]])
    if weights.sum() == 0:
        return pd.Series(np.zeros(len(products_df)), index=products_df["id"])

    user_profile = weights @ tfidf_matrix
    sims = cosine_similarity(user_profile, tfidf_matrix).flatten()
    return pd.Series(sims, index=products_df["id"])


def collaborative_scores(products_df, interactions_df, user_id):
    if products_df.empty or interactions_df.empty:
        return pd.Series(np.zeros(len(products_df)), index=products_df["id"])

    matrix_df = interactions_df.pivot_table(
        index="user_id",
        columns="product_id",
        values="interaction_value",
        fill_value=0.0,
    )

    if user_id not in matrix_df.index:
        return pd.Series(np.zeros(len(products_df)), index=products_df["id"])

    n_users, n_items = matrix_df.shape
    if n_users < 2 or n_items < 2:
        return pd.Series(np.zeros(len(products_df)), index=products_df["id"])

    n_components = min(10, n_users - 1, n_items - 1)
    if n_components < 1:
        return pd.Series(np.zeros(len(products_df)), index=products_df["id"])

    svd = TruncatedSVD(n_components=n_components, random_state=42)
    user_factors = svd.fit_transform(matrix_df.values)
    item_factors = svd.components_
    reconstructed = np.dot(user_factors, item_factors)

    user_idx = matrix_df.index.get_loc(user_id)
    scores = reconstructed[user_idx]
    scores = pd.Series(scores, index=matrix_df.columns)

    # Normalize to 0..1
    if scores.max() > scores.min():
        scores = (scores - scores.min()) / (scores.max() - scores.min())
    else:
        scores = scores * 0.0

    return scores.reindex(products_df["id"], fill_value=0.0)


def hybrid_scores(products_df, interactions_df, user_id):
    content = content_based_scores(products_df, interactions_df, user_id)
    collab = collaborative_scores(products_df, interactions_df, user_id)

    has_content = content.sum() > 0
    has_collab = collab.sum() > 0

    if not has_content and not has_collab:
        # Fallback to popularity by interactions
        popularity = interactions_df.groupby("product_id")["interaction_value"].mean()
        popularity = popularity.reindex(products_df["id"], fill_value=0.0)
        if popularity.max() > popularity.min():
            popularity = (popularity - popularity.min()) / (popularity.max() - popularity.min())
        return popularity

    if has_content and has_collab:
        content_weight = 0.5
        collab_weight = 0.5
    elif has_content:
        content_weight = 1.0
        collab_weight = 0.0
    else:
        content_weight = 0.0
        collab_weight = 1.0

    return (content_weight * content) + (collab_weight * collab)


def main():
    parser = argparse.ArgumentParser(description="PasarKraft Python Recommender")
    parser.add_argument("--host", default=os.getenv("PK_DB_HOST", "sql204.infinityfree.com"))
    parser.add_argument("--user", default=os.getenv("PK_DB_USER", "if0_42022884"))
    parser.add_argument("--password", default=os.getenv("PK_DB_PASSWORD", ""))
    parser.add_argument("--database", default=os.getenv("PK_DB_NAME", "if0_42022884_pasarkraft_db"))
    parser.add_argument("--api-base", default=os.getenv("PK_API_BASE", ""))
    parser.add_argument("--api-key", default=os.getenv("PK_API_KEY", ""))
    parser.add_argument("--input-json", default="")
    parser.add_argument("--output-json", default="")
    parser.add_argument("--user-id", type=int, required=True)
    parser.add_argument("--limit", type=int, default=8)
    parser.add_argument("--write-db", action="store_true")
    args = parser.parse_args()

    if args.input_json:
        products_df, interactions_df = load_input_json(args.input_json)
    elif args.api_base:
        try:
            products_df, interactions_df = fetch_api_data(args.api_base, args.api_key)
        except (HTTPError, URLError) as exc:
            raise SystemExit(f"API fetch failed: {exc}")
    else:
        conn = get_connection(args)
        cursor = conn.cursor()
        products_df = fetch_dataframe(
            cursor,
            "SELECT id, title, description, category, subcategory, technique, color, material, style, tags, stock FROM products",
        )
        interactions_df = fetch_dataframe(
            cursor,
            "SELECT user_id, product_id, interaction_value FROM user_interactions",
        )

    products_df = build_product_metadata(products_df)

    scores = hybrid_scores(products_df, interactions_df, args.user_id)

    interacted_products = set(
        interactions_df[interactions_df["user_id"] == args.user_id]["product_id"].tolist()
    )

    results = (
        pd.DataFrame({"product_id": scores.index, "score": scores.values})
        .merge(products_df[["id", "stock"]], left_on="product_id", right_on="id", how="left")
        .drop(columns=["id"])
    )
    results = results[results["stock"].fillna(0) > 0]
    results = results[~results["product_id"].isin(interacted_products)]
    results = results.sort_values(by="score", ascending=False).head(args.limit)

    output = {
        "user_id": args.user_id,
        "generated_at": datetime.utcnow().isoformat() + "Z",
        "recommendations": [
            {"product_id": int(row.product_id), "score": float(row.score)}
            for row in results.itertuples(index=False)
        ],
    }

    if args.output_json:
        with open(args.output_json, 'w', encoding='utf-8') as handle:
            json.dump(output, handle, indent=2)

    if args.write_db:
        if args.api_base:
            payload = {
                'user_id': args.user_id,
                'recommendation_type': 'hybrid_py',
                'recommendations': output['recommendations']
            }
            try:
                post_api_results(args.api_base, args.api_key, payload)
            except (HTTPError, URLError) as exc:
                raise SystemExit(f"API write failed: {exc}")
        else:
            cursor.execute(
                "DELETE FROM recommendations WHERE user_id = %s AND recommendation_type = 'hybrid_py'",
                (args.user_id,),
            )
            for item in output["recommendations"]:
                cursor.execute(
                    "INSERT INTO recommendations (user_id, product_id, score, recommendation_type) VALUES (%s, %s, %s, 'hybrid_py')",
                    (args.user_id, item["product_id"], item["score"]),
                )
            conn.commit()

    if not args.api_base:
        cursor.close()
        conn.close()

    print(json.dumps(output, indent=2))


if __name__ == "__main__":
    main()
