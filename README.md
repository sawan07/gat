# Global Alliance Trust (GAT)

Web app for Global Alliance Trust, built and hosted by Amverio.

| Path | What it is |
|---|---|
| `/` | **GAT Donation Assistant**: AI chat that explains GAT's causes and takes donation pledges (amount, cause, one-off/monthly, Gift Aid, dedication, anonymity), inside the GAT site header and footer |
| `/concepts/` | Homepage design concepts preview (Online Soft) |
| `/healthz` | Health check for Coolify |

The assistant was moved here from the `amverio` repo, where it was the
"fundraising" demo domain (persona "Bright Horizons Trust", last present in
amverio commit `80d22ca`). It now runs on its own, with no domain registry.

## Files

```
app.py              FastAPI server: chat API, rate limiting, static pages
data.py             GAT profile + causes (descriptions are placeholders)
tools.py            get_campaigns / create_donation + their schemas
system_prompt.md    Assistant instructions and guardrails
static/index.html   Donation assistant page (GAT header/footer design)
static/img/         GAT logo (cut from the design mock-up; replace with the real file)
static/concepts/    Homepage concepts preview + images
static/robots.txt   Disallow all (keeps it out of search engines)
tests/test_app.py   Offline tests (no API key needed)
```

## Run locally

```bash
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
export OPENAI_API_KEY=sk-...
uvicorn app:app --reload --port 8000
```

Open http://localhost:8000 for the assistant and http://localhost:8000/concepts/ for the concepts.

Tests: `pip install pytest && pytest -q`

## Environment variables

| Variable | Default | Meaning |
|---|---|---|
| `OPENAI_API_KEY` | (required) | OpenAI key |
| `GAT_MODEL` | `gpt-4.1` | Model used for the assistant |
| `GAT_GIFT_AID` | `1` | Set `0` if GAT isn't registered with HMRC for Gift Aid; the assistant then stops offering it |
| `GAT_RATE_PER_10MIN` | `30` | Messages allowed per visitor IP per 10 minutes |
| `GAT_RATE_PER_DAY` | `150` | Messages allowed per visitor IP per day |

## Deploy to gat.amverio.co.uk (Coolify on Hetzner)

Same setup as the main Amverio app.

1. **DNS**: wherever `amverio.co.uk` is managed, add an `A` record
   `gat` → the Hetzner server's IPv4 (the same IP `amverio.co.uk` points to;
   add an `AAAA` too if you use IPv6). Skip this if a wildcard
   `*.amverio.co.uk` record already points at the server.
2. **Push** the `gat-ai-agent` branch (or merge it into `main`) to `github.com/sawan07/gat`.
3. In Coolify: **Project → + New → Resource → Private Repository (GitHub App)**
   (or with deploy key), pick `sawan07/gat` and the branch.
4. **Build pack**: `Dockerfile`. **Ports exposes**: `8000`.
5. **Domains**: `https://gat.amverio.co.uk`. Coolify/Traefik issues the
   Let's Encrypt certificate once DNS resolves to the server.
6. **Environment variables**: add `OPENAI_API_KEY` (and any optional ones
   above). Never commit it.
7. **Health check** (optional): path `/healthz`, port `8000`. The image also
   has a Docker `HEALTHCHECK`.
8. **Deploy**, then check:
   - `https://gat.amverio.co.uk/healthz` → `{"ok": true, "agent": "gat"}`
   - `https://gat.amverio.co.uk/` loads the assistant and replies
   - `https://gat.amverio.co.uk/concepts/` shows the three concepts

## Before taking real donations

- Pledges are kept **in memory only** and nobody is notified, so the
  "secure payment link will be sent" promise isn't fulfilled yet. Next steps:
  store pledges (SQLite volume or Postgres) and email them to the GAT team
  and/or create a Stripe payment link, as the Amverio restaurant demo does.
- Replace the placeholder cause descriptions in `data.py` with GAT's wording.
- Confirm GAT's Gift Aid registration (or set `GAT_GIFT_AID=0`).
- Replace `static/img/gat-logo.png` with the real logo, fill the nav, social and
  legal links (`href="#"`) and the `CONTACT` details in `static/index.html`.
