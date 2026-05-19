import statistics
from datetime import datetime

import psycopg2

from config import settings
from http_client import request_json


def get_db_connection():
    return psycopg2.connect(
        settings.database_url
    )


def fetch_latest_product_by_sku(sku: str):
    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute("""
        SELECT
            p.sku,
            p.name,
            p.description,
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
            WHERE sku = %s
            GROUP BY sku
        ) latest ON latest.max_id = p.id
        LIMIT 1
    """, (sku,))

    row = cur.fetchone()

    cur.close()
    conn.close()

    if not row:
        return None

    return {
        "sku": str(row[0] or ""),
        "name": str(row[1] or ""),
        "description": str(row[2] or ""),
        "category": str(row[3] or ""),
        "material": str(row[4] or ""),
        "color": str(row[5] or ""),
        "size": str(row[6] or ""),
    }


def normalize_search_result(item: dict, method: str):
    if method == "tfidf":
        sku = str(item.get("product_sku", "")).strip()
        if not sku:
            return None

        product = fetch_latest_product_by_sku(sku)
        if product is None:
            return None

        product["similarity"] = float(item.get("similarity", 0))
        return product

    return {
        "sku": str(item.get("sku", "")),
        "name": str(item.get("name", "")),
        "description": str(item.get("description", "")),
        "category": str(item.get("category", "")),
        "material": str(item.get("material", "")),
        "color": str(item.get("color", "")),
        "size": str(item.get("size", "")),
        "similarity": float(item.get("similarity", 0)),
    }


def calculate_query_precision(query: str, results: list[dict]):
    if not results:
        return 0.0

    terms = [
        term.lower().strip()
        for term in query.split()
        if len(term.strip()) > 1
    ]

    relevant = 0

    for result in results:
        text = " ".join([
            str(result.get("name", "")),
            str(result.get("description", "")),
            str(result.get("category", "")),
            str(result.get("material", "")),
            str(result.get("color", "")),
            str(result.get("size", "")),
        ]).lower()

        if any(term in text for term in terms):
            relevant += 1

    return relevant / len(results)


def call_search_method(method: str, query: str, limit: int):
    endpoint = {
        "tfidf": "/search",
        "semantic_vector": "/semantic/search",
    }[method]

    status, data, elapsed_ms = request_json(
        "POST",
        f"{settings.search_url}{endpoint}",
        json={
            "query": query,
            "limit": limit,
        },
    )

    raw_results = data.get("results", []) if status == 200 else []

    results = []

    for item in raw_results:
        if not isinstance(item, dict):
            continue

        normalized = normalize_search_result(item, method)

        if normalized is not None:
            results.append(normalized)

    return status, results, elapsed_ms


def evaluate_search(limit: int = 10):
    methods = [
        "tfidf",
        "semantic_vector",
    ]

    rows = []

    for query in settings.queries:
        for method in methods:
            status, results, elapsed_ms = call_search_method(
                method=method,
                query=query,
                limit=limit,
            )

            precision = calculate_query_precision(query, results)

            rows.append({
                "method": method,
                "query": query,
                "status": status,
                "response_time_ms": elapsed_ms,
                "result_count": len(results),
                "has_results": len(results) > 0,
                "precision_at_k": precision,
            })

    summary = {}

    for method in methods:
        method_rows = [
            row for row in rows
            if row["method"] == method and row["status"] == 200
        ]

        with_results = [
            row for row in method_rows
            if row["has_results"]
        ]

        summary[method] = {
            "queries_evaluated": len(method_rows),
            "queries_with_results": len(with_results),
            "result_hit_rate": (
                len(with_results) / len(method_rows)
                if method_rows else 0
            ),
            "avg_response_time_ms": (
                statistics.mean(row["response_time_ms"] for row in method_rows)
                if method_rows else 0
            ),
            "avg_precision_at_k": (
                statistics.mean(row["precision_at_k"] for row in method_rows)
                if method_rows else 0
            ),
        }

    return {
        "methods_compared": methods,
        "limit": limit,
        "summary": summary,
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