"""
Global Alliance Trust: web server for the GAT donation assistant.

Routes:
    /              the donation assistant chat page (static/index.html)
                   (the same API also powers the chat bubble on the WordPress
                   site; GAT_ALLOWED_ORIGINS lists the sites allowed to call it)
    /concepts/     the homepage design concepts preview (static/concepts/)
    /api/new-thread, /api/chat   the chat API used by the page
    /healthz       health check for Coolify

Conversation history lives in memory per thread_id and is lost on restart
or redeploy. Each visitor IP is rate limited so a public URL can't run up
the OpenAI bill.
"""

import os
import json
import time
import uuid
import logging
from datetime import datetime

from fastapi import FastAPI, HTTPException, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse, RedirectResponse
from fastapi.staticfiles import StaticFiles
from pydantic import BaseModel
from openai import OpenAI

from tools import TOOL_SCHEMAS, TOOL_FUNCTIONS, gift_aid_enabled
from payments import stripe_enabled

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
log = logging.getLogger("gat")

MODEL = os.environ.get("GAT_MODEL", "gpt-4.1").strip()
MAX_TOOL_HOPS = 6                     # cap on chained tool calls per turn
MAX_MESSAGES_PER_THREAD = 60          # messages kept per thread (incl. tool results)
MAX_COMPLETION_TOKENS = 600
MAX_INPUT_CHARS = 1000
THREAD_TTL_SECONDS = 60 * 60 * 6      # drop threads untouched for 6h

RATE_WINDOW_SECONDS = 600
RATE_MAX_PER_WINDOW = int(os.environ.get("GAT_RATE_PER_10MIN", "30"))
RATE_MAX_PER_DAY = int(os.environ.get("GAT_RATE_PER_DAY", "150"))

_here = os.path.dirname(os.path.abspath(__file__))

with open(os.path.join(_here, "system_prompt.md"), encoding="utf-8") as f:
    _PROMPT_TEMPLATE = f.read()

_GIFT_AID_ON = (
    "- Always ask if they're a UK taxpayer so Gift Aid can be claimed: it adds an\n"
    "  extra 25p per £1 from HMRC at no extra cost to the donor. If yes, set\n"
    "  gift_aid to true and mention the bonus amount when you confirm."
)
_GIFT_AID_OFF = (
    "- Gift Aid is not available. Don't offer it, and if asked, say the team\n"
    "  can advise. Always set gift_aid to false."
)

OPENAI_TOOLS = [
    {
        "type": "function",
        "function": {
            "name": t["name"],
            "description": t["description"],
            "parameters": t["input_schema"],
        },
    }
    for t in TOOL_SCHEMAS
]


def system_prompt() -> str:
    # Built per thread so the date is always "now", not process start time.
    now = datetime.now()
    base = _PROMPT_TEMPLATE.replace(
        "{gift_aid_rule}", _GIFT_AID_ON if gift_aid_enabled() else _GIFT_AID_OFF
    )
    return (
        f"Today's date is {now.strftime('%A, %Y-%m-%d')}, current time {now.strftime('%H:%M')}.\n\n"
        + base
    )


def run_tool(name: str, tool_input: dict) -> dict:
    fn = TOOL_FUNCTIONS.get(name)
    if fn is None:
        return {"error": f"Unknown tool: {name}"}
    try:
        return fn(**tool_input)
    except Exception as e:
        return {"error": str(e)}


# ---------------------------------------------------------------------------
# In-memory thread store: {thread_id: {"messages": [...], "last_seen": ts}}
# ---------------------------------------------------------------------------
THREADS: dict[str, dict] = {}


def _evict_stale_threads() -> None:
    cutoff = time.time() - THREAD_TTL_SECONDS
    for tid in [tid for tid, t in THREADS.items() if t["last_seen"] < cutoff]:
        del THREADS[tid]


# ---------------------------------------------------------------------------
# Per-IP rate limiting
# ---------------------------------------------------------------------------
_ip_requests: dict[str, dict[str, list[float]]] = {}


def _client_ip(request: Request) -> str:
    return (
        request.headers.get("cf-connecting-ip")
        or request.headers.get("x-forwarded-for", "").split(",")[0].strip()
        or (request.client.host if request.client else "unknown")
    )


def _check_rate_limit(ip: str) -> None:
    now = time.time()
    data = _ip_requests.setdefault(ip, {"window": [], "day": []})
    data["window"] = [t for t in data["window"] if now - t < RATE_WINDOW_SECONDS]
    data["day"] = [t for t in data["day"] if now - t < 86400]
    if len(data["window"]) >= RATE_MAX_PER_WINDOW or len(data["day"]) >= RATE_MAX_PER_DAY:
        raise HTTPException(
            status_code=429,
            detail="You've sent a lot of messages in a short time. Please wait a few minutes and try again.",
        )
    data["window"].append(now)
    data["day"].append(now)


def _client() -> OpenAI:
    api_key = os.environ.get("OPENAI_API_KEY")
    if not api_key:
        raise HTTPException(status_code=500, detail="Server is missing OPENAI_API_KEY.")
    return OpenAI(api_key=api_key)


class ChatRequest(BaseModel):
    thread_id: str
    message: str


