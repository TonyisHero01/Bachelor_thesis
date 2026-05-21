import math
import statistics
from datetime import datetime

import pandas as pd
import psycopg2

from config import settings
from http_client import request_json


RELEVANT_LABELS = {"E", "S"}
LABEL_GAIN = {
    "E": 3,
    "S": 2,
    "C": 1,
    "I": 0,
}


def get_db_connection():
    return psycopg2.connect(settings.database_url)


def fetch_imported_skus():
    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute("SELECT sku FROM product")

    rows = cur.fetchall()

    cur.close()
    conn.close()

    return {str(row[0]) for row in rows if row[0]}


def load_esci_ground_truth(limit: int = 100):
    imported_skus = fetch_imported_skus()

    df = pd.read_parquet(
        settings.esci_examples_path,
        columns=[
            "query",
            "product_id",
            "product_locale",
            "esci_label",
            "split",
        ],
    )

    df = df[
        (df["product_locale"] == "us")
        & (df["split"] == "test")
        & (df["product_id"].isin(imported_skus))
    ]

    ground_truth = {}

    for _, row in df.iterrows():
        query = str(row["query"]).strip()
        product_id = str(row["product_id"]).strip()
        label = str(row["esci_label"]).strip()

        if not query or not product_id:
            continue

        ground_truth.setdefault(query, {})
        ground_truth[query][product_id] = label

    filtered = {}

    for query, labels in ground_truth.items():
        has_relevant = any(
            label in RELEVANT_LABELS
            for label in labels.values()
        )

        if has_relevant:
            filtered[query] = labels

        if len(filtered) >= limit:
            break

    return filtered


def fetch_products_by_skus(skus: list[str]):
    if not skus:
        return {}

    conn = get_db_connection()
    cur = conn.cursor()

    cur.execute(
        """
        SELECT
            p.sku,
            p.name,
            p.description,
            c.name AS category,
            p.material,
            pc.name AS color,
            s.name AS size
        FROM product p
        LEFT JOIN category c ON p.category_id = c.id
        LEFT JOIN productcolor pc ON p.color_id = pc.id
        LEFT JOIN size s ON p.size_id = s.id
        WHERE p.sku = ANY(%s)
        """,
        (skus,),
    )

    rows = cur.fetchall()

    cur.close()
    conn.close()

    products = {}

    for row in rows:
        products[str(row[0])] = {
            "sku": str(row[0] or ""),
            "name": str(row[1] or ""),
            "description": str(row[2] or ""),
            "category": str(row[3] or ""),
            "material": str(row[4] or ""),
            "color": str(row[5] or ""),
            "size": str(row[6] or ""),
        }

    return products


def extract_sku(item: dict, method: str):
    if method in ["tfidf", "elasticsearch_bm25"]:
        return str(item.get("product_sku", "")).strip()

    return str(item.get("sku", "")).strip()


def call_search_method(method: str, query: str, limit: int):
    endpoint = {
        "tfidf": "/search",
        "semantic_vector": "/semantic/search",
        "elasticsearch_bm25": "/elastic/search",
    }[method]

    status, data, elapsed_ms = request_json(
        "POST",
        f"{settings.search_url}{endpoint}",
        json={
            "query": query,
            "limit": limit,
        },
    )

    raw_results = data.get("results", []) if status == 200 else []

    skus = []

    for item in raw_results:
        if not isinstance(item, dict):
            continue

        sku = extract_sku(item, method)

        if sku:
            skus.append(sku)

    products_by_sku = fetch_products_by_skus(skus)

    results = []

    for sku in skus:
        product = products_by_sku.get(sku)

        if product:
            results.append(product)

    return status, results, elapsed_ms


def precision_at_k(results, labels):
    if not results:
        return 0.0

    relevant_count = 0

    for result in results:
        label = labels.get(result["sku"], "I")

        if label in RELEVANT_LABELS:
            relevant_count += 1

    return relevant_count / len(results)


