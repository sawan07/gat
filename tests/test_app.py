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
import mailer
import payments
import tools


@pytest.fixture(autouse=True)
def no_stripe(monkeypatch):
    for k in ("STRIPE_SECRET_KEY", "BREVO_API_KEY", "BREVO_SENDER_EMAIL", "SMTP_USER", "SMTP_PASSWORD"):
        monkeypatch.delenv(k, raising=False)
    mailer._sent.clear()


def test_email_not_configured_is_not_promised():
    out = tools.create_donation(amount=10, campaign_id="GENERAL", donor_name="A", donor_email="a@example.com")
    assert out["email_sent"] is False and "Do not tell the donor" in out["email_note"]


def test_email_sent_via_brevo_api(monkeypatch, stripe_calls):
    monkeypatch.setenv("BREVO_API_KEY", "xkeysib-fake")
    monkeypatch.setenv("BREVO_SENDER_EMAIL", "donations@amverio.co.uk")
    sent = []

    class Resp:
        def __enter__(self): return self
        def __exit__(self, *a): return False

    def fake_urlopen(req, timeout=None):
        sent.append(json.loads(req.data))
        return Resp()

    monkeypatch.setattr(mailer.urllib.request, "urlopen", fake_urlopen)
    out = tools.create_donation(amount=50, campaign_id="EDUCATION", donor_name="Hasan",
                                donor_email="h@example.com", gift_aid=True)
    assert out["email_sent"] is True
    msg = sent[0]
    assert msg["to"][0]["email"] == "h@example.com"
    assert msg["sender"] == {"name": "Global Alliance Trust", "email": "donations@amverio.co.uk"}
    assert "https://buy.stripe.com/test_abc" in msg["htmlContent"] and "+£12.50" in msg["textContent"]
    # throttled after 3 per hour
    for _ in range(3):
        tools.create_donation(amount=5, campaign_id="GENERAL", donor_name="H", donor_email="h@example.com")
    assert len(sent) == 3


@pytest.fixture
def stripe_calls(monkeypatch):
    """Fake Stripe API: records calls, returns a price then a payment link."""
    monkeypatch.setenv("STRIPE_SECRET_KEY", "sk_test_fake")
    calls = []

    def fake_post(path, fields):
        calls.append((path, dict(fields)))
        if path == "prices":
            return {"id": "price_123"}
        return {"url": "https://buy.stripe.com/test_abc"}

    monkeypatch.setattr(payments, "_post", fake_post)
    return calls


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


def test_no_stripe_key_means_no_link():
    out = tools.create_donation(amount=10, campaign_id="GENERAL", donor_name="A", donor_email="a@example.com")
    assert out["success"] and out["payment_link"] is None
    assert "Do not invent a link" in out["payment"]


def test_one_off_payment_link(stripe_calls):
    out = tools.create_donation(amount=25.5, campaign_id="EDUCATION", donor_name="A",
                                donor_email="a@example.com", dedication_type="in_memory_of",
                                dedication_name="My Grandmother")
    assert out["payment_link"] == "https://buy.stripe.com/test_abc"
    (p_path, price), (l_path, link) = stripe_calls
    assert p_path == "prices" and price["unit_amount"] == "2550" and "recurring[interval]" not in price
    assert "In memory of My Grandmother" in price["product_data[name]"]
    assert l_path == "payment_links" and link["line_items[0][price]"] == "price_123"
    assert link["restrictions[completed_sessions][limit]"] == "1"
    assert link["after_completion[redirect][url]"].endswith(f"/thank-you?donation={out['id']}")
    assert link["submit_type"] == "donate"
    assert link["payment_intent_data[metadata][donation_id]"] == out["id"]


def test_monthly_payment_link(stripe_calls):
    out = tools.create_donation(amount=10, campaign_id="YOUTH", donor_name="A",
                                donor_email="a@example.com", frequency="monthly", gift_aid=True)
    (_, price), (_, link) = stripe_calls
    assert price["recurring[interval]"] == "month"
    assert "submit_type" not in link and "payment_intent_data[metadata][donation_id]" not in link
    assert link["subscription_data[metadata][gift_aid]"] == "yes"
    assert out["payment_link"].startswith("https://buy.stripe.com/")


def test_stripe_failure_falls_back(monkeypatch):
    monkeypatch.setenv("STRIPE_SECRET_KEY", "sk_test_fake")
    def boom(path, fields):
        raise OSError("network down")
    monkeypatch.setattr(payments, "_post", boom)
    out = tools.create_donation(amount=10, campaign_id="GENERAL", donor_name="A", donor_email="a@example.com")
    assert out["success"] and out["payment_link"] is None


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
    assert client.get("/healthz").json()["ok"] is True
    assert "Thank you" in client.get("/thank-you?donation=D0001").text
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


def test_chat_returns_payment_for_pay_button(client, monkeypatch, stripe_calls):
    args = json.dumps(dict(amount=20, campaign_id="COMMUNITY", donor_name="A", donor_email="a@example.com"))
    call = SimpleNamespace(id="c1", function=SimpleNamespace(name="create_donation", arguments=args))
    fake = FakeOpenAI([_msg(tool_calls=[call]), _msg("Here's your link.")])
    monkeypatch.setattr(gat_app, "_client", lambda: fake)
    tid = client.post("/api/new-thread").json()["thread_id"]
    body = client.post("/api/chat", json={"thread_id": tid, "message": "yes, confirm"}).json()
    assert body["payment"] == {"url": "https://buy.stripe.com/test_abc", "amount": 20.0,
                               "frequency": "one_off", "campaign_name": "Community Services"}


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
