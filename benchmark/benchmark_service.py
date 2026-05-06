import csv
import io
import statistics

from config import settings
from http_client import request_json


def run_benchmark():
    rows = []

    status, data, elapsed = request_json(
        "POST",
        f"{settings.search_url}/search",
        json={
            "query": settings.queries[0],
            "limit": 10,
        },
    )

    rows.append({
        "type": "vector_cold",
        "query": settings.queries[0],
        "response_time_ms": elapsed,
        "result_count": len(data.get("results", [])) if status == 200 else 0,
        "status": status,
    })

    for query in settings.queries:
        vector_times = []
        vector_count = 0
        vector_status = 200

        for _ in range(settings.benchmark_repeat_count):
            status, data, elapsed = request_json(
                "POST",
                f"{settings.search_url}/search",
                json={
                    "query": query,
                    "limit": 10,
                },
            )

            vector_times.append(elapsed)
            vector_status = status
            vector_count = len(data.get("results", [])) if status == 200 else 0

        rows.append({
            "type": "vector",
            "query": query,
            "response_time_ms": statistics.mean(vector_times),
            "result_count": vector_count,
            "status": vector_status,
        })

        sql_times = []
        sql_count = 0
        sql_status = 200

        for _ in range(settings.benchmark_repeat_count):
            status, data, elapsed = request_json(
                "GET",
                f"{settings.bms_url}/search-like",
                params={"q": query},
            )

            sql_times.append(elapsed)
            sql_status = status
            sql_count = len(data.get("results", [])) if status == 200 else 0

        rows.append({
            "type": "sql_like",
            "query": query,
            "response_time_ms": statistics.mean(sql_times),
            "result_count": sql_count,
            "status": sql_status,
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