import json
import logging
from typing import Any, Dict

from repositories.product_repository import (
    fetch_products,
    fetch_latest_product_by_sku,
    fetch_relevance_config_by_method,
    fetch_all_product_vectors,
    save_product_vector,
    delete_product_vector,
    count_product_vectors,
    count_products,
    count_distinct_skus,
    build_documents,
)
from lexical.search_index import search_index
from lexical.text_preprocessor import (
    build_product_document,
    normalize_text,
)


logger = logging.getLogger(__name__)

LEXICAL_METHOD = "lexical"


def parse_algorithm_settings(config: dict) -> dict:
    settings = config.get("algorithm_settings", {})

    if isinstance(settings, dict):
        return settings

    if isinstance(settings, str):
        try:
            parsed = json.loads(settings)
            return parsed if isinstance(parsed, dict) else {}
        except json.JSONDecodeError:
            logger.warning(
                "[CONFIG][LEXICAL] invalid algorithm_settings JSON"
            )

    return {}


def get_algorithm_settings_section(
    config: dict,
    section: str,
) -> dict:
    settings = parse_algorithm_settings(config)
    value = settings.get(section, {})

    return value if isinstance(value, dict) else {}


def get_non_negative_float(
    value: Any,
    default: float,
) -> float:
    try:
        result = float(value)
    except (TypeError, ValueError):
        return default

    if result < 0:
        return default

    return result


def get_positive_int(
    value: Any,
    default: int,
) -> int:
    try:
        result = int(value)
    except (TypeError, ValueError):
        return default

    if result < 1:
        return default

    return result


def get_non_negative_int(
    value: Any,
    default: int,
) -> int:
    try:
        result = int(value)
    except (TypeError, ValueError):
        return default

    if result < 0:
        return default

    return result


def get_boolean(
    value: Any,
    default: bool,
) -> bool:
    if isinstance(value, bool):
        return value

    if isinstance(value, str):
        normalized = value.strip().lower()

        if normalized in {"true", "1", "yes", "on"}:
            return True

        if normalized in {"false", "0", "no", "off"}:
            return False

    if isinstance(value, int):
        return value != 0

    return default


def load_lexical_config() -> dict:
    config = fetch_relevance_config_by_method(LEXICAL_METHOD)

    if not isinstance(config, dict):
        raise RuntimeError(
            "Lexical relevance configuration could not be loaded."
        )

    config["search_method"] = LEXICAL_METHOD

    return config


def get_lexical_runtime_config() -> dict:
    current_config = getattr(search_index, "config", None)

    if (
        isinstance(current_config, dict)
        and current_config.get("search_method") == LEXICAL_METHOD
    ):
        return current_config

    config = load_lexical_config()
    search_index.config = config

    return config


def build_metadata(
    rows: list[dict],
    config: dict | None = None,
) -> Dict[str, dict]:
    metadata: Dict[str, dict] = {}

    for row in rows:
        sku = str(row.get("sku") or "").strip()

        if not sku or sku in metadata:
            continue

        metadata[sku] = {
            "category": normalize_text(
                row.get("category") or "",
                config,
            ),
            "material": normalize_text(
                row.get("material") or "",
                config,
            ),
            "color": normalize_text(
                row.get("color") or "",
                config,
            ),
            "size": normalize_text(
                row.get("size") or "",
                config,
            ),
        }

    return metadata


def build_one_metadata(
    row: dict,
    config: dict | None = None,
) -> dict:
    return {
        "category": normalize_text(
            row.get("category") or "",
            config,
        ),
        "material": normalize_text(
            row.get("material") or "",
            config,
        ),
        "color": normalize_text(
            row.get("color") or "",
            config,
        ),
        "size": normalize_text(
            row.get("size") or "",
            config,
        ),
    }


