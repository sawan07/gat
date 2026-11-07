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


# ---------------------------------------------------------------------------
# Approved information the assistant may share (get_gat_info). Taken from
# GAT's website content document. The assistant answers general questions
# ONLY from this text, so anything not here gets "please contact the team".
# Placeholders in the document (address, phone, fee, opening hours, names)
# are deliberately left out until GAT confirms them.
# ---------------------------------------------------------------------------

GAT_INFO = {
    "about": {
        "title": "About Global Alliance Trust",
        "text": (
            "Global Alliance Trust (GAT) is a non-profit organisation focused on community "
            "service, education, welfare and charitable initiatives. It aims to create "
            "opportunities for individuals and families to access support, take part in "
            "community activities and benefit from educational and social initiatives. "
            "Its approach is based on compassion, cooperation and a commitment to serving "
            "the community."
        ),
        "page": "about-us",
    },
    "vision_mission": {
        "title": "Vision, mission and values",
        "text": (
            "Vision: a stronger, more connected and supportive community where individuals "
            "and families have opportunities to learn, participate and thrive. "
            "Mission: to support the community through education, welfare, charitable "
            "activities and community engagement: encouraging participation and "
            "cooperation, promoting access to education, supporting initiatives that "
            "benefit individuals and families, encouraging wellbeing and social inclusion, "
            "creating opportunities for volunteering, and developing services that respond "
            "to community needs. "
            "Values: compassion, community, education, respect and cooperation."
        ),
        "page": "about-us",
    },
    "services": {
        "title": "Our services",
        "text": (
            "GAT's service areas: Community Services (food bank support, clothing bank "
            "initiatives, volunteering); Elderly Services (companionship, social "
            "interaction, reducing isolation); Women's Services (participation, health, "
            "wellbeing and educational activities); Youth Services (engagement, skills and "
            "personal development); Children's Services (tuition and educational "
            "activities); Education (Sunnah Academy / Maktab); Wellbeing and Counselling "
            "(information and referral to appropriate support); Legal and Advisory Services "
            "(information and signposting; any legal advice depends on the relevant "
            "provider); Religious Activities (religious education and community "
            "activities). Availability, age groups and arrangements depend on the "
            "programmes running: people should contact GAT for details."
        ),
        "page": "our-services",
    },
    "sunnah_academy": {
        "title": "Sunnah Academy / Maktab",
        "text": (
            "Sunnah Academy / Maktab is an educational programme within Global Alliance "
            "Trust's community offering. Parents and guardians can learn about the "
            "programme on the website, review the enrolment requirements and register "
            "their child online. For questions about classes, timetables, age groups or "
            "fees, parents should contact GAT."
        ),
        "page": "sunnah-academy",
    },
    "enrolment": {
        "title": "How to enrol at Sunnah Academy / Maktab",
        "text": (
            "Enrolment is done by the parent or guardian on the website's online "
            "enrolment form (not in this chat). Step 1: complete the student enrolment "
            "form (student details, parent/guardian details, programme or class, "
            "declarations). Step 2: review and submit. Step 3: pay the applicable "
            "enrolment fee securely online, where a fee applies. Step 4: receive "
            "confirmation of the application and payment. Step 5: the Academy reviews the "
            "application and contacts the parent or guardian about next steps. Payment "
            "does not automatically guarantee a place unless confirmed by the Academy's "
            "enrolment policy. The fee is shown on the website once confirmed."
        ),
        "page": "sunnah-academy/enrol",
    },
    "donating": {
        "title": "Donating",
        "text": (
            "People can donate on the website's Donate page (choose £10, £25, £50, £100 or "
            "another amount, one-off or monthly, and a cause), or right here in this chat. "
            "Payments are made on Stripe's secure page; card details are never taken in "
            "the chat. Monthly gifts are set up as a monthly payment through Stripe. "
            "Depending on the Trust's priorities and funding, donations may support "
            "community welfare, educational programmes, children and young people, older "
            "community members and charitable activities. Questions about receipts, "
            "refunds or changing a monthly gift: contact GAT."
        ),
        "page": "donate",
    },
    "volunteering": {
        "title": "Volunteering and other ways to help",
        "text": (
            "Besides donating, people can support GAT by sharing information about its "
            "work, volunteering where opportunities are available, or exploring partnership "
            "and advertising opportunities. To volunteer, get in touch through the Contact "
            "page."
        ),
        "page": "contact-us",
    },
    "advertising": {
        "title": "Advertising with GAT",
        "text": (
            "Businesses can advertise on GAT's website: their logo in the scrolling "
            "advertiser banner, optionally linked to their website, and featured in the "
            "advertiser showcase. Steps: enquire through the Advertising page, provide "
            "company name, logo and website, agree the package, duration and fee, then the "
            "advert is added after approval. Prices are confirmed by GAT on enquiry."
        ),
        "page": "advertising",
    },
    "news_events": {
        "title": "News and events",
        "text": (
            "GAT shares news, announcements, activities and upcoming events on its News & "
            "Events page. For details of a particular event, contact GAT."
        ),
        "page": "news-and-events",
    },
    "contact": {
        "title": "Contacting GAT",
        "text": (
            "Questions about services, the Sunnah Academy, donations, volunteering or "
            "advertising can be sent through the Contact page form on the website. The "
            "address, phone number, email and opening hours are shown on that page."
        ),
        "page": "contact-us",
    },
}
