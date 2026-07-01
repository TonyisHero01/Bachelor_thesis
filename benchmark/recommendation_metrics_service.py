import psycopg2
from psycopg2.extras import RealDictCursor

from config import settings


def get_connection():
    return psycopg2.connect(
        settings.database_url,
        cursor_factory=RealDictCursor,
    )


def fetch_one(sql: str) -> dict:
    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(sql)
            row = cursor.fetchone()

    return dict(row or {})


def fetch_all(sql: str) -> list[dict]:
    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(sql)
            rows = cursor.fetchall()

    return [dict(row) for row in rows]


def fetch_recommendation_coverage() -> dict:
    sql = """
        WITH latest_product AS (
            SELECT p.*
            FROM product p
            INNER JOIN (
                SELECT sku, MAX(id) AS max_id
                FROM product
                WHERE sku IS NOT NULL
                  AND sku <> ''
                  AND sku <> 'UNKNOWN'
                GROUP BY sku
            ) latest ON latest.max_id = p.id
            WHERE p.hidden = false
        ),
        recommended AS (
            SELECT COUNT(DISTINCT recommended_sku) AS recommended_count
            FROM recommendation_event_log
            WHERE event_type = 'impression'
              AND recommended_sku IS NOT NULL
              AND recommended_sku <> ''
        ),
        visible AS (
            SELECT COUNT(DISTINCT sku) AS visible_count
            FROM latest_product
        )
        SELECT
            recommended.recommended_count,
            visible.visible_count,
            CASE
                WHEN visible.visible_count = 0 THEN 0
                ELSE ROUND(
                    (recommended.recommended_count::numeric / visible.visible_count::numeric) * 100,
                    2
                )
            END AS coverage_percent
        FROM recommended, visible;
    """

    row = fetch_one(sql)

    return {
        "recommended_count": int(row.get("recommended_count") or 0),
        "visible_count": int(row.get("visible_count") or 0),
        "coverage_percent": float(row.get("coverage_percent") or 0),
    }


def fetch_recommendation_diversity() -> dict:
    sql = """
        WITH latest_product AS (
            SELECT p.*
            FROM product p
            INNER JOIN (
                SELECT sku, MAX(id) AS max_id
                FROM product
                WHERE sku IS NOT NULL
                  AND sku <> ''
                  AND sku <> 'UNKNOWN'
                GROUP BY sku
            ) latest ON latest.max_id = p.id
            WHERE p.hidden = false
        ),
        category_counts AS (
            SELECT
                p.category_id,
                COUNT(*) AS impression_count
            FROM recommendation_event_log l
            JOIN latest_product p ON p.sku = l.recommended_sku
            WHERE l.event_type = 'impression'
              AND p.category_id IS NOT NULL
            GROUP BY p.category_id
        ),
        summary AS (
            SELECT
                COUNT(*) AS category_count,
                COALESCE(SUM(impression_count), 0) AS total_impressions,
                COALESCE(MAX(impression_count), 0) AS top_category_impressions
            FROM category_counts
        )
        SELECT
            category_count,
            total_impressions,
            top_category_impressions,
            CASE
                WHEN total_impressions = 0 THEN 0
                ELSE ROUND(
                    (top_category_impressions::numeric / total_impressions::numeric) * 100,
                    2
                )
            END AS top_category_share_percent
        FROM summary;
    """

    row = fetch_one(sql)

    return {
        "category_count": int(row.get("category_count") or 0),
        "total_impressions": int(row.get("total_impressions") or 0),
        "top_category_impressions": int(row.get("top_category_impressions") or 0),
        "top_category_share_percent": float(row.get("top_category_share_percent") or 0),
    }


