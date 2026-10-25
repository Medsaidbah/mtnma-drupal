'use strict';
const one = (selector) => document.querySelector(selector);
const all = (selector) => [...document.querySelectorAll(selector)];
const on = (selector, event, handler) => one(selector)?.addEventListener(event, handler);
const normalize = (text) => text.toLowerCase().normalize('NFKD')
  .replace(/[\u0300-\u036F\u064B-\u065F\u0670]/g, '').replace(/[أإآ]/g, 'ا').replace(/ى/g, 'ي');
// Interface text for the Arabic pages and the French pages in fr/
const t = {
  ar: {
    noResults: 'لا توجد نتائج مطابقة للبحث.', services: 'خدمات', media: 'مواد إعلامية',
    linkCopied: 'تم نسخ رابط الخبر.', linkFallback: 'يمكنك نسخ الرابط من شريط عنوان المتصفح.',
    name: 'الاسم:', subject: 'الموضوع:',
    messageCopied: 'تم نسخ الرسالة. يمكنك لصقها في قناة التواصل الرسمية التي تختارها.',
    messageFallback: 'تعذر النسخ التلقائي. يرجى نسخ الرسالة يدوياً.',
    pause: 'إيقاف التمرير التلقائي', play: 'تشغيل التمرير التلقائي',
  },
  fr: {
    noResults: 'Aucun résultat ne correspond à la recherche.', services: 'services', media: 'contenus',
    linkCopied: 'Lien de l’article copié.', linkFallback: 'Vous pouvez copier le lien depuis la barre d’adresse du navigateur.',
    name: 'Nom\u00a0:', subject: 'Objet\u00a0:',
    messageCopied: 'Message copié. Vous pouvez le coller dans le canal de contact officiel de votre choix.',
    messageFallback: 'La copie automatique a échoué. Veuillez copier le message manuellement.',
    pause: 'Mettre en pause le défilement automatique', play: 'Reprendre le défilement automatique',
  },
}[document.documentElement.lang === 'fr' ? 'fr' : 'ar'];

on('#menu-toggle', 'click', () => {
  const open = one('#mobile-menu').classList.toggle('open');
  one('#menu-toggle').setAttribute('aria-expanded', String(open));
});
const closeAccess = () => {
  one('#access-panel')?.classList.remove('open');
  one('#access-toggle')?.setAttribute('aria-expanded', 'false');
};
document.addEventListener('click', (event) => {
  all('.dropdown[open]').forEach((menu) => { if (!menu.contains(event.target)) menu.open = false; });
  if (!event.target.closest('#access-panel, #access-toggle')) closeAccess();
});
document.addEventListener('keydown', (event) => {
  if (event.key !== 'Escape') return;
  all('.dropdown[open]').forEach((menu) => { menu.open = false; });
  one('#mobile-menu').classList.remove('open');
  one('#menu-toggle').setAttribute('aria-expanded', 'false');
  closeAccess();
});

on('#search-toggle', 'click', () => {
  one('#search-dialog').showModal();
  one('#site-search').focus();
});
on('[data-close]', 'click', () => one('#search-dialog').close());
on('#site-search', 'input', () => {
  const query = normalize(one('#site-search').value);
  let visible = 0;
  all('#search-results a').forEach((item) => {
    item.hidden = !normalize(item.textContent).includes(query);
    if (!item.hidden) visible++;
  });
  one('#search-empty').hidden = visible > 0;
});

on('#access-toggle', 'click', () => {
  const open = one('#access-panel').classList.toggle('open');
  one('#access-toggle').setAttribute('aria-expanded', String(open));
});
let fontScale = 1;
on('#font-more', 'click', () => {
  fontScale = Math.min(1.3, fontScale + 0.1);
  document.documentElement.style.setProperty('--font-scale', fontScale);
});
on('#font-reset', 'click', () => {
  fontScale = 1;
  document.documentElement.style.setProperty('--font-scale', 1);
});
on('#contrast-toggle', 'click', () => {
  const active = document.body.classList.toggle('high-contrast');
  one('#contrast-toggle').setAttribute('aria-pressed', String(active));
});

// Card filtering by category and search text
function filterCards({ container, cards, buttons, attribute, search, count, suffix }) {
  if (!one(container)) return;
  let category = 'all';
  const update = () => {
    const query = normalize(one(search)?.value || '');
    let visible = 0;
    all(`${container} ${cards}`).forEach((card) => {
      card.hidden = (category !== 'all' && card.dataset.category !== category)
        || !normalize(card.textContent).includes(query);
      if (!card.hidden) visible++;
    });
    if (one(count)) one(count).textContent = visible ? `${visible} ${suffix}` : t.noResults;
  };
  all(buttons).forEach((button) => button.addEventListener('click', () => {
    category = button.getAttribute(attribute);
    all(buttons).forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
    update();
  }));
  on(search, 'input', update);
  update();
}
filterCards({container:'#service-results',cards:'.service-card',buttons:'[data-service-filter]',attribute:'data-service-filter',search:'#service-search',count:'#service-count',suffix:t.services});
filterCards({container:'#news-results',cards:'.news-card',buttons:'[data-news-filter]',attribute:'data-news-filter',search:'#news-search',count:'#news-count',suffix:t.media});
filterCards({container:'#tender-results',cards:'.document-row',buttons:'[data-tender-filter]',attribute:'data-tender-filter',search:'#unused-search',count:'#unused-count',suffix:''});
on('#document-search', 'input', () => {
  const query = normalize(one('#document-search').value);
  all('#document-results .document-row').forEach((row) => { row.hidden = !normalize(row.textContent).includes(query); });
});

