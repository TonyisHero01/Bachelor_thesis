import csv
import io
import statistics

from config import settings
from http_client import request_json


SEARCH_METHODS = {
    "tfidf": {
        "method": "POST",
        "url": lambda query: f"{settings.search_url}/search",
        "json": lambda query: {"query": query, "limit": 10},
        "params": lambda query: None,
    },
    "semantic_vector": {
        "method": "POST",
        "url": lambda query: f"{settings.search_url}/semantic/search",
        "json": lambda query: {"query": query, "limit": 10},
        "params": lambda query: None,
    },
    "sql_like": {
        "method": "GET",
        "url": lambda query: f"{settings.bms_url}/search-like",
        "json": lambda query: None,
        "params": lambda query: {"q": query},
    },
}


def call_method(method_name: str, query: str):
    method_config = SEARCH_METHODS[method_name]

    status, data, elapsed = request_json(
        method_config["method"],
        method_config["url"](query),
        json=method_config["json"](query),
        params=method_config["params"](query),
    )

    return status, data, elapsed


def count_results(data, status: int):
    if status != 200 or not isinstance(data, dict):
        return 0

    results = data.get("results", [])

    if not isinstance(results, list):
        return 0

    return len(results)


def run_benchmark():
    rows = []

    first_query = settings.queries[0]

    for method_name in ["tfidf", "semantic_vector"]:
        status, data, elapsed = call_method(method_name, first_query)

        rows.append({
            "type": f"{method_name}_cold",
            "query": first_query,
            "response_time_ms": elapsed,
            "result_count": count_results(data, status),
            "status": status,
        })

    for query in settings.queries:
        for method_name in ["tfidf", "semantic_vector", "sql_like"]:
            times = []
            result_count = 0
            final_status = 200

            for _ in range(settings.benchmark_repeat_count):
                status, data, elapsed = call_method(method_name, query)

                times.append(elapsed)
                final_status = status
                result_count = count_results(data, status)

            rows.append({
                "type": method_name,
                "query": query,
                "response_time_ms": statistics.mean(times) if times else 0,
                "result_count": result_count,
                "status": final_status,
            })

    return rows


def rows_to_csv(rows):
    output = io.StringIO()

    writer = csv.DictWriter(
        output,
        fieldnames=[
            "type",
            "query",
            "response_time_ms",
            "result_count",
            "status",
        ],
    )

    writer.writeheader()
    writer.writerows(rows)

    return output.getvalue()

def normalize_log_filters(filters: dict | None):
    if filters is None:
        filters = {}

    limit = filters.get("limit", 200)

    try:
        limit = int(limit)
    except Exception:
        limit = 200

    limit = max(20, min(limit, 1000))

    return {
        "event_type": (filters.get("event_type") or "").strip(),
        "page_type": (filters.get("page_type") or "").strip(),
        "algorithm": (filters.get("algorithm") or "").strip(),
        "session_id": (filters.get("session_id") or "").strip(),
        "customer_id": (filters.get("customer_id") or "").strip(),
        "source_sku": (filters.get("source_sku") or "").strip(),
        "recommended_sku": (filters.get("recommended_sku") or "").strip(),
        "date_from": (filters.get("date_from") or "").strip(),
        "date_to": (filters.get("date_to") or "").strip(),
        "limit": limit,
    }


def build_recommendation_log_where(filters: dict):
    conditions = []
    params = []

    if filters["event_type"]:
        conditions.append("rel.event_type = %s")
        params.append(filters["event_type"])

    if filters["page_type"]:
        conditions.append("rel.page_type = %s")
        params.append(filters["page_type"])

    if filters["algorithm"]:
        conditions.append("rel.algorithm = %s")
        params.append(filters["algorithm"])

    if filters["session_id"]:
        conditions.append("rel.session_id ILIKE %s")
        params.append(f"%{filters['session_id']}%")

    if filters["customer_id"]:
        conditions.append("CAST(rel.customer_id AS TEXT) = %s")
        params.append(filters["customer_id"])

    if filters["source_sku"]:
        conditions.append("rel.source_sku ILIKE %s")
        params.append(f"%{filters['source_sku']}%")

    if filters["recommended_sku"]:
        conditions.append("rel.recommended_sku ILIKE %s")
        params.append(f"%{filters['recommended_sku']}%")

    if filters["date_from"]:
        conditions.append("rel.created_at >= %s")
        params.append(filters["date_from"])

    if filters["date_to"]:
        conditions.append("rel.created_at <= %s")
        params.append(filters["date_to"])

    if not conditions:
        return "", params

    return "WHERE " + " AND ".join(conditions), params


