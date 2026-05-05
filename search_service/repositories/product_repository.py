import pickle
from typing import Dict

from database import get_connection


DEFAULT_RELEVANCE_CONFIG = {
    "name_weight": 20,
    "description_weight": 5,
    "category_weight": 4,
    "material_weight": 2,
    "color_weight": 2,
    "size_weight": 2,
    "attributes_weight": 2,
    "same_category_bonus": 0.35,
    "same_material_bonus": 0.15,
    "same_color_bonus": 0.10,
    "same_size_bonus": 0.10,
}


def fetch_active_relevance_config() -> dict:
    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                """
                SELECT
                    name_weight,
                    description_weight,
                    category_weight,
                    material_weight,
                    color_weight,
                    size_weight,
                    attributes_weight,
                    same_category_bonus,
                    same_material_bonus,
                    same_color_bonus,
                    same_size_bonus
                FROM search_relevance_config
                WHERE active = true
                ORDER BY id DESC
                LIMIT 1
                """
            )

            row = cursor.fetchone()

            if not row:
                return DEFAULT_RELEVANCE_CONFIG.copy()

            config = DEFAULT_RELEVANCE_CONFIG.copy()
            config.update(row)

            return config


def fetch_products() -> list[dict]:
    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                """
                SELECT
                    p.*,
                    c.name AS category,
                    co.name AS color,
                    s.name AS size
                FROM product p
                INNER JOIN (
                    SELECT sku, MAX(id) AS max_id
                    FROM product
                    WHERE sku IS NOT NULL AND sku <> ''
                    GROUP BY sku
                ) latest ON latest.max_id = p.id
                LEFT JOIN category c ON p.category_id = c.id
                LEFT JOIN ProductColor co ON p.color_id = co.id
                LEFT JOIN Size s ON p.size_id = s.id
                WHERE p.hidden = false
                ORDER BY p.id DESC
                """
            )
            return cursor.fetchall()


def fetch_latest_product_by_sku(sku: str) -> dict | None:
    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                """
                SELECT
                    p.*,
                    c.name AS category,
                    co.name AS color,
                    s.name AS size
                FROM product p
                LEFT JOIN category c ON p.category_id = c.id
                LEFT JOIN ProductColor co ON p.color_id = co.id
                LEFT JOIN Size s ON p.size_id = s.id
                WHERE p.sku = %s
                ORDER BY p.id DESC
                LIMIT 1
                """,
                (sku,),
            )
            return cursor.fetchone()


def save_product_vector(sku: str, document: str, vector) -> None:
    vector_blob = pickle.dumps(vector)

    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                """
                INSERT INTO product_document_vector (sku, document, vector)
                VALUES (%s, %s, %s)
                ON CONFLICT (sku)
                DO UPDATE SET
                    document = EXCLUDED.document,
                    vector = EXCLUDED.vector
                """,
                (sku, document, vector_blob),
            )
        conn.commit()


def delete_product_vector(sku: str) -> None:
    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                """
                DELETE FROM product_document_vector
                WHERE sku = %s
                """,
                (sku,),
            )
        conn.commit()


def fetch_all_product_vectors() -> list[dict]:
    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                """
                SELECT sku, document, vector
                FROM product_document_vector
                WHERE vector IS NOT NULL
                """
            )
            return cursor.fetchall()


def count_product_vectors() -> int:
    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute("SELECT COUNT(*) AS cnt FROM product_document_vector;")
            return int(cursor.fetchone()["cnt"])


def count_products() -> int:
    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute("SELECT COUNT(*) AS cnt FROM product;")
            return int(cursor.fetchone()["cnt"])


def count_distinct_skus() -> int:
    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                """
                SELECT COUNT(DISTINCT sku) AS cnt
                FROM product
                WHERE sku IS NOT NULL AND sku <> '';
                """
            )
            return int(cursor.fetchone()["cnt"])


def build_documents(rows: list[dict], builder_fn, config: dict) -> Dict[str, str]:
    documents: Dict[str, str] = {}
    seen = set()

    for row in rows:
        sku = str(row.get("sku") or "").strip()

        if not sku or sku in seen:
            continue

        doc = builder_fn(row, config)

        if doc:
            documents[sku] = doc
            seen.add(sku)

    return documents