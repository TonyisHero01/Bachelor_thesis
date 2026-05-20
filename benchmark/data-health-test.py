import pyarrow.parquet as pq

parquet_file = pq.ParquetFile(
    "esci-data/shopping_queries_dataset/shopping_queries_dataset_products.parquet"
)

total_checked = 0

for batch in parquet_file.iter_batches(
    batch_size=5000,
    columns=[
        "product_id",
        "product_title",
        "product_description",
        "product_brand",
        "product_color",
        "product_locale",
    ]
):
    df = batch.to_pandas()
    total_checked += len(df)

    us_df = df[df["product_locale"] == "us"]

    if not us_df.empty:
        print(us_df.head(10))
        print("Checked rows:", total_checked)
        break
else:
    print("No US products found.")