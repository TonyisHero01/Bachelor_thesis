import json
import os
import random
import re
from datetime import datetime, timedelta

import psycopg2
import pyarrow.parquet as pq
import requests


SEARCH_URL = os.getenv("SEARCH_URL", "http://search-service:8000").rstrip("/")
SEARCH_API_KEY = os.getenv("SEARCH_API_KEY")

ESCI_PRODUCTS_PATH = os.getenv(
    "ESCI_PRODUCTS_PATH",
    "/app/esci-data/shopping_queries_dataset/shopping_queries_dataset_products.parquet",
)

PRODUCT_COUNT = int(os.getenv("ESCI_PRODUCT_COUNT", "5000"))

DB_HOST = "db"
DB_PORT = 5432
DB_NAME = "app"
DB_USER = "user"
DB_PASSWORD = "password"


COLORS = {
    "white": ("White", "#ffffff"),
    "black": ("Black", "#000000"),
    "blue": ("Blue", "#2563eb"),
    "red": ("Red", "#dc2626"),
    "gray": ("Gray", "#6b7280"),
    "grey": ("Gray", "#6b7280"),
    "green": ("Green", "#16a34a"),
    "yellow": ("Yellow", "#eab308"),
    "pink": ("Pink", "#ec4899"),
    "brown": ("Brown", "#92400e"),
    "silver": ("Silver", "#c0c0c0"),
}

SIZES = ["XS", "S", "M", "L", "XL", "XXL"]


def connect():
    return psycopg2.connect(
        host=DB_HOST,
        port=DB_PORT,
        dbname=DB_NAME,
        user=DB_USER,
        password=DB_PASSWORD,
    )


def clean_text(value, max_len=None):
    if value is None:
        return None

    text = str(value).strip()

    if text == "" or text.lower() == "nan":
        return None

    text = re.sub(r"\s+", " ", text)

    if max_len and len(text) > max_len:
        return text[:max_len - 3] + "..."

    return text


def guess_category(title, description):
    text = f"{title or ''} {description or ''}".lower()

    rules = [
        ("Smartphones", ["iphone", "phone", "smartphone", "galaxy", "pixel", "xiaomi", "mobile"]),
        ("Laptops", ["laptop", "notebook", "macbook", "chromebook", "thinkpad"]),
        ("Keyboards", ["keyboard", "keycap", "mechanical"]),
        ("Mice", ["mouse", "mice"]),
        ("Headphones", ["headphone", "headset", "earbuds", "earphones", "bluetooth audio"]),
        ("Monitors", ["monitor", "display", "screen"]),
        ("T-Shirts", ["t-shirt", "shirt", "tee"]),
        ("Jackets", ["jacket", "coat", "hoodie", "sweater"]),
        ("Shoes", ["shoes", "sneaker", "boot", "footwear"]),
        ("Accessories", ["case", "bag", "cable", "charger", "adapter", "cover"]),
    ]

    for category, keywords in rules:
        if any(keyword in text for keyword in keywords):
            return category

    return "Accessories"


def guess_material(title, description):
    text = f"{title or ''} {description or ''}".lower()

    materials = [
        "cotton", "leather", "plastic", "aluminum", "metal",
        "rubber", "polyester", "nylon", "glass", "wood",
    ]

    for material in materials:
        if material in text:
            return material

    return random.choice(["plastic", "metal", "cotton", "polyester"])


def normalize_color(raw_color, title):
    text = f"{raw_color or ''} {title or ''}".lower()

    for key, value in COLORS.items():
        if key in text:
            return value

    return ("Other", "#999999")


def guess_size(title):
    text = f" {title or ''} ".upper()

    for size in ["XXL", "XL", "XS", "L", "M", "S"]:
        if f" {size} " in text or f"({size})" in text:
            return size

    return None


def ensure_currency(cur):
    cur.execute("SELECT id FROM currency ORDER BY id ASC LIMIT 1")
    row = cur.fetchone()

    if row:
        return row[0]

    cur.execute(
        """
        INSERT INTO currency (name, value, is_default)
        VALUES ('CZK', 1, true)
        RETURNING id
        """
    )

    return cur.fetchone()[0]


