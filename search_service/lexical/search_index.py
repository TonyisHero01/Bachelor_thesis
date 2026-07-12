import ast
import json
import logging
import pickle
from typing import Any, Dict, List

import numpy as np
from scipy.sparse import vstack
from sklearn.feature_extraction.text import HashingVectorizer
from sklearn.metrics.pairwise import cosine_similarity


logger = logging.getLogger(__name__)


DEFAULT_VECTORIZER_SETTINGS = {
    "token_pattern": r"\b\w+\b",
    "lowercase": True,
    "ngram_range": (1, 2),
    "n_features": 2**18,
    "alternate_sign": False,
    "normalization": "l2",
}


def normalize_pickle_blob(value):
    if value is None:
        return None

    if isinstance(value, bytes):
        return value

    if isinstance(value, bytearray):
        return bytes(value)

    if isinstance(value, memoryview):
        return value.tobytes()

    if isinstance(value, str):
        text = value.strip()

        if text.startswith("\\x"):
            return bytes.fromhex(text[2:])

        if text.startswith("b'") or text.startswith('b"'):
            return ast.literal_eval(text)

        return text.encode("latin1")

    raise TypeError(
        f"Unsupported vector blob type: {type(value)}"
    )


def parse_boolean(
    value: Any,
    default: bool,
) -> bool:
    if isinstance(value, bool):
        return value

    if isinstance(value, int):
        return value != 0

    if isinstance(value, str):
        normalized = value.strip().lower()

        if normalized in {"true", "1", "yes", "on"}:
            return True

        if normalized in {"false", "0", "no", "off"}:
            return False

    return default


def parse_positive_int(
    value: Any,
    default: int,
) -> int:
    try:
        result = int(value)
    except (TypeError, ValueError):
        return default

    return result if result > 0 else default


def parse_non_negative_float(
    value: Any,
    default: float,
) -> float:
    try:
        result = float(value)
    except (TypeError, ValueError):
        return default

    if not np.isfinite(result) or result < 0:
        return default

    return result