def fetch_grouped_recommendation_log(cur, where_sql, params, column):
    cur.execute(
        f"""
        SELECT
            rel.{column},
            COUNT(*) AS total_count
        FROM recommendation_event_log rel
        {where_sql}
        GROUP BY rel.{column}
        ORDER BY total_count DESC
        LIMIT 20
        """,
        params,
    )

    return [
        {
            "label": str(row[0] or "unknown"),
            "count": int(row[1] or 0),
        }
        for row in cur.fetchall()
    ]


def fetch_recommendation_event_log_report(filters: dict | None = None):
    filters = normalize_log_filters(filters)

    conn = get_db_connection()
    cur = conn.cursor()

    where_sql, params = build_recommendation_log_where(filters)

    cur.execute(
        f"""
        SELECT COUNT(*)
        FROM recommendation_event_log rel
        {where_sql}
        """,
        params,
    )
    total_events = int(cur.fetchone()[0] or 0)

    cur.execute(
        f"""
        SELECT COUNT(*)
        FROM recommendation_event_log rel
        {where_sql}
        AND rel.event_type = 'impression'
        """
        if where_sql
        else """
        SELECT COUNT(*)
        FROM recommendation_event_log rel
        WHERE rel.event_type = 'impression'
        """,
        params,
    )
    impression_count = int(cur.fetchone()[0] or 0)

    cur.execute(
        f"""
        SELECT COUNT(*)
        FROM recommendation_event_log rel
        {where_sql}
        AND rel.event_type = 'click'
        """
        if where_sql
        else """
        SELECT COUNT(*)
        FROM recommendation_event_log rel
        WHERE rel.event_type = 'click'
        """,
        params,
    )
    click_count = int(cur.fetchone()[0] or 0)

    cur.execute(
        f"""
        SELECT COUNT(DISTINCT rel.session_id)
        FROM recommendation_event_log rel
        {where_sql}
        """,
        params,
    )
    unique_sessions = int(cur.fetchone()[0] or 0)

    cur.execute(
        f"""
        SELECT COUNT(DISTINCT rel.customer_id)
        FROM recommendation_event_log rel
        {where_sql}
        """,
        params,
    )
    unique_customers = int(cur.fetchone()[0] or 0)

    cur.execute(
        f"""
        SELECT COUNT(DISTINCT rel.recommended_sku)
        FROM recommendation_event_log rel
        {where_sql}
        """,
        params,
    )
    unique_recommended_products = int(cur.fetchone()[0] or 0)

    by_event_type = fetch_grouped_recommendation_log(
        cur,
        where_sql,
        params,
        "event_type",
    )

    by_algorithm = fetch_grouped_recommendation_log(
        cur,
        where_sql,
        params,
        "algorithm",
    )

    by_page_type = fetch_grouped_recommendation_log(
        cur,
        where_sql,
        params,
        "page_type",
    )

    cur.execute(
        f"""
        SELECT
            rel.id,
            rel.session_id,
            rel.customer_id,
            rel.page_type,
            rel.source_sku,
            source_product.name AS source_name,
            rel.recommended_sku,
            recommended_product.name AS recommended_name,
            rel.algorithm,
            rel.rank_position,
            rel.score,
            rel.event_type,
            rel.created_at
        FROM recommendation_event_log rel
        LEFT JOIN (
            SELECT DISTINCT ON (sku)
                sku,
                name
            FROM product
            ORDER BY sku, id DESC
        ) source_product ON source_product.sku = rel.source_sku
        LEFT JOIN (
            SELECT DISTINCT ON (sku)
                sku,
                name
            FROM product
            ORDER BY sku, id DESC
        ) recommended_product ON recommended_product.sku = rel.recommended_sku
        {where_sql}
        ORDER BY rel.created_at DESC, rel.id DESC
        LIMIT %s
        """,
        params + [filters["limit"]],
    )

    events = []

    for row in cur.fetchall():
        events.append({
            "id": row[0],
            "session_id": str(row[1] or ""),
            "customer_id": row[2],
            "page_type": str(row[3] or ""),
            "source_sku": str(row[4] or ""),
            "source_name": str(row[5] or ""),
            "recommended_sku": str(row[6] or ""),
            "recommended_name": str(row[7] or ""),
            "algorithm": str(row[8] or ""),
            "rank_position": row[9],
            "score": row[10],
            "event_type": str(row[11] or ""),
            "created_at": row[12].isoformat() if row[12] else "",
        })

    cur.close()
    conn.close()

    ctr = (
        click_count / impression_count
        if impression_count > 0
        else 0
    )

    return {
        "filters": filters,
        "summary": {
            "total_events": total_events,
            "impression_count": impression_count,
            "click_count": click_count,
            "ctr": ctr,
            "unique_sessions": unique_sessions,
            "unique_customers": unique_customers,
            "unique_recommended_products": unique_recommended_products,
        },
        "by_event_type": by_event_type,
        "by_algorithm": by_algorithm,
        "by_page_type": by_page_type,
        "events": events,
    }