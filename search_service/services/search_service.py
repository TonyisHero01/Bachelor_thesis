from typing import Dict

from repositories.product_repository import (
    fetch_products,
    count_products,
    count_distinct_skus,
    build_documents,
)
from services.search_index import search_index
from services.text_preprocessor import build_product_document


def rebuild_search_index() -> int:
    rows = fetch_products()
    documents: Dict[str, str] = build_documents(rows, build_product_document)
    return search_index.rebuild(documents)


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