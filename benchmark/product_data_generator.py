import json
import random
import psycopg2
from datetime import datetime, timedelta
import os
import requests
import pandas as pd
import pyarrow.parquet as pq

SEARCH_URL = os.getenv("SEARCH_URL", "http://search-service:8000").rstrip("/")
SEARCH_API_KEY = os.getenv("SEARCH_API_KEY")

DB_HOST = "db"
DB_PORT = 5432
DB_NAME = "app"
DB_USER = "user"
DB_PASSWORD = "password"

PRODUCT_COUNT = 5000
CUSTOMER_COUNT = 80
ORDER_COUNT = 300
VIEW_LOG_COUNT = 1500
SEARCH_LOG_COUNT = 800


CATEGORIES = [
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

COLORS = [
    ("White", "#ffffff"),
    ("Black", "#000000"),
    ("Blue", "#2563eb"),
    ("Red", "#dc2626"),
    ("Gray", "#6b7280"),
]

SIZES = ["S", "M", "L", "XL"]

MATERIALS = {
    "Laptops": ["aluminum", "plastic", "magnesium"],
    "Smartphones": ["glass", "aluminum", "plastic"],
    "Keyboards": ["plastic", "aluminum"],
    "Mice": ["plastic", "rubber"],
    "Headphones": ["plastic", "leather", "metal"],
    "Monitors": ["plastic", "metal"],
    "T-Shirts": ["cotton", "polyester"],
    "Jackets": ["cotton", "polyester", "denim", "leather"],
    "Shoes": ["leather", "textile", "rubber"],
    "Accessories": ["plastic", "nylon", "metal"],
}

SEARCH_QUERIES = [
    "computer for playing games",
    "portable computer for work",
    "device for typing",
    "screen for gaming",
    "phone for taking photos",
    "small mobile device",
    "comfortable shoes for running",
    "warm clothes for winter",
    "casual cotton clothing",
    "bag for carrying a laptop",
]

CATEGORY_KEYWORDS = {
    "Laptops": ["laptop", "notebook", "computer", "pc", "macbook"],
    "Smartphones": ["phone", "smartphone", "iphone", "galaxy", "mobile"],
    "Keyboards": ["keyboard", "typing"],
    "Mice": ["mouse", "mice"],
    "Headphones": ["headphones", "earbuds", "headset"],
    "Monitors": ["monitor", "screen", "display"],
    "T-Shirts": ["t shirt", "t-shirt", "shirt", "tee"],
    "Jackets": ["jacket", "coat", "hoodie", "winter"],
    "Shoes": ["shoes", "sneakers", "boots"],
    "Accessories": ["case", "cable", "charger", "adapter", "bag", "sleeve", "hub"],
}

ESCI_BASE_DIR = os.getenv(
    "ESCI_BASE_DIR",
    "/app/esci-data/shopping_queries_dataset",
)

ESCI_EXAMPLES_PATH = os.path.join(
    ESCI_BASE_DIR,
    "shopping_queries_dataset_examples.parquet",
)

ESCI_PRODUCTS_PATH = os.path.join(
    ESCI_BASE_DIR,
    "shopping_queries_dataset_products.parquet",
)

ESCI_QUERY_LIMIT = int(os.getenv("ESCI_QUERY_LIMIT", "300"))
ESCI_PRODUCTS_PER_QUERY = int(os.getenv("ESCI_PRODUCTS_PER_QUERY", "25"))

def pick_even(items, index):
    return items[(index - 1) % len(items)]

def connect():
    return psycopg2.connect(
        host=DB_HOST,
        port=DB_PORT,
        dbname=DB_NAME,
        user=DB_USER,
        password=DB_PASSWORD,
    )

def guess_category(text: str):
    text = str(text or "").lower()

    for category, keywords in CATEGORY_KEYWORDS.items():
        for keyword in keywords:
            if keyword in text:
                return category

    return None

def load_esci_product_category_map():
    print("Loading ESCI examples...")

    df = pd.read_parquet(
        ESCI_EXAMPLES_PATH,
        columns=[
            "query",
            "product_id",
            "product_locale",
            "esci_label",
            "split",
        ],
    )

    df = df[
        (df["product_locale"] == "us")
        & (df["split"] == "test")
        & (df["esci_label"].isin(["E", "S", "C"]))
    ]

    product_category_map = {}
    selected_queries = 0

    for query, group in df.groupby("query"):
        category = guess_category(query)

        if not category:
            continue

        relevant_group = group[group["esci_label"].isin(["E", "S"])]

        if len(relevant_group) == 0:
            continue

        query_product_ids = (
            group["product_id"]
            .dropna()
            .astype(str)
            .drop_duplicates()
            .head(ESCI_PRODUCTS_PER_QUERY)
            .tolist()
        )

        if not query_product_ids:
            continue

        for product_id in query_product_ids:
            if product_id not in product_category_map:
                product_category_map[product_id] = category

        selected_queries += 1

        if selected_queries >= ESCI_QUERY_LIMIT:
            break

        if len(product_category_map) >= PRODUCT_COUNT:
            break

    print(f"Selected ESCI queries: {selected_queries}")
    print(f"Selected ESCI product ids: {len(product_category_map)}")

    return product_category_map

def load_esci_products_by_ids(product_category_map: dict[str, str]):
    print("Loading selected ESCI products...")

    product_ids = {
        str(product_id)
        for product_id in product_category_map.keys()
        if product_id
    }

    if not product_ids:
        return []

    parquet_file = pq.ParquetFile(ESCI_PRODUCTS_PATH)

    columns = [
        "product_id",
        "product_title",
        "product_description",
        "product_bullet_point",
        "product_brand",
        "product_color",
        "product_locale",
    ]

    products = []

    for row_group_index in range(parquet_file.num_row_groups):
        table = parquet_file.read_row_group(
            row_group_index,
            columns=columns,
        )

        df = table.to_pandas()

        df = df[
            (df["product_locale"] == "us")
            & (df["product_id"].astype(str).isin(product_ids))
        ]

        if df.empty:
            continue

        for _, row in df.iterrows():
            sku = str(row["product_id"]).strip()

            if not sku:
                continue

            category = product_category_map.get(sku)

            if not category:
                continue

            title = str(row.get("product_title") or "").strip()
            description = str(row.get("product_description") or "").strip()
            bullet_point = str(row.get("product_bullet_point") or "").strip()
            brand = str(row.get("product_brand") or "").strip()
            color = str(row.get("product_color") or "").strip()

            full_description = " ".join(
                part
                for part in [
                    title,
                    description,
                    bullet_point,
                    f"Brand: {brand}." if brand else "",
                    f"Color: {color}." if color else "",
                ]
                if part
            )

            products.append({
                "sku": sku,
                "name": title if title else sku,
                "description": full_description,
                "brand": brand,
                "color": color,
                "category": category,
            })

    unique_products = {}
    for product in products:
        unique_products[product["sku"]] = product

    result = list(unique_products.values())

    print(f"Loaded ESCI products: {len(result)}")

    return result

def run_full_reindex():
    headers = {
        "Content-Type": "application/json",
    }

    if SEARCH_API_KEY:
        headers["X-API-KEY"] = SEARCH_API_KEY

    response = requests.post(
        f"{SEARCH_URL}/reindex",
        headers=headers,
        json={
            "mode": "full",
            "reason": "benchmark_data_generator",
            "context": {
                "stage": "after_product_catalog_generation",
                "product_count": PRODUCT_COUNT,
                "customer_count": CUSTOMER_COUNT,
            },
        },
        timeout=120,
    )

    if response.status_code >= 400:
        raise RuntimeError(
            f"Reindex failed: {response.status_code} {response.text}"
        )

    print("Full reindex finished.")

def clear_data(cur):
    cur.execute("DELETE FROM customer_product_view_log;")
    cur.execute("DELETE FROM customer_search_log;")
    cur.execute("DELETE FROM order_items;")
    cur.execute("DELETE FROM orders;")
    cur.execute("DELETE FROM cart;")
    cur.execute("DELETE FROM product_document_vector;")
    cur.execute("DELETE FROM product;")
    cur.execute("DELETE FROM category;")
    cur.execute("DELETE FROM ProductColor;")
    cur.execute("DELETE FROM size;")
    cur.execute("DELETE FROM customer;")


def insert_categories(cur):
    category_ids = {}

    for name in CATEGORIES:
        cur.execute(
            """
            INSERT INTO category (name)
            VALUES (%s)
            RETURNING id
            """,
            (name,),
        )
        category_ids[name] = cur.fetchone()[0]

    return category_ids


def insert_colors(cur):
    color_ids = {}

    for name, hex_value in COLORS:
        cur.execute(
            """
            INSERT INTO ProductColor (name, hex)
            VALUES (%s, %s)
            RETURNING id
            """,
            (name, hex_value),
        )
        color_ids[name] = cur.fetchone()[0]

    return color_ids


def insert_sizes(cur):
    size_ids = {}

    for name in SIZES:
        cur.execute(
            """
            INSERT INTO size (name)
            VALUES (%s)
            RETURNING id
            """,
            (name,),
        )
        size_ids[name] = cur.fetchone()[0]

    return size_ids


def insert_products(cur, category_ids, color_ids, size_ids):
    products = []

    product_category_map = load_esci_product_category_map()
    esci_products = load_esci_products_by_ids(product_category_map)

    if not esci_products:
        raise RuntimeError("No ESCI products loaded. Check ESCI parquet files.")

    now = datetime.now()

    for index, esci_product in enumerate(esci_products, start=1):
        sku = esci_product["sku"]
        name = esci_product["name"]
        description = esci_product["description"]
        brand = esci_product["brand"]
        category = esci_product["category"]

        category_id = category_ids[category]

        color_text = str(esci_product.get("color") or "").lower()

        if "black" in color_text:
            color_id = color_ids["Black"]
        elif "white" in color_text:
            color_id = color_ids["White"]
        elif "blue" in color_text:
            color_id = color_ids["Blue"]
        elif "red" in color_text:
            color_id = color_ids["Red"]
        else:
            color_id = color_ids["Gray"]

        material = random.choice(MATERIALS.get(category, ["unknown"]))

        is_fashion = category in ["T-Shirts", "Jackets", "Shoes"]

        if is_fashion:
            size_name = random.choice(SIZES)
            size_id = size_ids[size_name]
        else:
            size_id = None

        price = round(random.uniform(5, 3000), 2)
        stock = random.randint(0, 250)

        attributes = {
            "source": "amazon_esci",
            "brand": brand,
            "original_product_id": sku,
            "benchmark_category": category,
        }

        image = f"esci-product-{index:05d}.jpg"

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
                %s, %s, %s, 1
            )
            RETURNING id
            """,
            (
                name,
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
                random.choice([100, 100, 100, 90, 80, 70]),
                json.dumps(attributes),
                sku,
                category_id,
                size_id,
                color_id,
            ),
        )

        product_id = cur.fetchone()[0]

        products.append({
            "id": product_id,
            "sku": sku,
            "name": name,
            "category": category,
        })

        if len(products) >= PRODUCT_COUNT:
            break

    print(f"Inserted ESCI products: {len(products)}")

    return products

def insert_customers(cur):
    customers = []

    for i in range(1, CUSTOMER_COUNT + 1):
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


def insert_wishlists(cur, customers, products):
    for customer_id in customers:
        wishlist_products = random.sample(products, k=random.randint(3, 12))
        wishlist_ids = [p["id"] for p in wishlist_products]

        cur.execute(
            """
            UPDATE customer
            SET wishlist = %s::json
            WHERE id = %s
            """,
            (json.dumps(wishlist_ids), customer_id),
        )


def insert_view_logs(cur, customers, products):
    now = datetime.now()

    for _ in range(VIEW_LOG_COUNT):
        customer_id = random.choice(customers)
        product = random.choice(products)

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


def insert_search_logs(cur, customers):
    now = datetime.now()

    for _ in range(SEARCH_LOG_COUNT):
        customer_id = random.choice(customers)
        query = random.choice(SEARCH_QUERIES)

        cur.execute(
            """
            INSERT INTO customer_search_log (
                customer_id,
                query,
                result_count,
                session_id,
                created_at
            ) VALUES (
                %s, %s, %s, %s, %s
            )
            """,
            (
                customer_id,
                query,
                random.randint(1, 20),
                f"session-{customer_id}",
                now - timedelta(minutes=random.randint(1, 100000)),
            ),
        )


def insert_orders(cur, customers, products):
    now = datetime.now()

    for _ in range(ORDER_COUNT):
        customer_id = random.choice(customers)
        order_products = random.sample(products, k=random.randint(1, 5))

        total_price = 0.0

        cur.execute(
            """
            INSERT INTO orders (
                customer_id,
                total_price,
                address,
                order_created_at,
                is_completed,
                payment_status,
                payment_method,
                delivery_status,
                notes,
                discount,
                delivery_method
            ) VALUES (
                %s, 0, %s, %s, true,
                'COMPLETED',
                'CARD',
                'COMPLETED',
                NULL,
                0,
                'delivery'
            )
            RETURNING id
            """,
            (
                customer_id,
                "Benchmark street 1, Prague",
                now - timedelta(days=random.randint(1, 120)),
            ),
        )

        order_id = cur.fetchone()[0]

        for product in order_products:
            quantity = random.randint(1, 3)
            unit_price = round(random.uniform(199, 45000), 2)
            subtotal = round(unit_price * quantity / 1.21, 2)
            total_price += unit_price * quantity

            cur.execute(
                """
                INSERT INTO order_items (
                    order_id,
                    product_id,
                    product_name,
                    quantity,
                    unit_price,
                    subtotal,
                    sku
                ) VALUES (
                    %s, %s, %s, %s, %s, %s, %s
                )
                """,
                (
                    order_id,
                    product["id"],
                    product["name"],
                    quantity,
                    unit_price,
                    subtotal,
                    product["sku"],
                ),
            )

        cur.execute(
            """
            UPDATE orders
            SET total_price = %s
            WHERE id = %s
            """,
            (round(total_price, 2), order_id),
        )

def fetch_recommended_skus(sku: str, limit: int = 10):
    try:
        response = requests.get(
            f"{SEARCH_URL}/recommend/{sku}",
            params={"limit": limit},
            timeout=10,
        )

        if response.status_code >= 400:
            return []

        data = response.json()

        return [
            item["product_sku"]
            for item in data.get("results", [])
            if item.get("product_sku")
        ]

    except Exception:
        return []
    
PERSONAS = {
    "tech_worker": {
        "categories": ["Laptops", "Keyboards", "Mice", "Monitors", "Headphones"],
        "queries": [
            "business laptop",
            "wireless keyboard",
            "office mouse",
            "4k monitor",
            "noise cancelling headphones",
        ],
    },
    "gamer": {
        "categories": ["Laptops", "Keyboards", "Mice", "Headphones", "Monitors"],
        "queries": [
            "gaming laptop",
            "gaming mouse",
            "mechanical keyboard",
            "gaming headset",
            "gaming monitor",
        ],
    },
    "fashion_user": {
        "categories": ["T-Shirts", "Jackets", "Shoes", "Accessories"],
        "queries": [
            "cotton shirt",
            "black jacket",
            "running shoes",
            "oversized t-shirt",
            "winter jacket",
        ],
    },
    "phone_user": {
        "categories": ["Smartphones", "Headphones", "Accessories"],
        "queries": [
            "smartphone",
            "phone case",
            "bluetooth earbuds",
            "usb-c cable",
            "compact smartphone",
        ],
    },
}


def find_product_by_sku(products: list[dict], sku: str):
    for product in products:
        if product["sku"] == sku:
            return product

    return None


def unique_products(products: list[dict]):
    seen = set()
    result = []

    for product in products:
        sku = product.get("sku")

        if not sku or sku in seen:
            continue

        seen.add(sku)
        result.append(product)

    return result


def build_products_by_category(products: list[dict]):
    grouped = {}

    for product in products:
        category = product.get("category")

        if not category:
            continue

        grouped.setdefault(category, []).append(product)

    return grouped


def insert_customer_search_logs(cur, customer_id: int, queries: list[str]):
    now = datetime.now()

    for query in random.sample(queries, k=min(len(queries), random.randint(2, 5))):
        cur.execute(
            """
            INSERT INTO customer_search_log (
                customer_id,
                query,
                result_count,
                session_id,
                created_at
            ) VALUES (
                %s, %s, %s, %s, %s
            )
            """,
            (
                customer_id,
                query,
                random.randint(3, 20),
                f"session-{customer_id}",
                now - timedelta(minutes=random.randint(1, 100000)),
            ),
        )


def insert_customer_view_logs(cur, customer_id: int, products: list[dict]):
    now = datetime.now()

    for product in products:
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


def insert_customer_wishlist(cur, customer_id: int, products: list[dict]):
    wishlist_products = random.sample(
        products,
        k=min(len(products), random.randint(3, 10)),
    )

    wishlist_ids = [
        product["id"]
        for product in wishlist_products
    ]

    cur.execute(
        """
        UPDATE customer
        SET wishlist = %s::json
        WHERE id = %s
        """,
        (
            json.dumps(wishlist_ids),
            customer_id,
        ),
    )


def insert_customer_orders(cur, customer_id: int, products: list[dict]):
    if not products:
        return

    now = datetime.now()
    order_count = random.randint(1, 4)

    for _ in range(order_count):
        order_products = random.sample(
            products,
            k=min(len(products), random.randint(1, 4)),
        )

        total_price = 0.0

        cur.execute(
            """
            INSERT INTO orders (
                customer_id,
                total_price,
                address,
                order_created_at,
                is_completed,
                payment_status,
                payment_method,
                delivery_status,
                notes,
                discount,
                delivery_method
            ) VALUES (
                %s, 0, %s, %s, true,
                'COMPLETED',
                'CARD',
                'COMPLETED',
                NULL,
                0,
                'delivery'
            )
            RETURNING id
            """,
            (
                customer_id,
                "Benchmark street 1, Prague",
                now - timedelta(days=random.randint(1, 120)),
            ),
        )

        order_id = cur.fetchone()[0]

        for product in order_products:
            quantity = random.randint(1, 3)
            unit_price = round(random.uniform(199, 45000), 2)
            subtotal = round(unit_price * quantity / 1.21, 2)
            total_price += unit_price * quantity

            cur.execute(
                """
                INSERT INTO order_items (
                    order_id,
                    product_id,
                    product_name,
                    quantity,
                    unit_price,
                    subtotal,
                    sku
                ) VALUES (
                    %s, %s, %s, %s, %s, %s, %s
                )
                """,
                (
                    order_id,
                    product["id"],
                    product["name"],
                    quantity,
                    unit_price,
                    subtotal,
                    product["sku"],
                ),
            )

        cur.execute(
            """
            UPDATE orders
            SET total_price = %s
            WHERE id = %s
            """,
            (
                round(total_price, 2),
                order_id,
            ),
        )


def insert_persona_behavior(cur, customers: list[int], products: list[dict]):
    products_by_category = build_products_by_category(products)
    persona_names = list(PERSONAS.keys())

    for index, customer_id in enumerate(customers):
        persona_name = persona_names[index % len(persona_names)]
        persona = PERSONAS[persona_name]

        seed_candidates = []

        for category in persona["categories"]:
            pool = products_by_category.get(category, [])

            if not pool:
                continue

            seed_candidates.extend(
                random.sample(
                    pool,
                    k=min(len(pool), 8),
                )
            )

        seed_candidates = unique_products(seed_candidates)

        if not seed_candidates:
            continue

        behavior_products = random.sample(
            seed_candidates,
            k=min(len(seed_candidates), random.randint(10, 20)),
        )

        view_products = random.sample(
            behavior_products,
            k=min(len(behavior_products), random.randint(8, 16)),
        )

        insert_customer_view_logs(
            cur,
            customer_id,
            view_products,
        )

        insert_customer_wishlist(
            cur,
            customer_id,
            behavior_products,
        )

        order_pool = random.sample(
            behavior_products,
            k=min(len(behavior_products), random.randint(4, 10)),
        )

        insert_customer_orders(
            cur,
            customer_id,
            order_pool,
        )

        insert_customer_search_logs(
            cur,
            customer_id,
            persona["queries"],
        )

        print(
            f"Customer {customer_id}: persona={persona_name}, "
            f"history_products={len(behavior_products)}"
        )
    
def main():
    conn = connect()
    cur = conn.cursor()

    print("Clearing old benchmark data...")
    clear_data(cur)

    print("Creating categories...")
    category_ids = insert_categories(cur)

    print("Creating colors...")
    color_ids = insert_colors(cur)

    print("Creating sizes...")
    size_ids = insert_sizes(cur)

    print(f"Creating {PRODUCT_COUNT} products...")
    products = insert_products(cur, category_ids, color_ids, size_ids)

    print(f"Creating {CUSTOMER_COUNT} customers...")
    customers = insert_customers(cur)

    print("Creating persona-based historical behavior...")
    insert_persona_behavior(cur, customers, products)

    conn.commit()

    cur.close()
    conn.close()

    print("Running full reindex after catalog generation...")
    run_full_reindex()

    print("Done.")
    print(f"Products: {len(products)}")
    print(f"Customers: {len(customers)}")

if __name__ == "__main__":
    main()