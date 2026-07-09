from pathlib import Path

from fastapi import APIRouter, Request, Form, Query
from fastapi.responses import HTMLResponse, RedirectResponse
from fastapi.templating import Jinja2Templates


from user_studies.repository import UserStudyRepository


BASE_DIR = Path(__file__).resolve().parent

router = APIRouter(
    prefix="/user-studies",
    tags=["user-studies"],
)

templates = Jinja2Templates(directory=str(BASE_DIR / "templates"))

repo = UserStudyRepository()


@router.get("/", response_class=HTMLResponse)
async def show_user_study_form(
    request: Request,
    success: bool = Query(False),
    study_id: int | None = Query(None),
):
    return templates.TemplateResponse(
        "user_study_form.html",
        {
            "request": request,
            "success": success,
            "study_id": study_id,
        },
    )


@router.post("/submit", response_class=HTMLResponse)
async def submit_user_study(
    request: Request,

    search_task: str = Form(""),
    tested_query: str = Form(""),

    lexical_relevance: str = Form(...),
    lexical_ranking_quality: str = Form(...),
    lexical_result_diversity: str = Form(...),
    lexical_overall_satisfaction: str = Form(...),

    semantic_relevance: str = Form(...),
    semantic_ranking_quality: str = Form(...),
    semantic_result_diversity: str = Form(...),
    semantic_overall_satisfaction: str = Form(...),

    bm25_relevance: str = Form(...),
    bm25_ranking_quality: str = Form(...),
    bm25_result_diversity: str = Form(...),
    bm25_overall_satisfaction: str = Form(...),

    preferred_algorithm: str = Form(...),
    easiest_to_understand: str = Form(""),
    comment: str = Form(""),
):
    answers = {
        "search_task": search_task,
        "tested_query": tested_query,

        "lexical": {
            "relevance": lexical_relevance,
            "ranking_quality": lexical_ranking_quality,
            "result_diversity": lexical_result_diversity,
            "overall_satisfaction": lexical_overall_satisfaction,
        },

        "semantic_vector": {
            "relevance": semantic_relevance,
            "ranking_quality": semantic_ranking_quality,
            "result_diversity": semantic_result_diversity,
            "overall_satisfaction": semantic_overall_satisfaction,
        },

        "elasticsearch_bm25": {
            "relevance": bm25_relevance,
            "ranking_quality": bm25_ranking_quality,
            "result_diversity": bm25_result_diversity,
            "overall_satisfaction": bm25_overall_satisfaction,
        },

        "comparison": {
            "preferred_algorithm": preferred_algorithm,
            "easiest_to_understand": easiest_to_understand,
        },

        "comment": comment,
    }

    user_agent = request.headers.get("user-agent")
    ip_address = request.client.host if request.client else None

    study_id = repo.save_study(
        form_name="search_algorithm_comparison_user_study",
        page_type="benchmark_user_study",
        source="python_user_study_form",
        answers=answers,
        user_agent=user_agent,
        ip_address=ip_address,
    )

    return RedirectResponse(
        url=f"/user-studies/?success=1&study_id={study_id}",
        status_code=303,
    )