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
| Header / footer   | `main` and `footer` menus (footer: top-level items are column titles). |
| Other text        | Theme interface strings, translated in `themes/custom/mtnma/translations/*.po`. |

Articles flagged *From the archive* show "From the Ministry archive" instead of
their date; give them an older created date so they sort after dated news.

## Commands

```sh
ddev drush pm:enable --yes mtnma_site
ddev drush php:script web/modules/custom/mtnma_site/scripts/seed.php

# After editing theme strings in scripts/build_translations.py:
python3 web/themes/custom/mtnma/scripts/build_translations.py
ddev drush locale:import ar /var/www/html/web/themes/custom/mtnma/translations/ar.po --type=customized --override=all
ddev drush locale:import fr /var/www/html/web/themes/custom/mtnma/translations/fr.po --type=customized --override=all
```

The seed script records what it created in state (`mtnma_site.seed`) and
never overwrites existing items, so it is safe to re-run.
