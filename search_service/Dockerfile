FROM python:3.11-slim

WORKDIR /app

ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends gcc libpq-dev; \
    pip install --no-cache-dir --upgrade pip; \
    rm -rf /var/lib/apt/lists/*

COPY requirements.txt /app/requirements.txt

RUN set -eux; \
    pip install --no-cache-dir -r /app/requirements.txt

COPY . /app/

EXPOSE 8000

CMD ["uvicorn", "main:app", "--host=0.0.0.0", "--port=8000"]