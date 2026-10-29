'use strict';
(() => {
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.main-nav');
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  function menu(open, returnFocus = false) {
    if (!toggle || !nav) return;
    nav.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    document.body.classList.toggle('menu-is-open', open);
    if (returnFocus) toggle.focus();
  }
  toggle?.addEventListener('click', () => menu(toggle.getAttribute('aria-expanded') !== 'true'));
  nav?.querySelectorAll('a').forEach(a => a.addEventListener('click', () => menu(false)));
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && toggle?.getAttribute('aria-expanded') === 'true') menu(false, true);
  });
  document.addEventListener('click', e => {
    if (toggle?.getAttribute('aria-expanded') === 'true' && !e.target.closest('.site-header')) menu(false);
  });
  nav?.addEventListener('focusout', () => {
    requestAnimationFrame(() => { if (!document.activeElement.closest('.site-header')) menu(false); });
  });
  const desktop = window.matchMedia('(min-width: 961px)');
  desktop.addEventListener('change', e => { if (e.matches) menu(false); });

  // CSS stays readable with JavaScript disabled. Only unseen elements are hidden.
  if ('IntersectionObserver' in window) {
    const header = document.querySelector('.site-header');
    const topline = document.querySelector('.site-topline');
    if (topline && header) new IntersectionObserver(([entry]) => header.classList.toggle('is-scrolled', !entry.isIntersecting)).observe(topline);
    const reveals = [...document.querySelectorAll('[data-reveal]')];
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.classList.remove('reveal-pending');
        observer.unobserve(entry.target);
      });
    }, { threshold: 0.06, rootMargin: '0px 0px -24px 0px' });
    if (!reduced.matches) reveals.forEach((el, i) => {
      if (el.getBoundingClientRect().top < window.innerHeight) return;
      el.classList.add('reveal-pending', 'reveal-step-' + (i % 3));
      observer.observe(el);
    });
    reduced.addEventListener('change', e => { if (e.matches) reveals.forEach(el => el.classList.remove('reveal-pending')); });
    const hero = document.querySelector('.hero-visual');
    if (hero) new IntersectionObserver(([entry]) => hero.classList.toggle('motion-active', entry.isIntersecting)).observe(hero);
  }

  document.querySelectorAll('[data-enhance]').forEach(el => el.hidden = false);
  document.querySelectorAll('[data-filter-grid]').forEach(grid => {
    const input = document.getElementById(grid.dataset.filterGrid);
    if (!input) return;
    const items = [...grid.querySelectorAll('[data-filter-item]')];
    const count = document.querySelector('[data-filter-count="' + grid.id + '"]');
    const empty = document.querySelector('[data-filter-empty="' + grid.id + '"]');
    const clear = document.querySelector('[data-search-clear="' + input.id + '"]');
    const initial = count?.textContent;
    const normalise = text => text.toLocaleLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g, '');
    function filter() {
      const query = normalise(input.value.trim());
      let visible = 0;
      items.forEach(item => {
        item.hidden = !normalise(item.textContent).includes(query);
        if (!item.hidden) { visible++; if (query) item.classList.remove('reveal-pending'); }
      });
      if (count) count.textContent = query ? visible + (visible === 1 ? ' result found' : ' results found') : initial;
      if (empty) empty.hidden = visible !== 0;
      if (clear) clear.hidden = !input.value;
    }
    input.addEventListener('input', filter);
    clear?.addEventListener('click', () => { input.value = ''; filter(); input.focus(); });
    filter();
  });
  document.querySelectorAll('[data-password-toggle]').forEach(button => {
    const input = document.getElementById(button.dataset.passwordToggle);
    if (!input) return;
    button.hidden = false;
    button.addEventListener('click', () => {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.textContent = show ? 'Hide' : 'Show';
      button.setAttribute('aria-pressed', String(show));
      button.setAttribute('aria-label', show ? 'Hide trick password' : 'Show trick password');
    });
  });
  document.querySelector('.instruction-gate [role="alert"]')?.focus();
})();
