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


def pick_interaction(rng):
    roll = rng.random()
    if roll < 0.6:
        return "click", 2.0
    if roll < 0.85:
        return "wishlist", 3.0
    return "purchase", 5.0


def main():
    parser = argparse.ArgumentParser(description="Generate synthetic user interactions for PasarKraft")
    parser.add_argument("--host", default=os.getenv("PK_DB_HOST", "sql204.infinityfree.com"))
    parser.add_argument("--user", default=os.getenv("PK_DB_USER", "if0_42022884"))
    parser.add_argument("--password", default=os.getenv("PK_DB_PASSWORD", ""))
    parser.add_argument("--database", default=os.getenv("PK_DB_NAME", "if0_42022884_pasarkraft_db"))
    parser.add_argument("--users", type=int, default=50)
    parser.add_argument("--interactions", type=int, default=200)
    parser.add_argument("--seed", type=int, default=42)
    args = parser.parse_args()

    rng = random.Random(args.seed)

    conn = get_connection(args)
    cursor = conn.cursor()

    user_ids = fetch_ids(cursor, "SELECT id FROM users WHERE role = 'buyer'")
    product_ids = fetch_ids(cursor, "SELECT id FROM products")

    if not user_ids or not product_ids:
        print("No users or products found. Seed users/products before generating interactions.")
        return

    if len(user_ids) > args.users:
        user_ids = rng.sample(user_ids, args.users)

    interactions = []
    for _ in range(args.interactions):
        uid = rng.choice(user_ids)
        pid = rng.choice(product_ids)
        interaction_type, interaction_value = pick_interaction(rng)
        interactions.append((uid, pid, interaction_type, interaction_value))

    insert_sql = (
        "INSERT INTO user_interactions (user_id, product_id, interaction_type, interaction_value) "
        "VALUES (%s, %s, %s, %s)"
    )
    cursor.executemany(insert_sql, interactions)
    conn.commit()

    print(
        f"Inserted {len(interactions)} interactions across {len(user_ids)} users at "
        f"{datetime.utcnow().isoformat()}Z"
    )


if __name__ == "__main__":
    main()
