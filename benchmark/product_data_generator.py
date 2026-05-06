import random
import psycopg2
from datetime import datetime

DB_HOST = "db"
DB_PORT = 5432
DB_NAME = "app"
DB_USER = "user"
DB_PASSWORD = "password"

PRODUCT_COUNT = 120

CATEGORIES = [
    "Computers",
    "Mobile Devices",
    "Accessories",
    "Audio",
    "Fashion",
    "Shoes",
    "Bags",
    "Sportswear",
]

COLORS = [
    ("White", "#ffffff"),
    ("Black", "#000000"),
    ("Blue", "#2563eb"),
    ("Red", "#dc2626"),
    ("Grey", "#6b7280"),
]

SIZES = ["XS", "S", "M", "L", "XL"]

PRODUCTS = {
    "Computers": [
        "Gaming Laptop", "Business Laptop", "Ultrabook", "Desktop PC", "4K Monitor",
        "Mechanical Keyboard", "Wireless Keyboard", "Ergonomic Mouse", "External SSD"
    ],
    "Mobile Devices": [
        "Smartphone", "Tablet", "Smart Watch", "Power Bank", "USB-C Charger",
        "Phone Case", "Wireless Charging Pad"
    ],
    "Accessories": [
        "USB-C Hub", "Laptop Stand", "Webcam", "Memory Card", "HDMI Cable",
        "Portable Hard Drive", "Desk Lamp"
    ],
    "Audio": [
        "Bluetooth Headphones", "Wireless Earbuds", "Gaming Headset", "Portable Speaker",
        "Studio Microphone", "Soundbar"
    ],
    "Fashion": [
        "Cotton T-Shirt", "Formal Shirt", "Hoodie", "Winter Jacket", "Slim Fit Jeans",
        "Summer Dress", "Casual Sweater"
    ],
    "Shoes": [
        "Running Sneakers", "Leather Boots", "Casual Shoes", "Training Shoes",
        "Outdoor Sandals"
    ],
    "Bags": [
        "Leather Backpack", "Laptop Bag", "Travel Backpack", "Shoulder Bag",
        "Sports Bag"
    ],
    "Sportswear": [
        "Sports Shorts", "Training T-Shirt", "Running Jacket", "Yoga Pants",
        "Fitness Hoodie"
    ],
}

FEATURES = [
    "lightweight", "durable", "comfortable", "premium quality", "modern design",
    "daily use", "professional", "travel friendly", "high performance",
    "water resistant", "breathable", "ergonomic", "compact", "reliable"
]

MATERIALS = [
    "plastic", "metal", "aluminium", "cotton", "leather", "denim", "polyester"
]


def reset_database(cur):
    cur.execute("""
        TRUNCATE TABLE
            product_document_vector,
            search_query_log,
            customer_search_log,
            cart,
            order_items,
            orders,
            product,
            category,
            ProductColor,
            size
        RESTART IDENTITY CASCADE;
    """)


def insert_categories(cur):
    ids = {}

    for name in CATEGORIES:
        cur.execute(
            "INSERT INTO category (name) VALUES (%s) RETURNING id",
            (name,),
        )
        ids[name] = cur.fetchone()[0]

    return ids


def insert_colors(cur):
    ids = {}

    for name, hex_value in COLORS:
        cur.execute(
            'INSERT INTO ProductColor (name, hex) VALUES (%s, %s) RETURNING id',
            (name, hex_value),
        )
        ids[name] = cur.fetchone()[0]

    return ids


def insert_sizes(cur):
    ids = {}

    for name in SIZES:
        cur.execute(
            "INSERT INTO size (name) VALUES (%s) RETURNING id",
            (name,),
        )
        ids[name] = cur.fetchone()[0]

    return ids


def build_product(category_name):
    base_name = random.choice(PRODUCTS[category_name])
    color_name, _ = random.choice(COLORS)

    selected_features = random.sample(FEATURES, k=random.randint(3, 5))
    material = random.choice(MATERIALS)

    name = f"{color_name} {base_name}"

    description = (
        f"{name} with {', '.join(selected_features)}. "
        f"Designed for everyday use and suitable for customers looking for "
        f"{category_name.lower()} products."
    )

    return name, description, color_name, material


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

    reset_database(cur)

    category_ids = insert_categories(cur)
    color_ids = insert_colors(cur)
    size_ids = insert_sizes(cur)

    category_names = list(category_ids.keys())

    for i in range(1, PRODUCT_COUNT + 1):
        sku = f"SKU{i:03d}"
        image = f"sku{i:03d}.jpg"

        category_name = random.choice(category_names)
        category_id = category_ids[category_name]

        name, description, color_name, material = build_product(category_name)

        color_id = color_ids[color_name]

        if category_name in ["Fashion", "Shoes", "Sportswear"]:
            size_id = random.choice(list(size_ids.values()))
        else:
            size_id = None

        price = round(random.uniform(150, 12000), 2)

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
                name,
                description,
                random.randint(5, 300),
                f'["{image}"]',
                now,
                now,
                round(random.uniform(5, 100), 2),
                round(random.uniform(5, 100), 2),
                round(random.uniform(5, 100), 2),
                round(random.uniform(0.1, 10), 2),
                material,
                price,
                False,
                random.choice([100.0, 95.0, 90.0, 85.0, 80.0]),
                "{}",
                1,
                sku,
                21.0,
                category_id,
                size_id,
                color_id,
                1,
            ),
        )

    conn.commit()
    cur.close()
    conn.close()

    print(f"Reset database and inserted {PRODUCT_COUNT} realistic products.")


if __name__ == "__main__":
    main()