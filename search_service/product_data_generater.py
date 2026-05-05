import random
import psycopg2
from datetime import datetime

DB_HOST = "localhost"
DB_PORT = 5432
DB_NAME = "app"
DB_USER = "user"
DB_PASSWORD = "password"

PRODUCT_COUNT = 100

WORDS = ["alpha", "beta", "gamma", "delta", "omega", "nova", "prime", "basic", "smart", "pro"]


def random_name() -> str:
    return " ".join(random.sample(WORDS, k=random.randint(2, 4))).title()


def main():
    conn = psycopg2.connect(
        host=DB_HOST,
        port=DB_PORT,
        dbname=DB_NAME,
        user=DB_USER,
        password=DB_PASSWORD,
    )

    cur = conn.cursor()
    now = datetime.now()

    for i in range(1, PRODUCT_COUNT + 1):
        sku = f"SKU{i:03d}"
        image = f"sku{i:03d}.jpg"

        cur.execute(
            """
            INSERT INTO product (
                name,
                description,
                number_in_stock,
                image_urls,
                created_at,
                updated_at,
                width,
                height,
                length,
                weight,
                material,
                price,
                hidden,
                discount,
                attributes,
                version,
                sku,
                tax_rate,
                category_id,
                size_id,
                color_id,
                currency_id
            ) VALUES (
                %s, %s, %s, %s::json, %s, %s,
                %s, %s, %s, %s,
                %s, %s, %s, %s,
                %s::json, %s, %s, %s,
                %s, %s, %s, %s
            )
            """,
            (
                random_name(),
                random_name() + " product description",
                random.randint(1, 200),
                f'["{image}"]',
                now,
                now,
                round(random.uniform(5, 100), 2),
                round(random.uniform(5, 100), 2),
                round(random.uniform(5, 100), 2),
                round(random.uniform(0.1, 10), 2),
                random.choice(["cotton", "plastic", "metal", "wood"]),
                round(random.uniform(50, 5000), 2),
                False,
                100.0,
                "{}",
                1,
                sku,
                21.0,
                random.randint(1, 2),
                None,
                random.randint(1, 2),
                1,
            ),
        )

    conn.commit()
    cur.close()
    conn.close()

    print(f"Inserted {PRODUCT_COUNT} products.")


if __name__ == "__main__":
    main()