def recommend_session_products(
    viewed_skus: list[str],
    cart_skus: list[str],
    current_sku: str | None = None,
    limit: int = 10,
):
    try:
        if search_index.matrix is None:
            rebuild_search_index_from_saved_vectors()

        index_broken = (
            search_index.matrix is None
            or len(search_index.skus) == 0
            or len(search_index.documents) == 0
        )

        if (
            not index_broken
            and len(search_index.skus)
            != search_index.matrix.shape[0]
        ):
            index_broken = True

        if index_broken:
            rebuild_search_index()

    except Exception:
        logger.exception(
            "[RECOMMEND][SESSION] failed to prepare index"
        )
        return []

    config = get_lexical_runtime_config()

    session_settings = get_algorithm_settings_section(
        config,
        "session_recommendation",
    )

    current_product_weight = get_non_negative_float(
        session_settings.get("current_product_weight"),
        1.0,
    )

    cart_product_weight = get_non_negative_float(
        session_settings.get("cart_product_weight"),
        0.90,
    )

    viewed_product_weight = get_non_negative_float(
        session_settings.get("viewed_product_weight"),
        0.70,
    )

    max_viewed_seeds = get_non_negative_int(
        session_settings.get("max_viewed_seeds"),
        5,
    )

    max_cart_seeds = get_non_negative_int(
        session_settings.get("max_cart_seeds"),
        5,
    )

    max_total_seeds = get_positive_int(
        session_settings.get("max_total_seeds"),
        8,
    )

    candidate_multiplier = get_positive_int(
        session_settings.get("candidate_multiplier"),
        3,
    )

    viewed_skus = [
        str(sku).strip()
        for sku in (viewed_skus or [])
        if str(sku).strip()
    ]

    cart_skus = [
        str(sku).strip()
        for sku in (cart_skus or [])
        if str(sku).strip()
    ]

    current_sku = str(current_sku or "").strip()

    selected_viewed_skus = (
        viewed_skus[-max_viewed_seeds:]
        if max_viewed_seeds > 0
        else []
    )

    selected_cart_skus = (
        cart_skus[-max_cart_seeds:]
        if max_cart_seeds > 0
        else []
    )

    source_skus: list[tuple[str, float]] = []

    if current_sku and current_product_weight > 0:
        source_skus.append(
            (
                current_sku,
                current_product_weight,
            )
        )

    if cart_product_weight > 0:
        for sku in selected_cart_skus:
            source_skus.append(
                (
                    sku,
                    cart_product_weight,
                )
            )

    if viewed_product_weight > 0:
        for sku in selected_viewed_skus:
            source_skus.append(
                (
                    sku,
                    viewed_product_weight,
                )
            )

    source_skus = source_skus[:max_total_seeds]

    if not source_skus:
        return []

    excluded_skus = (
        set(viewed_skus)
        | set(cart_skus)
    )

    if current_sku:
        excluded_skus.add(current_sku)

    merged: dict[str, dict] = {}

    candidate_limit = max(
        limit * candidate_multiplier,
        limit,
    )

    for source_sku, weight in source_skus:
        candidates = search_index.recommend_by_sku(
            source_sku,
            limit=candidate_limit,
        )

        for item in candidates:
            candidate_sku = str(
                item.get("product_sku") or ""
            ).strip()

            if not candidate_sku:
                continue

            if candidate_sku in excluded_skus:
                continue

            score = (
                float(item.get("similarity", 0))
                * weight
            )

            if score <= 0:
                continue

            if candidate_sku not in merged:
                merged[candidate_sku] = {
                    "product_sku": candidate_sku,
                    "sku": candidate_sku,
                    "similarity": score,
                }
            else:
                merged[candidate_sku]["similarity"] += score

    results = sorted(
        merged.values(),
        key=lambda item: item["similarity"],
        reverse=True,
    )

    return results[:limit]


def rebuild_search_index() -> int:
    logger.info("[REINDEX][FULL] started")

    config = load_lexical_config()
    rows = fetch_products()

    documents: Dict[str, str] = build_documents(
        rows,
        build_product_document,
        config,
    )

    metadata = build_metadata(
        rows,
        config,
    )

    count = search_index.rebuild(
        documents=documents,
        metadata=metadata,
        config=config,
    )

    for sku, document in documents.items():
        vector = search_index.vectorize_document(document)

        save_product_vector(
            sku,
            document,
            vector,
        )

    logger.info(
        "[REINDEX][FULL] finished indexed=%d",
        count,
    )

    return count


def rebuild_search_index_from_saved_vectors() -> int:
    logger.info(
        "[INDEX] loading saved vectors from database"
    )

    config = load_lexical_config()
    rows = fetch_products()
    metadata = build_metadata(
        rows,
        config,
    )
    vector_rows = fetch_all_product_vectors()

    return search_index.rebuild_from_saved_vectors(
        rows=vector_rows,
        metadata=metadata,
        config=config,
    )


def partial_reindex_product(sku: str) -> int:
    sku = str(sku or "").strip()

    if not sku:
        logger.warning(
            "[REINDEX][PARTIAL] empty sku"
        )
        return 0

    logger.info(
        "[REINDEX][PARTIAL] started sku=%s",
        sku,
    )

    config = load_lexical_config()
    search_index.config = config

    row = fetch_latest_product_by_sku(sku)

    if not row:
        delete_product_vector(sku)
        search_index.remove_one(sku)

        logger.info(
            "[REINDEX][PARTIAL] deleted sku=%s "
            "reason=not_found",
            sku,
        )

        return 0

    if bool(row.get("hidden")):
        delete_product_vector(sku)
        search_index.remove_one(sku)

        logger.info(
            "[REINDEX][PARTIAL] deleted sku=%s "
            "reason=hidden",
            sku,
        )

        return 0

    document = build_product_document(
        row,
        config,
    )

    if not document:
        delete_product_vector(sku)
        search_index.remove_one(sku)

        logger.info(
            "[REINDEX][PARTIAL] deleted sku=%s "
            "reason=empty_document",
            sku,
        )

        return 0

    vector = search_index.vectorize_document(document)
    metadata = build_one_metadata(
        row,
        config,
    )

    save_product_vector(
        sku,
        document,
        vector,
    )

    search_index.update_one(
        sku,
        document,
        vector,
        metadata,
    )

    logger.info(
        "[REINDEX][PARTIAL] finished "
        "sku=%s product_id=%s document_length=%d",
        sku,
        row.get("id"),
        len(document),
    )

    return 1


