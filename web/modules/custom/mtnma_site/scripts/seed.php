<?php

/**
 * @file
 * Seeds the Arabic/French site content taken from the static replicas.
 *
 * Section page bodies, article texts, summaries, source dates and categories
 * come from content/reference.json and content/pages/*.html (see
 * scripts/extract_reference.py). They are only written into fields that are
 * still empty, and each item is done once.
 *
 * Usage: ddev drush php:script web/modules/custom/mtnma_site/scripts/seed.php
 *
 * On a new site installed from config/sync (scripts/setup-site.sh), this
 * creates all content: the front page, the 16 section pages, news, digital
 * services, menu links and the managed image files (copied from the theme).
 *
 * Safe to re-run: every item is keyed in state (mtnma_site.seed) and skipped
 * once created, so editor changes made in the UI are never overwritten.
 */

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\File\FileExists;
use Drupal\file\Entity\File;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;

\Drupal::moduleHandler()->loadInclude('mtnma_site', 'install');
mtnma_site_ensure_content_model();

$state = \Drupal::state();
$seeded = $state->get('mtnma_site.seed', []);
$remember = function (string $key, int $id) use (&$seeded, $state) {
  $seeded[$key] = $id;
  $state->set('mtnma_site.seed', $seeded);
};

// Site name per language; it ends every page title ("Home | Ministry").
\Drupal::configFactory()->getEditable('system.site')
  ->set('name', 'وزارة التحول الرقمي وعصرنة الإدارة')
  ->save();
\Drupal::languageManager()->getLanguageConfigOverride('fr', 'system.site')
  ->set('name', 'Ministère de la Transformation Numérique et de la Modernisation de l’Administration')
  ->save();

/**
 * Returns a permanent managed file copied from the theme images directory.
 */
$image = function (string $name) use (&$seeded, $remember): File {
  $key = "file:$name";
  if (isset($seeded[$key]) && ($file = File::load($seeded[$key]))) {
    return $file;
  }
  $fs = \Drupal::service('file_system');
  $directory = 'public://mtnma';
  $fs->prepareDirectory($directory, 1);
  $source = \Drupal::service('extension.list.theme')->getPath('mtnma') . '/images/' . $name;
  $uri = $fs->copy($source, "$directory/$name", FileExists::Replace);
  $file = File::create(['uri' => $uri, 'status' => 1, 'uid' => 1]);
  $file->save();
  $remember($key, (int) $file->id());
  return $file;
};

/**
 * Creates an Arabic node with a French translation, unless already seeded.
 */
$node = function (string $key, array $shared, array $ar, array $fr) use (&$seeded, $remember): Node {
  if (isset($seeded["node:$key"]) && ($existing = Node::load($seeded["node:$key"]))) {
    return $existing;
  }
  $node = Node::create($shared + $ar + ['langcode' => 'ar', 'uid' => 1, 'status' => 1]);
  $node->save();
  // Translatable base fields (promote, sticky, created) are not copied by
  // addTranslation(), so the French translation gets the same values.
  $copied = array_intersect_key($shared, array_flip(['promote', 'sticky', 'created']));
  $node->addTranslation('fr', $fr + $copied + ['status' => 1, 'uid' => 1, 'created' => $node->getCreatedTime()])->save();
  $remember("node:$key", (int) $node->id());
  return $node;
};

// Front page node, rendered by node--front.html.twig. Adopt the page the site
// already uses as its front page. A new site installed from config/sync has
// "/node/2" configured but no nodes yet: the front page is created with that
// ID, so the configuration stays as exported (and the other nodes get the
// same IDs as on the original site).
$site = \Drupal::configFactory()->getEditable('system.site');
$front = isset($seeded['node:front']) ? Node::load($seeded['node:front']) : NULL;
$front_id = preg_match('#^/node/(\d+)$#', (string) $site->get('page.front'), $match) ? (int) $match[1] : NULL;
if (!$front && $front_id) {
  $front = Node::load($front_id);
}
if (!$front) {
  $values = ['type' => 'page', 'langcode' => 'ar', 'title' => 'الرئيسية', 'uid' => 1, 'status' => 1, 'promote' => 0];
  $front = Node::create($values + ($front_id ? ['nid' => $front_id] : []));
  $front->enforceIsNew()->save();
}
if (!$front->hasTranslation('fr')) {
  $front->addTranslation('fr', ['title' => 'Accueil', 'status' => 1])->save();
}
if ($site->get('page.front') !== '/node/' . $front->id()) {
  $site->set('page.front', '/node/' . $front->id())->save();
}
$remember('node:front', (int) $front->id());

