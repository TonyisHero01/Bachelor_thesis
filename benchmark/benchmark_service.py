import csv
import io
import statistics

from config import settings
from http_client import request_json


SEARCH_METHODS = {
    "tfidf": {
        "method": "POST",
        "url": lambda query: f"{settings.search_url}/search",
        "json": lambda query: {"query": query, "limit": 10},
        "params": lambda query: None,
    },
    "semantic_vector": {
        "method": "POST",
        "url": lambda query: f"{settings.search_url}/semantic/search",
        "json": lambda query: {"query": query, "limit": 10},
        "params": lambda query: None,
    },
    "sql_like": {
        "method": "GET",
        "url": lambda query: f"{settings.bms_url}/search-like",
        "json": lambda query: None,
        "params": lambda query: {"q": query},
    },
}


def call_method(method_name: str, query: str):
    method_config = SEARCH_METHODS[method_name]

    status, data, elapsed = request_json(
        method_config["method"],
        method_config["url"](query),
        json=method_config["json"](query),
        params=method_config["params"](query),
    )

    return status, data, elapsed


def count_results(data, status: int):
    if status != 200 or not isinstance(data, dict):
        return 0

    results = data.get("results", [])

    if not isinstance(results, list):
        return 0

    return len(results)


def run_benchmark():
    rows = []

    first_query = settings.queries[0]

    for method_name in ["tfidf", "semantic_vector"]:
        status, data, elapsed = call_method(method_name, first_query)

        rows.append({
            "type": f"{method_name}_cold",
            "query": first_query,
            "response_time_ms": elapsed,
            "result_count": count_results(data, status),
            "status": status,
        })

    for query in settings.queries:
        for method_name in ["tfidf", "semantic_vector", "sql_like"]:
            times = []
            result_count = 0
            final_status = 200

            for _ in range(settings.benchmark_repeat_count):
                status, data, elapsed = call_method(method_name, query)

                times.append(elapsed)
                final_status = status
                result_count = count_results(data, status)

            rows.append({
                "type": method_name,
                "query": query,
                "response_time_ms": statistics.mean(times) if times else 0,
                "result_count": result_count,
                "status": final_status,
            })

    return rows


def rows_to_csv(rows):
    output = io.StringIO()

    writer = csv.DictWriter(
        output,
        fieldnames=[
            "type",
            "query",
            "response_time_ms",
            "result_count",
            "status",
        ],
    )

    writer.writeheader()
    writer.writerows(rows)

    return output.getvalue()