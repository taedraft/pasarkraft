import argparse
import json
import os
from datetime import datetime
from urllib.parse import urlencode
from urllib.request import Request, urlopen

import pandas as pd

from recommender import build_product_metadata, fetch_api_data, hybrid_scores


def fetch_buyers(api_base, api_key):
    params = {'key': api_key} if api_key else {}
    url = api_base.rstrip('/') + '/api/recommender_data.php'
    if params:
        url = url + '?' + urlencode(params)

    # Some hosts inject JS to block non-browser clients; add small bypass flag
    if 'i=' not in url:
        sep = '&' if '?' in url else '?'
        url = url + sep + 'i=1'

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
    except json.JSONDecodeError:
        raise SystemExit(f"API returned non-JSON response: {raw[:200]}")

    buyers = payload.get('buyers', [])
    return [int(uid) for uid in buyers if str(uid).isdigit()]


def build_user_recommendations(products_df, interactions_df, user_id, limit):
    scores = hybrid_scores(products_df, interactions_df, user_id)

    interacted_products = set(
        interactions_df[interactions_df['user_id'] == user_id]['product_id'].tolist()
        if not interactions_df.empty
        else []
    )

    results = (
        pd.DataFrame({'product_id': scores.index, 'score': scores.values})
        .merge(products_df[['id', 'stock']], left_on='product_id', right_on='id', how='left')
        .drop(columns=['id'])
    )
    results = results[results['stock'].fillna(0) > 0]
    results = results[~results['product_id'].isin(interacted_products)]
    results = results.sort_values(by='score', ascending=False).head(limit)

    return [
        {'product_id': int(row.product_id), 'score': float(row.score)}
        for row in results.itertuples(index=False)
    ]


def post_bulk_results(api_base, api_key, payload):
    params = {'key': api_key} if api_key else {}
    url = api_base.rstrip('/') + '/api/recommender_bulk_write.php'
    if params:
        url = url + '?' + urlencode(params)

    body = json.dumps(payload).encode('utf-8')
    req = Request(url, data=body, headers={'Content-Type': 'application/json'})
    with urlopen(req) as resp:
        return json.loads(resp.read().decode('utf-8'))


def main():
    parser = argparse.ArgumentParser(description='Daily PasarKraft recommender sync via API')
    parser.add_argument('--api-base', default=os.getenv('PK_API_BASE', ''), required=False)
    parser.add_argument('--api-key', default=os.getenv('PK_API_KEY', ''), required=False)
    parser.add_argument('--input-json', default='', required=False,
                        help='Path to local export JSON (skip API fetch)')
    parser.add_argument('--limit', type=int, default=8)
    parser.add_argument('--recommendation-type', default='hybrid_py_daily')
    parser.add_argument('--output-json', default='')
    args = parser.parse_args()

    if not args.api_base:
        raise SystemExit('Missing --api-base. Example: --api-base http://pasarkraft.xo.je/')

    if args.input_json:
        products_df, interactions_df = load_input_json(args.input_json)
        products_df = build_product_metadata(products_df)
        buyers = sorted(interactions_df['user_id'].astype(int).unique().tolist()) if not interactions_df.empty else []
    else:
        products_df, interactions_df = fetch_api_data(args.api_base, args.api_key)
        products_df = build_product_metadata(products_df)

        buyers = fetch_buyers(args.api_base, args.api_key)
    if not buyers and not interactions_df.empty:
        buyers = sorted(interactions_df['user_id'].astype(int).unique().tolist())

    runs = []
    for user_id in buyers:
        recs = build_user_recommendations(products_df, interactions_df, int(user_id), args.limit)
        runs.append({'user_id': int(user_id), 'recommendations': recs})

    payload = {
        'generated_at': datetime.utcnow().isoformat() + 'Z',
        'recommendation_type': args.recommendation_type,
        'runs': runs,
    }

    if args.output_json:
        with open(args.output_json, 'w', encoding='utf-8') as handle:
            json.dump(payload, handle, indent=2)

    response = post_bulk_results(args.api_base, args.api_key, payload)
    print(json.dumps({'status': 'ok', 'buyers': len(buyers), 'api_response': response}, indent=2))


if __name__ == '__main__':
    main()
