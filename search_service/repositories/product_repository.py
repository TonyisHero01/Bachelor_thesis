import json
import pickle
from copy import deepcopy
from typing import Any, Callable, Dict

import psycopg2

from database import get_connection


METHOD_LEXICAL = "lexical"
METHOD_SEMANTIC_VECTOR = "semantic_vector"
METHOD_ELASTICSEARCH_BM25 = "elasticsearch_bm25"

SUPPORTED_SEARCH_METHODS = {
    METHOD_LEXICAL,
    METHOD_SEMANTIC_VECTOR,
    METHOD_ELASTICSEARCH_BM25,
}


COMMON_RELEVANCE_DEFAULTS = {
    "id": None,
    "name": "Default relevance configuration",
    "active": False,

    "same_category_bonus": 0.35,
    "same_material_bonus": 0.15,
    "same_color_bonus": 0.10,
    "same_size_bonus": 0.10,

    "same_category_recommendation_weight": 0.35,
    "same_color_recommendation_weight": 0.10,
    "same_size_recommendation_weight": 0.10,

    "wishlist_recommendation_weight": 0.30,
    "order_history_recommendation_weight": 0.25,
    "search_history_recommendation_weight": 0.20,
    "view_history_recommendation_weight": 0.35,

    "max_recommendation_per_category": 4,
    "recommendation_diversity_penalty": 0.10,

    "recommendation_enabled": True,
    "recommendation_logging_enabled": True,
}


METHOD_DEFAULT_RELEVANCE_CONFIGS = {
    METHOD_LEXICAL: {
        **COMMON_RELEVANCE_DEFAULTS,

        "name": "Lexical configuration",
        "search_method": METHOD_LEXICAL,
        "active": True,

        "name_weight": 20,
        "description_weight": 5,
        "category_weight": 4,
        "material_weight": 2,
        "color_weight": 2,
        "size_weight": 2,
        "attributes_weight": 2,

        "algorithm_settings": {
            "vectorizer": {
                "ngram_range": [1, 2],
                "n_features": 262144,
                "alternate_sign": False,
                "normalization": "l2",
                "lowercase": True,
                "token_pattern": r"\b\w+\b",
            },
            "candidate_filter": {
                "minimum_query_token_matches": 1,
                "fallback_to_all_products": True,
            },
            "partial_match": {
                "require_all_query_tokens": True,
                "minimum_query_token_matches": 1,
                "base_score": 1.0,
                "merge_bonus_weight": 0.20,
            },
            "session_recommendation": {
                "current_product_weight": 1.0,
                "viewed_product_weight": 0.70,
                "cart_product_weight": 0.90,
                "max_viewed_seeds": 5,
                "max_cart_seeds": 5,
                "max_total_seeds": 8,
                "candidate_multiplier": 3,
            },
        },
    },

    METHOD_SEMANTIC_VECTOR: {
        **COMMON_RELEVANCE_DEFAULTS,

        "name": "Semantic vector configuration",
        "search_method": METHOD_SEMANTIC_VECTOR,
        "active": False,

        "name_weight": 1,
        "description_weight": 1,
        "category_weight": 1,
        "material_weight": 1,
        "color_weight": 1,
        "size_weight": 1,
        "attributes_weight": 0,

        "algorithm_settings": {
            "document_fields": {
                "name": True,
                "category": True,
                "description": True,
                "material": True,
                "color": True,
                "size": True,
                "attributes": False,
            },
            "text_normalization": {
                "lowercase": True,
                "replace_hyphen_with_space": True,
                "replace_underscore_with_space": True,
            },
            "reranking": {
                "semantic_similarity_weight": 0.75,
                "lexical_overlap_weight": 0.25,
                "minimum_token_length": 2,
            },
            "candidate_pool": {
                "multiplier": 5,
                "minimum_candidates": 50,
            },
            "session_recommendation": {
                "current_product_weight": 1.0,
                "viewed_product_weight": 0.70,
                "cart_product_weight": 0.90,
                "max_viewed_seeds": 5,
                "max_cart_seeds": 5,
                "max_total_seeds": 8,
            },
        },
    },

    METHOD_ELASTICSEARCH_BM25: {
        **COMMON_RELEVANCE_DEFAULTS,

        "name": "Elasticsearch BM25 configuration",
        "search_method": METHOD_ELASTICSEARCH_BM25,
        "active": False,

        "name_weight": 5,
        "description_weight": 2,
        "category_weight": 3,
        "material_weight": 1,
        "color_weight": 1,
        "size_weight": 1,
        "attributes_weight": 0,

        "algorithm_settings": {
            "search_query": {
                "type": "best_fields",
                "operator": "or",
                "field_weights": {
                    "name": 5,
                    "category": 3,
                    "description": 2,
                    "material": 1,
                    "color": 1,
                    "size": 1,
                    "sku": 2,
                },
            },
            "recommendation_query": {
                "type": "best_fields",
                "operator": "or",
                "field_weights": {
                    "name": 5,
                    "category": 4,
                    "description": 2,
                    "material": 2,
                    "color": 1,
                    "size": 1,
                    "sku": 2,
                },
                "candidate_multiplier": 3,
                "minimum_candidates": 20,
                "exclude_source_sku": True,
            },
            "session_recommendation": {
                "current_product_weight": 1.0,
                "viewed_product_weight": 0.70,
                "cart_product_weight": 0.90,
                "max_viewed_seeds": 5,
                "max_cart_seeds": 5,
                "max_total_seeds": 8,
            },
        },
    },
}