// Section pages that the menus link to; their content is added below.
$pages = [
  'about' => ['عن الوزارة', 'Le Ministère'],
  'minister' => ['عن معالي الوزير', 'Le Ministre'],
  'organization' => ['الهيكل التنظيمي', 'Organigramme'],
  'agencies' => ['الهيئات تحت الوصاية', 'Établissements sous tutelle'],
  'laws' => ['النصوص القانونية', 'Textes juridiques'],
  'services' => ['الخدمات الرقمية', 'Services numériques'],
  'strategies' => ['الاستراتيجيات والسياسات', 'Stratégies et politiques'],
  'studies' => ['الدراسات والتقارير', 'Études et rapports'],
  'indicators' => ['مؤشرات القطاع', 'Indicateurs du secteur'],
  'media' => ['المركز الإعلامي', 'Centre des médias'],
  'gallery' => ['الصور والمرئيات', 'Photos et vidéos'],
  'projects' => ['المشاريع', 'Projets'],
  'innovation' => ['الابتكار', 'Innovation'],
  'tenders' => ['العروض والمناقصات', 'Appels d’offres'],
  'contact' => ['اتصل بنا', 'Contact'],
  'links' => ['روابط مفيدة', 'Liens utiles'],
];
$page_nodes = [];
foreach ($pages as $slug => [$ar_title, $fr_title]) {
  $page_nodes[$slug] = $node("page:$slug", ['type' => 'page'],
    ['title' => $ar_title, 'path' => ['alias' => "/$slug"]],
    ['title' => $fr_title, 'path' => ['alias' => "/$slug"]],
  );
}

