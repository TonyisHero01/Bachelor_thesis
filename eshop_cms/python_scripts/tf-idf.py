import pymysql
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
import numpy as np
from dotenv import load_dotenv
import os
import sys
import json
#print(os.getcwd())
dotenv_path = '../.env'

load_dotenv(dotenv_path)

database_url = os.getenv("DATABASE_URL")
print(database_url)

if database_url:
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
    print(host_port)
    host = host_port[0]
    port = host_port[1]


connection = pymysql.connect(host=host,
                             user=username,
                             password=password,
                             database=dbname,
                             charset='utf8mb4',
                             cursorclass=pymysql.cursors.DictCursor)

def row_to_string(row, weight_factor=20):
    id = str(row['ID']) if row['ID'] is not None else ''
    name = row['NAME'] if row['NAME'] is not None else ''
    kategory = row['KATEGORY'] if row['KATEGORY'] is not None else ''
    description = row['DESCRIPTION'] if row['DESCRIPTION'] is not None else ''
    number_in_stock = str(row['NUMBER_IN_STOCK']) if row['NUMBER_IN_STOCK'] is not None else ''
    add_time = row['ADD_TIME'] if row['ADD_TIME'] is not None else ''
    width = str(row['WIDTH']) if row['WIDTH'] is not None else ''
    height = str(row['HEIGHT']) if row['HEIGHT'] is not None else ''
    length = str(row['LENGTH']) if row['LENGTH'] is not None else ''
    weight = str(row['WEIGHT']) if row['WEIGHT'] is not None else ''
    material = row['MATERIAL'] if row['MATERIAL'] is not None else ''
    color = row['COLOR'] if row['COLOR'] is not None else ''
    price = str(row['PRICE']) if row['PRICE'] is not None else ''
    
    name_weighted = ' '.join([name] * weight_factor)
    description_weighted = ' '.join([description] * weight_factor)
    product_info_document = f"{id} {name_weighted} {kategory} {description_weighted} {number_in_stock} {add_time} {width} {height} {length} {weight} {material} {color} {price}"
    
    return product_info_document
def update_product_vectors(documents, products, cursor):

    vectorizer = TfidfVectorizer()

    tfidf_matrix = vectorizer.fit_transform(list(documents.values()))
    dense_tfidf_matrix = tfidf_matrix.toarray()
    for product, vector in zip(products, dense_tfidf_matrix):
        product_id = product['ID']
        vector_str = ','.join(map(str, vector))
        sql = "INSERT INTO Product_Document_Vector (id, document, vector) VALUES (%s, %s, %s)"
        cursor.execute(sql, (product_id, documents[product_id], vector_str))
        
    connection.commit()

def get_documents(cursor):
    sql = "SELECT * FROM Product"
    sql3 = "Drop Table IF EXISTS Product_Document_Vector;"
    cursor.execute(sql3)
    sql2 = "CREATE TABLE IF NOT EXISTS Product_Document_Vector (id INT PRIMARY KEY, document TEXT, vector TEXT);"
    
    cursor.execute(sql2)
    cursor.execute(sql)
    
    products = cursor.fetchall()
    documents = {}

    for row in products:
        #print(row)
        product_info_document = row_to_string(row)
        documents[row["ID"]] = product_info_document
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
    vectorizer = TfidfVectorizer(sublinear_tf=True, norm='l2')
    
    all_docs = documents + [query]
    vectorizer.fit(all_docs)
    
    query_vector = vectorizer.transform([query]).toarray()
    
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

            print(json.dumps(results))
    finally:
        connection.close()