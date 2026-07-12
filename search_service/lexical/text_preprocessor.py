import json
import re
from typing import Any


DEFAULT_TEXT_PREPROCESSING = {
    "lowercase": True,
    "remove_digits": True,
    "replace_non_word_characters": True,
    "collapse_whitespace": True,
}


def parse_boolean(
    value: Any,
    default: bool,
) -> bool:
    if isinstance(value, bool):
        return value

    if isinstance(value, int):
        return value != 0

    if isinstance(value, str):
        normalized = value.strip().lower()

        if normalized in {"true", "1", "yes", "on"}:
            return True

        if normalized in {"false", "0", "no", "off"}:
            return False

    return default


def parse_algorithm_settings(config: dict | None) -> dict:
    if not isinstance(config, dict):
        return {}

    settings = config.get("algorithm_settings", {})

    if isinstance(settings, dict):
        return settings

    if isinstance(settings, str):
        try:
            parsed = json.loads(settings)

            if isinstance(parsed, dict):
                return parsed
        except json.JSONDecodeError:
            return {}

    return {}


def get_text_preprocessing_settings(
    config: dict | None = None,
) -> dict:
    settings = DEFAULT_TEXT_PREPROCESSING.copy()

    algorithm_settings = parse_algorithm_settings(config)

    configured_settings = algorithm_settings.get(
        "text_preprocessing",
        {},
    )

    if not isinstance(configured_settings, dict):
        return settings

    settings["lowercase"] = parse_boolean(
        configured_settings.get("lowercase"),
        settings["lowercase"],
    )

    settings["remove_digits"] = parse_boolean(
        configured_settings.get("remove_digits"),
        settings["remove_digits"],
    )

    settings["replace_non_word_characters"] = parse_boolean(
        configured_settings.get(
            "replace_non_word_characters"
        ),
        settings["replace_non_word_characters"],
    )

    settings["collapse_whitespace"] = parse_boolean(
        configured_settings.get("collapse_whitespace"),
        settings["collapse_whitespace"],
    )

    return settings


def normalize_text(
    text: str,
    config: dict | None = None,
) -> str:
    text = str(text or "")

    settings = get_text_preprocessing_settings(
        config
    )

    if settings["lowercase"]:
        text = text.lower()

    if settings["remove_digits"]:
        text = re.sub(r"\d+", "", text)

    if settings["replace_non_word_characters"]:
        text = re.sub(r"[^\w\s]", " ", text)

    if settings["collapse_whitespace"]:
        text = re.sub(r"\s+", " ", text)

    return text.strip()


def repeat_text(
    text: str,
    weight: int,
    config: dict | None = None,
) -> str:
    normalized_text = normalize_text(
        text,
        config,
    )

    try:
        normalized_weight = max(
            0,
            int(weight),
        )
    except (TypeError, ValueError):
        normalized_weight = 0

    if (
        not normalized_text
        or normalized_weight <= 0
    ):
        return ""

    return " ".join(
        [normalized_text] * normalized_weight
    )


def normalize_attributes(
    attributes,
    config: dict | None = None,
) -> str:
    if not attributes:
        return ""

    if isinstance(attributes, str):
        try:
            attributes = json.loads(attributes)
        except json.JSONDecodeError:
            return normalize_text(
                attributes,
                config,
            )

    if isinstance(attributes, dict):
        parts = []

        for key, value in attributes.items():
            parts.append(str(key))
            parts.append(str(value))

        return normalize_text(
            " ".join(parts),
            config,
        )

    if isinstance(attributes, list):
        return normalize_text(
            " ".join(map(str, attributes)),
            config,
        )

    return normalize_text(
        str(attributes),
        config,
    )


def build_product_document(
    row: dict,
    config: dict,
) -> str:
    sku = str(
        row.get("sku") or ""
    ).strip()

    attributes = normalize_attributes(
        row.get("attributes"),
        config,
    )

    parts = [
        # SKU 保持原始形式，避免删除型号中的数字。
        sku,

        repeat_text(
            row.get("name") or "",
            config.get("name_weight", 20),
            config,
        ),

        repeat_text(
            row.get("description") or "",
            config.get("description_weight", 5),
            config,
        ),

        repeat_text(
            row.get("category") or "",
            config.get("category_weight", 4),
            config,
        ),

        repeat_text(
            row.get("material") or "",
            config.get("material_weight", 2),
            config,
        ),

        repeat_text(
            row.get("color") or "",
            config.get("color_weight", 2),
            config,
        ),

        repeat_text(
            row.get("size") or "",
            config.get("size_weight", 2),
            config,
        ),

        repeat_text(
            attributes,
            config.get("attributes_weight", 2),
            config,
        ),
    ]

    return " ".join(
        part
        for part in parts
        if part
    ).strip()