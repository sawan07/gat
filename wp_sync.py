"""
Records each donation in the GAT WordPress site (Donations admin), so
donations made through this assistant appear in the same list as those made
on the website's Donate page. Stripe's webhook to WordPress then marks them
Paid / Refunded / Cancelled using the payment link id.

Set in Coolify (values are shown in WordPress → Settings → GAT Settings):
    GAT_WP_URL     e.g. https://gat-site.amverio.co.uk
    GAT_WP_SECRET  the "GAT_WP_SECRET" value from that settings page

If they're not set, or WordPress can't be reached, the chat carries on as
normal; the failure is only logged.
"""

import json
import logging
import os
import urllib.request

log = logging.getLogger("gat.wp_sync")


def wp_enabled() -> bool:
    return bool(os.environ.get("GAT_WP_URL", "").strip() and os.environ.get("GAT_WP_SECRET", "").strip())


def record_donation(donation: dict) -> bool:
    if not wp_enabled():
        return False
    url = os.environ["GAT_WP_URL"].strip().rstrip("/") + "/wp-json/gat/v1/assistant-donation"
    payload = {
        "donation_id": donation["id"],
        "amount": donation["amount"],
        "frequency": donation["frequency"],
        "campaign_name": donation["campaign_name"],
        "donor_name": donation["donor_name"],
        "donor_email": donation["donor_email"],
        "anonymous": donation.get("anonymous", False),
        "gift_aid": donation.get("gift_aid", False),
        "dedication_type": donation.get("dedication_type", "none"),
        "dedication_name": donation.get("dedication_name", ""),
        "message": donation.get("message", ""),
        "payment_link_id": donation.get("payment_link_id") or "",
    }
    req = urllib.request.Request(
        url,
        data=json.dumps(payload).encode("utf-8"),
        headers={
            "Content-Type": "application/json",
            "X-GAT-Secret": os.environ["GAT_WP_SECRET"].strip(),
            "User-Agent": "GAT-Donation-Assistant",
        },
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=8) as resp:
            body = json.loads(resp.read().decode("utf-8") or "{}")
        log.info("Donation %s recorded in WordPress as %s", donation["id"], body.get("ref"))
        return True
    except Exception as e:
        log.warning("Could not record donation %s in WordPress: %s", donation["id"], e)
        return False