def search_products(
    query: str,
    limit: int = 50,
    retry: bool = True,
):
    try:
        if search_index.matrix is None:
            logger.warning(
                "[SEARCH] matrix empty -> reload vectors"
            )

            rebuild_search_index_from_saved_vectors()

        if (
            search_index.matrix is not None
            and len(search_index.skus)
            != search_index.matrix.shape[0]
        ):
            logger.warning(
                "[SEARCH] inconsistent index detected "
                "skus=%d matrix_rows=%d -> reload vectors",
                len(search_index.skus),
                search_index.matrix.shape[0],
            )

            rebuild_search_index_from_saved_vectors()

    except Exception as exc:
        logger.exception(
            "[SEARCH] failed loading vectors: %s",
            exc,
        )

        try:
            logger.warning(
                "[SEARCH] fallback full rebuild"
            )

            rebuild_search_index()

        except Exception:
            logger.exception(
                "[SEARCH] full rebuild failed"
            )

    if search_index.matrix is None:
        try:
            logger.warning(
                "[SEARCH] matrix still empty -> full rebuild"
            )

            rebuild_search_index()

        except Exception:
            logger.exception(
                "[SEARCH] final full rebuild failed"
            )

            return []

    config = get_lexical_runtime_config()

    partial_match_settings = (
        get_algorithm_settings_section(
            config,
            "partial_match",
        )
    )

    require_all_query_tokens = get_boolean(
        partial_match_settings.get(
            "require_all_query_tokens"
        ),
        True,
    )

    minimum_query_token_matches = get_positive_int(
        partial_match_settings.get(
            "minimum_query_token_matches"
        ),
        1,
    )

    partial_match_base_score = get_non_negative_float(
        partial_match_settings.get("base_score"),
        1.0,
    )

    partial_match_merge_weight = get_non_negative_float(
        partial_match_settings.get(
            "merge_bonus_weight"
        ),
        0.20,
    )

    normalized_query = normalize_text(query)

    partial_results = []

    query_tokens = set(
        normalized_query.split()
    )

    for (
        sku,
        document_tokens,
    ) in search_index.document_tokens.items():
        if not query_tokens:
            continue

        matching_token_count = len(
            query_tokens.intersection(
                document_tokens
            )
        )

        if require_all_query_tokens:
            is_partial_match = query_tokens.issubset(
                document_tokens
            )
        else:
            is_partial_match = (
                matching_token_count
                >= minimum_query_token_matches
            )

        if not is_partial_match:
            continue

        partial_results.append(
            {
                "product_sku": sku,
                "sku": sku,
                "similarity": partial_match_base_score,
            }
        )

    index_broken = (
        search_index.matrix is None
        or len(search_index.skus) == 0
        or len(search_index.documents) == 0
    )

    if (
        not index_broken
        and len(search_index.skus)
        != search_index.matrix.shape[0]
    ):
        index_broken = True

    if retry and index_broken:
        logger.warning(
            "[SEARCH] broken index -> auto rebuild + retry"
        )

        try:
            rebuild_search_index()

            return search_products(
                query=query,
                limit=limit,
                retry=False,
            )

        except Exception:
            logger.exception(
                "[SEARCH] retry rebuild failed"
            )

            return []

    vector_results = search_index.search(
        query,
        limit,
    )

    merged_map: dict[str, dict] = {}

    for item in vector_results:
        sku = str(
            item.get("product_sku") or ""
        ).strip()

        if sku:
            merged_map[sku] = item

    for item in partial_results:
        sku = str(
            item.get("product_sku") or ""
        ).strip()

        if not sku:
            continue

        if sku in merged_map:
            merged_map[sku]["similarity"] = (
                float(
                    merged_map[sku].get(
                        "similarity",
                        0,
                    )
                )
                + float(item["similarity"])
                * partial_match_merge_weight
            )
        else:
            merged_map[sku] = item

    results = sorted(
        merged_map.values(),
        key=lambda item: float(
            item.get("similarity", 0)
        ),
        reverse=True,
    )[:limit]

    return results


def recommend_products(
    sku: str,
    limit: int = 10,
):
    try:
        if search_index.matrix is None:
            rebuild_search_index_from_saved_vectors()

        index_broken = (
            search_index.matrix is None
            or len(search_index.skus) == 0
            or len(search_index.documents) == 0
        )

        if (
            not index_broken
            and len(search_index.skus)
            != search_index.matrix.shape[0]
        ):
            index_broken = True

        if index_broken:
            logger.warning(
                "[RECOMMEND] broken index -> full rebuild"
            )

            rebuild_search_index()

    except Exception:
        logger.exception(
            "[RECOMMEND] failed to prepare index"
        )

        return []

    return search_index.recommend_by_sku(
        sku,
        limit,
    )


def get_index_status() -> dict:
    return {
        "product_rows": count_products(),
        "distinct_skus": count_distinct_skus(),
        "vector_rows": count_product_vectors(),
        "indexed_documents": len(
            search_index.documents
        ),
    }