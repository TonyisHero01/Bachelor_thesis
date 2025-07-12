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

def row_to_string(row, weight_factor=20):
    sku = row.get('sku', '') or ''
    name = row.get('name', '') or ''
    category = row.get('category', '') or ''
    description = row.get('description', '') or ''

    if not name and not description:
        return ''

    name = custom_preprocessor(name)
    description = custom_preprocessor(description)
    category = custom_preprocessor(category)

    name_weighted = ' '.join([name] * weight_factor) if name else ''
    description_weighted = ' '.join([description] * 5) if description else ''

    product_info_document = f"{sku} {name_weighted} {category} {description_weighted}"
    return product_info_document

def get_documents(cursor):
    sql = """
        SELECT p.*, c.name AS category
        FROM product p
        LEFT JOIN category c ON p.category_id = c.id
    """
    cursor.execute(sql)
    products = cursor.fetchall()

    cursor.execute("DROP TABLE IF EXISTS product_document_vector;")
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS product_document_vector (
            sku TEXT PRIMARY KEY,
            document TEXT,
            vector TEXT
        );
    """)

    # ✅ 强制提交表结构更改
    connection.commit()

    documents = {}
    seen_skus = set()

    print(f"Fetched {len(products)} products.")
    print("Sample product row:", products[0] if products else "None")

    for row in products:
        if row['sku'] not in seen_skus:
            product_info_document = row_to_string(row)
            if product_info_document:
                documents[row["sku"]] = product_info_document
                seen_skus.add(row['sku'])

    return documents, products

def update_product_vectors(documents, products, cursor):
    if not documents:
        return

    vectorizer = TfidfVectorizer(
        preprocessor=custom_preprocessor,
        token_pattern=r'\b\w+\b',
        lowercase=True,
        ngram_range=(1, 2)
    )

    tfidf_matrix = vectorizer.fit_transform(list(documents.values()))
    dense_tfidf_matrix = tfidf_matrix.toarray()

    cursor.execute("DELETE FROM Product_Document_Vector;")

    for sku, vector in zip(documents.keys(), dense_tfidf_matrix):
        vector_str = ','.join(map(str, vector))
        cursor.execute("""
            INSERT INTO Product_Document_Vector (sku, document, vector)
            VALUES (%s, %s, %s)
            ON CONFLICT (sku) DO UPDATE
            SET document = EXCLUDED.document, vector = EXCLUDED.vector
        """, (sku, documents[sku], vector_str))

    connection.commit()

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

    vectors = tfidf_matrix[:-1].toarray()
    query_vector = tfidf_matrix[-1].toarray()

    similarities = cosine_similarity(query_vector, vectors).flatten()
    sorted_indices = np.argsort(similarities)[::-1]

    sorted_product_skus = [product_skus[i] for i in sorted_indices]
    sorted_similarities = [similarities[i] for i in sorted_indices]

    return sorted_product_skus, sorted_similarities

if __name__ == "__main__":
    try:
        with connection.cursor() as cursor:
            if len(sys.argv) > 1 and sys.argv[1] == "__TRAIN__":
                documents_, products = get_documents(cursor)
                update_product_vectors(documents_, products, cursor)
                print("✅ TF-IDF vectors updated.")
            else:
                query = sys.argv[1] if len(sys.argv) > 1 else ""
                product_skus, documents, vectors = fetch_product_vectors(cursor)
                sorted_product_skus, sorted_similarities = search_products(query, product_skus, documents, vectors)

                results = [
                    {"product_sku": sku, "similarity": sim}
                    for sku, sim in zip(sorted_product_skus, sorted_similarities)
                ]
                print(json.dumps(results))
    finally:
        connection.close()