from typing import Dict

from database import get_connection


def fetch_products() -> list[dict]:
    """
    Fetch latest product version for each SKU with category name.
    Latest version means the product row with the highest id.
    """
    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                """
                SELECT p.*, c.name AS category
                FROM product p
                INNER JOIN (
                    SELECT sku, MAX(id) AS max_id
                    FROM product
                    WHERE sku IS NOT NULL AND sku <> ''
                    GROUP BY sku
                ) latest ON latest.max_id = p.id
                LEFT JOIN category c ON p.category_id = c.id
                ORDER BY p.id DESC
                """
            )
            return cursor.fetchall()


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


def build_documents(rows: list[dict], builder_fn) -> Dict[str, str]:
    """
    Convert DB rows into {sku: document}.
    Deduplicate by SKU.
    """
    documents: Dict[str, str] = {}
    seen = set()

    for row in rows:
        sku = row.get("sku")

        if not sku:
            continue

        sku = str(sku).strip()

        if not sku or sku in seen:
            continue

        doc = builder_fn(row)

        if doc:
            documents[sku] = doc
            seen.add(sku)

    return documents