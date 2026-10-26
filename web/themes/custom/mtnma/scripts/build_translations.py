"""Builds translations/{ar,fr}.po for the mtnma theme interface strings.

Run: python3 web/themes/custom/mtnma/scripts/build_translations.py
Then: ddev drush locale:import ar web/themes/custom/mtnma/translations/ar.po --override=all
"""
import pathlib

# English source -> (Arabic, French), taken from the static replicas.
STRINGS = {
    'Skip to content': ('انتقل إلى المحتوى', 'Aller au contenu'),
    'Ministry of Digital Transformation — Home': ('وزارة التحول الرقمي — الرئيسية', 'Ministère de la Transformation Numérique et de la Modernisation de l’Administration — Accueil'),
    'Emblem of the Islamic Republic of Mauritania': ('شعار الجمهورية الإسلامية الموريتانية', 'Emblème de la République Islamique de Mauritanie'),
    'Islamic Republic of Mauritania': ('الجمهورية الإسلامية الموريتانية', 'République Islamique de Mauritanie'),
    'Honour – Fraternity – Justice': ('شرف – إخاء – عدل', 'Honneur – Fraternité – Justice'),
    'Islamic Republic of Mauritania — Honour, Fraternity, Justice': ('الجمهورية الإسلامية الموريتانية — شرف إخاء عدل', 'République Islamique de Mauritanie — Honneur, Fraternité, Justice'),
    'Ministry of Digital Transformation and Administrative Modernization': ('وزارة التحول الرقمي وعصرنة الإدارة', 'Ministère de la Transformation Numérique et de la Modernisation de l’Administration'),
    'Ministry of Digital Transformation and Administrative Modernization — services, news and strategies': ('وزارة التحول الرقمي وعصرنة الإدارة — الخدمات والأخبار والاستراتيجيات', 'Ministère de la Transformation Numérique et de la Modernisation de l’Administration — services, actualités et stratégies'),
    'Accessibility options': ('خيارات إمكانية الوصول', 'Options d’accessibilité'),
    'Search the site': ('البحث في الموقع', 'Rechercher sur le site'),
    'Larger text': ('تكبير الخط', 'Agrandir le texte'),
    'Original text size': ('الخط الأصلي', 'Taille d’origine'),
    'High contrast': ('تباين عالٍ', 'Contraste élevé'),
    'Main navigation': ('التنقل الرئيسي', 'Navigation principale'),
    'Open all site pages': ('فتح جميع صفحات الموقع', 'Ouvrir toutes les pages du site'),
    'All pages': ('جميع الصفحات', 'Toutes les pages'),
    'Breadcrumb': ('مسار التنقل', 'Fil d’Ariane'),
    'Home': ('الرئيسية', 'Accueil'),
    'Emblem of the Republic': ('شعار الجمهورية', 'Emblème de la République'),
    'Towards an administration closer to citizens, and simpler digital services.': ('نحو إدارة أقرب إلى المواطن، وخدمات رقمية أكثر سهولة.', 'Pour une administration plus proche des citoyens et des services numériques plus simples.'),
    'Development version inspired by a Figma design · Reference content: <a href=":url" target="_blank" rel="noopener">official Ministry website</a>': (
        'نسخة تطوير مستوحاة من تصميم Figma · المحتوى المرجعي: <a href=":url" target="_blank" rel="noopener">موقع الوزارة الرسمي</a>',
        'Version de développement inspirée d’une maquette Figma · Contenu de référence\u00a0: <a href=":url" target="_blank" rel="noopener">site officiel du Ministère</a>'),
    'Back to top of page': ('العودة إلى أعلى الصفحة', 'Retour en haut de la page'),
    'Close search': ('إغلاق البحث', 'Fermer la recherche'),
    'Search for a page or a service': ('ابحث عن صفحة أو خدمة', 'Rechercher une page ou un service'),
    'Example: digital services': ('مثال: الخدمات الرقمية', 'Exemple\u00a0: services numériques'),
    'No results. Try another word.': ('لا توجد نتائج. جرّب كلمة أخرى.', 'Aucun résultat. Essayez un autre mot.'),
    # Homepage.
    'carousel': ('عرض متحرك', 'carrousel'),
    'Featured news': ('أخبار الواجهة', 'Actualités à la une'),
    'slide': ('شريحة', 'diapositive'),
    '@index of @count': ('@index من @count', '@index sur @count'),
    'News item @index': ('الخبر @index', 'Actualité @index'),
    'Pause automatic scrolling': ('إيقاف التمرير التلقائي', 'Mettre en pause le défilement automatique'),
    'Ministry news · @date': ('من أخبار الوزارة · @date', 'Actualités du Ministère · @date'),
    'From the Ministry archive': ('من أرشيف الوزارة', 'Archives du Ministère'),
    'Read the article': ('تفاصيل الخبر', 'Lire l’article'),
    'Read the news': ('اقرأ الخبر', 'Lire l’article'),
    'The Ministry in figures': ('الوزارة بالأرقام', 'Le Ministère en chiffres'),
    'Indicators published in the Ministry archive': ('مؤشرات منشورة في أرشيف الوزارة', 'Indicateurs publiés dans les archives du Ministère'),
    'Internet penetration rate': ('معدل النفاذ إلى الإنترنت', 'Taux de pénétration d’Internet'),
    'Mobile phone penetration · 2022': ('نفاذ الهاتف المحمول · 2022', 'Pénétration de la téléphonie mobile · 2022'),
    'km': ('كم', 'km'),
    'Optical fibre · 2021': ('الألياف البصرية · 2021', 'Fibre optique · 2021'),
    'Top news': ('الأخبار الرئيسية', 'Principales actualités'),
    'Follow Ministry news and digital transformation activities': ('تابع أخبار الوزارة وأنشطة التحول الرقمي', 'Suivez les actualités du Ministère et les activités de transformation numérique'),
    'All news': ('جميع الأخبار', 'Toutes les actualités'),
    'Explore the Media Centre': ('استكشف المركز الإعلامي', 'Explorer le Centre des médias'),
    'Digital services': ('الخدمات الرقمية', 'Services numériques'),
    'Your procedures, closer to you. Choose a service and go directly to its official portal.': ('معاملاتك أقرب إليك. اختر الخدمة وانتقل مباشرةً إلى بوابتها الرسمية.', 'Vos démarches, plus proches de vous. Choisissez un service et accédez directement à son portail officiel.'),
    'All services': ('جميع الخدمات', 'Tous les services'),
    'Go to the service': ('الدخول إلى الخدمة', 'Accéder au service'),
    'Clear services, simpler steps': ('خدمات واضحة، وخطوات أسهل', 'Des services clairs, des démarches simplifiées'),
    'A single portal to discover government platforms and access them.': ('بوابة موحدة للتعرّف على المنصات الحكومية والوصول إليها.', 'Un portail unique pour découvrir les plateformes gouvernementales et y accéder.'),
    'Find your service': ('ابحث عن خدمتك', 'Trouvez votre service'),
    'Mr. Ahmed Salem Bede': ('السيد أحمد سالم ابده', 'M. Ahmed Salem Bede'),
    'Minister of Digital Transformation and Administrative Modernization': ('وزير التحول الرقمي وعصرنة الإدارة', 'Ministre de la Transformation Numérique et de la Modernisation de l’Administration'),
    'About the Minister': ('عن معالي الوزير', 'Le Ministre'),
    'About the Ministry': ('عن الوزارة', 'Le Ministère'),
    'Digital transformation serving the citizen': ('التحول الرقمي في خدمة المواطن', 'La transformation numérique au service du citoyen'),
    'Learn about the missions of the Ministry and its bodies, and explore the policies and initiatives aimed at developing public services and making use of digital tools.': (
        'اطّلع على مهام الوزارة وهيئاتها، واستكشف السياسات والمبادرات الرامية إلى تطوير الخدمات العمومية والاستفادة من الأدوات الرقمية.',
        'Découvrez les missions du Ministère et de ses organismes, et explorez les politiques et initiatives visant à développer les services publics et à tirer parti des outils numériques.'),
    'Organization chart': ('الهيكل التنظيمي', 'Organigramme'),
    'Organization and operation': ('تنظيم وتسيير العمل', 'Organisation et fonctionnement'),
    'Supervised bodies': ('الهيئات تحت الوصاية', 'Établissements sous tutelle'),
    'Complementary institutions': ('مؤسسات تعمل بتكامل', 'Des institutions complémentaires'),
    'Strategies and policies': ('الاستراتيجيات والسياسات', 'Stratégies et politiques'),
    'Vision and priorities of the sector': ('رؤية وأولويات القطاع', 'Vision et priorités du secteur'),
    'Sector indicators': ('مؤشرات القطاع', 'Indicateurs du secteur'),
    'Data on digital transformation': ('بيانات عن التحول الرقمي', 'Données sur la transformation numérique'),
    'Areas of action of the Ministry': ('محاور عمل الوزارة', 'Axes d’intervention du Ministère'),
    'Integrated paths to build a digital future that serves everyone': ('مسارات متكاملة لبناء مستقبل رقمي يخدم الجميع', 'Des axes complémentaires pour bâtir un avenir numérique au service de tous'),
    'Infrastructure': ('البنية التحتية', 'Infrastructure'),
    'Strengthening connectivity, developing digital infrastructure and hosting data.': ('تعزيز الاتصال وتطوير البنية الرقمية واستضافة البيانات.', 'Renforcer la connectivité, développer l’infrastructure numérique et héberger les données.'),
    'Administrative modernization': ('عصرنة الإدارة', 'Modernisation de l’administration'),
    'Simplifying procedures and improving the experience of public service users.': ('تبسيط المساطر وتحسين تجربة المستفيد من الخدمة العمومية.', 'Simplifier les procédures et améliorer l’expérience des usagers du service public.'),
    'Innovation': ('الابتكار', 'Innovation'),
    'Supporting new solutions and entrepreneurship in the digital field.': ('دعم الحلول الجديدة وريادة الأعمال في المجال الرقمي.', 'Soutenir les solutions nouvelles et l’entrepreneuriat dans le domaine numérique.'),
    'Digital transformation': ('التحول الرقمي', 'Transformation numérique'),
    'Developing the policies and tools that support the digitization of services.': ('تطوير السياسات والأدوات التي تدعم رقمنة الخدمات.', 'Élaborer les politiques et les outils qui favorisent la digitalisation des services.'),
    'Learn more': ('اكتشف المزيد', 'En savoir plus'),
    'Media Centre': ('المركز الإعلامي', 'Centre des médias'),
    'News and events from the Ministry archive': ('أخبار وفعاليات من أرشيف الوزارة', 'Actualités et événements issus des archives du Ministère'),
    'See more': ('عرض المزيد', 'Voir plus'),
    'Back to the Media Centre': ('العودة إلى المركز الإعلامي', 'Retour au Centre des médias'),
    # Section pages, listings and articles.
    'Related pages': ('الصفحات ذات الصلة', 'Pages associées'),
    'Ministry news': ('أخبار الوزارة', 'Actualités du Ministère'),
    'Citizens': ('المواطنون', 'Citoyens'),
    'Businesses': ('المؤسسات', 'Entreprises'),
    'Education and family': ('التعليم والأسرة', 'Éducation et famille'),
    'Search the news': ('البحث في الأخبار', 'Rechercher dans les actualités'),
    'Search the news…': ('ابحث في الأخبار...', 'Rechercher une actualité...'),
    'Search for a service': ('البحث عن خدمة', 'Rechercher un service'),
    'Search for a service…': ('ابحث عن خدمة...', 'Rechercher un service...'),
    'No results match your search.': ('لا توجد نتائج مطابقة للبحث.', 'Aucun résultat ne correspond à la recherche.'),
    'Read the original article': ('قراءة الخبر الأصلي', 'Lire l’article original'),
    'Copy the article link': ('نسخ رابط الخبر', 'Copier le lien de l’article'),
    'Reference source: <a href=":url" target="_blank" rel="noopener noreferrer">official Ministry website</a>': (
        'المصدر المرجعي: <a href=":url" target="_blank" rel="noopener noreferrer">الموقع الرسمي للوزارة</a>',
        'Source\u00a0: <a href=":url" target="_blank" rel="noopener noreferrer">site officiel du Ministère</a>'),
    'Digital service': ('خدمة رقمية', 'Service numérique'),
    'Procedures are completed on the official website of each service. This site is a guide to those websites.': (
        'تُنجز المعاملات على المواقع الرسمية لكل خدمة. هذا الموقع دليل للوصول إلى تلك المواقع.',
        'Les démarches s’effectuent sur le site officiel de chaque service. Ce site est un guide pour y accéder.'),
    # Site search.
    'Pages, news and digital services of the Ministry.': ('صفحات الوزارة وأخبارها وخدماتها الرقمية.', 'Pages, actualités et services numériques du Ministère.'),
    'Search pages, news and services': ('ابحث في الصفحات والأخبار والخدمات', 'Rechercher dans les pages, les actualités et les services'),
    'Site pages': ('صفحات الموقع', 'Pages du site'),
    'No page title matches. Press Search to look in all content.': ('لا توجد صفحة بهذا العنوان. اضغط «بحث» للبحث في كل المحتوى.', 'Aucun titre de page ne correspond. Appuyez sur «\u00a0Rechercher\u00a0» pour chercher dans tout le contenu.'),
}

