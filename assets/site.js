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
  }

  document.body.classList.add('is-enhanced');
  document.querySelectorAll('[data-enhance]').forEach(el => el.hidden = false);
  document.querySelectorAll('[data-filter-grid]').forEach(grid => {
    const input = document.getElementById(grid.dataset.filterGrid);
    if (!input) return;
    const items = [...grid.querySelectorAll('[data-filter-item]')];
    const count = document.querySelector('[data-filter-count="' + grid.id + '"]');
    const empty = document.querySelector('[data-filter-empty="' + grid.id + '"]');
    const clear = document.querySelector('[data-search-clear="' + input.id + '"]');
    const filters = [...document.querySelectorAll('[data-filter-target="' + grid.id + '"]')];
    let category = 'all';
    const initial = count?.textContent;
    const normalise = text => text.toLocaleLowerCase().normalize('NFKD').replace(/[\u0300-\u036f]/g, '');
    function filter() {
      const query = normalise(input.value.trim());
      let visible = 0;
      items.forEach(item => {
        item.hidden = (category !== 'all' && item.dataset.category !== category) || !normalise(item.textContent + ' ' + (item.dataset.search || '')).includes(query);
        if (!item.hidden) { visible++; if (query) item.classList.remove('reveal-pending'); }
      });
      if (count) count.textContent = query || category !== 'all' ? visible + (visible === 1 ? ' result found' : ' results found') : initial;
      if (empty) empty.hidden = visible !== 0;
      if (clear) clear.hidden = !input.value;
    }
    input.addEventListener('input', filter);
    filters.forEach(button => button.addEventListener('click', () => {
      category = button.dataset.categoryFilter;
      filters.forEach(control => control.setAttribute('aria-pressed', String(control === button)));
      filter();
    }));
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
  // Native dialogs retain keyboard focus and expose only public catalogue data.
  const dialog = document.getElementById('product-dialog');
  let lastProductButton = null;
  let productGroup = [], productIndex = 0;
  if (dialog && typeof dialog.showModal === 'function') {
    let browseAnimation = null;
    function showProduct(article) {
      const art = article.querySelector('.product-visual').cloneNode(true);
      art.querySelectorAll('.price, .deck-price').forEach(el => el.remove());
      art.querySelectorAll('[loading]').forEach(el => el.loading = 'eager');
      document.getElementById('dialog-art').replaceChildren(art);
      document.getElementById('dialog-name').textContent = article.querySelector('h3').textContent;
      document.getElementById('dialog-price').textContent = article.querySelector('.price, .deck-price').textContent;
      document.getElementById('dialog-description').textContent = article.querySelector('.product-description').textContent;
      document.getElementById('dialog-position').textContent = (productIndex + 1) + ' of ' + productGroup.length;
      dialog.querySelector('.dialog-browse').hidden = productGroup.length < 2;
    }
    function browseProduct(step) {
      productIndex = (productIndex + step + productGroup.length) % productGroup.length;
      showProduct(productGroup[productIndex]);
      browseAnimation?.cancel();
      if (!reduced.matches) browseAnimation = document.getElementById('dialog-art').animate([{opacity: .4, transform: 'translateX(' + (step * 12) + 'px)'}, {opacity: 1, transform: 'translateX(0)'}], {duration: 280, easing: 'ease-out'});
    }
    reduced.addEventListener('change', e => {if (e.matches) browseAnimation?.cancel();});
    dialog.querySelectorAll('[data-dialog-step]').forEach(button => button.addEventListener('click', () => browseProduct(Number(button.dataset.dialogStep))));
    document.querySelectorAll('[data-product-open]').forEach(button => {
      button.addEventListener('click', () => {
        const article = button.closest('.product, .deck');
        if (!article) return;
        productGroup = [...article.parentElement.querySelectorAll('.product, .deck')].filter(item => !item.hidden);
        productIndex = productGroup.indexOf(article);
        showProduct(article);
        lastProductButton = button;
        dialog.showModal();
        document.body.classList.add('dialog-open');
      });
    });
    dialog.querySelector('.dialog-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('keydown', e => {
      if (['ArrowLeft', 'ArrowRight'].includes(e.key) && productGroup.length > 1) {e.preventDefault();browseProduct(e.key === 'ArrowLeft' ? -1 : 1);return;}
      if (e.key !== 'Tab') return;
      const controls = [...dialog.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex="0"]')].filter(el => el.getClientRects().length > 0);
      const first = controls[0], last = controls[controls.length - 1];
      if (e.shiftKey && document.activeElement === first) {e.preventDefault();last?.focus();}
      else if (!e.shiftKey && document.activeElement === last) {e.preventDefault();first?.focus();}
    });
    dialog.addEventListener('click', e => {
      if (e.target !== dialog) return;
      const box = dialog.getBoundingClientRect();
      if (e.clientX < box.left || e.clientX > box.right || e.clientY < box.top || e.clientY > box.bottom) dialog.close();
    });
    dialog.addEventListener('close', () => {
      browseAnimation?.cancel();
      document.body.classList.remove('dialog-open');
      lastProductButton?.focus({preventScroll: true});
    });
    document.getElementById('dialog-visit').addEventListener('click', () => dialog.close());
  } else {
    document.querySelectorAll('[data-product-open]').forEach(button => button.hidden = true);
    document.body.classList.remove('is-enhanced');
  }

  // Keep a quiet navigation cue as the visitor moves between the home sections.
  if (document.body.classList.contains('home-page') && 'IntersectionObserver' in window) {
    const sectionLinks = [...document.querySelectorAll('.main-nav a[href^="/#"]')];
    const sectionObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        sectionLinks.forEach(link => {
          if (link.hash === '#' + entry.target.id) link.setAttribute('aria-current', 'location');
          else link.removeAttribute('aria-current');
        });
      });
    }, {rootMargin: '-15% 0px -65% 0px', threshold: 0});
    document.querySelectorAll('#collection, #visit, .hero, #story, .instruction-callout').forEach(section => sectionObserver.observe(section));
  }

  document.querySelectorAll('[data-rail]').forEach(rail => {
    const buttons = [...document.querySelectorAll('[data-rail-target="' + rail.id + '"]')];
    let pending = false;
    const update = () => {
      buttons.forEach(button => {
        const backwards = Number(button.dataset.railStep) < 0;
        button.disabled = backwards ? rail.scrollLeft <= 2 : rail.scrollLeft >= rail.scrollWidth - rail.clientWidth - 3;
      });
      pending = false;
    };
    const move = step => {
      const card = rail.firstElementChild;
      const distance = card ? card.getBoundingClientRect().width + parseFloat(getComputedStyle(rail).gap || 0) : rail.clientWidth;
      rail.scrollBy({left: step * distance, behavior: reduced.matches ? 'instant' : 'smooth'});
    };
    buttons.forEach(button => button.addEventListener('click', () => move(Number(button.dataset.railStep))));
    rail.addEventListener('keydown', e => {
      if (e.target !== rail || !['ArrowLeft', 'ArrowRight'].includes(e.key)) return;
      e.preventDefault();move(e.key === 'ArrowLeft' ? -1 : 1);
    });
    rail.addEventListener('scroll', () => {if (!pending) {pending = true;requestAnimationFrame(update);}}, {passive: true});
    window.addEventListener('resize', update);
    update();
  });

  // Fine-pointer depth stays small; no perpetual animation or touch interception.
  const depth = document.querySelector('[data-depth]');
  const fine = window.matchMedia('(pointer: fine)');
  let depthAnimation = null;
  let depthFrame = null;
  let pointer = null;
  const resetDepth = () => {
    if (depthFrame !== null) cancelAnimationFrame(depthFrame);
    depthFrame = null;
    depthAnimation?.cancel();depthAnimation = null;
  };
  if (depth) {
    const surface = depth.querySelector('.hero-image');
    depth.addEventListener('pointermove', e => {
      if (reduced.matches || !fine.matches || !surface.animate) return;
      pointer = {x: e.clientX, y: e.clientY};
      if (depthFrame !== null) return;
      depthFrame = requestAnimationFrame(() => {
        depthFrame = null;
        const box = depth.getBoundingClientRect();
        const x = Math.max(-1, Math.min(1, (pointer.x - box.left) / box.width * 2 - 1));
        const y = Math.max(-1, Math.min(1, (pointer.y - box.top) / box.height * 2 - 1));
        const current = getComputedStyle(surface).transform;
        depthAnimation?.cancel();
        depthAnimation = surface.animate([{transform: current}, {transform: 'perspective(1000px) rotateX(' + (-y * 1.8) + 'deg) rotateY(' + (x * 2.1) + 'deg)'}], {duration: 300, easing: 'ease-out', fill: 'forwards'});
      });
    });
    depth.addEventListener('pointerleave', resetDepth);
    reduced.addEventListener('change', e => {if (e.matches) resetDepth();});
  }

  document.querySelector('.instruction-gate [role="alert"]')?.focus();
})();