def recall_at_k(results, labels):
    relevant_total = sum(
        1
        for label in labels.values()
        if label in RELEVANT_LABELS
    )

    if relevant_total == 0:
        return 0.0

    found = 0

    for result in results:
        label = labels.get(result["sku"], "I")

        if label in RELEVANT_LABELS:
            found += 1

    return found / relevant_total


def dcg_at_k(results, labels):
    score = 0.0

    for index, result in enumerate(results, start=1):
        label = labels.get(result["sku"], "I")
        gain = LABEL_GAIN.get(label, 0)

        score += gain / math.log2(index + 1)

    return score


def ndcg_at_k(results, labels, limit):
    ideal_gains = sorted(
        [
            LABEL_GAIN.get(label, 0)
            for label in labels.values()
        ],
        reverse=True,
    )[:limit]

    ideal_dcg = 0.0

    for index, gain in enumerate(ideal_gains, start=1):
        ideal_dcg += gain / math.log2(index + 1)

    if ideal_dcg == 0:
        return 0.0

    return dcg_at_k(results, labels) / ideal_dcg


def mrr(results, labels):
    for index, result in enumerate(results, start=1):
        label = labels.get(result["sku"], "I")

        if label in RELEVANT_LABELS:
            return 1 / index

    return 0.0


def build_result_preview(results, labels, limit=5):
    preview = []

    for index, result in enumerate(results[:limit], start=1):
        sku = result["sku"]

        preview.append({
            "rank": index,
            "sku": sku,
            "name": result.get("name", ""),
            "category": result.get("category", ""),
            "label": labels.get(sku, "not_labeled"),
        })

    return preview


def evaluate_search(limit: int = 10):
    methods = [
        "tfidf",
        "semantic_vector",
        "elasticsearch_bm25",
    ]

    ground_truth = load_esci_ground_truth(
        limit=settings.esci_query_limit
    )

    rows = []

    for query, labels in ground_truth.items():
        for method in methods:
            status, results, elapsed_ms = call_search_method(
                method=method,
                query=query,
                limit=limit,
            )

            precision = precision_at_k(results, labels)
            recall = recall_at_k(results, labels)
            ndcg = ndcg_at_k(results, labels, limit)
            reciprocal_rank = mrr(results, labels)

            rows.append({
                "method": method,
                "query": query,
                "status": status,
                "response_time_ms": elapsed_ms,
                "result_count": len(results),
                "has_results": len(results) > 0,
                "precision_at_k": precision,
                "recall_at_k": recall,
                "ndcg_at_k": ndcg,
                "mrr": reciprocal_rank,
                "top_results": build_result_preview(results, labels),
            })

    summary = {}

    for method in methods:
        method_rows = [
            row for row in rows
            if row["method"] == method and row["status"] == 200
        ]

        with_results = [
            row for row in method_rows
            if row["has_results"]
        ]

        summary[method] = {
            "queries_evaluated": len(method_rows),
            "queries_with_results": len(with_results),
            "result_hit_rate": (
                len(with_results) / len(method_rows)
                if method_rows else 0
            ),
            "avg_response_time_ms": (
                statistics.mean(row["response_time_ms"] for row in method_rows)
                if method_rows else 0
            ),
            "avg_precision_at_k": (
                statistics.mean(row["precision_at_k"] for row in method_rows)
                if method_rows else 0
            ),
            "avg_recall_at_k": (
                statistics.mean(row["recall_at_k"] for row in method_rows)
                if method_rows else 0
            ),
            "avg_ndcg_at_k": (
                statistics.mean(row["ndcg_at_k"] for row in method_rows)
                if method_rows else 0
            ),
            "avg_mrr": (
                statistics.mean(row["mrr"] for row in method_rows)
                if method_rows else 0
            ),
        }

    return {
        "methods_compared": methods,
        "limit": limit,
        "query_count": len(ground_truth),
        "summary": summary,
        "details": rows,
    }


def run_evaluation():
    return {
        "generated_at": datetime.utcnow().isoformat() + "Z",
        "search_evaluation": evaluate_search(),
    }