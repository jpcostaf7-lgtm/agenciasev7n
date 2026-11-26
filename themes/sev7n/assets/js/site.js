/* Tema Agência Sev7n — Autor: João Costa */
(function () {
  'use strict';

  // Menu do celular
  var burger = document.querySelector('[data-burger]');
  var nav = document.querySelector('[data-nav]');
  if (burger && nav) {
    var close = function () {
      nav.classList.remove('is-open');
      burger.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    };
    burger.addEventListener('click', function () {
      var open = !nav.classList.contains('is-open');
      nav.classList.toggle('is-open', open);
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.style.overflow = open ? 'hidden' : '';
    });
    nav.addEventListener('click', function (e) { if (e.target.closest('a')) { close(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && nav.classList.contains('is-open')) { close(); burger.focus(); } });
  }

  // Vídeo do topo: só no computador e sem "reduzir movimento"
  var vid = document.querySelector('[data-hero-video]');
  if (vid && window.matchMedia('(min-width: 900px) and (prefers-reduced-motion: no-preference)').matches && !(navigator.connection && navigator.connection.saveData)) {
    vid.src = vid.getAttribute('data-src');
    vid.addEventListener('canplay', function () { vid.classList.add('is-on'); }, { once: true });
    var p = vid.play();
    if (p && p.catch) { p.catch(function () {}); }
  }

  // Portfólio: "ver mais"
  document.querySelectorAll('[data-pf-more]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var grid = document.getElementById(btn.getAttribute('aria-controls'));
      var first = null;
      grid.querySelectorAll('[data-more]').forEach(function (li) { li.hidden = false; if (!first) { first = li; } });
      btn.setAttribute('aria-expanded', 'true');
      btn.parentNode.hidden = true;
      if (first) { var a = first.querySelector('a'); if (a) { a.focus({ preventScroll: true }); } }
    });
  });

  // Acessar meu projeto: nome → nome.agenciasev7n.com.br
  document.querySelectorAll('[data-project]').forEach(function (form) {
    var input = form.querySelector('[data-project-input]');
    var prev = form.querySelector('[data-project-preview]');
    var domain = form.getAttribute('data-domain');
    var slug = function () {
      return input.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '').replace(/-+/g, '-').replace(/^-|-$/g, '');
    };
    input.addEventListener('input', function () {
      var s = slug();
      prev.textContent = s ? 'Vamos abrir: https://' + s + '.' + domain : '';
    });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var s = slug();
      if (!s) { input.focus(); return; }
      if (window.gtag) { window.gtag('event', 'acessar_projeto', { projeto: s }); }
      window.open('https://' + s + '.' + domain, '_blank', 'noopener');
    });
  });

  // Cliques no WhatsApp viram evento no Google Analytics
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-wa]');
    if (!a) { return; }
    try {
      if (window.gtag) { window.gtag('event', 'whatsapp_click', { page_path: location.pathname }); }
      else if (window.dataLayer) { window.dataLayer.push({ event: 'whatsapp_click', page_path: location.pathname }); }
    } catch (err) {}
  });
})();