// News articles. Order on the homepage comes from the created date: dated
// news first, archive items (no date shown) older than all of them.
$articles = [
  'address' => [
    'date' => '2026-09-29 10:00', 'promote' => 1, 'image' => 'news-address.jpeg',
    'ar' => ['الوزارة تستعرض النظام الوطني للعنونة الرقمية', 'الوزارة تستعرض النظام الوطني للعنونة الرقمية', ''],
    'fr' => ['Le Ministère présente le Système national d’adressage numérique', 'Le Ministère présente le Système national d’adressage numérique', ''],
  ],
  'standards' => [
    'date' => '2026-09-28 10:00', 'promote' => 1, 'sticky' => 1, 'image' => 'news-standards.jpeg', 'position' => '50% 0%',
    'ar' => ['توحيد البنية الرقمية للدولة: من صياغة المعايير إلى تطبيقها', 'الوزير خلال إطلاق برنامج توحيد البنية الرقمية للدولة في سبتمبر 2026', ''],
    'fr' => ['Standardisation de l’architecture numérique de l’État : de la formulation des normes à leur application', 'Le Ministre lors du lancement du programme de standardisation de l’architecture numérique de l’État, en septembre 2026', ''],
  ],
  'wfp' => [
    'date' => '2026-09-15 10:00', 'promote' => 1, 'sticky' => 1, 'image' => 'news-wfp.jpg', 'hero' => 'hero-wfp.jpg', 'position' => '55% 24%',
    'hero_title' => ['fr' => 'Le Ministre examine avec le PAM les perspectives de coopération dans la transformation numérique'],
    'ar' => ['الوزير يبحث مع برنامج الأغذية العالمي آفاق التعاون في التحول الرقمي', 'الوزير يبحث مع برنامج الأغذية العالمي آفاق التعاون في التحول الرقمي', '', 'الوزير يترأس اجتماعاً مع وفد برنامج الأغذية العالمي في مقر الوزارة'],
    'fr' => ['Le Ministre examine avec le Programme alimentaire mondial (PAM) les perspectives de coopération en matière de transformation numérique', 'Le Ministre examine avec le Programme alimentaire mondial (PAM) les perspectives de coopération en matière de transformation numérique', '', 'Le Ministre préside une réunion avec une délégation du Programme alimentaire mondial (PAM) au siège du Ministère'],
  ],
  'cable' => [
    'date' => '2026-05-05 10:00', 'promote' => 1, 'image' => 'news-cable.jpg',
    'ar' => ['إنزال فرع جديد من الكابل البحري ألالينك في نواذيبو', 'إنزال فرع جديد من الكابل البحري ألالينك في نواذيبو', 'ربط ثانٍ مباشر بأوروبا عبر أكثر من 670 كلم من الكابل البحري، بتمويل مشترك من الدولة والاتحاد الأوروبي.'],
    'fr' => ['Atterrage d’une nouvelle branche du câble sous-marin EllaLink à Nouadhibou', 'Atterrage d’une nouvelle branche du câble sous-marin EllaLink à Nouadhibou', 'Une deuxième connexion directe vers l’Europe grâce à plus de 670 km de câble sous-marin, cofinancée par l’État et l’Union européenne.'],
  ],
  'university' => [
    'date' => '2025-06-01 10:00', 'archive' => 1, 'promote' => 1, 'sticky' => 1, 'image' => 'news-university.jpg', 'hero' => 'hero-university.jpg', 'position' => '50% 46%',
    'ar' => ['رقمنة إجراءات تسجيل الطلاب في جامعة نواكشوط', 'رقمنة إجراءات تسجيل الطلاب في جامعة نواكشوط', 'إتاحة خدمة التسجيل الجامعي إلكترونياً على البوابة الوطنية للخدمات العمومية «خدماتي».', 'إطلاق خدمة التسجيل عن بعد لطلاب جامعة نواكشوط وربطها ببوابة خدمتي'],
    'fr' => ['Dématérialisation des procédures d’inscription des étudiants à l’Université de Nouakchott', 'Dématérialisation des procédures d’inscription des étudiants à l’Université de Nouakchott', 'Mise en ligne du service d’inscription universitaire sur le portail national des services publics « Khidmaty ».', 'Lancement du service d’inscription à distance des étudiants de l’Université de Nouakchott, relié au portail Khidmaty'],
  ],
  'rimer' => [
    'date' => '2024-06-11 10:00',
    'ar' => ['تقديم الدراسات الفنية للشبكة الموريتانية للتعليم والبحث (RIMER)', '', ''],
    'fr' => ['Présentation des études techniques du Réseau mauritanien d’enseignement et de recherche (RIMER)', '', ''],
  ],
  'traffic' => [
    'date' => '2025-05-01 10:00', 'archive' => 1, 'promote' => 1, 'image' => 'news-services.jpg',
    'ar' => ['ربط النظام الرقمي للمرور ببوابة «خدماتي»', 'ربط النظام الرقمي للمرور ببوابة «خدماتي»', 'خدمة تتيح إشعار المواطنين بالمخالفات المرورية عبر البوابة الوطنية للخدمات الرقمية.'],
    'fr' => ['Raccordement du système numérique du trafic routier au portail « Khidmaty »', 'Raccordement du système numérique du trafic routier au portail « Khidmaty »', 'Un service permettant de notifier aux citoyens leurs infractions routières via le portail national des services numériques.'],
  ],
];
$timezone = new \DateTimeZone('Africa/Nouakchott');
foreach ($articles as $slug => $article) {
  $file = isset($article['image']) ? $image($article['image']) : NULL;
  $hero = isset($article['hero']) ? $image($article['hero']) : NULL;
  $values = [];
  foreach (['ar', 'fr'] as $langcode) {
    [$title, $alt, $summary] = $article[$langcode];
    $values[$langcode] = [
      'title' => $title,
      'field_image' => $file ? ['target_id' => $file->id(), 'alt' => $alt] : [],
      'field_hero_image' => $hero ? ['target_id' => $hero->id(), 'alt' => $article[$langcode][3]] : [],
      'body' => $summary ? ['value' => "<p>$summary</p>", 'summary' => $summary, 'format' => 'basic_html'] : [],
      'path' => ['alias' => "/news/$slug"],
      'field_hero_title' => $article['hero_title'][$langcode] ?? NULL,
    ];
  }
  $node("article:$slug", [
    'type' => 'article',
    'created' => (new \DateTime($article['date'], $timezone))->getTimestamp(),
    'promote' => $article['promote'] ?? 0,
    'sticky' => $article['sticky'] ?? 0,
    'field_archive' => $article['archive'] ?? 0,
    'field_image_position' => $article['position'] ?? NULL,
  ], $values['ar'], $values['fr']);
}

