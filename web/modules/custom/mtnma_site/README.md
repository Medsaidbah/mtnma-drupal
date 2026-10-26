# MTNIMA site

Content model and seed content for the Arabic/French homepage built by the
`mtnma` theme.

## What the homepage reads

| Section           | Source                                                                 |
|-------------------|------------------------------------------------------------------------|
| Hero carousel     | Published **sticky** articles, newest first (max 3). Uses *Hero image* and *Carousel title* when set. |
| Top news          | Published **promoted** articles, newest first: items 1–4.             |
| Media Centre      | The same list, items 4–6 (continues from the last "top news" row).    |
| Digital services  | Published promoted **Digital service** nodes, oldest first.           |
| "All news/services" links | The `/media` and `/services` section pages and their listings. |
| Header / footer   | `main` and `footer` menus (footer: top-level items are column titles). |
| Other text        | Theme interface strings, translated in `themes/custom/mtnma/translations/*.po`. |

Articles flagged *From the archive* show "From the Ministry archive" instead of
their date; give them an older created date so they sort after dated news.

## Section pages

The 16 pages in the menus (`/about`, `/minister`, ... `/links`) are *Basic
page* nodes in Arabic and French:

- **Banner lead** is the sentence under the title; **Related pages** is the bar
  under the banner (include the page itself to mark it as current).
- **Body** holds the replica's markup (cards, document lists, the contact
  form) in the *Page layout HTML* format. It has no editor and no role is
  granted it, so only administrators can change it. Links written as
  `href="/minister"` are given the page's language prefix when displayed.
- **Listing** shows a Drupal listing above the body: `news` (Media Centre),
  `services` or `gallery`.

The content comes from the reference site: `scripts/extract_reference.py`
writes `content/reference.json` and `content/pages/*.html`, and `seed.php`
copies them into fields that are still empty.

## Listings and search

| Listing | View | Filters (query string) |
|---------|------|------------------------|
| Media Centre | `mtnma_news` | `category=news\|events`, `keys=` |
| Digital services | `mtnma_services` | `category=citizens\|business\|education`, `keys=` |
| Photos | `mtnma_gallery` | – (articles that have an image) |

Article cards use the *Teaser* view mode, gallery tiles the *Photo* view mode.

The header search box submits to the core content search (`/ar/search/node`,
`/fr/search/node`), which lists results in the current language only and
matches Arabic words regardless of short vowels, alef/hamza spelling or an
attached article ("خدمات" finds "الخدمات"). The index is updated on cron
(`automated_cron`), or immediately with `ddev drush cron`.

## Languages

The public site is Arabic (default, RTL) and French (LTR). English is kept
only for the administration interface: public `/en/...` URLs redirect
permanently to the same page in French (`EnglishFrontendSubscriber`).

## Commands

```sh
ddev drush pm:enable --yes mtnma_site   # or, on an existing site: ddev drush updatedb --yes
ddev drush php:script web/modules/custom/mtnma_site/scripts/seed.php

# After changing the reference site (unzipped to a directory):
python3 -I web/modules/custom/mtnma_site/scripts/extract_reference.py /path/to/mtnma-mauritanie

# After editing theme strings in scripts/build_translations.py:
python3 web/themes/custom/mtnma/scripts/build_translations.py
ddev drush locale:import ar /var/www/html/web/themes/custom/mtnma/translations/ar.po --type=customized --override=all
ddev drush locale:import fr /var/www/html/web/themes/custom/mtnma/translations/fr.po --type=customized --override=all
```

The seed script records what it created in state (`mtnma_site.seed`) and
never overwrites existing items, so it is safe to re-run.
