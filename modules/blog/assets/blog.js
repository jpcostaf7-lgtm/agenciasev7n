/* Sev7n Xpress — Blog | Autor: João Costa */
(function () {
  'use strict';
  // Conta a visualização depois que a página carrega (não atrasa a leitura)
  if (window.S7X_BLOG_VIEW && !/bot|crawl|spider|slurp/i.test(navigator.userAgent)) {
    setTimeout(function () {
      try {
        if (navigator.sendBeacon) navigator.sendBeacon(window.S7X_BLOG_VIEW);
        else fetch(window.S7X_BLOG_VIEW, { method: 'POST', keepalive: true });
      } catch (e) {}
    }, 2500);
  }
  // Copiar link
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-blog-copy]');
    if (!b) return;
    var done = function () {
      b.classList.add('is-copied');
      b.setAttribute('aria-label', 'Link copiado');
      setTimeout(function () { b.classList.remove('is-copied'); b.setAttribute('aria-label', 'Copiar link'); }, 2000);
    };
    if (navigator.clipboard) navigator.clipboard.writeText(b.getAttribute('data-blog-copy')).then(done, function () {});
  });
  // Barra de progresso da leitura
  var bar = document.querySelector('.blog-progress span'), art = document.querySelector('.blog-content');
  if (bar && art) {
    var tick = false;
    var upd = function () {
      var r = art.getBoundingClientRect(), h = r.height - window.innerHeight * 0.6;
      var p = h > 0 ? Math.min(1, Math.max(0, -r.top / h)) : 0;
      bar.style.width = (p * 100).toFixed(1) + '%';
      tick = false;
    };
    window.addEventListener('scroll', function () { if (!tick) { tick = true; requestAnimationFrame(upd); } }, { passive: true });
    upd();
  }
})();