# Generic words that core also translates; the "mtnma" context keeps the
# theme's wording without changing core's translation.
CONTEXT_STRINGS = {
    'All': ('الكل', 'Tous'),
    'Events': ('الفعاليات', 'Événements'),
    'News': ('خبر', 'Actualité'),
    'Page': ('صفحة', 'Page'),
    'Open': ('فتح', 'Ouvrir'),
}

# Result counts above the listings: (singular, plural) -> (Arabic forms in
# the order of the Arabic plural formula: 1, 0, 2, 3-10, 11-99, 100+), (French).
PLURALS = {
    ('1 news item', '@count news items'): (
        ['خبر واحد', 'لا توجد أخبار', 'خبران', '@count أخبار', '@count خبراً', '@count خبر'],
        ['1 actualité', '@count actualités']),
    ('1 service', '@count services'): (
        ['خدمة واحدة', 'لا توجد خدمات', 'خدمتان', '@count خدمات', '@count خدمة', '@count خدمة'],
        ['1 service', '@count services']),
    ('1 photo', '@count photos'): (
        ['صورة واحدة', 'لا توجد صور', 'صورتان', '@count صور', '@count صورة', '@count صورة'],
        ['1 photo', '@count photos']),
}

# Long month names as written in Mauritania (core Arabic uses "أيلول/سبتمبر").
MONTHS_AR = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر']
MONTHS_EN = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']


