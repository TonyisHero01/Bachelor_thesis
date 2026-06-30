from html import escape


def percentage(value):
    return f"{value * 100:.0f}%"


def h(value):
    return escape(str(value or ""))


def format_score(value):
    if value is None:
        return "-"

    try:
        return f"{float(value):.4f}"
    except Exception:
        return "-"


def status_label(value, good_threshold=0.65):
    if value >= good_threshold:
        return "good"

    if value >= 0.5:
        return "warn"

    return "bad"


def page_style():
    return """
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f4f6f8;
            color: #222;
        }

        .page {
            margin: 40px auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            padding: 28px;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
        }

        h1 {
            margin-top: 0;
            font-size: 30px;
        }

        h2 {
            margin-top: 32px;
        }

        .meta {
            color: #666;
            line-height: 1.6;
            margin-bottom: 18px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 24px 0;
        }

        a,
        button {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 10px;
            border: none;
            background: #111827;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        a.secondary {
            background: #4b5563;
        }

        button {
            background: #2563eb;
        }

        .summary,
        .metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 16px;
            margin: 24px 0;
        }

        .metric-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 18px;
            line-height: 1.5;
        }

        .metric-card span {
            display: block;
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .metric-card strong {
            display: block;
            font-size: 22px;
        }

        .metric-card.good {
            background: #ecfdf5;
            border-color: #a7f3d0;
        }

        .metric-card.warn {
            background: #fffbeb;
            border-color: #fde68a;
        }

        .metric-card.bad {
            background: #fef2f2;
            border-color: #fecaca;
        }

        .metric-card.result strong {
            font-size: 16px;
        }

        .notice {
            padding: 16px;
            border-radius: 14px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e3a8a;
            line-height: 1.6;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 22px;
            overflow: hidden;
        }

        th,
        td {
            border-bottom: 1px solid #e5e7eb;
            padding: 11px;
            text-align: left;
            font-size: 14px;
        }

        th {
            background: #f3f4f6;
            color: #374151;
        }

        tr:hover td {
            background: #f9fafb;
        }

        .empty {
            color: #6b7280;
            background: #f9fafb;
            padding: 18px;
            border-radius: 14px;
        }

        .chart-card {
            margin: 28px 0;
            padding: 20px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
        }

        .chart-card h2 {
            margin-top: 0;
        }

        .chart-card canvas {
            max-height: 320px;
        }

        .metric-table-wrapper {
            width: 100%;
            overflow-x: auto;
            margin: 24px 0;
        }

        .metric-table {
            width: 100%;
            min-width: 980px;
            border-collapse: separate;
            border-spacing: 12px;
        }

        .metric-table th,
        .metric-table td {
            border: none;
            padding: 0;
            background: transparent;
            vertical-align: top;
        }

        .metric-table thead th {
            color: #4b5563;
            font-size: 14px;
            text-align: center;
            white-space: nowrap;
        }

        .metric-table tbody th {
            font-size: 15px;
            text-align: left;
            white-space: nowrap;
            padding-top: 24px;
        }

        .metric-table .metric-card {
            margin: 0;
            min-width: 60px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-top: 18px;
        }

        .filter-grid label {
            display: block;
            font-size: 13px;
            color: #4b5563;
            margin-bottom: 6px;
        }

        .filter-grid input,
        .filter-grid select {
            width: 100%;
            box-sizing: border-box;
            padding: 9px 10px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 14px;
            background: white;
        }

        .tag {
            display: inline-block;
            padding: 4px 9px;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
            font-size: 12px;
            white-space: nowrap;
        }

        .tag.click {
            background: #ecfdf5;
            color: #065f46;
        }

        .tag.impression {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .small-muted {
            color: #6b7280;
            font-size: 12px;
            margin-top: 4px;
        }

        .wide-table {
            min-width: 1300px;
        }
    </style>
    """