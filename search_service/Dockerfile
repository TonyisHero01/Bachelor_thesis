FROM python:3.11-slim

WORKDIR /app

ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1

RUN set -eux; \
    pip install --no-cache-dir --upgrade pip

COPY search_service/api/requirements.txt /app/requirements.txt

RUN set -eux; \
    pip install --no-cache-dir -r /app/requirements.txt

COPY search_service/api/ /app/

EXPOSE 8000

CMD ["uvicorn", "main:app", "--host=0.0.0.0", "--port=8000"]