# Global Alliance Trust (GAT)

Web app for Global Alliance Trust, built and hosted by Amverio.
Live at **https://gat.amverio.co.uk** (Coolify app `gat-ai`, project Amverio → production).

| Path | What it is |
|---|---|
| `/` | **GAT Donation Assistant**: AI chat that explains GAT's causes and takes donations (amount, cause, one-off/monthly, Gift Aid, dedication, anonymity), then gives the donor a Stripe payment link in the chat, inside the GAT site header and footer |
| `/thank-you` | Where Stripe sends donors after paying |
| `/concepts/` | Design previews for GAT (Online Soft) |
| `/healthz` | Health check; also shows whether Stripe is configured |

The assistant was moved here from the `amverio` repo, where it was the
"fundraising" demo domain (persona "Bright Horizons Trust", last present in
amverio commit `80d22ca`). It now runs on its own, with no domain registry.

## Files

```
app.py               FastAPI server: chat API, rate limiting, static pages
data.py              GAT profile + causes (descriptions are placeholders)
tools.py             get_campaigns / create_donation + their schemas
payments.py          Stripe Payment Links for one-off and monthly donations
system_prompt.md     Assistant instructions and guardrails
static/index.html    Donation assistant page (GAT header/footer design)
static/thank-you.html  Post-payment page
static/img/          GAT logo (cut from the design mock-up; replace with the real file)
static/concepts/     Design previews + images
static/robots.txt    Disallow all (keeps it out of search engines)
tests/test_app.py    Offline tests (no API keys needed; OpenAI and Stripe are faked)
```

## How payment works

When the donor confirms, `create_donation` calls Stripe (`payments.py`):

1. creates a **Price** for the exact amount: one-off, or **recurring monthly** for monthly gifts;
2. creates a **Payment Link** for it, usable once, with the donation details
   (id, cause, Gift Aid, donor name/email) as metadata on the link and on the
   resulting payment or subscription, redirecting to `/thank-you` afterwards.

The chat shows the link in the assistant's reply **and** as a Pay button
(sent separately by the API, so it works even if the model mangles the URL).
No card details ever pass through this app. If `STRIPE_SECRET_KEY` isn't set,
or Stripe errors, no link is shown and the assistant says the team will be in
touch.

**The key decides who gets the money**: `STRIPE_SECRET_KEY` must be Global
Alliance Trust's own Stripe account. Use an `sk_test_...` key while testing
(pay with card `4242 4242 4242 4242`), then switch to `sk_live_...`.

## Run locally

```bash
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
export OPENAI_API_KEY=sk-...
export STRIPE_SECRET_KEY=sk_test_...    # optional
export GAT_PUBLIC_URL=http://localhost:8000
uvicorn app:app --reload --port 8000
```

Tests: `pip install pytest && pytest -q`

## Environment variables

| Variable | Default | Meaning |
|---|---|---|
| `OPENAI_API_KEY` | (required) | OpenAI key |
| `STRIPE_SECRET_KEY` | (unset) | GAT's Stripe secret key. Unset = no payment links |
| `GAT_PUBLIC_URL` | `https://gat.amverio.co.uk` | Base URL Stripe redirects back to |
| `GAT_MODEL` | `gpt-4.1` | Model used for the assistant |
| `GAT_GIFT_AID` | `1` | Set `0` if GAT isn't registered with HMRC for Gift Aid; the assistant then stops offering it |
| `GAT_RATE_PER_10MIN` | `30` | Messages allowed per visitor IP per 10 minutes |
| `GAT_RATE_PER_DAY` | `150` | Messages allowed per visitor IP per day |

## Deployment (Coolify on Hetzner)

Set up already; pushing to `main` redeploys. For reference:

- DNS: Cloudflare `A` record `gat` → `159.69.31.187` (proxied; SSL mode Full).
- Coolify app `gat-ai`: GitHub App `sawan07-personal`, repo `sawan07/gat`,
  branch `main`, build pack Dockerfile, port `8000`, domain `https://gat.amverio.co.uk`.
- Coolify's own health check is **off**: it runs `curl` inside the container,
  which the slim Python image doesn't have. The Dockerfile's `HEALTHCHECK`
  (Python-based) is used instead.
- Environment variables are set in Coolify, never committed.

Check after a deploy: `/healthz` → `{"ok": true, "agent": "gat", "payments": true}`.

## Still to do before real donations

- Use GAT's own **live** Stripe key, and turn on email receipts in Stripe
  (Settings → Customer emails).
- Donation records live in memory only; Stripe is the record of payments.
  For a donor list / Gift Aid claims, add a Stripe webhook + database, or
  export from the Stripe dashboard (metadata holds the Gift Aid flag).
- HMRC needs a proper Gift Aid declaration from each donor; the chat
  question alone may not be enough. Check with GAT's accountant.
- Replace the placeholder cause descriptions in `data.py` with GAT's wording.
- Replace `static/img/gat-logo.png` with the real logo, fill the nav, social and
  legal links (`href="#"`) and the `CONTACT` details in `static/index.html`.
