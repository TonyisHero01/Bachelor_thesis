import logging
from typing import Dict, List

import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity


logger = logging.getLogger(__name__)


class SearchIndex:
    def __init__(self):
        self.vectorizer = TfidfVectorizer(
            token_pattern=r"\b\w+\b",
            lowercase=True,
            ngram_range=(1, 2),
        )
        self.skus: List[str] = []
        self.documents: List[str] = []
        self.matrix = None
        self.metadata: Dict[str, dict] = {}
        self.config: dict = {}

    def rebuild(
        self,
        documents: Dict[str, str],
        metadata: Dict[str, dict] | None = None,
        config: dict | None = None,
    ) -> int:
        logger.info("Rebuilding search index...")

        self.skus = list(documents.keys())
        self.documents = list(documents.values())
        self.metadata = metadata or {}
        self.config = config or {}

        if not self.documents:
            self.matrix = None
            logger.warning("No documents found for indexing")
            return 0

        self.matrix = self.vectorizer.fit_transform(self.documents)

        logger.info(f"Indexed {len(self.documents)} documents")
        return len(self.documents)

    def search(self, query: str, limit: int = 50):
        if self.matrix is None:
            logger.warning("Search requested but index is empty")
            return []

        query = (query or "").strip()

        if not query:
            return []

        query_vector = self.vectorizer.transform([query])
        scores = cosine_similarity(query_vector, self.matrix).flatten()

        idx = np.argsort(scores)[::-1][:limit]

        return [
            {
                "product_sku": self.skus[i],
                "similarity": float(scores[i]),
            }
            for i in idx
            if scores[i] > 0
        ]

    def recommend_by_sku(self, sku: str, limit: int = 10):
        if self.matrix is None:
            logger.warning("Recommendation requested but index is empty")
            return []

        sku = (sku or "").strip()

        if not sku or sku not in self.skus:
            return []

        product_index = self.skus.index(sku)
        base_sku = self.skus[product_index]
        base_meta = self.metadata.get(base_sku, {})

        product_vector = self.matrix[product_index]
        base_scores = cosine_similarity(product_vector, self.matrix).flatten()
        adjusted_scores = base_scores.copy()

        same_category_bonus = float(self.config.get("same_category_bonus", 0.0))
        same_material_bonus = float(self.config.get("same_material_bonus", 0.0))
        same_color_bonus = float(self.config.get("same_color_bonus", 0.0))
        same_size_bonus = float(self.config.get("same_size_bonus", 0.0))

        for i, candidate_sku in enumerate(self.skus):
            if candidate_sku == base_sku:
                adjusted_scores[i] = -1
                continue

            candidate_meta = self.metadata.get(candidate_sku, {})

            if (
                same_category_bonus > 0
                and base_meta.get("category")
                and base_meta.get("category") == candidate_meta.get("category")
            ):
                adjusted_scores[i] += same_category_bonus

            if (
                same_material_bonus > 0
                and base_meta.get("material")
                and base_meta.get("material") == candidate_meta.get("material")
            ):
                adjusted_scores[i] += same_material_bonus

            if (
                same_color_bonus > 0
                and base_meta.get("color")
                and base_meta.get("color") == candidate_meta.get("color")
            ):
                adjusted_scores[i] += same_color_bonus

            if (
                same_size_bonus > 0
                and base_meta.get("size")
                and base_meta.get("size") == candidate_meta.get("size")
            ):
                adjusted_scores[i] += same_size_bonus

        idx = np.argsort(adjusted_scores)[::-1]

        results = []

        for i in idx:
            if adjusted_scores[i] <= 0:
                continue

            results.append(
                {
                    "product_sku": self.skus[i],
                    "similarity": float(adjusted_scores[i]),
                }
            )

            if len(results) >= limit:
                break

        return results


search_index = SearchIndex()