def fetch_popularity_amplification() -> dict:
    summary_sql = """
        WITH top_recommended AS (
            SELECT
                recommended_sku AS sku,
                COUNT(*) AS recommendation_count
            FROM recommendation_event_log
            WHERE event_type = 'impression'
              AND recommended_sku IS NOT NULL
              AND recommended_sku <> ''
            GROUP BY recommended_sku
            ORDER BY recommendation_count DESC
            LIMIT 10
        ),
        top_sold AS (
            SELECT
                sku,
                SUM(quantity) AS sold_count
            FROM order_items
            WHERE sku IS NOT NULL
              AND sku <> ''
              AND sku <> 'UNKNOWN'
            GROUP BY sku
            ORDER BY sold_count DESC
            LIMIT 10
        ),
        overlap AS (
            SELECT COUNT(*) AS overlap_count
            FROM top_recommended r
            JOIN top_sold s ON s.sku = r.sku
        )
        SELECT
            (SELECT COUNT(*) FROM top_recommended) AS top_recommended_count,
            (SELECT COUNT(*) FROM top_sold) AS top_sold_count,
            overlap.overlap_count,
            CASE
                WHEN (SELECT COUNT(*) FROM top_recommended) = 0 THEN 0
                ELSE ROUND(
                    (overlap.overlap_count::numeric / (SELECT COUNT(*) FROM top_recommended)::numeric) * 100,
                    2
                )
            END AS popularity_overlap_percent
        FROM overlap;
    """

    top_recommended_sql = """
        SELECT
            recommended_sku,
            COUNT(*) AS recommendation_count
        FROM recommendation_event_log
        WHERE event_type = 'impression'
          AND recommended_sku IS NOT NULL
          AND recommended_sku <> ''
        GROUP BY recommended_sku
        ORDER BY recommendation_count DESC
        LIMIT 10;
    """

    top_sold_sql = """
        SELECT
            sku,
            SUM(quantity) AS sold_count
        FROM order_items
        WHERE sku IS NOT NULL
          AND sku <> ''
          AND sku <> 'UNKNOWN'
        GROUP BY sku
        ORDER BY sold_count DESC
        LIMIT 10;
    """

    row = fetch_one(summary_sql)

    return {
        "top_recommended_count": int(row.get("top_recommended_count") or 0),
        "top_sold_count": int(row.get("top_sold_count") or 0),
        "overlap_count": int(row.get("overlap_count") or 0),
        "popularity_overlap_percent": float(row.get("popularity_overlap_percent") or 0),
        "top_recommended": fetch_all(top_recommended_sql),
        "top_sold": fetch_all(top_sold_sql),
    }


def fetch_recommendation_freshness() -> dict:
    sql = """
        WITH latest_product AS (
            SELECT p.*
            FROM product p
            INNER JOIN (
                SELECT sku, MAX(id) AS max_id
                FROM product
                WHERE sku IS NOT NULL
                  AND sku <> ''
                  AND sku <> 'UNKNOWN'
                GROUP BY sku
            ) latest ON latest.max_id = p.id
            WHERE p.hidden = false
        ),
        recommended_products AS (
            SELECT DISTINCT l.recommended_sku
            FROM recommendation_event_log l
            WHERE l.event_type = 'impression'
              AND l.recommended_sku IS NOT NULL
              AND l.recommended_sku <> ''
        ),
        joined AS (
            SELECT
                p.sku,
                p.updated_at,
                EXTRACT(EPOCH FROM (NOW() - p.updated_at)) / 86400 AS age_days
            FROM recommended_products r
            JOIN latest_product p ON p.sku = r.recommended_sku
            WHERE p.updated_at IS NOT NULL
        )
        SELECT
            COUNT(*) AS recommended_product_count,
            ROUND(AVG(age_days)::numeric, 2) AS avg_age_days,
            COUNT(*) FILTER (
                WHERE updated_at >= NOW() - INTERVAL '30 days'
            ) AS fresh_30d_count,
            CASE
                WHEN COUNT(*) = 0 THEN 0
                ELSE ROUND(
                    (
                        COUNT(*) FILTER (
                            WHERE updated_at >= NOW() - INTERVAL '30 days'
                        )::numeric / COUNT(*)::numeric
                    ) * 100,
                    2
                )
            END AS fresh_30d_percent
        FROM joined;
    """

    row = fetch_one(sql)

    return {
        "recommended_product_count": int(row.get("recommended_product_count") or 0),
        "avg_age_days": float(row.get("avg_age_days") or 0),
        "fresh_30d_count": int(row.get("fresh_30d_count") or 0),
        "fresh_30d_percent": float(row.get("fresh_30d_percent") or 0),
    }


def fetch_recommendation_event_metrics() -> dict:
    return {
        "coverage": fetch_recommendation_coverage(),
        "diversity": fetch_recommendation_diversity(),
        "popularity_amplification": fetch_popularity_amplification(),
        "freshness": fetch_recommendation_freshness(),
    }