# 保留这个名称，避免其他旧代码导入时报错。
# 它表示 Lexical 的默认配置。
DEFAULT_RELEVANCE_CONFIG = deepcopy(
    METHOD_DEFAULT_RELEVANCE_CONFIGS[METHOD_LEXICAL]
)


RELEVANCE_CONFIG_COLUMNS = """
    id,
    name,
    search_method,
    active,

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
    same_size_bonus,

    same_category_recommendation_weight,
    same_color_recommendation_weight,
    same_size_recommendation_weight,

    wishlist_recommendation_weight,
    order_history_recommendation_weight,
    search_history_recommendation_weight,
    view_history_recommendation_weight,

    max_recommendation_per_category,
    recommendation_diversity_penalty,

    recommendation_enabled,
    recommendation_logging_enabled,

    algorithm_settings
"""


def validate_search_method(search_method: str) -> str:
    normalized_method = str(search_method or "").strip()

    if normalized_method not in SUPPORTED_SEARCH_METHODS:
        raise ValueError(
            f"Unsupported search method: {normalized_method}"
        )

    return normalized_method


def deep_merge_dicts(
    base: dict,
    override: dict,
) -> dict:
    result = deepcopy(base)

    for key, value in override.items():
        if (
            isinstance(value, dict)
            and isinstance(result.get(key), dict)
        ):
            result[key] = deep_merge_dicts(
                result[key],
                value,
            )
        else:
            result[key] = value

    return result


def normalize_algorithm_settings(value: Any) -> dict:
    if isinstance(value, dict):
        return value

    if isinstance(value, str):
        try:
            parsed = json.loads(value)

            if isinstance(parsed, dict):
                return parsed
        except json.JSONDecodeError:
            return {}

    return {}


def build_relevance_config(
    search_method: str,
    row: dict | None = None,
) -> dict:
    search_method = validate_search_method(search_method)

    default_config = deepcopy(
        METHOD_DEFAULT_RELEVANCE_CONFIGS[search_method]
    )

    if not row:
        return default_config

    row_data = dict(row)

    row_data["algorithm_settings"] = (
        normalize_algorithm_settings(
            row_data.get("algorithm_settings")
        )
    )

    config = deep_merge_dicts(
        default_config,
        row_data,
    )

    # 不允许数据库返回值改变本次请求的目标算法。
    config["search_method"] = search_method

    return config


def fetch_relevance_config_by_method(
    search_method: str,
) -> dict:
    search_method = validate_search_method(search_method)

    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                f"""
                SELECT
                    {RELEVANCE_CONFIG_COLUMNS}
                FROM search_relevance_config
                WHERE search_method = %s
                LIMIT 1
                """,
                (search_method,),
            )

            row = cursor.fetchone()

    return build_relevance_config(
        search_method=search_method,
        row=row,
    )


def fetch_active_search_method() -> str:
    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                """
                SELECT search_method
                FROM search_relevance_config
                WHERE active = true
                LIMIT 1
                """
            )

            row = cursor.fetchone()

    if not row:
        return METHOD_LEXICAL

    if isinstance(row, dict):
        search_method = row.get("search_method")
    else:
        search_method = row[0]

    try:
        return validate_search_method(search_method)
    except ValueError:
        return METHOD_LEXICAL


