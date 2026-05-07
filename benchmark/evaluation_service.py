import statistics
from datetime import datetime

import psycopg2

from config import settings
from http_client import request_json


def get_db_connection():
    return psycopg2.connect(
        settings.database_url
    )


def evaluate_search(limit: int = 10):
    rows = []

    for query in settings.queries:
        status, data, elapsed_ms = request_json(
            "POST",
            f"{settings.search_url}/search",
            json={
                "query": query,
                "limit": limit,
            },
        )

        results = data.get("results", []) if status == 200 else []

        rows.append({
            "query": query,
            "status": status,
            "response_time_ms": elapsed_ms,
            "result_count": len(results),
            "has_results": len(results) > 0,
        })

    successful = [
        row for row in rows
        if row["status"] == 200
    ]

    with_results = [
        row for row in successful
        if row["has_results"]
    ]

    return {
        "queries_evaluated": len(rows),
        "successful_queries": len(successful),
        "queries_with_results": len(with_results),
        "result_hit_rate": len(with_results) / len(rows) if rows else 0,
        "avg_response_time_ms": (
            statistics.mean(row["response_time_ms"] for row in successful)
            if successful else 0
        ),
        "details": rows,
    }


def fetch_customers_with_behavior():
    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute("""
        SELECT DISTINCT customer_id
        FROM customer_product_view_log
        WHERE customer_id IS NOT NULL

        UNION

        SELECT DISTINCT customer_id
        FROM customer_search_log
        WHERE customer_id IS NOT NULL

        UNION

        SELECT DISTINCT o.customer_id
        FROM orders o
        WHERE o.customer_id IS NOT NULL
    """)

    rows = cur.fetchall()

    cur.close()
    conn.close()

    return [
        {"id": row[0]}
        for row in rows
        if row[0] is not None
    ]


def fetch_user_recent_viewed_skus(customer_id: int, limit: int = 10):
    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute("""
        SELECT sku
        FROM customer_product_view_log
        WHERE customer_id = %s
          AND sku IS NOT NULL
          AND sku <> ''
        ORDER BY viewed_at DESC
        LIMIT %s
    """, (customer_id, limit))

    rows = cur.fetchall()

    cur.close()
    conn.close()

    skus = []

    for row in rows:
        sku = str(row[0]).strip()

        if sku and sku not in skus:
            skus.append(sku)

    return skus


def fetch_categories_for_skus(skus: list[str]):
    if not skus:
        return []

    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute("""
        SELECT DISTINCT p.sku, c.name
        FROM product p
        LEFT JOIN category c ON p.category_id = c.id
        INNER JOIN (
            SELECT sku, MAX(id) AS max_id
            FROM product
            WHERE sku = ANY(%s)
            GROUP BY sku
        ) latest ON latest.max_id = p.id
        WHERE p.sku = ANY(%s)
    """, (skus, skus))

    rows = cur.fetchall()

    cur.close()
    conn.close()

    categories = []

    for _, category in rows:
        category = str(category or "").strip()

        if category and category not in categories:
            categories.append(category)

    return categories


def call_recommend_api(sku: str, limit: int = 10):
    status, data, _ = request_json(
        "GET",
        f"{settings.search_url}/recommend/{sku}",
        params={"limit": limit},
    )

    if status != 200:
        return []

    results = data.get("results", [])

    return results if isinstance(results, list) else []


def fetch_product_profiles_for_skus(skus: list[str]):
    if not skus:
        return []

    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute("""
        SELECT DISTINCT
            p.sku,
            c.name AS category,
            p.material,
            pc.name AS color,
            s.name AS size
        FROM product p
        LEFT JOIN category c ON p.category_id = c.id
        LEFT JOIN ProductColor pc ON p.color_id = pc.id
        LEFT JOIN size s ON p.size_id = s.id
        INNER JOIN (
            SELECT sku, MAX(id) AS max_id
            FROM product
            WHERE sku = ANY(%s)
            GROUP BY sku
        ) latest ON latest.max_id = p.id
        WHERE p.sku = ANY(%s)
    """, (skus, skus))

    rows = cur.fetchall()

    cur.close()
    conn.close()

    return [
        {
            "sku": str(row[0] or ""),
            "category": str(row[1] or ""),
            "material": str(row[2] or ""),
            "color": str(row[3] or ""),
            "size": str(row[4] or ""),
        }
        for row in rows
    ]


def field_similarity(history_profiles: list[dict], recommended_profiles: list[dict], field: str):
    history_values = {
        p[field]
        for p in history_profiles
        if p.get(field)
    }

    recommended_values = {
        p[field]
        for p in recommended_profiles
        if p.get(field)
    }

    if not history_values or not recommended_values:
        return 0.0

    intersection = history_values & recommended_values
    union = history_values | recommended_values

    return len(intersection) / len(union)


def evaluate_recommendations(limit: int = 10):
    users = fetch_customers_with_behavior()

    total = 0

    category_scores = []
    material_scores = []
    color_scores = []
    size_scores = []
    combined_scores = []
    diversity_scores = []

    for user in users:
        history_skus = fetch_user_recent_viewed_skus(user["id"], limit=10)

        if not history_skus:
            continue

        recommended = []

        for sku in history_skus[:3]:
            recommended.extend(call_recommend_api(sku, limit))

        recommended_skus = list(dict.fromkeys(
            item["product_sku"]
            for item in recommended
            if item.get("product_sku")
        ))[:limit]

        if not recommended_skus:
            continue

        history_profiles = fetch_product_profiles_for_skus(history_skus)
        recommended_profiles = fetch_product_profiles_for_skus(recommended_skus)

        if not history_profiles or not recommended_profiles:
            continue

        category_similarity = field_similarity(
            history_profiles,
            recommended_profiles,
            "category",
        )

        material_similarity = field_similarity(
            history_profiles,
            recommended_profiles,
            "material",
        )

        color_similarity = field_similarity(
            history_profiles,
            recommended_profiles,
            "color",
        )

        size_similarity = field_similarity(
            history_profiles,
            recommended_profiles,
            "size",
        )

        combined_similarity = (
            category_similarity * 0.50
            + material_similarity * 0.20
            + color_similarity * 0.20
            + size_similarity * 0.10
        )

        recommended_categories = {
            p["category"]
            for p in recommended_profiles
            if p.get("category")
        }

        total += 1

        category_scores.append(category_similarity)
        material_scores.append(material_similarity)
        color_scores.append(color_similarity)
        size_scores.append(size_similarity)
        combined_scores.append(combined_similarity)
        diversity_scores.append(len(recommended_categories))

    return {
        "evaluated_users": total,

        "avg_interest_similarity": (
            sum(combined_scores) / len(combined_scores)
            if combined_scores else 0
        ),

        "avg_category_similarity": (
            sum(category_scores) / len(category_scores)
            if category_scores else 0
        ),

        "avg_material_similarity": (
            sum(material_scores) / len(material_scores)
            if material_scores else 0
        ),

        "avg_color_similarity": (
            sum(color_scores) / len(color_scores)
            if color_scores else 0
        ),

        "avg_size_similarity": (
            sum(size_scores) / len(size_scores)
            if size_scores else 0
        ),

        "avg_category_diversity": (
            sum(diversity_scores) / len(diversity_scores)
            if diversity_scores else 0
        ),
    }

def run_evaluation():
    return {
        "generated_at": datetime.utcnow().isoformat() + "Z",
        "search_evaluation": evaluate_search(),
        "recommendation_evaluation": evaluate_recommendations(),
    }