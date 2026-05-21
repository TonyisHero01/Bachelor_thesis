from elasticsearch import Elasticsearch

from config import settings
from repositories.product_repository import fetch_active_relevance_config


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