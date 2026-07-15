import csv
import io
import math
import statistics

from config import settings
from http_client import request_json


SEARCH_METHODS = {
    "lexical": {
        "method": "POST",
        "url": lambda query: f"{settings.search_url}/lexical/search",
        "json": lambda query: {"query": query, "limit": 10},
        "params": lambda query: None,
    },
    "semantic_vector": {
        "method": "POST",
        "url": lambda query: f"{settings.search_url}/semantic/search",
        "json": lambda query: {"query": query, "limit": 10},
        "params": lambda query: None,
    },
    "elasticsearch_bm25": {
        "method": "POST",
        "url": lambda query: f"{settings.search_url}/elastic/search",
        "json": lambda query: {"query": query, "limit": 10},
        "params": lambda query: None,
    },
}

def percentile(values: list[float], quantile: float):
    if not values:
        return 0.0

    ordered = sorted(values)

    if len(ordered) == 1:
        return ordered[0]

    position = (len(ordered) - 1) * quantile
    lower_index = math.floor(position)
    upper_index = math.ceil(position)

    if lower_index == upper_index:
        return ordered[lower_index]

    fraction = position - lower_index

    return ordered[lower_index] + (
        ordered[upper_index]
        - ordered[lower_index]
    ) * fraction

def call_method_with_retry(method_name: str, query: str, max_attempts: int = 3):
    last_status = 0
    last_data = {}
    last_elapsed = 0.0

    for attempt in range(max_attempts):
        status, data, elapsed = call_method(method_name, query)

        last_status = status
        last_data = data
        last_elapsed = elapsed

        if status == 200:
            return status, data, elapsed

    return last_status, last_data, last_elapsed

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

    methods = [
        "lexical",
        "semantic_vector",
        "elasticsearch_bm25",
    ]

    first_query = settings.queries[0]

    for method_name in methods:
        status, data, elapsed = call_method(
            method_name,
            first_query,
        )

        successful = status == 200

        rows.append({
            "type": f"{method_name}_cold",
            "query": first_query,
            "response_time_ms": elapsed,
            "median_response_time_ms": elapsed,
            "p95_response_time_ms": elapsed,
            "result_count": count_results(
                data,
                status,
            ),
            "status": status,
            "successful_runs": 1 if successful else 0,
            "failed_runs": 0 if successful else 1,
            "request_success_rate": (
                1.0 if successful else 0.0
            ),
        })

    for query in settings.queries:
        for method_name in methods:
            successful_times = []
            successful_result_counts = []
            statuses = []

            for _ in range(
                settings.benchmark_repeat_count
            ):
                status, data, elapsed = call_method(
                    method_name,
                    query,
                )

                statuses.append(status)

                if status == 200:
                    successful_times.append(elapsed)
                    successful_result_counts.append(
                        count_results(data, status)
                    )

            successful_runs = len(
                successful_times
            )

            failed_runs = (
                settings.benchmark_repeat_count
                - successful_runs
            )

            rows.append({
                "type": method_name,
                "query": query,

                "response_time_ms": (
                    statistics.mean(
                        successful_times
                    )
                    if successful_times
                    else 0.0
                ),

                "median_response_time_ms": (
                    statistics.median(
                        successful_times
                    )
                    if successful_times
                    else 0.0
                ),

                "p95_response_time_ms": percentile(
                    successful_times,
                    0.95,
                ),

                "result_count": (
                    successful_result_counts[-1]
                    if successful_result_counts
                    else 0
                ),

                "status": (
                    200
                    if failed_runs == 0
                    else (
                        statuses[-1]
                        if statuses
                        else 0
                    )
                ),

                "successful_runs": successful_runs,
                "failed_runs": failed_runs,

                "request_success_rate": (
                    successful_runs
                    / settings.benchmark_repeat_count
                ),
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
            "median_response_time_ms",
            "p95_response_time_ms",
            "result_count",
            "status",
            "successful_runs",
            "failed_runs",
            "request_success_rate",
        ],
    )

    writer.writeheader()
    writer.writerows(rows)

    return output.getvalue()