// Homepage hero: autoplaying news carousel with progress dots, swipe and keyboard
function heroCarousel(hero) {
  const slides = all('.hero-slide');
  const tabs = all('.hero-dot');
  const toggle = one('#hero-toggle');
  const reduce = matchMedia('(prefers-reduced-motion: reduce)');
  const ease = 'cubic-bezier(0.77, 0, 0.175, 1)';
  const wipe = 1000;
  const pauses = new Set();
  const rtl = getComputedStyle(hero).direction === 'rtl';
  let current = 0;
  let settle = () => {};

  // Split each headline into words for the blur reveal (letters would break Arabic joining)
  all('.hero-title').forEach((title) => {
    const words = title.textContent.trim().split(/\s+/);
    title.setAttribute('aria-label', words.join(' '));
    title.replaceChildren(...words.flatMap((word, i) => {
      const span = document.createElement('span');
      span.className = 'hero-word';
      span.style.setProperty('--i', i);
      span.textContent = word;
      span.setAttribute('aria-hidden', 'true');
      return i ? [' ', span] : [span];
    }));
    title.parentElement.querySelector('.btn').style.setProperty('--i', words.length);
  });

  const pause = (reason, active) => {
    pauses[active ? 'add' : 'delete'](reason);
    hero.toggleAttribute('data-paused', pauses.size > 0);
    hero.toggleAttribute('data-stopped', pauses.has('user'));
    one('#hero-slides').setAttribute('aria-live', pauses.has('user') || pauses.has('focus') ? 'polite' : 'off');
  };

  // dir 1 = next: the next slide arrives from the left in RTL, from the right in LTR
  const show = (next, dir = next > current ? 1 : -1) => {
    if (next === current) return;
    const from = slides[current];
    const to = slides[next];
    settle();
    slides.forEach((slide) => slide.classList.remove('is-entering'));
    from.classList.replace('is-active', 'is-leaving');
    to.classList.add('is-active');
    void to.offsetWidth;
    to.classList.add('is-entering');
    const drift = [];
    if (!reduce.matches) {
      to.querySelector('.hero-media').animate(
        { clipPath: [(dir > 0) === rtl ? 'inset(0 100% 0 0)' : 'inset(0 0 0 100%)', 'inset(0 0% 0 0%)'] },
        { duration: wipe, easing: ease });
      drift.push(from.querySelector('.hero-media img').animate(
        { translate: ['0 0', `${dir * (rtl ? 6 : -6)}% 0`] },
        { duration: wipe, easing: ease, fill: 'forwards' }));
    }
    const timer = setTimeout(() => settle(), reduce.matches ? 450 : wipe);
    settle = () => {
      clearTimeout(timer);
      from.classList.remove('is-leaving');
      drift.forEach((animation) => animation.cancel());
      settle = () => {};
    };
    tabs.forEach((tab, i) => {
      tab.setAttribute('aria-selected', String(i === next));
      tab.tabIndex = i === next ? 0 : -1;
    });
    current = next;
  };
  const step = (dir) => show((current + dir + slides.length) % slides.length, dir);

  one('.hero-dots').addEventListener('animationend', (event) => {
    if (event.animationName === 'heroProgress') step(1);
  });
  tabs.forEach((tab, i) => tab.addEventListener('click', () => show(i)));
  one('.hero-dots').addEventListener('keydown', (event) => {
    const keys = { ArrowLeft: current + (rtl ? 1 : -1), ArrowRight: current + (rtl ? -1 : 1), Home: 0, End: slides.length - 1 };
    if (!(event.key in keys)) return;
    event.preventDefault();
    show((keys[event.key] + slides.length) % slides.length);
    tabs[current].focus();
  });
  toggle.addEventListener('click', () => {
    pause('user', !pauses.has('user'));
    toggle.setAttribute('aria-label', pauses.has('user') ? t.play : t.pause);
  });

  // Pause while reading or using the controls, while keyboard focus is inside, and when not visible
  all('.hero-content, .hero-controls').forEach((area) => {
    area.addEventListener('pointerenter', (event) => { if (event.pointerType === 'mouse') pause('hover', true); });
    area.addEventListener('pointerleave', () => pause('hover', false));
  });
  hero.addEventListener('focusin', (event) => { if (event.target.matches(':focus-visible')) pause('focus', true); });
  hero.addEventListener('focusout', (event) => { if (!hero.contains(event.relatedTarget)) pause('focus', false); });
  document.addEventListener('visibilitychange', () => pause('hidden', document.hidden));
  new IntersectionObserver(([entry]) => pause('offscreen', !entry.isIntersecting)).observe(hero);

  // Swipe toward the start of the line for the next slide: right in RTL, left in LTR
  let startX = null;
  hero.addEventListener('pointerdown', (event) => { startX = event.pointerType === 'mouse' ? null : event.clientX; });
  hero.addEventListener('pointercancel', () => { startX = null; });
  hero.addEventListener('pointerup', (event) => {
    if (startX === null) return;
    const distance = event.clientX - startX;
    startX = null;
    if (Math.abs(distance) > 50) step((distance > 0) === rtl ? 1 : -1);
  });
}
if (one('.hero-slides')) heroCarousel(one('.hero'));
on('#copy-link', 'click', async () => {
  try {
    await navigator.clipboard.writeText(location.href);
    one('#copy-status').textContent = t.linkCopied;
  } catch { one('#copy-status').textContent = t.linkFallback; }
});
on('#contact-form', 'submit', async (event) => {
  event.preventDefault();
  const data = new FormData(event.currentTarget);
  const text = `${t.name} ${data.get('name')}\n${t.subject} ${data.get('subject')}\n\n${data.get('message')}`;
  try {
    await navigator.clipboard.writeText(text);
    one('#contact-status').textContent = t.messageCopied;
  } catch { one('#contact-status').textContent = t.messageFallback; }
});
