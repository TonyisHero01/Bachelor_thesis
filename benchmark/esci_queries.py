import pandas as pd

from config import settings


def load_esci_queries():
    df = pd.read_parquet(
        settings.esci_examples_path,
        columns=[
            "query",
            "product_locale",
            "esci_label",
            "split",
        ],
    )

    df = df[
        (df["product_locale"] == "us")
        & (df["split"] == "test")
        & (df["esci_label"].isin(["E", "S"]))
    ]

    queries = (
        df["query"]
        .dropna()
        .drop_duplicates()
        .head(settings.esci_query_limit)
        .tolist()
    )

    return queries