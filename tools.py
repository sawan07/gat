"""
The GAT donation assistant's tools: small, predictable functions the model
can call, each returning a plain dict.
"""

import os
import re

from payments import create_payment_link
from data import (
    ORG, CAMPAIGNS, CAMPAIGNS_BY_ID, DONATIONS, GIFT_AID_RATE,
    next_donation_id,
)

_EMAIL_RE = re.compile(r"^[^@\s]+@[^@\s]+\.[^@\s]+$")

VALID_FREQUENCIES = ("one_off", "monthly")
VALID_DEDICATIONS = ("none", "in_memory_of", "in_honor_of", "on_behalf_of")


def gift_aid_enabled() -> bool:
    return os.environ.get("GAT_GIFT_AID", "1").strip().lower() not in ("0", "false", "no", "off")


def _is_valid_email(email: str) -> bool:
    """Format check only (not a deliverability check)."""
    if not email or not isinstance(email, str):
        return False
    return bool(_EMAIL_RE.match(email.strip()))


# ---------------------------------------------------------------------------
# Tools
# ---------------------------------------------------------------------------

def get_campaigns() -> dict:
    """List the causes a donor can give to."""
    return {
        "organisation": ORG["name"],
        "currency": ORG["currency"],
        "campaigns": [
            {"id": c["id"], "name": c["name"], "description": c["description"]}
            for c in CAMPAIGNS
        ],
    }


def create_donation(
    amount: float,
    campaign_id: str,
    donor_name: str,
    donor_email: str,
    frequency: str = "one_off",
    anonymous: bool = False,
    gift_aid: bool = False,
    dedication_type: str = "none",
    dedication_name: str = "",
    message: str = "",
) -> dict:
    """
    Records a donation pledge. No payment is taken here: it records intent,
    to be completed through a secure payment link afterwards.
    """
    try:
        amount = float(amount)
    except (TypeError, ValueError):
        return {"success": False, "reason": "Donation amount must be a number."}
    if amount <= 0:
        return {"success": False, "reason": "Donation amount must be greater than zero."}

    campaign = CAMPAIGNS_BY_ID.get((campaign_id or "").strip().upper())
    if not campaign:
        return {
            "success": False,
            "reason": f"Unknown campaign: {campaign_id}. Call get_campaigns for the current list.",
        }

    if frequency not in VALID_FREQUENCIES:
        return {"success": False, "reason": "frequency must be 'one_off' or 'monthly'."}

    if dedication_type not in VALID_DEDICATIONS:
        return {
            "success": False,
            "reason": "dedication_type must be one of: " + ", ".join(VALID_DEDICATIONS),
        }

    if dedication_type != "none" and not dedication_name:
        return {"success": False, "reason": "Please provide the name this donation is dedicated to."}

    if not donor_name:
        return {"success": False, "reason": "A name is needed to confirm the donation."}

    if not _is_valid_email(donor_email):
        return {
            "success": False,
            "reason": "That doesn't look like a valid email address. We need one to send a receipt.",
        }

    gift_aid = bool(gift_aid) and gift_aid_enabled()
    amount = round(amount, 2)
    gift_aid_bonus = round(amount * GIFT_AID_RATE, 2) if gift_aid else 0.0

    donation = {
        "id": next_donation_id(),
        "amount": amount,
        "frequency": frequency,
        "campaign_id": campaign["id"],
        "campaign_name": campaign["name"],
        "donor_name": donor_name,
        "donor_email": donor_email.strip(),
        "anonymous": bool(anonymous),
        "gift_aid": gift_aid,
        "gift_aid_bonus": gift_aid_bonus,
        "dedication_type": dedication_type,
        "dedication_name": dedication_name,
        "message": message,
        "status": "PLEDGED",
    }
    payment_link = create_payment_link(donation)
    donation["payment_link"] = payment_link
    if payment_link:
        donation["payment"] = (
            "Share payment_link with the donor exactly as given; the chat also shows a Pay button. "
            + ("It sets up the monthly gift." if frequency == "monthly" else "It is for this one-off gift.")
            + " The link works once."
        )
    else:
        donation["payment"] = (
            "No payment link is available right now. Tell the donor the team will be in "
            "touch by email to complete the donation. Do not invent a link."
        )
    DONATIONS.append(donation)
    return {"success": True, "donation_id": donation["id"], **donation}


# ---------------------------------------------------------------------------
# Tool schemas (provider-neutral; app.py converts them to OpenAI's format)
# ---------------------------------------------------------------------------

TOOL_SCHEMAS = [
    {
        "name": "get_campaigns",
        "description": "Get the list of causes a donor can give to at Global Alliance Trust.",
        "input_schema": {"type": "object", "properties": {}},
    },
    {
        "name": "create_donation",
        "description": (
            "Record a donation and create its secure Stripe payment link (payment_link "
            "in the result). Only call "
            "this once the donor has confirmed the amount, frequency, cause, Gift Aid "
            "choice, and any dedication."
        ),
        "input_schema": {
            "type": "object",
            "properties": {
                "amount": {"type": "number", "description": "Donation amount in GBP."},
                "campaign_id": {"type": "string"},
                "donor_name": {"type": "string"},
                "donor_email": {"type": "string"},
                "frequency": {"type": "string", "enum": ["one_off", "monthly"]},
                "anonymous": {
                    "type": "boolean",
                    "description": "True if the donor wants to stay anonymous publicly.",
                },
                "gift_aid": {
                    "type": "boolean",
                    "description": "True if the donor confirmed they're a UK taxpayer and wants Gift Aid claimed.",
                },
                "dedication_type": {
                    "type": "string",
                    "enum": ["none", "in_memory_of", "in_honor_of", "on_behalf_of"],
                },
                "dedication_name": {
                    "type": "string",
                    "description": "Name the donation is dedicated to, if dedication_type isn't 'none'.",
                },
                "message": {"type": "string", "description": "Optional personal message from the donor."},
            },
            "required": ["amount", "campaign_id", "donor_name", "donor_email"],
        },
    },
]

TOOL_FUNCTIONS = {
    "get_campaigns": lambda **kwargs: get_campaigns(),
    "create_donation": lambda **kwargs: create_donation(**kwargs),
}