def clear_data(cur):
    cur.execute("DELETE FROM customer_product_view_log;")
    cur.execute("DELETE FROM customer_search_log;")
    cur.execute("DELETE FROM order_items;")
    cur.execute("DELETE FROM orders;")
    cur.execute("DELETE FROM cart;")
    cur.execute("DELETE FROM product_document_vector;")
    cur.execute("DELETE FROM product_semantic_vector;")
    cur.execute("DELETE FROM product_translation;")
    cur.execute("DELETE FROM product;")
    cur.execute("DELETE FROM color_translation;")
    cur.execute("DELETE FROM category_translation;")
    cur.execute("DELETE FROM category;")
    cur.execute('DELETE FROM productcolor;')
    cur.execute('DELETE FROM size;')
    cur.execute("DELETE FROM customer;")


def insert_categories(cur):
    names = [
        "Laptops",
        "Smartphones",
        "Keyboards",
        "Mice",
        "Headphones",
        "Monitors",
        "T-Shirts",
        "Jackets",
        "Shoes",
        "Accessories",
    ]

    result = {}

    for name in names:
        cur.execute(
            """
            INSERT INTO category (name)
            VALUES (%s)
            RETURNING id
            """,
            (name,),
        )
        result[name] = cur.fetchone()[0]

    return result


def insert_colors(cur):
    result = {}

    unique_colors = {
        "White": "#ffffff",
        "Black": "#000000",
        "Blue": "#2563eb",
        "Red": "#dc2626",
        "Gray": "#6b7280",
        "Green": "#16a34a",
        "Yellow": "#eab308",
        "Pink": "#ec4899",
        "Brown": "#92400e",
        "Silver": "#c0c0c0",
        "Other": "#999999",
    }

    for name, hex_value in unique_colors.items():
        cur.execute(
            """
            INSERT INTO productcolor (name, hex)
            VALUES (%s, %s)
            RETURNING id
            """,
            (name, hex_value),
        )
        result[name] = cur.fetchone()[0]

    return result


def insert_sizes(cur):
    result = {}

    for name in SIZES:
        cur.execute(
            """
            INSERT INTO size (name)
            VALUES (%s)
            RETURNING id
            """,
            (name,),
        )
        result[name] = cur.fetchone()[0]

    return result


def iter_us_products(limit):
    parquet_file = pq.ParquetFile(ESCI_PRODUCTS_PATH)

    collected = 0

    for batch in parquet_file.iter_batches(
        batch_size=5000,
        columns=[
            "product_id",
            "product_title",
            "product_description",
            "product_brand",
            "product_color",
            "product_locale",
        ],
    ):
        df = batch.to_pandas()
        df = df[df["product_locale"] == "us"]

        for _, row in df.iterrows():
            title = clean_text(row.get("product_title"), 255)

            if not title:
                continue

            yield row
            collected += 1

            if collected >= limit:
                return