// Digital services grid, in display order (oldest first).
$services = [
  'procedures' => ['document', ['بوابة الإجراءات والمساطر الإدارية', 'تعرّف على خطوات إنجاز معاملاتك والوثائق المطلوبة لدى الإدارات العمومية.', 'https://procedures.gov.mr/', ''], ['Portail des procédures et formalités administratives', 'Découvrez les étapes de vos démarches et les pièces requises auprès des administrations publiques.', 'https://procedures.gov.mr/', '']],
  'recruitment' => ['briefcase', ['منصة اللجنة الوطنية للمسابقات', 'اطّلع على مسابقات الاكتتاب وقدّم ملف ترشحك إلكترونياً.', 'https://recrutements.gov.mr/', ''], ['Plateforme de la Commission nationale des concours', 'Consultez les concours de recrutement et déposez votre dossier de candidature en ligne.', 'https://recrutements.gov.mr/', '']],
  'exams' => ['clipboard', ['منصة الامتحانات الوطنية', 'بوابة تقديم ملفات الترشح للامتحانات للمؤسسات التعليمية والمترشحين الأحرار.', 'https://dec.education.gov.mr/', ''], ['Plateforme des examens nationaux', 'Portail de dépôt des dossiers de candidature aux examens, destiné aux établissements d’enseignement et aux candidats libres.', 'https://dec.education.gov.mr/', '']],
  'autism' => ['person', ['تطبيق أطفال التوحد', 'أداة تعليمية رقمية لدعم الأطفال ومتابعة تطوّر مهاراتهم.', 'https://mtnima.gov.mr/%D8%A7%D9%84%D8%AE%D8%AF%D9%85%D8%A7%D8%AA-%D8%A7%D9%84%D8%B1%D9%82%D9%85%D9%8A%D8%A9/', 'تفاصيل التطبيق'], ['Application pour enfants autistes', 'Un outil éducatif numérique pour accompagner les enfants et suivre l’évolution de leurs compétences.', 'https://mtnima.gov.mr/fr/les-services-numeriques/', 'Détails de l’application']],
  'construction' => ['building', ['تأهيل وتصنيف مؤسسات البناء', 'خدمة إلكترونية لتسهيل إجراءات تصنيف وتأهيل مؤسسات البناء والأشغال.', 'https://qce.gov.mr/', ''], ['Qualification et classification des entreprises de construction', 'Un service en ligne pour faciliter les procédures de classification et de qualification des entreprises de construction et de travaux publics.', 'https://qce.gov.mr/', '']],
];
$created = (new \DateTime('2026-01-01 10:00', $timezone))->getTimestamp();
foreach ($services as $slug => [$icon, $ar, $fr]) {
  $translate = fn(array $v) => [
    'title' => $v[0],
    'field_service_description' => $v[1],
    'field_service_link' => ['uri' => $v[2], 'title' => $v[3]],
  ];
  $node("service:$slug", ['type' => 'service', 'promote' => 1, 'created' => $created++, 'field_service_icon' => $icon], $translate($ar), $translate($fr));
}

/**
 * Creates a translated menu link, unless already seeded.
 */
