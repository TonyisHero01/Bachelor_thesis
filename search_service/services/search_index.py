import logging
import pickle
from typing import Dict, List

import numpy as np
from scipy.sparse import vstack
from sklearn.feature_extraction.text import HashingVectorizer
from sklearn.metrics.pairwise import cosine_similarity

logger = logging.getLogger(__name__)


class SearchIndex:
    def __init__(self):
        self.vectorizer = HashingVectorizer(
            token_pattern=r"\b\w+\b",
            lowercase=True,
            ngram_range=(1, 2),
            n_features=2**18,
            alternate_sign=False,
            norm="l2",
        )

        self.skus: List[str] = []
        self.documents: Dict[str, str] = {}
        self.matrix = None
        self.metadata: Dict[str, dict] = {}
        self.config: dict = {}

    def vectorize_document(self, document: str):
        return self.vectorizer.transform([document])

    def rebuild(
        self,
        documents: Dict[str, str],
        metadata: Dict[str, dict] | None = None,
        config: dict | None = None,
    ) -> int:
        logger.info("[REINDEX][FULL] rebuilding in-memory search index")

        self.skus = list(documents.keys())
        self.documents = documents.copy()
        self.metadata = metadata or {}
        self.config = config or {}

        if not self.documents:
            self.matrix = None
            logger.warning("[REINDEX][FULL] no documents found")
            return 0

        self.matrix = self.vectorizer.transform(
            list(self.documents.values())
        )

        logger.info("[REINDEX][FULL] indexed %d documents", len(self.documents))
        return len(self.documents)

    def rebuild_from_saved_vectors(
        self,
        rows: list[dict],
        metadata: Dict[str, dict] | None = None,
        config: dict | None = None,
    ) -> int:
        self.skus = []
        self.documents = {}
        vectors = []

        for row in rows:
            sku = str(row.get("sku") or "").strip()
            document = str(row.get("document") or "")
            vector_blob = row.get("vector")

            if not sku or vector_blob is None:
                continue

            vector = pickle.loads(vector_blob)

            self.skus.append(sku)
            self.documents[sku] = document
            vectors.append(vector)

        self.metadata = metadata or {}
        self.config = config or {}

        if not vectors:
            self.matrix = None
            logger.warning("[INDEX] no saved vectors found")
            return 0

        self.matrix = vstack(vectors)
        logger.info("[INDEX] loaded %d saved vectors", len(vectors))
        return len(vectors)

    def update_one(self, sku: str, document: str, vector, metadata: dict | None = None):
        sku = sku.strip()

        if not sku:
            return

        if sku in self.skus:
            index = self.skus.index(sku)
            self.documents[sku] = document

            if self.matrix is not None:
                rows = [self.matrix[i] for i in range(self.matrix.shape[0])]
                rows[index] = vector
                self.matrix = vstack(rows)

            logger.info("[REINDEX][PARTIAL] updated vector in memory for sku=%s", sku)
        else:
            self.skus.append(sku)
            self.documents[sku] = document

            if self.matrix is None:
                self.matrix = vector
            else:
                self.matrix = vstack([self.matrix, vector])

            logger.info("[REINDEX][PARTIAL] added vector in memory for sku=%s", sku)

        if metadata is not None:
            self.metadata[sku] = metadata

    def remove_one(self, sku: str):
        sku = sku.strip()

        if sku not in self.skus:
            logger.info("[REINDEX][PARTIAL] sku=%s not found in memory index", sku)
            return

        index = self.skus.index(sku)

        self.skus.pop(index)
        self.documents.pop(sku, None)
        self.metadata.pop(sku, None)

        if self.matrix is None:
            return

        rows = [
            self.matrix[i]
            for i in range(self.matrix.shape[0])
            if i != index
        ]

        self.matrix = vstack(rows) if rows else None
        logger.info("[REINDEX][PARTIAL] removed vector from memory for sku=%s", sku)

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