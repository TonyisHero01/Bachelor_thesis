import logging
from typing import Dict, List

import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity


logger = logging.getLogger(__name__)


class SearchIndex:
    """
    In-memory TF-IDF index.
    - build once (on startup or /reindex)
    - search many times (no re-fit on each query)
    """

    def __init__(self):
        self.vectorizer = TfidfVectorizer(
            token_pattern=r"\b\w+\b",
            lowercase=True,
            ngram_range=(1, 2),
        )
        self.skus: List[str] = []
        self.documents: List[str] = []
        self.matrix = None

    def rebuild(self, documents: Dict[str, str]) -> int:
        """
        Rebuild TF-IDF matrix from documents.
        """
        logger.info("Rebuilding search index...")

        self.skus = list(documents.keys())
        self.documents = list(documents.values())

        if not self.documents:
            self.matrix = None
            logger.warning("No documents found for indexing")
            return 0

        self.matrix = self.vectorizer.fit_transform(self.documents)

        logger.info(f"Indexed {len(self.documents)} documents")
        return len(self.documents)

    def search(self, query: str, limit: int = 50):
        """
        Search using cosine similarity.
        """
        if self.matrix is None:
            logger.warning("Search requested but index is empty")
            return []

        query = (query or "").strip()

        if not query:
            return []

        query_vector = self.vectorizer.transform([query])
        scores = cosine_similarity(query_vector, self.matrix).flatten()

        idx = np.argsort(scores)[::-1][:limit]

        results = [
            {
                "product_sku": self.skus[i],
                "similarity": float(scores[i]),
            }
            for i in idx
            if scores[i] > 0
        ]

        return results


# global singleton index (shared across requests)
search_index = SearchIndex()