class Payment(BaseModel):
    url: str
    amount: float
    frequency: str
    campaign_name: str


class ChatResponse(BaseModel):
    thread_id: str
    reply: str
    # Set when this turn created a donation with a Stripe link, so the page
    # can show a Pay button that doesn't depend on the model copying the URL.
    payment: Payment | None = None


app = FastAPI(title="Global Alliance Trust Assistant", docs_url=None, redoc_url=None)


def allowed_origins() -> list[str]:
    """Websites allowed to embed the chat bubble (comma-separated env)."""
    raw = os.environ.get("GAT_ALLOWED_ORIGINS", "https://gat-site.amverio.co.uk")
    return [o.strip().rstrip("/") for o in raw.split(",") if o.strip()]


# The WordPress site's chat bubble calls /api/* from another origin.
app.add_middleware(
    CORSMiddleware,
    allow_origins=allowed_origins(),
    allow_methods=["GET", "POST"],
    allow_headers=["Content-Type"],
    allow_credentials=False,
    max_age=600,
)


@app.get("/healthz")
def healthz():
    return {"ok": True, "agent": "gat", "payments": stripe_enabled()}


@app.post("/api/new-thread")
def new_thread():
    _evict_stale_threads()
    thread_id = str(uuid.uuid4())
    THREADS[thread_id] = {
        "messages": [{"role": "system", "content": system_prompt()}],
        "last_seen": time.time(),
    }
    return {"thread_id": thread_id}


@app.post("/api/chat", response_model=ChatResponse)
def chat(req: ChatRequest, request: Request):
    message = (req.message or "").strip()
    if not message:
        raise HTTPException(status_code=400, detail="Empty message.")
    if len(message) > MAX_INPUT_CHARS:
        raise HTTPException(
            status_code=400,
            detail=f"That message is too long (max {MAX_INPUT_CHARS} characters).",
        )

    _evict_stale_threads()
    thread = THREADS.get(req.thread_id or "")
    if thread is None:
        raise HTTPException(
            status_code=404,
            detail="This conversation has expired. Please start a new one.",
        )

    _check_rate_limit(_client_ip(request))
    thread["last_seen"] = time.time()
    messages = thread["messages"]

    client = _client()
    messages.append({"role": "user", "content": message})

    hops = 0
    payment = None
    while True:
        try:
            response = client.chat.completions.create(
                model=MODEL,
                tools=OPENAI_TOOLS,
                messages=messages,
                max_tokens=MAX_COMPLETION_TOKENS,
                temperature=0.3,
            )
        except Exception:
            log.exception("OpenAI call failed")
            # Drop the unanswered user turn so a retry doesn't send it twice.
            if hops == 0:
                messages.pop()
            raise HTTPException(status_code=502, detail="Sorry, the assistant is unavailable right now. Please try again.")

        msg = response.choices[0].message
        messages.append(msg.model_dump(exclude_none=True))

        if not msg.tool_calls:
            reply = msg.content or ""
            break

        hops += 1
        if hops > MAX_TOOL_HOPS:
            reply = "Sorry, something went wrong putting that together. Could you try rephrasing?"
            # Close out the dangling tool calls so the thread stays valid.
            for call in msg.tool_calls:
                messages.append({"role": "tool", "tool_call_id": call.id,
                                 "content": json.dumps({"error": "aborted"})})
            messages.append({"role": "assistant", "content": reply})
            break

        for call in msg.tool_calls:
            try:
                args = json.loads(call.function.arguments or "{}")
            except json.JSONDecodeError:
                args = {}
            result = run_tool(call.function.name, args)
            log.info("tool_call thread=%s name=%s success=%s",
                     req.thread_id, call.function.name, result.get("success", "n/a"))
            if call.function.name == "create_donation" and result.get("success") and result.get("payment_link"):
                payment = Payment(url=result["payment_link"], amount=result["amount"],
                                  frequency=result["frequency"], campaign_name=result["campaign_name"])
            messages.append({
                "role": "tool",
                "tool_call_id": call.id,
                "content": json.dumps(result),
            })

    # Trim long threads, keeping the system prompt. Never start the kept
    # tail on a tool result (it must follow its assistant tool_calls message).
    if len(messages) > MAX_MESSAGES_PER_THREAD:
        tail = messages[-(MAX_MESSAGES_PER_THREAD - 1):]
        while tail and tail[0].get("role") != "user":
            tail.pop(0)
        messages[:] = [messages[0]] + tail

    return ChatResponse(thread_id=req.thread_id, reply=reply, payment=payment)


@app.get("/thank-you", include_in_schema=False)
def thank_you():
    # Stripe redirects here after payment. Payment status itself is in the
    # Stripe dashboard (this app keeps no donation records between restarts).
    return FileResponse(os.path.join(_here, "static", "thank-you.html"))


@app.get("/concepts", include_in_schema=False)
def concepts_redirect():
    # Trailing slash so the page's relative image paths resolve.
    return RedirectResponse("/concepts/", status_code=301)


# Static files last, so the API routes above take priority.
app.mount("/", StaticFiles(directory=os.path.join(_here, "static"), html=True), name="static")