def quote(text):
    return '"' + text.replace('\\', '\\\\').replace('"', '\\"') + '"'


def build(index, langcode, plural):
    lines = ['msgid ""', 'msgstr ""', '"Project-Id-Version: mtnma\\n"', '"MIME-Version: 1.0\\n"',
             '"Content-Type: text/plain; charset=utf-8\\n"', '"Content-Transfer-Encoding: 8bit\\n"',
             f'"Plural-Forms: {plural}\\n"', '']
    for source, targets in STRINGS.items():
        lines += [f'msgid {quote(source)}', f'msgstr {quote(targets[index])}', '']
    for source, targets in CONTEXT_STRINGS.items():
        lines += ['msgctxt "mtnma"', f'msgid {quote(source)}', f'msgstr {quote(targets[index])}', '']
    for (singular, plural), targets in PLURALS.items():
        lines += [f'msgid {quote(singular)}', f'msgid_plural {quote(plural)}']
        lines += [f'msgstr[{i}] {quote(form)}' for i, form in enumerate(targets[index])] + ['']
    if langcode == 'ar':
        for en, ar in zip(MONTHS_EN, MONTHS_AR):
            lines += ['msgctxt "Long month name"', f'msgid {quote(en)}', f'msgstr {quote(ar)}', '']
    return '\n'.join(lines)


out = pathlib.Path(__file__).resolve().parent.parent / 'translations'
(out / 'ar.po').write_text(build(0, 'ar', 'nplurals=6; plural=((n==1)?(0):((n==0)?(1):((n==2)?(2):((((n%100)>=3)&&((n%100)<=10))?(3):((((n%100)>=11)&&((n%100)<=99))?(4):5)))));'), encoding='utf-8')
(out / 'fr.po').write_text(build(1, 'fr', 'nplurals=2; plural=(n>1);'), encoding='utf-8')
print(f'{len(STRINGS)} strings written to {out}')
