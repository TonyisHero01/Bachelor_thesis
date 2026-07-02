from elasticsearch import Elasticsearch

from config import settings
from repositories.product_repository import (
    fetch_active_relevance_config,
    fetch_latest_product_by_sku,
)

INDEX_NAME = "products"

class ElasticProductSearchService:
    def __init__(self):
        self.client = Elasticsearch(settings.elasticsearch_url)
        self.config = fetch_active_relevance_config()

    def create_index(self):
        if self.client.indices.exists(index=INDEX_NAME):
            self.client.indices.delete(index=INDEX_NAME)

        self.client.indices.create(
            index=INDEX_NAME,
            mappings={
                "properties": {
                    "product_id": {"type": "integer"},
                    "sku": {"type": "keyword"},
                    "name": {"type": "text"},
                    "description": {"type": "text"},
                    "category": {"type": "text"},
                    "material": {"type": "text"},
                    "color": {"type": "text"},
                    "size": {"type": "text"},
                    "price": {"type": "float"},
                }
            },
        )

    def recommend_by_sku(self, sku: str, limit: int = 10):
        sku = str(sku or "").strip()

        if sku == "":
            return {
                "method": "elasticsearch_bm25_recommendation",
                "sku": sku,
                "limit": limit,
                "results": [],
            }

        product = fetch_latest_product_by_sku(sku)

        if not product:
            return {
                "method": "elasticsearch_bm25_recommendation",
                "sku": sku,
                "limit": limit,
                "results": [],
            }

        query_parts = [
            str(product.get("name") or ""),
            str(product.get("category") or ""),
            str(product.get("description") or ""),
            str(product.get("material") or ""),
            str(product.get("color") or ""),
            str(product.get("size") or ""),
            str(product.get("sku") or ""),
        ]

        query_text = " ".join(
            part.strip()
            for part in query_parts
            if part and part.strip()
        )

        if query_text == "":
            return {
                "method": "elasticsearch_bm25_recommendation",
                "sku": sku,
                "limit": limit,
                "results": [],
            }

        response = self.client.search(
            index=INDEX_NAME,
            size=max(limit * 3, 20),
            query={
                "bool": {
                    "must": [
                        {
                            "multi_match": {
                                "query": query_text,
                                "fields": [
                                    "name^5",
                                    "category^4",
                                    "description^2",
                                    "material^2",
                                    "color",
                                    "size",
                                    "sku^2",
                                ],
                                "type": "best_fields",
                                "operator": "or",
                            }
                        }
                    ],
                    "must_not": [
                        {
                            "term": {
                                "sku": sku,
                            }
                        }
                    ],
                }
            },
        )

        results = []

        for hit in response["hits"]["hits"]:
            source = hit["_source"]
            result_sku = str(source.get("sku") or "").strip()

            if result_sku == "" or result_sku == sku:
                continue

            results.append({
                "id": source.get("product_id"),
                "name": source.get("name"),
                "description": source.get("description"),
                "price": source.get("price"),
                "sku": result_sku,
                "product_sku": result_sku,
                "similarity": float(hit["_score"]),
                "method": "elasticsearch_bm25_recommendation",
            })

            if len(results) >= limit:
                break

        return {
            "method": "elasticsearch_bm25_recommendation",
            "sku": sku,
            "limit": limit,
            "results": results,
        }

    def build_document(self, product):
        return {
            "product_id": product["id"],
            "sku": product["sku"],
            "name": product["name"] or "",
            "description": product["description"] or "",
            "category": product["category_name"] or "",
            "material": product["material"] or "",
            "color": product["color_name"] or "",
            "size": product["size_name"] or "",
            "price": float(product["price"] or 0),
        }

    def index_products(self, products):
        for product in products:
            document = self.build_document(product)

            self.client.index(
                index=INDEX_NAME,
                id=document["product_id"],
                document=document,
            )

        self.client.indices.refresh(index=INDEX_NAME)

    def search(self, query: str, limit: int = 10):
        response = self.client.search(
            index=INDEX_NAME,
            size=limit,
            query={
                "multi_match": {
                    "query": query,
                    "fields": [
                        "name^5",
                        "category^3",
                        "description^2",
                        "material",
                        "color",
                        "size",
                        "sku^2",
                    ],
                    "type": "best_fields",
                    "operator": "or",
                }
            },
        )

        results = []

        for hit in response["hits"]["hits"]:
            source = hit["_source"]

            results.append({
                "id": source["product_id"],
                "name": source["name"],
                "description": source["description"],
                "price": source["price"],
                "sku": source["sku"],
                "product_sku": source["sku"],
                "similarity": float(hit["_score"]),
                "method": "elasticsearch_bm25",
            })

        return {
            "method": "elasticsearch_bm25",
            "query": query,
            "limit": limit,
            "results": results,
        }