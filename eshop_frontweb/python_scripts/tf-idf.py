import psycopg2
from psycopg2.extras import DictCursor
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
import numpy as np
from dotenv import load_dotenv
import os
import sys
import json
import re

dotenv_path = '../.env'
load_dotenv()

database_url = os.getenv("DATABASE_URL")
if not database_url:
    raise ValueError("DATABASE_URL is null, please check .env file")

db_info = database_url.split("//")[1].split("@")
username, password = db_info[0].split(":")
host, rest = db_info[1].split(":")
port, dbname = rest.split("/")

connection = psycopg2.connect(
    host=host,
    user=username,
    password=password,
    database=dbname,
    port=port,
    cursor_factory=DictCursor
)

def custom_preprocessor(text):
    text = text.lower()
    text = re.sub(r'\d+', '', text)
    text = re.sub(r'[^\w\s]', '', text)
    return text.strip()

def fetch_product_vectors(cursor):
    cursor.execute("SELECT sku, document, vector FROM Product_Document_Vector")
    result = cursor.fetchall()

    product_skus = []
    documents = []
    vectors = []

    for row in result:
        product_skus.append(row['sku'])
        documents.append(row['document'])
        vector = np.array([float(x) for x in row['vector'].split(',')])
        vectors.append(vector)

    return product_skus, documents, np.array(vectors)

def search_products(query, product_skus, documents, vectors):
    vectorizer = TfidfVectorizer(
        preprocessor=custom_preprocessor,
        token_pattern=r'\b\w+\b',
        lowercase=True,
        ngram_range=(1, 2)
    )

    all_docs = documents + [query]
    tfidf_matrix = vectorizer.fit_transform(all_docs)

    doc_vectors = tfidf_matrix[:-1].toarray()
    query_vector = tfidf_matrix[-1].toarray()

    similarities = cosine_similarity(query_vector, doc_vectors).flatten()
    sorted_indices = np.argsort(similarities)[::-1]

    sorted_product_skus = [product_skus[i] for i in sorted_indices]
    sorted_similarities = [similarities[i] for i in sorted_indices]

    return sorted_product_skus, sorted_similarities

if __name__ == "__main__":
    try:
        with connection.cursor() as cursor:
            query = sys.argv[1]
            product_skus, documents, vectors = fetch_product_vectors(cursor)
            sorted_product_skus, sorted_similarities = search_products(query, product_skus, documents, vectors)

            results = [
                {"product_sku": sku, "similarity": sim}
                for sku, sim in zip(sorted_product_skus, sorted_similarities)
            ]

            print(json.dumps(results))
    finally:
        connection.close()