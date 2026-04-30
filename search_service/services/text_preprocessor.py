import re


def normalize_text(text: str) -> str:
    """
    Basic normalization:
    - lowercase
    - remove digits
    - remove punctuation
    - collapse whitespace
    """
    text = (text or "").lower()
    text = re.sub(r"\d+", "", text)
    text = re.sub(r"[^\w\s]", " ", text)
    text = re.sub(r"\s+", " ", text)
    return text.strip()


def build_product_document(row: dict, name_weight: int = 20) -> str:
    """
    Build weighted document string for a product.
    Name is heavily weighted, description moderately.
    """
    sku = str(row.get("sku") or "").strip()
    name = normalize_text(row.get("name") or "")
    category = normalize_text(row.get("category") or "")
    description = normalize_text(row.get("description") or "")

    if not name and not description:
        return ""

    name_part = " ".join([name] * name_weight) if name else ""
    description_part = " ".join([description] * 5) if description else ""

    return f"{sku} {name_part} {category} {description_part}".strip()