$link = function (string $menu, string $key, string $uri, string $ar, string $fr, int $weight, ?MenuLinkContent $parent = NULL) use (&$seeded, $remember): MenuLinkContent {
  if (isset($seeded["menu:$menu:$key"]) && ($existing = MenuLinkContent::load($seeded["menu:$menu:$key"]))) {
    return $existing;
  }
  $item = MenuLinkContent::create([
    'menu_name' => $menu,
    'title' => $ar,
    'link' => ['uri' => $uri],
    'weight' => $weight,
    'expanded' => TRUE,
    'langcode' => 'ar',
    'parent' => $parent ? $parent->getPluginId() : '',
  ]);
  $item->save();
  $item->addTranslation('fr', ['title' => $fr])->save();
  $remember("menu:$menu:$key", (int) $item->id());
  return $item;
};
$page = fn(string $slug) => 'entity:node/' . $page_nodes[$slug]->id();
$title = fn(string $slug, int $i) => $pages[$slug][$i];

// Adopt the existing "Home" link rather than adding a second one.
if (!isset($seeded['menu:main:home'])) {
  foreach (\Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties(['menu_name' => 'main', 'link__uri' => 'route:<front>']) as $home) {
    $home->set('weight', 0)->save();
    if (!$home->hasTranslation('fr')) {
      $home->addTranslation('fr', ['title' => 'Accueil'])->save();
    }
    $remember('menu:main:home', (int) $home->id());
    break;
  }
}

$weight = 0;
$link('main', 'home', 'route:<front>', 'الرئيسية', 'Accueil', $weight++);
$about = $link('main', 'about-group', 'route:<nolink>', 'عن الوزارة', 'Le Ministère', $weight++);
foreach (['about', 'minister', 'organization', 'agencies', 'laws'] as $i => $slug) {
  $link('main', $slug, $page($slug), $title($slug, 0), $title($slug, 1), $i, $about);
}
$link('main', 'services', $page('services'), $title('services', 0), $title('services', 1), $weight++);
$strategies = $link('main', 'strategies-group', 'route:<nolink>', 'الاستراتيجيات والدراسات', 'Stratégies et études', $weight++);
foreach (['strategies', 'studies', 'indicators'] as $i => $slug) {
  $link('main', $slug, $page($slug), $title($slug, 0), $title($slug, 1), $i, $strategies);
}
foreach (['media', 'projects', 'innovation', 'tenders', 'contact'] as $slug) {
  $link('main', $slug, $page($slug), $title($slug, 0), $title($slug, 1), $weight++);
}

$footer = [
  'about' => [['عن الوزارة', 'Le Ministère'], ['about', 'minister', 'organization', 'agencies']],
  'knowledge' => [['الاستراتيجيات والمعرفة', 'Stratégies et savoirs'], ['strategies', 'studies', 'laws', 'indicators']],
  'media' => [['المركز الإعلامي', 'Centre des médias'], ['media', 'gallery', 'tenders', 'projects']],
  'quick' => [['روابط سريعة', 'Accès rapides'], ['services', 'innovation', 'contact', 'links']],
];
// Footer labels that differ from the page title.
$footer_titles = ['media' => ['الأخبار', 'Actualités']];
$weight = 0;
foreach ($footer as $group => [[$ar, $fr], $slugs]) {
  $heading = $link('footer', "$group-group", 'route:<nolink>', $ar, $fr, $weight++);
  foreach ($slugs as $i => $slug) {
    [$link_ar, $link_fr] = $footer_titles[$slug] ?? $pages[$slug];
    $link('footer', $slug, $page($slug), $link_ar, $link_fr, $i, $heading);
  }
}

// Section page, article and service content from the reference site.
$reference = Json::decode(file_get_contents(__DIR__ . '/../content/reference.json'));
$file_urls = \Drupal::service('file_url_generator');
// Reference images ("assets/minister.jpg") become managed files.
$with_files = fn(string $html) => preg_replace_callback('/src="assets\/([^"]+)"/', fn(array $m) => 'src="' . $file_urls->generateString($image($m[1])->getFileUri()) . '"', $html);

