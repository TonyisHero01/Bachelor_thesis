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

# 加载 .env 变量
dotenv_path = '../.env'
load_dotenv()

# 读取数据库连接信息
database_url = os.getenv("DATABASE_URL")
#print("Database URL:", database_url)

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
    id = str(row['id']) if row['id'] is not None else ''
    name = row['name'] if row['name'] is not None else ''
    category = row['category'] if row['category'] is not None else ''
    description = row['description'] if row['description'] is not None else ''

    if not name and not description:
        return ''

    # 预处理文本
    name = custom_preprocessor(name)
    description = custom_preprocessor(description)
    category = custom_preprocessor(category)

    name_weighted = ' '.join([name] * weight_factor) if name else ''
    description_weighted = ' '.join([description] * weight_factor) if description else name_weighted

    product_info_document = f"{id} {name_weighted} {category} {description_weighted}"

    return product_info_document

# 更新 TF-IDF 向量
def update_product_vectors(documents, products, cursor):
    vectorizer = TfidfVectorizer(
        preprocessor=custom_preprocessor,
        token_pattern=r'\b\w+\b',
        lowercase=True,
        ngram_range=(1,2)  # 允许单个单词和双词组合
    )

    tfidf_matrix = vectorizer.fit_transform(list(documents.values()))
    dense_tfidf_matrix = tfidf_matrix.toarray()

    for product, vector in zip(products, dense_tfidf_matrix):
        product_id = product['id']
        vector_str = ','.join(map(str, vector))
        sql = "INSERT INTO Product_Document_Vector (id, document, vector) VALUES (%s, %s, %s)"
        cursor.execute(sql, (product_id, documents[product_id], vector_str))

    connection.commit()

# 获取产品文本数据
def get_documents(cursor):
    sql = "SELECT * FROM Product"
    cursor.execute("DROP TABLE IF EXISTS Product_Document_Vector;")
    cursor.execute("CREATE TABLE IF NOT EXISTS Product_Document_Vector (id INT PRIMARY KEY, document TEXT, vector TEXT);")
    
    cursor.execute(sql)
    products = cursor.fetchall()
    documents = {}

    for row in products:
        product_info_document = row_to_string(row)
        if product_info_document:
            documents[row["id"]] = product_info_document

    return documents, products

# 获取产品向量
def fetch_product_vectors(cursor):
    sql = "SELECT id, document, vector FROM Product_Document_Vector"
    cursor.execute(sql)
    result = cursor.fetchall()

    product_ids = []
    documents = []
    vectors = []

    for row in result:
        product_ids.append(row['id'])
        documents.append(row['document'])
        vector_str = row['vector']
        vector = np.array([float(x) for x in vector_str.split(',')])

        #if np.all(vector == 0):  
            #print(f"Warning: Product ID {row['id']} has zero vector!")

        vectors.append(vector)

    return product_ids, documents, np.array(vectors)

# 执行搜索
def search_products(query, product_ids, documents, vectors):
    vectorizer = TfidfVectorizer(
        sublinear_tf=True,
        norm='l2',
        token_pattern=r'\b\w+\b',
        lowercase=True,
        preprocessor=custom_preprocessor,
        ngram_range=(1,2)  # 同时考虑单个单词和双词组合
    )

    if isinstance(documents, dict):
        all_docs = list(documents.values())
    elif isinstance(documents, list):
        all_docs = documents
    else:
        raise TypeError("documents 必须是 dict 或 list!")

    all_docs.append(query)

    tfidf_matrix = vectorizer.fit_transform(all_docs)
    #print("TF-IDF Vocabulary:", vectorizer.vocabulary_)  # 调试 TF-IDF 词汇表

    vectors = tfidf_matrix[:-1].toarray()
    query_vector = tfidf_matrix[-1].toarray()

    similarities = cosine_similarity(query_vector, vectors).flatten()
    sorted_indices = np.argsort(similarities)[::-1]

    sorted_product_ids = [product_ids[idx] for idx in sorted_indices]
    sorted_similarities = [similarities[idx] for idx in sorted_indices]

    return sorted_product_ids, sorted_similarities

if __name__ == "__main__":
    try:
        with connection.cursor() as cursor:
            documents_, products = get_documents(cursor)
            update_product_vectors(documents_, products, cursor)

            query = sys.argv[1]

            product_ids, documents, vectors = fetch_product_vectors(cursor)

            sorted_product_ids, sorted_similarities = search_products(query, product_ids, documents, vectors)

            results = [
                {"product_id": product_id, "similarity": similarity}
                for product_id, similarity in zip(sorted_product_ids, sorted_similarities)
            ]

            print(json.dumps(results, indent=4))  # 美化 JSON 输出
    finally:
        connection.close()