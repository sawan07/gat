"""
Donation confirmation emails via Brevo (same account and settings as the
Amverio app): Brevo REST API when BREVO_API_KEY is an xkeysib- key, else SMTP
relay with SMTP_USER/SMTP_PASSWORD. If neither is configured, or sending
fails, send_donation_email returns False and the assistant doesn't claim an
email was sent.

BREVO_SENDER_EMAIL must be an address on a sender/domain verified in Brevo.
"""

import html
import json
import logging
import os
import smtplib
import time
import urllib.request
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.utils import formataddr

log = logging.getLogger("gat.mailer")

MAX_PER_ADDRESS_PER_HOUR = 3
_sent: dict[str, list[float]] = {}


def _throttled(email: str) -> bool:
    key = email.strip().lower()
    now = time.time()
    recent = [t for t in _sent.get(key, []) if now - t < 3600]
    _sent[key] = recent
    if len(recent) >= MAX_PER_ADDRESS_PER_HOUR:
        return True
    recent.append(now)
    return False


def _sender():
    email = os.environ.get("BREVO_SENDER_EMAIL", "").strip()
    name = os.environ.get("GAT_EMAIL_FROM_NAME", "").strip() or "Global Alliance Trust"
    return name, email


def _render(d: dict, payment_link: str | None) -> tuple[str, str, str]:
    monthly = d["frequency"] == "monthly"
    amount = f"£{d['amount']:.2f}" + (" a month" if monthly else "")
    rows = [
        ("Reference", d["id"]),
        ("Cause", d["campaign_name"]),
        ("Amount", amount),
        ("Type", "Monthly gift" if monthly else "One-off gift"),
    ]
    if d.get("gift_aid"):
        rows.append(("Gift Aid", f"Yes (+£{d['gift_aid_bonus']:.2f} from HMRC)"))
    if d.get("dedication_type", "none") != "none" and d.get("dedication_name"):
        label = {"in_memory_of": "In memory of", "in_honor_of": "In honour of",
                 "on_behalf_of": "On behalf of"}.get(d["dedication_type"], "For")
        rows.append(("Dedication", f"{label} {d['dedication_name']}"))
    if d.get("anonymous"):
        rows.append(("Anonymous", "Yes, your name won't be shown publicly"))

    subject = f"Your donation to Global Alliance Trust ({d['id']})"
    e = html.escape
    rows_html = "".join(
        f"<tr><td style='padding:6px 12px;color:#5b6b80'>{e(k)}</td>"
        f"<td style='padding:6px 12px;text-align:right;font-weight:600'>{e(str(v))}</td></tr>"
        for k, v in rows
    )
    if payment_link:
        cta = (
            f"<p>To complete your donation, please use the secure Stripe payment page:</p>"
            f"<p style='text-align:center;margin:24px 0'><a href='{e(payment_link)}' "
            f"style='background:#1d7ce0;color:#fff;text-decoration:none;padding:12px 24px;"
            f"border-radius:999px;font-weight:700;display:inline-block'>"
            f"{'Set up monthly gift' if monthly else 'Pay ' + e(amount) + ' securely'}</a></p>"
            f"<p style='font-size:13px;color:#5b6b80;text-align:center'>Or copy this link:<br>"
            f"<a href='{e(payment_link)}' style='color:#1d7ce0'>{e(payment_link)}</a></p>"
        )
        cta_text = f"Complete your donation securely with Stripe:\n{payment_link}\n"
    else:
        cta = "<p>A member of our team will be in touch shortly to complete your donation.</p>"
        cta_text = "A member of our team will be in touch shortly to complete your donation.\n"

    html_body = f"""<div style="font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;max-width:560px;margin:0 auto;color:#122540">
  <div style="background:#0b3a75;color:#fff;padding:20px 24px;border-radius:12px 12px 0 0">
    <div style="font-weight:800;letter-spacing:.5px">GLOBAL ALLIANCE TRUST</div>
    <div style="font-size:12px;opacity:.85">Non-Profit Organisation | Serving the Community</div>
  </div>
  <div style="border:1px solid #dde6f0;border-top:0;border-radius:0 0 12px 12px;padding:24px">
    <p>Dear {e(d['donor_name'])},</p>
    <p>Thank you for choosing to support Global Alliance Trust. Here are the details of your donation:</p>
    <table style="width:100%;border-collapse:collapse;background:#f3f7fc;border-radius:8px;padding:12px">{rows_html}</table>
    {cta}
    <p style="font-size:12px;color:#5b6b80;border-top:1px solid #dde6f0;padding-top:14px;margin-top:24px">
      You're receiving this because you made a donation through the Global Alliance Trust website.
    </p>
  </div>
</div>"""
    text_body = (
        f"Dear {d['donor_name']},\n\nThank you for choosing to support Global Alliance Trust.\n\n"
        + "\n".join(f"{k}: {v}" for k, v in rows)
        + "\n\n" + cta_text + "\nGlobal Alliance Trust - Serving the Community\n"
    )
    return subject, html_body, text_body


