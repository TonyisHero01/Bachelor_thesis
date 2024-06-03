import pymysql
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
import numpy as np
from dotenv import load_dotenv
import os
import sys
import json
# 指定.env文件的路径
dotenv_path = '../.env'

# 加载.env文件
load_dotenv(dotenv_path)

# 从环境变量中获取数据库URL
database_url = os.getenv("DATABASE_URL")

# 解析数据库URL
if database_url:
    # 假设数据库URL的格式为：mysql://用户名:密码@主机地址:端口号/数据库名
    # 使用字符串切片和分割操作获取各个部分的值
    # 假设数据库URL的格式是固定的，你可以根据实际情况进行调整
    db_info = database_url.split("//")[1]
    db_info = db_info.split("@")
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


connection = pymysql.connect(host=host,
                             user=username,
                             password=password,
                             database=dbname,
                             charset='utf8mb4',
                             cursorclass=pymysql.cursors.DictCursor)

def row_to_string(row, weight_factor=20):
    id = str(row['id']) if row['id'] is not None else ''
    name = row['name'] if row['name'] is not None else ''
    kategory = row['kategory'] if row['kategory'] is not None else ''
    description = row['description'] if row['description'] is not None else ''
    number_in_stock = str(row['number_in_stock']) if row['number_in_stock'] is not None else ''
    add_time = row['add_time'] if row['add_time'] is not None else ''
    width = str(row['width']) if row['width'] is not None else ''
    height = str(row['height']) if row['height'] is not None else ''
    length = str(row['length']) if row['length'] is not None else ''
    weight = str(row['weight']) if row['weight'] is not None else ''
    material = row['material'] if row['material'] is not None else ''
    color = row['color'] if row['color'] is not None else ''
    price = str(row['price']) if row['price'] is not None else ''
    
    name_weighted = ' '.join([name] * weight_factor)
    description_weighted = ' '.join([description] * weight_factor)
    # 拼接产品信息字符串
    product_info_document = f"{id} {name_weighted} {kategory} {description_weighted} {number_in_stock} {add_time} {width} {height} {length} {weight} {material} {color} {price}"
    
    return product_info_document
def update_product_vectors(documents, products, cursor):

    vectorizer = TfidfVectorizer()

    # 将文档集合转换为TF-IDF特征矩阵
    tfidf_matrix = vectorizer.fit_transform(list(documents.values()))
    dense_tfidf_matrix = tfidf_matrix.toarray()
    for product, vector in zip(products, dense_tfidf_matrix):
        product_id = product['id']
        vector_str = ','.join(map(str, vector))  # 将向量转换为字符串
        sql = "INSERT INTO Product_Document_Vector (id, document, vector) VALUES (%s, %s, %s)"
        cursor.execute(sql, (product_id, documents[product_id], vector_str))
        
    connection.commit()

def get_documents(cursor):
    
    # 执行查询
    sql = "SELECT * FROM Product"
    sql3 = "Drop Table Product_Document_Vector;"
    cursor.execute(sql3)
    sql2 = "CREATE TABLE IF NOT EXISTS Product_Document_Vector (id INT PRIMARY KEY, document TEXT, vector TEXT);"
    
    cursor.execute(sql2)
    cursor.execute(sql)
    
    # 获取查询结果
    products = cursor.fetchall()
    documents = {}
    # 处理查询结果
    for row in products:
        product_info_document = row_to_string(row)
        documents[row["id"]] = product_info_document
    return documents, products
    

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
        vectors.append(vector)
        
    return product_ids, documents, np.array(vectors)

def search_products(query, product_ids, documents, vectors):
    
    # 初始化TfidfVectorizer
    vectorizer = TfidfVectorizer(sublinear_tf=True, norm='l2')
    
    # 重新训练TF-IDF向量器以包括查询
    all_docs = documents + [query]
    vectorizer.fit(all_docs)
    
    # 将查询转换为TF-IDF向量
    query_vector = vectorizer.transform([query]).toarray()
    
    # 计算余弦相似度
    similarities = cosine_similarity(query_vector, vectors).flatten()
    
    # 对相似度进行排序，获得排序索引
    sorted_indices = np.argsort(similarities)[::-1]
    
    # 根据相似度排序的索引获取产品ID
    sorted_product_ids = [product_ids[idx] for idx in sorted_indices]
    sorted_similarities = [similarities[idx] for idx in sorted_indices]
    
    return sorted_product_ids, sorted_similarities

if __name__ == "__main__":
    try:
        # 创建一个游标对象
        with connection.cursor() as cursor:
            documents_, products = get_documents(cursor)
            update_product_vectors(documents_, products, cursor)
            # 示例用法
            query = sys.argv[1]

            # 获取产品向量
            product_ids, documents, vectors = fetch_product_vectors(cursor)

            # 搜索并获取排序的产品ID
            sorted_product_ids, sorted_similarities = search_products(query, product_ids, documents, vectors)

            results = [
                {"product_id": product_id, "similarity": similarity}
                for product_id, similarity in zip(sorted_product_ids, sorted_similarities)
            ]

            # 打印结果为 JSON
            print(json.dumps(results))
    finally:
        # 关闭数据库连接
        connection.close()