def insert_products(cur, category_ids, color_ids, size_ids, currency_id):
    products = []
    now = datetime.now()

    count = 0

    for row in iter_us_products(PRODUCT_COUNT):
        amazon_id = clean_text(row.get("product_id"), 255)
        title = clean_text(row.get("product_title"), 255)
        description = clean_text(row.get("product_description"), 255)
        brand = clean_text(row.get("product_brand"), 255)
        raw_color = clean_text(row.get("product_color"), 255)

        if not amazon_id or not title:
            continue

        category = guess_category(title, description)
        material = guess_material(title, description)

        color_name, _ = normalize_color(raw_color, title)
        color_id = color_ids.get(color_name, color_ids["Other"])

        size_name = guess_size(title)
        size_id = size_ids.get(size_name) if size_name else None

        price = round(random.uniform(5, 1500), 2)
        stock = random.randint(0, 250)

        attributes = {
            "source": "amazon_esci",
            "amazon_product_id": amazon_id,
            "brand": brand,
            "original_color": raw_color,
        }

        sku = amazon_id
        image = f"{amazon_id}.jpg"

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
                %s, %s, false, %s,
                %s::json, 1, %s, 21,
                %s, %s, %s, %s
            )
            RETURNING id
            """,
            (
                title,
                description,
                stock,
                json.dumps([image]),
                now - timedelta(days=random.randint(0, 120)),
                now,
                round(random.uniform(5, 100), 2),
                round(random.uniform(5, 100), 2),
                round(random.uniform(5, 100), 2),
                round(random.uniform(0.1, 10), 2),
                material,
                price,
                100.0,
                json.dumps(attributes),
                sku,
                category_ids[category],
                size_id,
                color_id,
                currency_id,
            ),
        )

        product_id = cur.fetchone()[0]

        products.append({
            "id": product_id,
            "sku": sku,
            "name": title,
            "category": category,
        })

        count += 1

        if count % 500 == 0:
            print(f"Inserted products: {count}")

    return products


def insert_customers(cur):
    customers = []

    for i in range(1, 81):
        email = f"customer{i:03d}@example.com"

        cur.execute(
            """
            INSERT INTO customer (
                email,
                password_hash,
                created_at,
                is_verified,
                wishlist
            ) VALUES (
                %s,
                %s,
                %s,
                true,
                %s::json
            )
            RETURNING id
            """,
            (
                email,
                "benchmark-password-hash",
                datetime.now() - timedelta(days=random.randint(1, 200)),
                json.dumps([]),
            ),
        )

        customers.append(cur.fetchone()[0])

    return customers


def insert_simple_behavior(cur, customers, products):
    now = datetime.now()

    for customer_id in customers:
        sample_products = random.sample(
            products,
            k=min(len(products), random.randint(8, 20)),
        )

        wishlist_ids = [p["id"] for p in sample_products[:8]]

        cur.execute(
            """
            UPDATE customer
            SET wishlist = %s::json
            WHERE id = %s
            """,
            (json.dumps(wishlist_ids), customer_id),
        )

        for product in sample_products:
            cur.execute(
                """
                INSERT INTO customer_product_view_log (
                    customer_id,
                    product_id,
                    sku,
                    session_id,
                    viewed_at
                ) VALUES (
                    %s, %s, %s, %s, %s
                )
                """,
                (
                    customer_id,
                    product["id"],
                    product["sku"],
                    f"session-{customer_id}",
                    now - timedelta(minutes=random.randint(1, 100000)),
                ),
            )


def post_json(path, payload=None, timeout=300):
    headers = {"Content-Type": "application/json"}

    if SEARCH_API_KEY:
        headers["X-API-KEY"] = SEARCH_API_KEY

    response = requests.post(
        f"{SEARCH_URL}{path}",
        headers=headers,
        json=payload or {},
        timeout=timeout,
    )

    print(path, response.status_code, response.text[:300])


def run_reindex():
    post_json(
        "/reindex",
        {
            "mode": "full",
            "reason": "amazon_esci_import",
            "context": {
                "product_count": PRODUCT_COUNT,
            },
        },
        timeout=300,
    )

    post_json("/semantic/reindex", {}, timeout=1200)


def main():
    conn = connect()
    cur = conn.cursor()

    print("Clearing old data...")
    clear_data(cur)

    print("Ensuring currency...")
    currency_id = ensure_currency(cur)

    print("Creating categories...")
    category_ids = insert_categories(cur)

    print("Creating colors...")
    color_ids = insert_colors(cur)

    print("Creating sizes...")
    size_ids = insert_sizes(cur)

    print(f"Importing {PRODUCT_COUNT} Amazon ESCI US products...")
    products = insert_products(
        cur,
        category_ids,
        color_ids,
        size_ids,
        currency_id,
    )

    print("Creating benchmark customers...")
    customers = insert_customers(cur)

    print("Creating simple customer behavior...")
    insert_simple_behavior(cur, customers, products)

    conn.commit()

    cur.close()
    conn.close()

    print("Running TF-IDF and semantic reindex...")
    run_reindex()

    print("Done.")
    print(f"Products imported: {len(products)}")


if __name__ == "__main__":
    main()
