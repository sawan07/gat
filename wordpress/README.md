# GAT website (WordPress)

The Global Alliance Trust website runs on WordPress on the Hetzner server
(Coolify project **Global Alliance Trust**, service `gat-wordpress`).

```
themes/gat/          Custom theme built from the approved design
plugins/gat-core/    Site features: online donations + Sunnah Academy enrolment
                     (Stripe), Students and Donations admin, advertisers, enquiry
                     forms, staff roles, simplified admin, starter content
dist/                Ready-to-upload zips (not committed; rebuild with the commands below)
```

## Installing / updating on the server

1. wp-admin → **Appearance → Themes → Add New Theme → Upload Theme** → `dist/gat-theme.zip`
   → Install → Activate. (For an update, upload the new zip and choose
   **Replace current with uploaded**.)
2. wp-admin → **Plugins → Add New Plugin → Upload Plugin** → `dist/gat-core.zip`
   → Install → Activate (same "Replace" option for updates).
3. First time only: **Tools → GAT Setup → Set up the website**. Creates all
   pages from GAT's content document, menus and settings, plus sample
   news/advertisers. Before launch: **Remove sample content** on the same page.

Rebuild the zips after changing code:

```bash
cd wordpress/themes && zip -qr ../dist/gat-theme.zip gat
cd ../plugins && zip -qr ../dist/gat-core.zip gat-core
```

## Donations and enrolment (Stripe)

| Page | Shortcode | What it does |
|---|---|---|
| Donate | `[gat_donate_form]` | £10/£25/£50/£100/other, one-off or monthly, cause, optional dedication, Gift Aid (only when switched on) → Stripe Checkout |
| Donate → Thank You | `[gat_donation_result]` | Confirms with Stripe and thanks the donor |
| Sunnah Academy → Student Enrolment | `[gat_enrolment_form]` | Sections A–D from GAT's content document → "Continue to Payment" (Stripe) when a fee is set |
| … → Enrolment → Thank You | `[gat_enrolment_result]` | "Thank You for Your Application" |

Admin:
- **Students**: list with Student ID, name, parent/guardian, contact number, class,
  enrolment date, payment status (Paid/Pending/Refunded) and application status
  (Pending/Approved/Declined); search, filters, bulk approve/decline, view/edit,
  private staff notes, CSV download. Administrators + Academy Admins only.
- **Donations**: totals (this month, this year, active monthly donors, Gift Aid
  to claim), filters, CSV download, Gift Aid claim CSV (HMRC schedule columns).
  Administrators + Donations Viewers (view only).
- **Settings → GAT Settings** (administrators): Stripe keys, webhook secret,
  enrolment fee / classes / open-closed, donation amounts / causes / monthly /
  Gift Aid, notification emails, the donation assistant's shared secret.

Data lives in its own tables (`wp_gat_students`, `wp_gat_donations`), never in
posts, so it can't leak into search, feeds or the REST API. No card data is
ever stored: payments happen on Stripe's page.

### Stripe set-up (once, in GAT's Stripe account)

1. **Key**: Developers → API keys → create a restricted key with
   *Checkout Sessions: Write*, plus *Payment Links, Prices, Products: Write* (used by the donation
   assistant). Paste it in Settings → GAT Settings. Same key on the assistant.
2. **Webhook**: Developers → Webhooks → Add endpoint →
   `https://<site>/wp-json/gat/v1/stripe-webhook`, events
   `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
   `checkout.session.async_payment_failed`, `checkout.session.expired`,
   `charge.refunded`, `invoice.paid`, `customer.subscription.deleted`.
   Paste its signing secret (`whsec_…`) in GAT Settings.
3. Refunds and cancelling monthly gifts are done in Stripe; WordPress updates itself.

### Donation assistant link

In Coolify (`gat-ai`), set `GAT_WP_URL` (this site) and `GAT_WP_SECRET` (shown in
GAT Settings). Assistant donations then appear under Donations, marked "Donation assistant".

## Who edits what

| Where | What |
|---|---|
| Appearance → Customize → **Homepage** | Every word, photo and button on the homepage (live preview) |
| Appearance → Customize → **Contact & Social** | Address, phone, email, hours, map, social links (footer + Contact page) |
| Pages | All other pages (block editor). Subtitle under the page title = the page's Excerpt |
| News & Events | News posts: they feed the homepage ticker and "Latest News & Events" |
| Advertisers | Logo, website link, show from/until dates (expired adverts hide themselves) |
| Enquiries | Messages from the Contact and Advertising forms (also emailed) |
| Appearance → Menus | Header menu, footer quick links, footer legal links |

Editor block styles (Styles panel): Group → *White panel* / *Blue highlight*;
Paragraph → *Yellow note*; Columns → *Cards*; List → *Tick list* / *Numbered steps*.

Shortcodes: `[gat_contact]`, `[gat_enquiry_form type="contact|advertising"]`, `[gat_advertisers]`.

## Roles

| Role | For | Can |
|---|---|---|
| Administrator | Amverio | Everything |
| GAT Editor | GAT staff | Pages, news, advertisers, enquiries, menus, homepage |
| SEO Manager | SEO freelancer | Rank Math tools, edit titles/meta/content on pages and news. No plugins, users, enquiries, students, donations; can't delete |
| Academy Admin | Academy staff | Students only |
| Donations Viewer | Trustee/treasurer | Donations only |

Every user can switch their own admin language (e.g. Bengali) under Profile → Language.

## Recommended plugins (install from wp-admin)

- **Rank Math SEO**: SEO for the SEO Manager role
- **WP Mail SMTP**: send form/notification emails through Brevo (otherwise they may not arrive)
- **Two Factor**: 2-step login for admins

## Next

- Enter the confirmed enrolment fee and class list (Settings → GAT Settings)
- Legal pages (privacy, enrolment terms, donation terms) approved by GAT
- Real domain, HTTPS, backups, launch checklist
