"""
Organisation profile and in-memory state for the Global Alliance Trust
donation assistant.

Moved here from the amverio repo, where it started life as the generic
"fundraising" demo domain (persona "Bright Horizons Trust"). It is now a
standalone app with one agent, so there is no domain registry any more.

The causes below follow the service areas on GAT's homepage concepts.
Descriptions are short placeholders: replace them with wording supplied by
Global Alliance Trust before this goes in front of real donors.
"""

import itertools

# ---------------------------------------------------------------------------
# Organisation identity
# ---------------------------------------------------------------------------

ORG = {
    "name": "Global Alliance Trust",
    "tagline": "Serving the Community",
    "mission": (
        "A non-profit organisation supporting individuals and families in the "
        "community through education, welfare, wellbeing and charitable services."
    ),
    "currency": "GBP",
}

# ---------------------------------------------------------------------------
# Causes a donor can give to.
# ---------------------------------------------------------------------------

CAMPAIGNS = [
    {
        "id": "COMMUNITY",
        "name": "Community Services",
        "description": "Food bank, clothing bank and volunteer-led support for local families.",
    },
    {
        "id": "EDUCATION",
        "name": "Sunnah Academy / Maktab",
        "description": "Education for children through the Sunnah Academy, Maktab and Madrasa.",
    },
    {
        "id": "YOUTH",
        "name": "Youth & Children's Services",
        "description": "Activities, tuition, engagement and development for children and young people.",
    },
    {
        "id": "ELDERLY",
        "name": "Elderly Services",
        "description": "Companionship, support and wellbeing for older members of the community.",
    },
    {
        "id": "WELLBEING",
        "name": "Counselling, Wellbeing & Advice",
        "description": "Counselling, women's services, and legal and advisory support.",
    },
    {
        "id": "GENERAL",
        "name": "General Fund",
        "description": "Unrestricted: goes wherever it's needed most across all our services.",
    },
]

CAMPAIGNS_BY_ID = {c["id"]: c for c in CAMPAIGNS}

# HMRC Gift Aid: a charity registered with HMRC can claim an extra 25p per
# £1 donated by a UK taxpayer. Set GAT_GIFT_AID=0 in the environment if GAT
# is not registered for Gift Aid, and the agent stops offering it.
GIFT_AID_RATE = 0.25

# ---------------------------------------------------------------------------
# In-memory state: reset whenever the process restarts or redeploys. Needs
# real storage (and a real payment provider) before taking live donations.
# ---------------------------------------------------------------------------

_donation_ids = itertools.count(1)

DONATIONS = []


def next_donation_id():
    return f"D{next(_donation_ids):04d}"
