"""Extracts section-page and article content from the static reference site.

Run: python3 -I web/modules/custom/mtnma_site/scripts/extract_reference.py <unzipped mtnma-mauritanie dir>

Writes content/reference.json (page leads, related pages, listings; article
categories, sources, consultation dates, card summaries and paragraphs) and content/pages/<slug>.<lang>.html (the
page body below the banner). seed.php imports both. Links between reference
pages become language-neutral paths ("/minister", "/news/cable") that the
"Language-aware internal links" filter prefixes at render time; image paths
keep the "assets/" prefix and seed.php points them at the managed files.
"""
import html
import json
import pathlib
import re
import sys

PAGES = ['about', 'minister', 'organization', 'agencies', 'laws', 'services', 'strategies', 'studies',
         'indicators', 'media', 'gallery', 'projects', 'innovation', 'tenders', 'contact', 'links']
ARTICLES = ['address', 'standards', 'wfp', 'cable', 'university', 'traffic', 'rimer']
# Pages whose cards/grid are replaced by a Drupal listing (see the mtnma_* views).
LISTINGS = {'media': 'news', 'services': 'services', 'gallery': 'gallery'}
LISTING_MARKUP = {
    'news': [r'<div class="filters">.*?</div>\n', r'<p class="results-count"[^>]*>\s*</p>\n', r'<div class="news-grid" id="news-results">.*?</article>\n</div>\n'],
    'services': [r'<div class="filters"[^>]*>.*?</div>\n', r'<p class="results-count"[^>]*>\s*</p>\n', r'<div class="services-grid" id="service-results">.*?</article>\n</div>\n'],
    'gallery': [r'<div class="photo-grid">.*?</figure>\n</div>\n'],
}
CATEGORIES = {'أخبار الوزارة': 'news', 'الفعاليات': 'events', 'Actualités du Ministère': 'news', 'Événements': 'events'}
MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre']


def main_content(path):
    text = path.read_text(encoding='utf-8')
    return re.search(r'<main[^>]*>\n?(.*)</main>', text, re.S).group(1)


def neutral_links(markup):
    markup = re.sub(r'href="(?:\.\./)?index\.html"', 'href="/"', markup)
    markup = re.sub(r'href="(?:\.\./)?article\.html"', 'href="/news/rimer"', markup)
    markup = re.sub(r'href="(?:\.\./)?article-([\w-]+)\.html"', r'href="/news/\1"', markup)
    markup = re.sub(r'href="(?:\.\./)?([\w-]+)\.html"', r'href="/\1"', markup)
    return re.sub(r'src="(?:\.\./)?assets/', 'src="assets/', markup)


def text(fragment):
    return html.unescape(re.sub(r'<[^>]+>', '', fragment)).strip()


def page(slug, langcode, root):
    main = main_content(root / (f'fr/{slug}.html' if langcode == 'fr' else f'{slug}.html'))
    banner = re.search(r'<section class="page-banner">.*?</section>\n', main, re.S)
    lead = re.search(r'<p>(.*?)</p>', banner.group(0), re.S)
    subnav = re.search(r'<nav class="subnav".*?</nav>\n', main, re.S)
    related = re.findall(r'href="([\w-]+)\.html"', subnav.group(0)) if subnav else []
    body = main[banner.end():]
    if subnav:
        body = body.replace(subnav.group(0), '', 1)
    listing = LISTINGS.get(slug)
    if listing:
        for pattern in LISTING_MARKUP[listing]:
            body, count = re.subn(pattern, '', body, count=1, flags=re.S)
            assert count == 1, (slug, langcode, pattern)
        # The page template supplies the section and wrapper around the listing.
        body = re.sub(r'^<section class="content-section[^"]*">\n<div class="wrap">\n(.*)</div>\n</section>\n?$', r'\1', body.strip() + '\n', flags=re.S)
    return {'lead': text(lead.group(1)) if lead else '', 'related': related, 'listing': listing, 'body': neutral_links(body.strip()) + '\n'}


def article(slug, langcode, root):
    main = main_content(root / (f'fr/article-{slug}.html' if langcode == 'fr' else f'article-{slug}.html'))
    meta = re.search(r'<div class="article-meta">(.*?)</div>', main, re.S).group(1)
    date = re.search(r'datetime="([\d-]+)"', meta)
    paragraphs = re.findall(r'<p>(.*?)</p>', main[main.index('article-meta'):main.index('article-actions')], re.S)
    source = re.search(r'<div class="article-actions">\s*<a class="btn" href="([^"]+)"', main)
    # "· consulté le 22 octobre 2026." after the source link (French pages).
    checked = re.search(r'consulté le (\d+) (\w+) (\d{4})', main)
    return {
        'title': text(re.search(r'<h1>(.*?)</h1>', main, re.S).group(1)),
        'date': date.group(1) if date else None,
        'category': CATEGORIES[text(re.search(r'<span>(.*?)</span>', meta).group(1))],
        'source': html.unescape(source.group(1)) if source else None,
        'paragraphs': [text(p) for p in paragraphs],
        'checked': f'{checked[3]}-{MONTHS.index(checked[2]) + 1:02d}-{int(checked[1]):02d}' if checked else None,
    }


def card_summaries(langcode, root):
    """Returns the Media Centre card text of each article, keyed by slug."""
    media = main_content(root / ('fr/media.html' if langcode == 'fr' else 'media.html'))
    cards = re.findall(r'<article class="news-card".*?href="(?:\.\./)?article-([\w-]+)\.html".*?<p>(.*?)</p>', media, re.S)
    return {slug: text(summary) for slug, summary in cards}


root = pathlib.Path(sys.argv[1])
out = pathlib.Path(__file__).resolve().parent.parent / 'content'
(out / 'pages').mkdir(parents=True, exist_ok=True)
data = {'pages': {}, 'articles': {}}
for slug in PAGES:
    entry = {}
    for langcode in ('ar', 'fr'):
        values = page(slug, langcode, root)
        (out / 'pages' / f'{slug}.{langcode}.html').write_text(values.pop('body'), encoding='utf-8')
        entry['related'], entry['listing'] = values.pop('related'), values.pop('listing')
        entry.setdefault('lead', {})[langcode] = values['lead']
    data['pages'][slug] = entry
summaries = {langcode: card_summaries(langcode, root) for langcode in ('ar', 'fr')}
for slug in ARTICLES:
    ar, fr = article(slug, 'ar', root), article(slug, 'fr', root)
    ar['summary'], fr['summary'] = summaries['ar'].get(slug), summaries['fr'].get(slug)
    data['articles'][slug] = {'date': ar['date'], 'category': ar['category'], 'checked': fr.pop('checked'), 'ar': ar, 'fr': fr}
    ar.pop('checked')
# Service categories, from the filter buttons of the services page.
services = main_content(root / 'services.html')
data['services'] = {text(title): category for category, title in re.findall(r'data-category="(\w+)">.*?<h3>(.*?)</h3>', services, re.S)}
(out / 'reference.json').write_text(json.dumps(data, ensure_ascii=False, indent=1) + '\n', encoding='utf-8')
print(f"{len(data['pages'])} pages, {len(data['articles'])} articles, {len(data['services'])} services written to {out}")
