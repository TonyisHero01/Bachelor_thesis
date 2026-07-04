import json
import psycopg2
from psycopg2.extras import RealDictCursor

from config import settings


USER_STUDY_TABLE = "user_studies"


def get_connection():
    return psycopg2.connect(
        settings.database_url,
        cursor_factory=RealDictCursor,
    )


def parse_answers(value):
    if value is None:
        return {}

    if isinstance(value, dict):
        return value

    try:
        return json.loads(value)
    except Exception:
        return {}


def to_int(value):
    try:
        return int(value)
    except Exception:
        return None


def avg(values):
    values = [
        value
        for value in values
        if value is not None
    ]

    if not values:
        return 0

    return round(sum(values) / len(values), 2)


def fetch_user_study_rows(limit: int = 500):
    sql = f"""
        SELECT
            id,
            session_id,
            customer_id,
            form_name,
            page_type,
            source,
            answers,
            created_at
        FROM {USER_STUDY_TABLE}
        ORDER BY created_at DESC, id DESC
        LIMIT %s
    """

    with get_connection() as conn:
        with conn.cursor() as cursor:
            cursor.execute(sql, (limit,))
            rows = cursor.fetchall()

    result = []

    for row in rows:
        item = dict(row)
        item["answers"] = parse_answers(item.get("answers"))
        result.append(item)

    return result


def build_rating_summary(rows):
    methods = {
        "tfidf": {
            "label": "TF-IDF",
            "relevance": [],
            "ranking_quality": [],
            "result_diversity": [],
            "overall_satisfaction": [],
        },
        "semantic_vector": {
            "label": "Semantic Vector",
            "relevance": [],
            "ranking_quality": [],
            "result_diversity": [],
            "overall_satisfaction": [],
        },
    }

    for row in rows:
        answers = row.get("answers", {})

        for method in ["tfidf", "semantic_vector"]:
            method_answers = answers.get(method, {})

            if not isinstance(method_answers, dict):
                continue

            for key in [
                "relevance",
                "ranking_quality",
                "result_diversity",
                "overall_satisfaction",
            ]:
                methods[method][key].append(
                    to_int(method_answers.get(key))
                )

    summary = {}

    for method, data in methods.items():
        summary[method] = {
            "label": data["label"],
            "relevance": avg(data["relevance"]),
            "ranking_quality": avg(data["ranking_quality"]),
            "result_diversity": avg(data["result_diversity"]),
            "overall_satisfaction": avg(data["overall_satisfaction"]),
        }

    return summary


def build_preference_summary(rows):
    preferred_algorithm = {}
    easiest_to_understand = {}

    for row in rows:
        answers = row.get("answers", {})
        comparison = answers.get("comparison", {})

        if not isinstance(comparison, dict):
            continue

        preferred = comparison.get("preferred_algorithm") or "unknown"
        easiest = comparison.get("easiest_to_understand") or "unknown"

        preferred_algorithm[preferred] = preferred_algorithm.get(preferred, 0) + 1
        easiest_to_understand[easiest] = easiest_to_understand.get(easiest, 0) + 1

    return {
        "preferred_algorithm": preferred_algorithm,
        "easiest_to_understand": easiest_to_understand,
    }


def build_recent_responses(rows, limit: int = 30):
    result = []

    for row in rows[:limit]:
        answers = row.get("answers", {})

        comparison = answers.get("comparison", {})
        if not isinstance(comparison, dict):
            comparison = {}

        result.append({
            "id": row.get("id"),
            "created_at": row.get("created_at").isoformat() if row.get("created_at") else "",
            "session_id": row.get("session_id") or "",
            "customer_id": row.get("customer_id"),
            "form_name": row.get("form_name") or "",
            "search_task": answers.get("search_task", ""),
            "tested_query": answers.get("tested_query", ""),
            "preferred_algorithm": comparison.get("preferred_algorithm", ""),
            "easiest_to_understand": comparison.get("easiest_to_understand", ""),
            "comment": answers.get("comment", ""),
        })

    return result


def fetch_user_study_metrics():
    rows = fetch_user_study_rows()

    unique_sessions = {
        row.get("session_id")
        for row in rows
        if row.get("session_id")
    }

    known_customers = {
        row.get("customer_id")
        for row in rows
        if row.get("customer_id") is not None
    }

    return {
        "summary": {
            "total_responses": len(rows),
            "unique_sessions": len(unique_sessions),
            "known_customers": len(known_customers),
        },
        "ratings": build_rating_summary(rows),
        "preferences": build_preference_summary(rows),
        "recent_responses": build_recent_responses(rows),
    }