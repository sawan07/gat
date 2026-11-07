"""
Stripe payment links for donations.

One link per pledge, created with the Stripe REST API (no SDK needed):
  1. a Price for the exact amount (recurring monthly for monthly gifts),
  2. a Payment Link for that price, usable once, that redirects to
     /thank-you on this site after payment.

Payment Links give short https://buy.stripe.com/... URLs, which survive being
repeated by the model and pasted into messages, unlike Checkout Session URLs.

STRIPE_SECRET_KEY decides which Stripe account receives the money: it must be
Global Alliance Trust's own account (sk_test_... while testing, sk_live_... for
real donations). With no key set, no link is created and the assistant tells
the donor the team will follow up instead; it never shows a fake link.
"""

import json
import logging
import os
import urllib.parse
import urllib.request

log = logging.getLogger("gat.payments")

STRIPE_API = "https://api.stripe.com/v1"


def stripe_enabled() -> bool:
    return bool(os.environ.get("STRIPE_SECRET_KEY", "").strip())


def public_url() -> str:
    return os.environ.get("GAT_PUBLIC_URL", "https://gat.amverio.co.uk").strip().rstrip("/")


def _post(path: str, fields: list) -> dict:
    req = urllib.request.Request(
        f"{STRIPE_API}/{path}",
        data=urllib.parse.urlencode(fields).encode("utf-8"),
        headers={"Authorization": f"Bearer {os.environ['STRIPE_SECRET_KEY'].strip()}"},
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=15) as resp:
        return json.loads(resp.read().decode("utf-8"))


def create_payment_link(donation: dict) -> str | None:
    """
    Returns a Stripe Payment Link URL for this donation, or None if Stripe
    isn't configured or the API call fails (the caller then falls back to
    "the team will be in touch").
    """
    if not stripe_enabled():
        return None

    donation_id = donation["id"]
    monthly = donation["frequency"] == "monthly"
    product_name = f"Donation to Global Alliance Trust: {donation['campaign_name']}"
    if donation.get("dedication_type", "none") != "none" and donation.get("dedication_name"):
        label = {"in_memory_of": "In memory of", "in_honor_of": "In honour of",
                 "on_behalf_of": "On behalf of"}.get(donation["dedication_type"], "For")
        product_name += f" ({label} {donation['dedication_name']})"

    metadata = {
        "donation_id": donation_id,
        "campaign_id": donation["campaign_id"],
        "frequency": donation["frequency"],
        "gift_aid": "yes" if donation.get("gift_aid") else "no",
        "anonymous": "yes" if donation.get("anonymous") else "no",
        "donor_name": donation.get("donor_name", "")[:500],
        "donor_email": donation.get("donor_email", "")[:500],
    }

    try:
        price_fields = [
            ("currency", "gbp"),
            ("unit_amount", str(int(round(donation["amount"] * 100)))),
            ("product_data[name]", product_name[:250]),
        ]
        if monthly:
            price_fields.append(("recurring[interval]", "month"))
        price = _post("prices", price_fields)

        link_fields = [
            ("line_items[0][price]", price["id"]),
            ("line_items[0][quantity]", "1"),
            ("restrictions[completed_sessions][limit]", "1"),
            ("after_completion[type]", "redirect"),
            ("after_completion[redirect][url]",
             f"{public_url()}/thank-you?donation={donation_id}"),
            ("submit_type", "donate") if not monthly else ("billing_address_collection", "auto"),
        ]
        for k, v in metadata.items():
            link_fields.append((f"metadata[{k}]", v))
            if monthly:
                link_fields.append((f"subscription_data[metadata][{k}]", v))
            else:
                link_fields.append((f"payment_intent_data[metadata][{k}]", v))

        link = _post("payment_links", link_fields)
        url = link.get("url")
        # The id lets WordPress match Stripe's payment back to this donation.
        donation["payment_link_id"] = link.get("id", "")
        if url:
            log.info("Stripe payment link created for donation %s (%s)", donation_id, donation["frequency"])
            return url
        log.warning("Stripe returned no URL for donation %s", donation_id)
    except Exception as e:
        detail = ""
        if hasattr(e, "read"):
            try:
                detail = e.read().decode("utf-8")[:500]
            except Exception:
                pass
        log.warning("Stripe payment link failed for donation %s: %s %s", donation_id, e, detail)
    return None
