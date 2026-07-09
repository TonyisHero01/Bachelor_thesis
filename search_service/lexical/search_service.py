import logging
from typing import Dict
import time

from repositories.product_repository import (
    fetch_products,
    fetch_latest_product_by_sku,
    fetch_active_relevance_config,
    fetch_all_product_vectors,
    save_product_vector,
    delete_product_vector,
    count_product_vectors,
    count_products,
    count_distinct_skus,
    build_documents,
)
from lexical.search_index import search_index
from lexical.text_preprocessor import build_product_document, normalize_text


logger = logging.getLogger(__name__)


def build_metadata(rows: list[dict]) -> Dict[str, dict]:
    metadata: Dict[str, dict] = {}

    for row in rows:
        sku = str(row.get("sku") or "").strip()

        if not sku or sku in metadata:
            continue

        metadata[sku] = {
            "category": normalize_text(row.get("category") or ""),
            "material": normalize_text(row.get("material") or ""),
            "color": normalize_text(row.get("color") or ""),
            "size": normalize_text(row.get("size") or ""),
        }

    return metadata

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
            and len(search_index.skus) != search_index.matrix.shape[0]
        ):
            index_broken = True

        if index_broken:
            rebuild_search_index()

    except Exception:
        logger.exception("[RECOMMEND][SESSION] failed to prepare index")
        return []

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

    source_skus = []

    if current_sku:
        source_skus.append((current_sku, 1.5))

    for sku in cart_skus:
        source_skus.append((sku, 1.3))

    for sku in viewed_skus[-10:]:
        source_skus.append((sku, 1.0))

    if not source_skus:
        return []

    excluded_skus = set(viewed_skus) | set(cart_skus)

    if current_sku:
        excluded_skus.add(current_sku)

    merged = {}

    for source_sku, weight in source_skus:
        candidates = search_index.recommend_by_sku(
            source_sku,
            limit=limit * 3,
        )

        for item in candidates:
            candidate_sku = item.get("product_sku")

            if not candidate_sku:
                continue

            if candidate_sku in excluded_skus:
                continue

            score = float(item.get("similarity", 0)) * weight

            if candidate_sku not in merged:
                merged[candidate_sku] = {
                    "product_sku": candidate_sku,
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

def build_one_metadata(row: dict) -> dict:
    return {
        "category": normalize_text(row.get("category") or ""),
        "material": normalize_text(row.get("material") or ""),
        "color": normalize_text(row.get("color") or ""),
        "size": normalize_text(row.get("size") or ""),
    }


def rebuild_search_index() -> int:
    logger.info("[REINDEX][FULL] started")

    config = fetch_active_relevance_config()
    rows = fetch_products()

    documents: Dict[str, str] = build_documents(
        rows,
        build_product_document,
        config,
    )

    metadata = build_metadata(rows)

    count = search_index.rebuild(
        documents=documents,
        metadata=metadata,
        config=config,
    )

    for sku, document in documents.items():
        vector = search_index.vectorize_document(document)
        save_product_vector(sku, document, vector)

    logger.info("[REINDEX][FULL] finished indexed=%d", count)

    return count


def rebuild_search_index_from_saved_vectors() -> int:
    logger.info("[INDEX] loading saved vectors from database")

    config = fetch_active_relevance_config()
    rows = fetch_products()
    metadata = build_metadata(rows)
    vector_rows = fetch_all_product_vectors()

    return search_index.rebuild_from_saved_vectors(
        rows=vector_rows,
        metadata=metadata,
        config=config,
    )


def partial_reindex_product(sku: str) -> int:
    sku = (sku or "").strip()

    if not sku:
        logger.warning("[REINDEX][PARTIAL] empty sku")
        return 0

    logger.info("[REINDEX][PARTIAL] started sku=%s", sku)

    config = fetch_active_relevance_config()
    search_index.config = config

    row = fetch_latest_product_by_sku(sku)

    if not row:
        delete_product_vector(sku)
        search_index.remove_one(sku)
        logger.info("[REINDEX][PARTIAL] deleted sku=%s reason=not_found", sku)
        return 0

    if bool(row.get("hidden")):
        delete_product_vector(sku)
        search_index.remove_one(sku)
        logger.info("[REINDEX][PARTIAL] deleted sku=%s reason=hidden", sku)
        return 0

    document = build_product_document(row, config)

    if not document:
        delete_product_vector(sku)
        search_index.remove_one(sku)
        logger.info("[REINDEX][PARTIAL] deleted sku=%s reason=empty_document", sku)
        return 0

    vector = search_index.vectorize_document(document)
    metadata = build_one_metadata(row)

    save_product_vector(sku, document, vector)
    search_index.update_one(sku, document, vector, metadata)

    logger.info(
        "[REINDEX][PARTIAL] finished sku=%s product_id=%s document_length=%d",
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
            logger.warning("[SEARCH] matrix empty -> reload vectors")
            rebuild_search_index_from_saved_vectors()

        if (
            search_index.matrix is not None
            and len(search_index.skus) != search_index.matrix.shape[0]
        ):
            logger.warning(
                "[SEARCH] inconsistent index detected skus=%d matrix_rows=%d -> reload vectors",
                len(search_index.skus),
                search_index.matrix.shape[0],
            )
            rebuild_search_index_from_saved_vectors()

    except Exception as e:
        logger.exception("[SEARCH] failed loading vectors: %s", e)

        try:
            logger.warning("[SEARCH] fallback full rebuild")
            rebuild_search_index()
        except Exception:
            logger.exception("[SEARCH] full rebuild failed")

    if search_index.matrix is None:
        try:
            logger.warning("[SEARCH] matrix still empty -> full rebuild")
            rebuild_search_index()
        except Exception:
            logger.exception("[SEARCH] final full rebuild failed")
            return []

    normalized_query = normalize_text(query)

    partial_results = []

    # =========================
    # PARTIAL MATCH SEARCH
    # =========================

    query_tokens = set(normalized_query.split())

    for sku, document_tokens in search_index.document_tokens.items():
        if not query_tokens or not query_tokens.issubset(document_tokens):
            continue

        score = 1.0

        partial_results.append({
            "product_sku": sku,
            "similarity": score,
        })
        

    # =========================
    # VECTOR SEARCH
    # =========================

    index_broken = (
        search_index.matrix is None
        or len(search_index.skus) == 0
        or len(search_index.documents) == 0
    )

    if (
        not index_broken
        and len(search_index.skus) != search_index.matrix.shape[0]
    ):
        index_broken = True

    if retry and index_broken:
        logger.warning("[SEARCH] broken index -> auto rebuild + retry")

        try:
            rebuild_search_index()

            return search_products(
                query=query,
                limit=limit,
                retry=False,
            )

        except Exception:
            logger.exception("[SEARCH] retry rebuild failed")
            return []

    semantic_results = search_index.search(query, limit)

    merged_map = {}

    for item in semantic_results:
        sku = item.get("product_sku")
        if sku:
            merged_map[sku] = item

    for item in partial_results:
        sku = item.get("product_sku")
        if not sku:
            continue

        if sku in merged_map:
            merged_map[sku]["similarity"] += item["similarity"] * 0.2
        else:
            merged_map[sku] = item

    results = sorted(
        merged_map.values(),
        key=lambda x: x["similarity"],
        reverse=True,
    )[:limit]

    return results


def recommend_products(sku: str, limit: int = 10):
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
            and len(search_index.skus) != search_index.matrix.shape[0]
        ):
            index_broken = True

        if index_broken:
            logger.warning("[RECOMMEND] broken index -> full rebuild")
            rebuild_search_index()

    except Exception:
        logger.exception("[RECOMMEND] failed to prepare index")
        return []

    return search_index.recommend_by_sku(sku, limit)


def get_index_status() -> dict:
    return {
        "product_rows": count_products(),
        "distinct_skus": count_distinct_skus(),
        "vector_rows": count_product_vectors(),
        "indexed_documents": len(search_index.documents),
    }