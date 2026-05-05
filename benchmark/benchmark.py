import os
import time
import requests
import csv

SEARCH_URL = os.getenv("SEARCH_URL", "http://search-service:8000")

QUERIES = [
    "alpha",
    "smart",
    "pro product",
    "omega",
    "basic item"
]

RESULTS = []

FRONTWEB_URL = os.getenv("BMS_URL")

def run_test():
    print("Running benchmark...")

    # cold start (vector)
    start = time.perf_counter()
    r = requests.post(f"{SEARCH_URL}/search", json={"query": QUERIES[0], "limit": 10})
    cold_time = (time.perf_counter() - start) * 1000

    RESULTS.append(["vector_cold", QUERIES[0], cold_time, len(r.json()["results"])])

    # warm tests
    for q in QUERIES:
        # ---- TF-IDF ----
        times_vec = []
        for _ in range(5):
            start = time.perf_counter()
            r = requests.post(f"{SEARCH_URL}/search", json={"query": q, "limit": 10})
            times_vec.append((time.perf_counter() - start) * 1000)

        avg_vec = sum(times_vec) / len(times_vec)

        RESULTS.append(["vector", q, avg_vec, len(r.json()["results"])])

        # ---- SQL LIKE ----
        times_sql = []
        for _ in range(5):
            start = time.perf_counter()
            r = requests.get(f"{FRONTWEB_URL}/search-like", params={"q": q})
            times_sql.append((time.perf_counter() - start) * 1000)

        avg_sql = sum(times_sql) / len(times_sql)

        RESULTS.append(["sql_like", q, avg_sql, len(r.json()["results"])])

def save_csv():
    with open("benchmark_results.csv", "w", newline="") as f:
        writer = csv.writer(f)
        writer.writerow(["type", "query", "response_time_ms", "result_count"])
        writer.writerows(RESULTS)

    print("Saved to benchmark_results.csv")

if __name__ == "__main__":
    run_test()
    save_csv()