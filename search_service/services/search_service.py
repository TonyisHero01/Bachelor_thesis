from typing import Dict

from repositories.product_repository import (
    fetch_products,
    fetch_active_relevance_config,
    count_products,
    count_distinct_skus,
    build_documents,
)
from services.search_index import search_index
from services.text_preprocessor import build_product_document, normalize_text


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


def rebuild_search_index() -> int:
    config = fetch_active_relevance_config()
    rows = fetch_products()

    documents: Dict[str, str] = build_documents(
        rows,
        build_product_document,
        config,
    )

    metadata = build_metadata(rows)

    return search_index.rebuild(
        documents=documents,
        metadata=metadata,
        config=config,
    )


def search_products(query: str, limit: int = 50):
    return search_index.search(query, limit)


def recommend_products(sku: str, limit: int = 10):
    return search_index.recommend_by_sku(sku, limit)


def get_index_status() -> dict:
    return {
        "product_rows": count_products(),
        "distinct_skus": count_distinct_skus(),
        "indexed_documents": len(search_index.documents),
    }