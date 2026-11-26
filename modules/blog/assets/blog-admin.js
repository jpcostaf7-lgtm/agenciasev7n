/* Sev7n Xpress — Blog no painel | Autor: João Costa */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var csrf = ($('meta[name=csrf]') || {}).content || '';
  var api = document.body.getAttribute('data-api') || '';

  /* ---------- Lista: marcar todos, ações em massa e por linha ---------- */
  var bulk = $('[data-blog-bulk]');
  if (bulk) {
    var all = $('[data-blog-all]', bulk);
    if (all) all.addEventListener('change', function () { $$('input[name="ids[]"]', bulk).forEach(function (c) { c.checked = all.checked; }); });
    var doSel = $('[data-blog-do]', bulk), catSel = $('[data-blog-cat]', bulk);
    if (doSel && catSel) doSel.addEventListener('change', function () { catSel.hidden = !/cat$/.test(doSel.value); });
    bulk.addEventListener('click', function (e) {
      var b = e.target.closest('[data-blog-single]');
      if (!b) return;
      var act = b.getAttribute('data-blog-single');
      if (act === 'trash' && !window.confirm('Mandar este post para a lixeira?')) { e.preventDefault(); return; }
      bulk.querySelector('input[name=action]').value = act;
    });
    bulk.addEventListener('submit', function (e) {
      var act = bulk.querySelector('input[name=action]').value;
      if (act === 'bulk' && doSel && doSel.value === 'delete' && !window.confirm('Excluir definitivamente os posts marcados? Não dá para desfazer.')) e.preventDefault();
    });
  }
  $$('button[data-confirm]').forEach(function (b) {
    b.addEventListener('click', function (e) { if (!window.confirm(b.getAttribute('data-confirm'))) e.preventDefault(); });
  });

  /* ---------- Editor de texto ---------- */
  var area = $('[data-ed-area]'), src = $('[data-ed-src]');
  if (!area || !src) return;
  try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}
  var htmlMode = false;
  var count = $('[data-ed-count]');
  var markDirty = function () {
    var form = area.closest('[data-dirty-check]');
    var h = form && $('[data-dirty-hint]', form);
    if (h) h.hidden = false;
    window.onbeforeunload = function () { return true; };
  };
  var sync = function () {
    if (!htmlMode) src.value = area.innerHTML;
    var words = (area.innerText || '').trim().split(/\s+/).filter(Boolean).length;
    if (count) count.textContent = words + ' palavras · ' + Math.max(1, Math.round(words / 220)) + ' min de leitura';
    checkKw();
  };
  area.addEventListener('input', function () { sync(); markDirty(); });
  src.addEventListener('input', function () { markDirty(); });
  area.closest('form').addEventListener('submit', function () {
    if (!htmlMode) src.value = area.innerHTML;
    window.onbeforeunload = null;
  });
  // Colar: mantém só a estrutura (títulos, listas, links, negrito), sem estilos do Word/Google Docs
  area.addEventListener('paste', function (e) {
    var cd = e.clipboardData || window.clipboardData;
    var html = cd.getData('text/html');
    e.preventDefault();
    if (!html) { document.execCommand('insertText', false, cd.getData('text/plain')); return; }
    var doc = new DOMParser().parseFromString(html, 'text/html');
    var keep = { P: 1, H2: 1, H3: 1, H4: 1, UL: 1, OL: 1, LI: 1, A: 1, STRONG: 1, B: 1, EM: 1, I: 1, BLOCKQUOTE: 1, BR: 1, TABLE: 1, THEAD: 1, TBODY: 1, TR: 1, TH: 1, TD: 1 };
    var clean = function (node) {
      $$('*', node).reverse().forEach(function (el) {
        if (/^(SCRIPT|STYLE|META|LINK|O:P)$/.test(el.tagName)) { el.remove(); return; }
        if (el.tagName === 'H1') { var h = doc.createElement('h2'); h.innerHTML = el.innerHTML; el.replaceWith(h); el = h; }
        if (!keep[el.tagName]) { el.replaceWith.apply(el, Array.prototype.slice.call(el.childNodes)); return; }
        Array.prototype.slice.call(el.attributes).forEach(function (a) { if (!(el.tagName === 'A' && a.name === 'href')) el.removeAttribute(a.name); });
      });
      return node.innerHTML;
    };
    document.execCommand('insertHTML', false, clean(doc.body));
  });

  var cmd = function (name, val) { area.focus(); document.execCommand(name, false, val || null); sync(); markDirty(); };
  $$('[data-ed]').forEach(function (b) {
    b.addEventListener('mousedown', function (e) { e.preventDefault(); });
    b.addEventListener('click', function () {
      var a = b.getAttribute('data-ed');
      if (a === 'html') {
        htmlMode = !htmlMode;
        if (htmlMode) { src.value = area.innerHTML; src.hidden = false; area.hidden = true; src.focus(); }
        else { area.innerHTML = src.value; src.hidden = true; area.hidden = false; sync(); }
        b.setAttribute('aria-pressed', htmlMode ? 'true' : 'false');
        return;
      }
      if (htmlMode) return;
      if (a.indexOf('formatBlock:') === 0) { cmd('formatBlock', '<' + a.split(':')[1] + '>'); return; }
      if (a === 'link') {
        var url = window.prompt('Endereço do link (https://... ou /pagina):', 'https://');
        if (url) cmd('createLink', url);
        return;
      }
      if (a === 'video') {
        var v = window.prompt('Cole o link do vídeo do YouTube:');
        var m = v && v.match(/(?:youtu\.be\/|v=|embed\/|shorts\/)([\w-]{11})/);
        if (m) cmd('insertHTML', '<iframe src="https://www.youtube-nocookie.com/embed/' + m[1] + '" title="Vídeo" allowfullscreen></iframe><p><br></p>');
        else if (v) window.alert('Não reconheci o link do YouTube.');
        return;
      }
      if (a === 'image') { $('[data-ed-file]').click(); return; }
      cmd(a);
    });
  });

  // Inserir imagem: envia para a biblioteca e coloca no texto com legenda
  var saved = null;
  area.addEventListener('blur', function () {
    var s = window.getSelection();
    if (s.rangeCount) saved = s.getRangeAt(0).cloneRange();
  });
  var fileIn = $('[data-ed-file]');
  fileIn.addEventListener('change', function () {
    var f = fileIn.files[0];
    if (!f) return;
    var alt = window.prompt('Descreva a imagem (texto alternativo, ajuda no Google e na acessibilidade):', '') || '';
    var fd = new FormData();
    fd.append('file', f); fd.append('alt', alt); fd.append('action', 'upload'); fd.append('_csrf', csrf);
    var status = count; if (status) status.textContent = 'Enviando imagem…';
    fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok) throw new Error(j.error || 'Falha no envio');
        area.focus();
        if (saved) { var s = window.getSelection(); s.removeAllRanges(); s.addRange(saved); }
        var cap = alt ? '<figcaption>' + alt.replace(/</g, '&lt;') + '</figcaption>' : '';
        document.execCommand('insertHTML', false, '<figure><img src="' + j.url + '" alt="' + alt.replace(/"/g, '&quot;') + '">' + cap + '</figure><p><br></p>');
        sync(); markDirty();
      })
      .catch(function (err) { window.alert('Não foi possível enviar a imagem: ' + err.message); sync(); });
    fileIn.value = '';
  });

  /* ---------- Endereço gerado pelo título ---------- */
  var title = $('[data-blog-title]'), slug = $('[data-blog-slug]');
  var toSlug = function (s) { return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 90); };
  if (title && slug) {
    var auto = slug.value === '';
    title.addEventListener('input', function () { if (auto) slug.value = toSlug(title.value); });
    slug.addEventListener('input', function () { auto = false; });
    slug.addEventListener('blur', function () { slug.value = toSlug(slug.value); });
  }

  /* ---------- Prévia no Google e conferência da palavra-chave ---------- */
  var seoT = $('[name="post[seo][title]"]'), seoD = $('[name="post[seo][description]"]'), exc = $('[name="post[excerpt]"]'), kw = $('[name="post[seo][focus_keyword]"]');
  var serpT = $('[data-serp-t]'), serpD = $('[data-serp-d]'), kwList = $('[data-blog-kw]');
  var norm = function (s) { return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
  function updSerp() {
    if (serpT) serpT.textContent = (seoT && seoT.value) || (title && title.value) || 'Título do post';
    if (serpD) serpD.textContent = ((seoD && seoD.value) || (exc && exc.value) || (area.innerText || '').slice(0, 160)).slice(0, 160);
  }
  function checkKw() {
    updSerp();
    if (!kwList) return;
    var k = norm(kw && kw.value).trim();
    if (!k) { kwList.innerHTML = '<li class="check check--info">Informe a palavra-chave foco para conferir o SEO do post.</li>'; return; }
    var text = norm(area.innerText);
    var first = norm((area.querySelector('p') || {}).innerText || '');
    var heads = $$('h2,h3', area).map(function (h) { return norm(h.innerText); });
    var words = text.split(/\s+/).filter(Boolean).length || 1;
    var hits = text.split(k).length - 1;
    var dens = (hits * k.split(/\s+/).length / words * 100);
    var items = [
      [norm(title && title.value).indexOf(k) > -1, 'Palavra-chave no título'],
      [norm((seoD && seoD.value) || (exc && exc.value)).indexOf(k) > -1, 'Palavra-chave na meta descrição'],
      [first.indexOf(k) > -1, 'Palavra-chave no primeiro parágrafo'],
      [heads.filter(function (h) { return h.indexOf(k) > -1; }).length > 0, 'Palavra-chave em algum subtítulo (H2/H3)'],
      [dens >= 0.5 && dens <= 2.5, 'Densidade da palavra-chave: ' + dens.toFixed(1) + '% (ideal entre 0,5% e 2,5%)'],
      [words >= 600, 'Tamanho do texto: ' + words + ' palavras (ideal 600 ou mais)'],
      [$$('a', area).length > 0, 'Texto tem links'],
      [$$('h2', area).length >= 2, 'Texto organizado em subtítulos H2']
    ];
    kwList.innerHTML = items.map(function (i) { return '<li class="check ' + (i[0] ? '' : 'check--warn') + '">' + i[1] + '</li>'; }).join('');
  }
  [seoT, seoD, exc, kw, title].forEach(function (el) { if (el) el.addEventListener('input', checkKw); });
  sync();
})();
