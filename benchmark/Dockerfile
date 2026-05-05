FROM python:3.11-slim

WORKDIR /app

COPY benchmark/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY benchmark/benchmark.py .

CMD ["uvicorn", "benchmark:app", "--host", "0.0.0.0", "--port", "8000"]