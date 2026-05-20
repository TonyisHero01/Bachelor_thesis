import pandas as pd

products = pd.read_parquet(
    "esci-data/shopping_queries_dataset/shopping_queries_dataset_products.parquet"
)

print(products.columns)
print(products.head())