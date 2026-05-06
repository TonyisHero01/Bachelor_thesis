import json
from datetime import datetime

from config import settings


def save_evaluation_report(report: dict):
    timestamp = datetime.utcnow().strftime("%Y%m%d_%H%M%S")

    latest_path = settings.report_dir / "evaluation_latest.json"
    dated_path = settings.report_dir / f"evaluation_{timestamp}.json"

    text = json.dumps(
        report,
        indent=2,
        ensure_ascii=False,
    )

    latest_path.write_text(text, encoding="utf-8")
    dated_path.write_text(text, encoding="utf-8")


def load_latest_evaluation_report():
    latest_path = settings.report_dir / "evaluation_latest.json"

    if not latest_path.exists():
        return {
            "error": "No evaluation report has been generated yet."
        }

    return json.loads(
        latest_path.read_text(encoding="utf-8")
    )