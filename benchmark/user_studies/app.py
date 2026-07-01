from pathlib import Path

from fastapi import APIRouter, Request, Form
from fastapi.responses import HTMLResponse
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
async def show_user_study_form(request: Request):
    return templates.TemplateResponse(
        "user_study_form.html",
        {
            "request": request,
        },
    )


@router.post("/submit", response_class=HTMLResponse)
async def submit_user_study(
    request: Request,
    recommendation_useful: str = Form(...),
    search_quality: str = Form(...),
    response_speed: str = Form(...),
    product_relevance: str = Form(...),
    interface_clarity: str = Form(...),
    comment: str = Form(""),
):
    answers = {
        "recommendation_useful": recommendation_useful,
        "search_quality": search_quality,
        "response_speed": response_speed,
        "product_relevance": product_relevance,
        "interface_clarity": interface_clarity,
        "comment": comment,
    }

    user_agent = request.headers.get("user-agent")
    ip_address = request.client.host if request.client else None

    study_id = repo.save_study(
        form_name="recommendation_user_study",
        page_type="benchmark",
        source="python_user_study_form",
        answers=answers,
        user_agent=user_agent,
        ip_address=ip_address,
    )

    return templates.TemplateResponse(
        "user_study_form.html",
        {
            "request": request,
            "success": True,
            "study_id": study_id,
        },
    )