foreach ($reference['pages'] as $slug => $info) {
  if (isset($seeded["content:page:$slug"])) {
    continue;
  }
  $section = $page_nodes[$slug];
  if ($section->get('field_related')->isEmpty()) {
    $section->set('field_related', array_map(fn(string $related) => ['target_id' => $page_nodes[$related]->id()], $info['related']));
  }
  if ($section->get('field_listing')->isEmpty()) {
    $section->set('field_listing', $info['listing']);
  }
  foreach (['ar', 'fr'] as $langcode) {
    $translation = $section->getTranslation($langcode);
    if ($translation->get('body')->isEmpty()) {
      $html = file_get_contents(__DIR__ . "/../content/pages/$slug.$langcode.html");
      $translation->set('body', ['value' => $with_files($html), 'format' => 'mtnma_html']);
    }
    if ($translation->get('field_lead')->isEmpty()) {
      $translation->set('field_lead', $info['lead'][$langcode]);
    }
  }
  $section->save();
  $remember("content:page:$slug", (int) $section->id());
}

foreach ($reference['articles'] as $slug => $info) {
  $article = isset($seeded["node:article:$slug"]) ? Node::load($seeded["node:article:$slug"]) : NULL;
  if (!$article || isset($seeded["content:article:$slug"])) {
    continue;
  }
  if ($article->get('field_category')->isEmpty()) {
    $article->set('field_category', $info['category']);
  }
  foreach (['ar', 'fr'] as $langcode) {
    $translation = $article->getTranslation($langcode);
    $paragraphs = $info[$langcode]['paragraphs'];
    $body = $translation->get('body')->first();
    // Replace an empty body, or the one-paragraph summary seeded earlier.
    if ($paragraphs && (!$body || $body->value === '<p>' . $body->summary . '</p>')) {
      $translation->set('body', [
        'value' => implode("\n", array_map(fn(string $p) => '<p>' . Html::escape($p) . '</p>', $paragraphs)),
        'summary' => $body?->summary ?: $paragraphs[0],
        'format' => 'basic_html',
      ]);
    }
    if ($translation->get('field_source_link')->isEmpty() && $info[$langcode]['source']) {
      $translation->set('field_source_link', ['uri' => $info[$langcode]['source']]);
    }
  }
  $article->save();
  $remember("content:article:$slug", (int) $article->id());
}

// Media Centre card summaries and the date each source was consulted.
foreach ($reference['articles'] as $slug => $info) {
  $article = isset($seeded["node:article:$slug"]) ? Node::load($seeded["node:article:$slug"]) : NULL;
  if (!$article || isset($seeded["content:article-details:$slug"])) {
    continue;
  }
  $changed = FALSE;
  if ($info['checked'] && $article->get('field_source_checked')->isEmpty()) {
    $article->set('field_source_checked', $info['checked']);
    $changed = TRUE;
  }
  foreach (['ar', 'fr'] as $langcode) {
    $body = $article->getTranslation($langcode)->get('body')->first();
    $summary = $info[$langcode]['summary'];
    // Replace an empty summary, or the first paragraph used as one above.
    if ($summary && $body && in_array($body->summary, ['', NULL, $info[$langcode]['paragraphs'][0]], TRUE) && $body->summary !== $summary) {
      $body->set('summary', $summary);
      $changed = TRUE;
    }
  }
  if ($changed) {
    $article->save();
  }
  $remember("content:article-details:$slug", (int) $article->id());
}

foreach (array_keys($services) as $slug) {
  $service = Node::load($seeded["node:service:$slug"] ?? 0);
  $category = $service ? ($reference['services'][$service->getTranslation('ar')->label()] ?? NULL) : NULL;
  if ($category && $service->get('field_service_category')->isEmpty()) {
    $service->set('field_service_category', $category)->save();
  }
}

// Index all content for the site search now rather than on the next cron
// runs (later saves are indexed as they happen, see SearchHooks).
$search = \Drupal::entityTypeManager()->getStorage('search_page')->load('node_search')->getPlugin();
$search->markForReindex();
do {
  $search->updateIndex();
  $status = $search->indexStatus();
} while ($status['remaining'] > 0);
echo "Search index: {$status['total']} nodes" . PHP_EOL;

echo 'Seeded items: ' . count($seeded) . PHP_EOL;
