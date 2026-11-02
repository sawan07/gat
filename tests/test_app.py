"""
Offline tests: tools, routes and the chat loop with a fake OpenAI client.
Run with:  pip install pytest && pytest -q
"""

import json
import os
import sys
from types import SimpleNamespace

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
os.environ.setdefault("OPENAI_API_KEY", "test-key")

import pytest
from fastapi.testclient import TestClient

import app as gat_app
import tools


@pytest.fixture
def client():
    gat_app._ip_requests.clear()
    return TestClient(gat_app.app)


# ---- tools ---------------------------------------------------------------

def test_campaigns_listed():
    out = tools.get_campaigns()
    assert out["organisation"] == "Global Alliance Trust"
    ids = {c["id"] for c in out["campaigns"]}
    assert {"COMMUNITY", "EDUCATION", "GENERAL"} <= ids


def test_donation_with_gift_aid():
    out = tools.create_donation(amount=40, campaign_id="community", donor_name="A Donor",
                                donor_email="a@example.com", gift_aid=True)
    assert out["success"] and out["gift_aid_bonus"] == 10.0
    assert out["campaign_name"] == "Community Services"


def test_gift_aid_disabled_by_env(monkeypatch):
    monkeypatch.setenv("GAT_GIFT_AID", "0")
    out = tools.create_donation(amount=40, campaign_id="GENERAL", donor_name="A",
                                donor_email="a@example.com", gift_aid=True)
    assert out["success"] and out["gift_aid"] is False and out["gift_aid_bonus"] == 0
    assert "Gift Aid is not available" in gat_app.system_prompt()


@pytest.mark.parametrize("kwargs,reason", [
    (dict(amount=0), "greater than zero"),
    (dict(amount="abc"), "number"),
    (dict(campaign_id="NOPE"), "Unknown campaign"),
    (dict(donor_email="not-an-email"), "valid email"),
    (dict(dedication_type="in_memory_of"), "dedicated"),
])
def test_donation_validation(kwargs, reason):
    base = dict(amount=10, campaign_id="GENERAL", donor_name="A", donor_email="a@example.com")
    base.update(kwargs)
    out = tools.create_donation(**base)
    assert not out["success"] and reason in out["reason"]


# ---- routes --------------------------------------------------------------

def test_health_and_pages(client):
    assert client.get("/healthz").json() == {"ok": True, "agent": "gat"}
    home = client.get("/")
    assert home.status_code == 200 and "Global Alliance Trust" in home.text
    r = client.get("/concepts", follow_redirects=False)
    assert r.status_code == 301 and r.headers["location"] == "/concepts/"
    concepts = client.get("/concepts/")
    assert concepts.status_code == 200 and "homepage concepts" in concepts.text
    assert client.get("/concepts/images/gat-homepage.jpg").status_code == 200
    assert "Disallow: /" in client.get("/robots.txt").text


def test_prompt_has_gift_aid_rule():
    p = gat_app.system_prompt()
    assert "{gift_aid_rule}" not in p and "Gift Aid" in p


# ---- chat loop with a fake model -----------------------------------------

def _msg(content=None, tool_calls=None):
    m = SimpleNamespace(content=content, tool_calls=tool_calls)
    m.model_dump = lambda exclude_none=True: {
        k: v for k, v in {"role": "assistant", "content": content,
                          "tool_calls": [{"id": c.id, "type": "function",
                                          "function": {"name": c.function.name,
                                                       "arguments": c.function.arguments}}
                                         for c in tool_calls] if tool_calls else None}.items()
        if v is not None}
    return SimpleNamespace(choices=[SimpleNamespace(message=m)])


class FakeOpenAI:
    def __init__(self, script):
        self.script = list(script)
        self.calls = []
        self.chat = SimpleNamespace(completions=SimpleNamespace(create=self._create))

    def _create(self, **kwargs):
        self.calls.append(json.loads(json.dumps(kwargs["messages"], default=str)))
        return self.script.pop(0)


def test_chat_runs_tool_then_replies(client, monkeypatch):
    call = SimpleNamespace(id="c1", function=SimpleNamespace(name="get_campaigns", arguments="{}"))
    fake = FakeOpenAI([_msg(tool_calls=[call]), _msg("We support six causes.")])
    monkeypatch.setattr(gat_app, "_client", lambda: fake)

    tid = client.post("/api/new-thread").json()["thread_id"]
    r = client.post("/api/chat", json={"thread_id": tid, "message": "What causes?"})
    assert r.status_code == 200 and r.json()["reply"] == "We support six causes."
    tool_msg = fake.calls[1][-1]
    assert tool_msg["role"] == "tool" and "Global Alliance Trust" in tool_msg["content"]


def test_chat_errors(client, monkeypatch):
    assert client.post("/api/chat", json={"thread_id": "nope", "message": "hi"}).status_code == 404
    tid = client.post("/api/new-thread").json()["thread_id"]
    assert client.post("/api/chat", json={"thread_id": tid, "message": "  "}).status_code == 400
    assert client.post("/api/chat", json={"thread_id": tid, "message": "x" * 1001}).status_code == 400


def test_rate_limit(client, monkeypatch):
    monkeypatch.setattr(gat_app, "RATE_MAX_PER_WINDOW", 2)
    monkeypatch.setattr(gat_app, "_client", lambda: FakeOpenAI([_msg("ok")] * 5))
    tid = client.post("/api/new-thread").json()["thread_id"]
    codes = [client.post("/api/chat", json={"thread_id": tid, "message": "hi"}).status_code for _ in range(3)]
    assert codes == [200, 200, 429]