def fetch_active_relevance_config() -> dict:
    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                f"""
                SELECT
                    {RELEVANCE_CONFIG_COLUMNS}
                FROM search_relevance_config
                WHERE active = true
                LIMIT 1
                """
            )

            row = cursor.fetchone()

    if not row:
        return fetch_relevance_config_by_method(
            METHOD_LEXICAL
        )

    if isinstance(row, dict):
        search_method = row.get("search_method")
    else:
        search_method = row[2]

    try:
        search_method = validate_search_method(
            search_method
        )
    except ValueError:
        search_method = METHOD_LEXICAL

    return build_relevance_config(
        search_method=search_method,
        row=row,
    )


def fetch_all_relevance_configs() -> dict[str, dict]:
    configs: dict[str, dict] = {}

    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                f"""
                SELECT
                    {RELEVANCE_CONFIG_COLUMNS}
                FROM search_relevance_config
                ORDER BY CASE search_method
                    WHEN 'lexical' THEN 1
                    WHEN 'semantic_vector' THEN 2
                    WHEN 'elasticsearch_bm25' THEN 3
                    ELSE 4
                END
                """
            )

            rows = cursor.fetchall()

    rows_by_method = {}

    for row in rows:
        row_data = dict(row)
        search_method = str(
            row_data.get("search_method") or ""
        ).strip()

        if search_method in SUPPORTED_SEARCH_METHODS:
            rows_by_method[search_method] = row_data

    for search_method in SUPPORTED_SEARCH_METHODS:
        configs[search_method] = build_relevance_config(
            search_method,
            rows_by_method.get(search_method),
        )

    return configs


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
                    SELECT
                        sku,
                        MAX(id) AS max_id
                    FROM product
                    WHERE sku IS NOT NULL
                      AND sku <> ''
                    GROUP BY sku
                ) latest
                    ON latest.max_id = p.id
                LEFT JOIN category c
                    ON p.category_id = c.id
                LEFT JOIN ProductColor co
                    ON p.color_id = co.id
                LEFT JOIN Size s
                    ON p.size_id = s.id
                WHERE p.hidden = false
                ORDER BY p.id DESC
                """
            )

            return cursor.fetchall()


def fetch_latest_product_by_sku(
    sku: str,
) -> dict | None:
    sku = str(sku or "").strip()

    if not sku:
        return None

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
                LEFT JOIN category c
                    ON p.category_id = c.id
                LEFT JOIN ProductColor co
                    ON p.color_id = co.id
                LEFT JOIN Size s
                    ON p.size_id = s.id
                WHERE p.sku = %s
                ORDER BY p.id DESC
                LIMIT 1
                """,
                (sku,),
            )

            return cursor.fetchone()


def save_product_vector(
    sku: str,
    document: str,
    vector,
) -> None:
    vector_blob = psycopg2.Binary(
        pickle.dumps(vector)
    )

    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                """
                INSERT INTO product_document_vector (
                    sku,
                    document,
                    vector
                )
                VALUES (%s, %s, %s)
                ON CONFLICT (sku)
                DO UPDATE SET
                    document = EXCLUDED.document,
                    vector = EXCLUDED.vector
                """,
                (
                    sku,
                    document,
                    vector_blob,
                ),
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
                SELECT
                    sku,
                    document,
                    vector
                FROM product_document_vector
                WHERE vector IS NOT NULL
                """
            )

            return cursor.fetchall()


def count_product_vectors() -> int:
    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                """
                SELECT COUNT(*) AS cnt
                FROM product_document_vector
                """
            )

            row = cursor.fetchone()

    if isinstance(row, dict):
        return int(row["cnt"])

    return int(row[0])


def count_products() -> int:
    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                """
                SELECT COUNT(*) AS cnt
                FROM product
                """
            )

            row = cursor.fetchone()

    if isinstance(row, dict):
        return int(row["cnt"])

    return int(row[0])


def count_distinct_skus() -> int:
    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(
                """
                SELECT COUNT(DISTINCT sku) AS cnt
                FROM product
                WHERE sku IS NOT NULL
                  AND sku <> ''
                """
            )

            row = cursor.fetchone()

    if isinstance(row, dict):
        return int(row["cnt"])

    return int(row[0])


def build_documents(
    rows: list[dict],
    builder_fn: Callable[[dict, dict], str],
    config: dict,
) -> Dict[str, str]:
    documents: Dict[str, str] = {}
    seen: set[str] = set()

    for row in rows:
        sku = str(
            row.get("sku") or ""
        ).strip()

        if not sku or sku in seen:
            continue

        document = builder_fn(
            row,
            config,
        )

        if not document:
            continue

        documents[sku] = document
        seen.add(sku)

    return documents