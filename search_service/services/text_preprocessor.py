import json
import re


def normalize_text(text: str) -> str:
    text = (text or "").lower()
    text = re.sub(r"\d+", "", text)
    text = re.sub(r"[^\w\s]", " ", text)
    text = re.sub(r"\s+", " ", text)
    return text.strip()


def repeat_text(text: str, weight: int) -> str:
    text = normalize_text(text)

    if not text:
        return ""

    weight = max(0, int(weight))

    if weight <= 0:
        return ""

    return " ".join([text] * weight)


def normalize_attributes(attributes) -> str:
    if not attributes:
        return ""

    if isinstance(attributes, str):
        try:
            attributes = json.loads(attributes)
        except json.JSONDecodeError:
            return normalize_text(attributes)

    if isinstance(attributes, dict):
        parts = []
        for key, value in attributes.items():
            parts.append(str(key))
            parts.append(str(value))
        return normalize_text(" ".join(parts))

    if isinstance(attributes, list):
        return normalize_text(" ".join(map(str, attributes)))

    return normalize_text(str(attributes))


def build_product_document(row: dict, config: dict) -> str:
    sku = str(row.get("sku") or "").strip()

    parts = [
        sku,
        repeat_text(row.get("name") or "", config.get("name_weight", 20)),
        repeat_text(row.get("description") or "", config.get("description_weight", 5)),
        repeat_text(row.get("category") or "", config.get("category_weight", 4)),
        repeat_text(row.get("material") or "", config.get("material_weight", 2)),
        repeat_text(row.get("color") or "", config.get("color_weight", 2)),
        repeat_text(row.get("size") or "", config.get("size_weight", 2)),
        repeat_text(
            normalize_attributes(row.get("attributes")),
            config.get("attributes_weight", 2),
        ),
    ]

    return " ".join(part for part in parts if part).strip()