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
import joblib  # ✅ 用于存储和加载 vectorizer

# 加载 .env 变量
dotenv_path = '../.env'
load_dotenv()

# 读取数据库连接信息
database_url = os.getenv("DATABASE_URL")

if not database_url:
    raise ValueError("DATABASE_URL 为空，请检查 .env 文件")

# 解析 DATABASE_URL
db_info = database_url.split("//")[1].split("@")
username_password, host_port_dbname = db_info[0], db_info[1]

username_password = username_password.split(":")
username = username_password[0]
password = username_password[1]

host_port_dbname = host_port_dbname.split("/")
host_port = host_port_dbname[0]
dbname = database_url.split("/")[-1].split("?")[0]

host_port = host_port.split(":")
host = host_port[0]
port = host_port[1]

# 连接数据库
connection = psycopg2.connect(
    host=host,
    user=username,
    password=password,
    database=dbname,
    port=port,
    cursor_factory=DictCursor
)

# 自定义文本预处理函数
def custom_preprocessor(text):
    text = text.lower()  # 转换为小写
    text = re.sub(r'\d+', '', text)  # 去除数字
    text = re.sub(r'[^\w\s]', '', text)  # 去除标点符号
    return text.strip()

# 将产品信息转换为文本格式
def row_to_string(row, weight_factor=20):
    sku = row['sku'] if row['sku'] is not None else ''
    name = row['name'] if row['name'] is not None else ''
    category = row['category'] if row['category'] is not None else ''
    description = row['description'] if row['description'] is not None else ''

    if not name and not description:
        return ''

    name = custom_preprocessor(name)
    description = custom_preprocessor(description)
    category = custom_preprocessor(category)

    name_weighted = ' '.join([name] * weight_factor) if name else ''
    description_weighted = ' '.join([description] * 5) if description else ''

    product_info_document = f"{sku} {name_weighted} {category} {description_weighted}"
    return product_info_document

# 获取产品文本数据
def get_documents(cursor):
    sql = "SELECT * FROM Product"
    cursor.execute(sql)
    products = cursor.fetchall()

    cursor.execute("DROP TABLE IF EXISTS Product_Document_Vector;")
    cursor.execute("CREATE TABLE IF NOT EXISTS Product_Document_Vector (sku TEXT PRIMARY KEY, document TEXT, vector TEXT);")

    documents = {}
    seen_skus = set()

    for row in products:
        if row['sku'] not in seen_skus:
            product_info_document = row_to_string(row)
            if product_info_document:
                documents[row["sku"]] = product_info_document
                seen_skus.add(row['sku'])

    return documents, products

# 更新 TF-IDF 向量
def update_product_vectors(documents, products, cursor):
    if not documents:
        return

    vectorizer = TfidfVectorizer(
        preprocessor=custom_preprocessor,
        token_pattern=r'\b\w+\b',
        lowercase=True,
        ngram_range=(1,2)
    )

    tfidf_matrix = vectorizer.fit_transform(list(documents.values()))
    dense_tfidf_matrix = tfidf_matrix.toarray()

    cursor.execute("DELETE FROM Product_Document_Vector;")

    processed_skus = set()
    unique_skus = list(documents.keys())
    
    for sku, vector in zip(unique_skus, dense_tfidf_matrix):
        if sku in processed_skus:
            continue

        vector_str = ','.join(map(str, vector))
        
        sql = """
        INSERT INTO Product_Document_Vector (sku, document, vector)
        VALUES (%s, %s, %s)
        ON CONFLICT (sku) DO UPDATE
        SET document = EXCLUDED.document, vector = EXCLUDED.vector
        """

        cursor.execute(sql, (sku, documents[sku], vector_str))
        processed_skus.add(sku)

    connection.commit()

# 获取产品向量
def fetch_product_vectors(cursor):
    sql = "SELECT sku, document, vector FROM Product_Document_Vector"
    cursor.execute(sql)
    result = cursor.fetchall()

    product_skus = []
    documents = []
    vectors = []

    for row in result:
        product_skus.append(row['sku'])
        documents.append(row['document'])
        vector_str = row['vector']
        vector = np.array([float(x) for x in vector_str.split(',')])
        vectors.append(vector)

    return product_skus, documents, np.array(vectors)

# 执行搜索
def search_products(query, product_skus, documents, vectors):
    vectorizer = TfidfVectorizer(
        preprocessor=custom_preprocessor,
        token_pattern=r'\b\w+\b',
        lowercase=True,
        ngram_range=(1,2)
    )

    all_docs = documents + [query]
    tfidf_matrix = vectorizer.fit_transform(all_docs)

    vectors = tfidf_matrix[:-1].toarray()
    query_vector = tfidf_matrix[-1].toarray()

    similarities = cosine_similarity(query_vector, vectors).flatten()
    sorted_indices = np.argsort(similarities)[::-1]

    sorted_product_skus = [product_skus[idx] for idx in sorted_indices]
    sorted_similarities = [similarities[idx] for idx in sorted_indices]

    return sorted_product_skus, sorted_similarities

if __name__ == "__main__":
    try:
        with connection.cursor() as cursor:
            documents_, products = get_documents(cursor)
            update_product_vectors(documents_, products, cursor)

            query = sys.argv[1]

            product_skus, documents, vectors = fetch_product_vectors(cursor)

            sorted_product_skus, sorted_similarities = search_products(query, product_skus, documents, vectors)

            results = [
                {"product_sku": product_sku, "similarity": similarity}
                for product_sku, similarity in zip(sorted_product_skus, sorted_similarities)
            ]

            print(json.dumps(results))
    finally:
        connection.close()