import pandas as pd

df = pd.read_parquet("/esci-data/shopping_queries_dataset/shopping_queries_dataset_examples.parquet")

print(df.head())
print(df.columns)
print(df[["query", "product_id", "esci_label"]].head(20))