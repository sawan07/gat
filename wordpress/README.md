# GAT website (WordPress)

The Global Alliance Trust website runs on WordPress on the Hetzner server
(Coolify project **Global Alliance Trust**, service `gat-wordpress`).

```
themes/gat/          Custom theme built from the approved design
plugins/gat-core/    Site features: advertisers, enquiry forms, staff roles,
                     simplified admin, starter content (Tools → GAT Setup)
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

- Sunnah Academy online enrolment + Stripe fee payment + Students admin (needs fee, classes, terms)
- Donations admin (Stripe webhook records donations from the Donate page and the chat assistant)
- Real domain, HTTPS, backups, launch checklist
