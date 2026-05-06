import time
import requests


def request_json(method: str, url: str, **kwargs):
    start = time.perf_counter()

    headers = kwargs.pop("headers", {})
    headers["X-BENCHMARK"] = "1"

    try:
        if method == "POST":
            response = requests.post(
                url,
                timeout=10,
                headers=headers,
                **kwargs,
            )
        else:
            response = requests.get(
                url,
                timeout=10,
                headers=headers,
                **kwargs,
            )

        elapsed_ms = (time.perf_counter() - start) * 1000

        try:
            data = response.json()
        except Exception:
            data = {"error": response.text[:500]}

        return response.status_code, data, elapsed_ms

    except Exception as exc:
        elapsed_ms = (time.perf_counter() - start) * 1000
        return 0, {"error": str(exc)}, elapsed_ms