def send_donation_email(donation: dict, payment_link: str | None) -> bool:
    to_email = donation["donor_email"]
    sender_name, sender_email = _sender()
    api_key = os.environ.get("BREVO_API_KEY", "").strip()
    smtp_user = os.environ.get("SMTP_USER", "").strip()
    smtp_pass = os.environ.get("SMTP_PASSWORD", "").strip()

    if not sender_email or not ((api_key.startswith("xkeysib")) or (smtp_user and smtp_pass)):
        log.info("Email not configured; skipping donation email for %s", donation["id"])
        return False
    if _throttled(to_email):
        log.warning("Donation email throttled for %s", donation["id"])
        return False

    subject, html_body, text_body = _render(donation, payment_link)

    if api_key.startswith("xkeysib"):
        try:
            payload = {
                "sender": {"name": sender_name, "email": sender_email},
                "to": [{"email": to_email, "name": donation["donor_name"]}],
                "subject": subject,
                "htmlContent": html_body,
                "textContent": text_body,
            }
            req = urllib.request.Request(
                "https://api.brevo.com/v3/smtp/email",
                data=json.dumps(payload).encode("utf-8"),
                headers={"api-key": api_key, "Content-Type": "application/json", "Accept": "application/json"},
                method="POST",
            )
            with urllib.request.urlopen(req, timeout=10):
                pass
            log.info("Donation email sent via Brevo API for %s", donation["id"])
            return True
        except Exception as e:
            detail = ""
            if hasattr(e, "read"):
                try:
                    detail = e.read().decode("utf-8")[:300]
                except Exception:
                    pass
            log.warning("Brevo API send failed for %s: %s %s", donation["id"], e, detail)

    if smtp_user and smtp_pass:
        try:
            host = os.environ.get("SMTP_HOST", "smtp-relay.brevo.com").strip()
            port_s = os.environ.get("SMTP_PORT", "587").strip()
            port = int(port_s) if port_s.isdigit() else 587
            msg = MIMEMultipart("alternative")
            msg["Subject"] = subject
            msg["From"] = formataddr((sender_name, sender_email))
            msg["To"] = formataddr((donation["donor_name"], to_email))
            msg.attach(MIMEText(text_body, "plain", "utf-8"))
            msg.attach(MIMEText(html_body, "html", "utf-8"))
            if port == 465:
                server = smtplib.SMTP_SSL(host, port, timeout=10)
            else:
                server = smtplib.SMTP(host, port, timeout=10)
                server.starttls()
            server.login(smtp_user, smtp_pass)
            server.sendmail(sender_email, [to_email], msg.as_string())
            server.quit()
            log.info("Donation email sent via SMTP for %s", donation["id"])
            return True
        except Exception as e:
            log.warning("SMTP send failed for %s: %s", donation["id"], e)

    return False