class SearchIndex:
    def __init__(self):
        self.skus: List[str] = []
        self.documents: Dict[str, str] = {}
        self.matrix = None
        self.metadata: Dict[str, dict] = {}
        self.config: dict = {}
        self.document_tokens: Dict[str, set[str]] = {}

        self.vectorizer = None
        self.vectorizer_signature = None
        self.vectorizer_n_features = 2**18

        self.configure_vectorizer({})

    def get_algorithm_settings(self) -> dict:
        value = self.config.get(
            "algorithm_settings",
            {},
        )

        if isinstance(value, dict):
            return value

        if isinstance(value, str):
            try:
                decoded = json.loads(value)

                if isinstance(decoded, dict):
                    return decoded

            except json.JSONDecodeError:
                logger.warning(
                    "[CONFIG][LEXICAL] invalid "
                    "algorithm_settings JSON"
                )

        return {}

    def get_algorithm_settings_section(
        self,
        section: str,
    ) -> dict:
        value = self.get_algorithm_settings().get(
            section,
            {},
        )

        return value if isinstance(value, dict) else {}

    def configure_vectorizer(
        self,
        config: dict | None,
    ) -> None:
        self.config = config or {}

        vectorizer_settings = (
            self.get_algorithm_settings_section(
                "vectorizer"
            )
        )

        token_pattern = str(
            vectorizer_settings.get(
                "token_pattern",
                DEFAULT_VECTORIZER_SETTINGS[
                    "token_pattern"
                ],
            )
        ).strip()

        if not token_pattern:
            token_pattern = DEFAULT_VECTORIZER_SETTINGS[
                "token_pattern"
            ]

        lowercase = parse_boolean(
            vectorizer_settings.get("lowercase"),
            DEFAULT_VECTORIZER_SETTINGS["lowercase"],
        )

        n_features = parse_positive_int(
            vectorizer_settings.get("n_features"),
            DEFAULT_VECTORIZER_SETTINGS["n_features"],
        )

        alternate_sign = parse_boolean(
            vectorizer_settings.get(
                "alternate_sign"
            ),
            DEFAULT_VECTORIZER_SETTINGS[
                "alternate_sign"
            ],
        )

        normalization = vectorizer_settings.get(
            "normalization",
            DEFAULT_VECTORIZER_SETTINGS[
                "normalization"
            ],
        )

        if normalization not in {"l1", "l2", None}:
            normalization = DEFAULT_VECTORIZER_SETTINGS[
                "normalization"
            ]

        ngram_range_value = vectorizer_settings.get(
            "ngram_range",
            DEFAULT_VECTORIZER_SETTINGS[
                "ngram_range"
            ],
        )

        ngram_range = self.normalize_ngram_range(
            ngram_range_value
        )

        signature = (
            token_pattern,
            lowercase,
            ngram_range,
            n_features,
            alternate_sign,
            normalization,
        )

        if (
            self.vectorizer is not None
            and signature == self.vectorizer_signature
        ):
            return

        self.vectorizer = HashingVectorizer(
            token_pattern=token_pattern,
            lowercase=lowercase,
            ngram_range=ngram_range,
            n_features=n_features,
            alternate_sign=alternate_sign,
            norm=normalization,
        )

        self.vectorizer_signature = signature
        self.vectorizer_n_features = n_features

        logger.info(
            "[CONFIG][LEXICAL] vectorizer configured "
            "token_pattern=%r lowercase=%s "
            "ngram_range=%s n_features=%s "
            "alternate_sign=%s normalization=%s",
            token_pattern,
            lowercase,
            ngram_range,
            n_features,
            alternate_sign,
            normalization,
        )

    @staticmethod
    def normalize_ngram_range(
        value: Any,
    ) -> tuple[int, int]:
        default_value = DEFAULT_VECTORIZER_SETTINGS[
            "ngram_range"
        ]

        if not isinstance(value, (list, tuple)):
            return default_value

        if len(value) != 2:
            return default_value

        try:
            minimum = int(value[0])
            maximum = int(value[1])
        except (TypeError, ValueError):
            return default_value

        if minimum < 1 or maximum < minimum:
            return default_value

        return minimum, maximum

    def clear_index(self) -> None:
        self.skus = []
        self.documents = {}
        self.matrix = None
        self.metadata = {}
        self.document_tokens = {}

    def vectorize_document(
        self,
        document: str,
    ):
        return self.vectorizer.transform(
            [document]
        )

    def rebuild(
        self,
        documents: Dict[str, str],
        metadata: Dict[str, dict] | None = None,
        config: dict | None = None,
    ) -> int:
        logger.info(
            "[REINDEX][FULL] rebuilding "
            "in-memory search index"
        )

        self.configure_vectorizer(config)

        self.skus = list(documents.keys())
        self.documents = documents.copy()

        self.document_tokens = {
            sku: self.tokenize(document)
            for sku, document in self.documents.items()
        }

        self.metadata = metadata or {}

        if not self.documents:
            self.matrix = None

            logger.warning(
                "[REINDEX][FULL] no documents found"
            )

            return 0

        self.matrix = self.vectorizer.transform(
            list(self.documents.values())
        )

        logger.info(
            "[REINDEX][FULL] indexed %d documents",
            len(self.documents),
        )

        return len(self.documents)

    def rebuild_from_saved_vectors(
        self,
        rows: list[dict],
        metadata: Dict[str, dict] | None = None,
        config: dict | None = None,
    ) -> int:
        self.configure_vectorizer(config)

        self.skus = []
        self.documents = {}
        self.document_tokens = {}

        vectors = []

        for row in rows:
            sku = str(
                row.get("sku") or ""
            ).strip()

            document = str(
                row.get("document") or ""
            )

            vector_blob = row.get("vector")

            if not sku or vector_blob is None:
                continue

            try:
                normalized_blob = normalize_pickle_blob(
                    vector_blob
                )

                vector = pickle.loads(
                    normalized_blob
                )

            except Exception:
                logger.exception(
                    "[INDEX] failed to load saved "
                    "vector sku=%s",
                    sku,
                )

                continue

            self.skus.append(sku)
            self.documents[sku] = document

            self.document_tokens[sku] = (
                self.tokenize(document)
            )

            vectors.append(vector)

        self.metadata = metadata or {}

        if not vectors:
            self.matrix = None

            logger.warning(
                "[INDEX] no saved vectors found"
            )

            return 0

        loaded_matrix = vstack(vectors)

        if (
            loaded_matrix.shape[1]
            != self.vectorizer_n_features
        ):
            logger.warning(
                "[INDEX] saved vector dimension mismatch "
                "saved=%s configured=%s; full reindex required",
                loaded_matrix.shape[1],
                self.vectorizer_n_features,
            )

            self.clear_index()

            return 0

        self.matrix = loaded_matrix

        logger.info(
            "[INDEX] loaded %d saved vectors",
            len(vectors),
        )

        return len(vectors)

    def tokenize(
        self,
        text: str,
    ) -> set[str]:
        return {
            token.strip().lower()
            for token in str(text or "").split()
            if token.strip()
        }

    def update_one(
        self,
        sku: str,
        document: str,
        vector,
        metadata: dict | None = None,
    ) -> None:
        sku = str(sku or "").strip()

        if not sku:
            return

        if sku in self.skus:
            index = self.skus.index(sku)

            self.documents[sku] = document
            self.document_tokens[sku] = (
                self.tokenize(document)
            )

            if self.matrix is not None:
                rows = [
                    self.matrix[i]
                    for i in range(
                        self.matrix.shape[0]
                    )
                ]

                rows[index] = vector
                self.matrix = vstack(rows)

            logger.info(
                "[REINDEX][PARTIAL] updated vector "
                "in memory for sku=%s",
                sku,
            )

        else:
            self.skus.append(sku)
            self.documents[sku] = document

            self.document_tokens[sku] = (
                self.tokenize(document)
            )

            if self.matrix is None:
                self.matrix = vector
            else:
                self.matrix = vstack(
                    [
                        self.matrix,
                        vector,
                    ]
                )

            logger.info(
                "[REINDEX][PARTIAL] added vector "
                "in memory for sku=%s",
                sku,
            )

        if metadata is not None:
            self.metadata[sku] = metadata

    def remove_one(
        self,
        sku: str,
    ) -> None:
        sku = str(sku or "").strip()

        if sku not in self.skus:
            logger.info(
                "[REINDEX][PARTIAL] sku=%s "
                "not found in memory index",
                sku,
            )

            return

        index = self.skus.index(sku)

        self.skus.pop(index)
        self.documents.pop(sku, None)
        self.metadata.pop(sku, None)
        self.document_tokens.pop(sku, None)

        if self.matrix is None:
            return

        rows = [
            self.matrix[i]
            for i in range(
                self.matrix.shape[0]
            )
            if i != index
        ]

        self.matrix = (
            vstack(rows)
            if rows
            else None
        )

        logger.info(
            "[REINDEX][PARTIAL] removed vector "
            "from memory for sku=%s",
            sku,
        )

    def search(
        self,
        query: str,
        limit: int = 50,
    ):
        if self.matrix is None:
            logger.warning(
                "Search requested but index is empty"
            )

            return []

        query = str(query or "").strip()

        if not query:
            return []

        limit = max(1, int(limit))

        query_vector = self.vectorizer.transform(
            [query]
        )

        query_terms = self.tokenize(query)

        candidate_filter = (
            self.get_algorithm_settings_section(
                "candidate_filter"
            )
        )

        minimum_query_token_matches = (
            parse_positive_int(
                candidate_filter.get(
                    "minimum_query_token_matches"
                ),
                1,
            )
        )

        fallback_to_all_products = parse_boolean(
            candidate_filter.get(
                "fallback_to_all_products"
            ),
            True,
        )

        candidate_indices = []

        for index, sku in enumerate(self.skus):
            document_tokens = (
                self.document_tokens.get(
                    sku,
                    set(),
                )
            )

            matching_count = len(
                query_terms.intersection(
                    document_tokens
                )
            )

            if (
                matching_count
                >= minimum_query_token_matches
            ):
                candidate_indices.append(index)

        if not candidate_indices:
            if not fallback_to_all_products:
                return []

            candidate_indices = list(
                range(len(self.skus))
            )

        candidate_matrix = self.matrix[
            candidate_indices
        ]

        scores = cosine_similarity(
            query_vector,
            candidate_matrix,
        ).flatten()

        positive_indices = np.where(
            scores > 0
        )[0]

        if len(positive_indices) == 0:
            return []

        top_k = min(
            limit,
            len(positive_indices),
        )

        if len(positive_indices) > top_k:
            top_indices = positive_indices[
                np.argpartition(
                    scores[positive_indices],
                    -top_k,
                )[-top_k:]
            ]

            top_indices = top_indices[
                np.argsort(
                    scores[top_indices]
                )[::-1]
            ]

        else:
            top_indices = positive_indices[
                np.argsort(
                    scores[positive_indices]
                )[::-1]
            ]

        results = []

        for local_index in top_indices:
            original_index = candidate_indices[
                local_index
            ]

            sku = self.skus[original_index]

            results.append(
                {
                    "product_sku": sku,
                    "sku": sku,
                    "similarity": float(
                        scores[local_index]
                    ),
                }
            )

        return results

    def recommend_by_sku(
        self,
        sku: str,
        limit: int = 10,
    ):
        if not parse_boolean(
            self.config.get(
                "recommendation_enabled"
            ),
            True,
        ):
            return []

        if self.matrix is None:
            logger.warning(
                "Recommendation requested "
                "but index is empty"
            )

            return []

        sku = str(sku or "").strip()

        if not sku or sku not in self.skus:
            return []

        limit = max(1, int(limit))

        product_index = self.skus.index(sku)
        base_sku = self.skus[product_index]

        base_metadata = self.metadata.get(
            base_sku,
            {},
        )

        product_vector = self.matrix[
            product_index
        ]

        base_scores = cosine_similarity(
            product_vector,
            self.matrix,
        ).flatten()

        adjusted_scores = base_scores.copy()

        same_category_bonus = (
            parse_non_negative_float(
                self.config.get(
                    "same_category_recommendation_weight"
                ),
                0.35,
            )
        )

        # 当前数据库没有
        # same_material_recommendation_weight，
        # 因此回退使用 same_material_bonus。
        same_material_bonus = (
            parse_non_negative_float(
                self.config.get(
                    "same_material_recommendation_weight",
                    self.config.get(
                        "same_material_bonus",
                        0.15,
                    ),
                ),
                0.15,
            )
        )

        same_color_bonus = (
            parse_non_negative_float(
                self.config.get(
                    "same_color_recommendation_weight"
                ),
                0.10,
            )
        )

        same_size_bonus = (
            parse_non_negative_float(
                self.config.get(
                    "same_size_recommendation_weight"
                ),
                0.10,
            )
        )

        max_per_category = parse_positive_int(
            self.config.get(
                "max_recommendation_per_category"
            ),
            4,
        )

        diversity_penalty = (
            parse_non_negative_float(
                self.config.get(
                    "recommendation_diversity_penalty"
                ),
                0.10,
            )
        )

        logger.info(
            "[RECOMMEND] method=lexical "
            "weights category=%s material=%s "
            "color=%s size=%s "
            "max_per_category=%s diversity=%s",
            same_category_bonus,
            same_material_bonus,
            same_color_bonus,
            same_size_bonus,
            max_per_category,
            diversity_penalty,
        )

        for index, candidate_sku in enumerate(
            self.skus
        ):
            if candidate_sku == base_sku:
                adjusted_scores[index] = -1

                continue

            candidate_metadata = self.metadata.get(
                candidate_sku,
                {},
            )

            if (
                same_category_bonus > 0
                and base_metadata.get("category")
                and base_metadata.get("category")
                == candidate_metadata.get("category")
            ):
                adjusted_scores[index] += (
                    same_category_bonus
                )

            if (
                same_material_bonus > 0
                and base_metadata.get("material")
                and base_metadata.get("material")
                == candidate_metadata.get("material")
            ):
                adjusted_scores[index] += (
                    same_material_bonus
                )

            if (
                same_color_bonus > 0
                and base_metadata.get("color")
                and base_metadata.get("color")
                == candidate_metadata.get("color")
            ):
                adjusted_scores[index] += (
                    same_color_bonus
                )

            if (
                same_size_bonus > 0
                and base_metadata.get("size")
                and base_metadata.get("size")
                == candidate_metadata.get("size")
            ):
                adjusted_scores[index] += (
                    same_size_bonus
                )

        sorted_indices = np.argsort(
            adjusted_scores
        )[::-1]

        results = []
        category_count: dict[str, int] = {}

        for index in sorted_indices:
            score = float(
                adjusted_scores[index]
            )

            if score <= 0:
                continue

            candidate_sku = self.skus[index]

            candidate_metadata = self.metadata.get(
                candidate_sku,
                {},
            )

            category = (
                candidate_metadata.get("category")
                or "unknown"
            )

            current_category_count = (
                category_count.get(
                    category,
                    0,
                )
            )

            if (
                current_category_count
                >= max_per_category
            ):
                overflow = (
                    current_category_count
                    - max_per_category
                    + 1
                )

                score -= (
                    diversity_penalty
                    * overflow
                )

            if score <= 0:
                continue

            if (
                current_category_count
                >= max_per_category * 2
            ):
                continue

            results.append(
                {
                    "product_sku": candidate_sku,
                    "sku": candidate_sku,
                    "similarity": score,
                    "category": category,
                }
            )

            category_count[category] = (
                current_category_count + 1
            )

            if len(results) >= limit:
                break

        return results


search_index = SearchIndex()