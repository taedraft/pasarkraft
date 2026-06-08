import argparse
import os
import random
from datetime import datetime

import mysql.connector


def get_connection(args):
    return mysql.connector.connect(
        host=args.host,
        user=args.user,
        password=args.password,
        database=args.database,
    )


def fetch_ids(cursor, query):
    cursor.execute(query)
    return [row[0] for row in cursor.fetchall()]


def main():
    parser = argparse.ArgumentParser(description="Generate synthetic user interactions for PasarKraft")
    parser.add_argument("--host", default=os.getenv("PK_DB_HOST", "sql204.infinityfree.com"))
    parser.add_argument("--user", default=os.getenv("PK_DB_USER", "if0_42022884"))
    parser.add_argument("--password", default=os.getenv("PK_DB_PASSWORD", ""))
    parser.add_argument("--database", default=os.getenv("PK_DB_NAME", "if0_42022884_pasarkraft_db"))
    parser.add_argument("--users", type=int, default=50)
    parser.add_argument("--products", type=int, default=100)
    parser.add_argument("--min-interactions", type=int, default=8)
    parser.add_argument("--max-interactions", type=int, default=20)
    parser.add_argument("--seed", type=int, default=42)
    args = parser.parse_args()

    random.seed(args.seed)

    conn = get_connection(args)
    cursor = conn.cursor()

    user_ids = fetch_ids(cursor, "SELECT id FROM users WHERE role = 'buyer'")
    product_rows = []
    cursor.execute("SELECT id, category FROM products")
    for pid, category in cursor.fetchall():
        product_rows.append((pid, category or ""))

    if not user_ids or not product_rows:
        print("No users or products found. Seed users/products before generating interactions.")
        return

    if len(user_ids) > args.users:
        user_ids = random.sample(user_ids, args.users)

    if len(product_rows) > args.products:
        product_rows = random.sample(product_rows, args.products)

    batik_ids = [pid for pid, cat in product_rows if cat.lower() == "batik"]
    wood_ids = [pid for pid, cat in product_rows if cat.lower() == "woodcraft"]
    all_ids = [pid for pid, _ in product_rows]

    def pick_product():
        if batik_ids and wood_ids:
            pool = batik_ids if random.random() < 0.5 else wood_ids
            return random.choice(pool)
        return random.choice(all_ids)

    interactions = []
    for uid in user_ids:
        total = random.randint(args.min_interactions, args.max_interactions)
        for _ in range(total):
            pid = pick_product()
            if random.random() < 0.3:
                interactions.append((uid, pid, "wishlist", 3.0))
            else:
                interactions.append((uid, pid, "click", 2.0))

    insert_sql = (
        "INSERT INTO user_interactions (user_id, product_id, interaction_type, interaction_value) "
        "VALUES (%s, %s, %s, %s)"
    )
    cursor.executemany(insert_sql, interactions)
    conn.commit()

    print(
        f"Inserted {len(interactions)} interactions for {len(user_ids)} users and "
        f"{len(product_rows)} products at {datetime.utcnow().isoformat()}Z"
    )


if __name__ == "__main__":
    main()
