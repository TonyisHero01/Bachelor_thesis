FROM python:3.11-slim

WORKDIR /app

COPY search_service/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY search_service/ .

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000"]