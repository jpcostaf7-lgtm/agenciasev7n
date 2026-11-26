/* Sev7n Xpress — Artigos com IA | Autor: João Costa */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var api = document.body.getAttribute('data-api') || '';
  var csrf = ($('meta[name=csrf]') || {}).content || '';
  function call(action, data) {
    var fd = new FormData();
    fd.append('action', action); fd.append('_csrf', csrf);
    Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
    return fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'O servidor demorou ou respondeu com erro (HTTP ' + r.status + '). Se o artigo foi criado, ele aparece em Posts do blog.' }; }); })
      .catch(function () { return { ok: false, error: 'Sem conexão com o servidor.' }; });
  }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function formData(form) { var d = {}; new FormData(form).forEach(function (v, k) { d[k] = v; }); return d; }

  /* Gerar um artigo */
  var gen = $('[data-ia-gen]');
  if (gen) {
    var out = $('[data-ia-out]'), box = $('[data-ia-result]'), go = $('[data-ia-go]', gen);
    gen.addEventListener('submit', function (e) {
      e.preventDefault();
      var d = formData(gen);
      if (!d.topic.trim() && !d.base.trim()) { $('[data-ia-topic]').focus(); return; }
      box.hidden = false; go.disabled = true;
      var t0 = Date.now(), timer = setInterval(function () { out.innerHTML = '<p>Escrevendo o artigo… ' + Math.round((Date.now() - t0) / 1000) + ' s</p>'; }, 1000);
      call('ia_generate', d).then(function (j) {
        clearInterval(timer); go.disabled = false;
        if (!j.ok) { out.innerHTML = '<div class="notice notice--error">' + esc(j.error) + '</div>'; return; }
        out.innerHTML = '<div class="ia-res">' + (j.image ? '<img src="' + esc(j.image) + '" alt="">' : '') + '<div><p class="ia-res__t">' + esc(j.title) + '</p><p class="small muted">' + esc(j.status) + ' · ' + j.words + ' palavras</p><p class="row" style="gap:.5rem"><a class="btn btn--primary btn--small" href="' + esc(j.edit) + '">Revisar e editar</a><a class="btn btn--small btn--ghost" href="' + esc(j.url) + '" target="_blank" rel="noopener">Ver no site</a></p></div></div>';
      });
    });
    var sugBtn = $('[data-ia-suggest]'), sug = $('[data-ia-sug]');
    if (sugBtn) sugBtn.addEventListener('click', function () {
      sug.hidden = false; sug.innerHTML = '<li class="muted">Pensando em pautas…</li>';
      call('ia_suggest', { category: gen.elements.category.value, count: 8 }).then(function (j) {
        if (!j.ok) { sug.innerHTML = '<li class="bad">' + esc(j.error) + '</li>'; return; }
        sug.innerHTML = j.topics.map(function (t) { return '<li><button type="button" class="linklike" data-ia-pick>' + esc(t) + '</button></li>'; }).join('');
      });
    });
    if (sug) sug.addEventListener('click', function (e) {
      var b = e.target.closest('[data-ia-pick]');
      if (b) { $('[data-ia-topic]').value = b.textContent; sug.hidden = true; }
    });
  }

  /* Lote */
  var bulk = $('[data-ia-bulk]');
  if (bulk) {
    var stop = false, stopBtn = $('[data-ia-stop]'), list = $('[data-ia-bulklist]'), st = $('[data-ia-bulkst]');
    $('[data-ia-suggest-bulk]').addEventListener('click', function () {
      var ta = $('[data-ia-topics]'); ta.placeholder = 'Pensando em pautas…';
      call('ia_suggest', { category: bulk.elements.category.value, count: 10 }).then(function (j) {
        if (!j.ok) { window.alert(j.error); return; }
        ta.value = (ta.value.trim() ? ta.value.trim() + '\n' : '') + j.topics.join('\n');
      });
    });
    stopBtn.addEventListener('click', function () { stop = true; stopBtn.disabled = true; stopBtn.textContent = 'Parando…'; });
    bulk.addEventListener('submit', function (e) {
      e.preventDefault();
      var d = formData(bulk);
      var topics = d.topics.split('\n').map(function (t) { return t.trim(); }).filter(Boolean);
      if (!topics.length) return;
      $('[data-ia-bulkres]').hidden = false; stop = false; stopBtn.hidden = false; stopBtn.disabled = false; stopBtn.textContent = 'Parar depois do atual';
      list.innerHTML = topics.map(function (t) { return '<li>' + esc(t) + ' <span class="muted small">na fila</span></li>'; }).join('');
      var i = 0, ok = 0;
      (function next() {
        if (i >= topics.length || stop) { st.textContent = 'Terminado: ' + ok + ' de ' + topics.length + ' artigo(s) criados.'; stopBtn.hidden = true; return; }
        var li = list.children[i];
        li.querySelector('span').textContent = 'escrevendo…';
        st.textContent = 'Gerando ' + (i + 1) + ' de ' + topics.length + '…';
        call('ia_generate', { topic: topics[i], category: d.category, status: d.status, words: d.words }).then(function (j) {
          li.querySelector('span').innerHTML = j.ok ? '<a href="' + esc(j.edit) + '">' + esc(j.title) + '</a> (' + esc(j.status) + ')' : '<span class="bad">' + esc(j.error) + '</span>';
          if (j.ok) ok++;
          i++; next();
        });
      })();
    });
  }

  /* Botões de teste e ações rápidas */
  $$('[data-ia-test]').forEach(function (b) {
    b.addEventListener('click', function () {
      var msg = b.parentNode.querySelector('[data-ia-test-msg]');
      b.disabled = true; msg.textContent = 'Aguarde…';
      call(b.getAttribute('data-ia-test')).then(function (j) { b.disabled = false; msg.textContent = j.ok ? j.message : j.error; msg.className = 'small ' + (j.ok ? 'ok' : 'bad'); });
    });
  });
  var now = $('[data-ia-auto-now]');
  if (now) now.addEventListener('click', function () {
    var msg = $('[data-ia-auto-msg]'); now.disabled = true;
    var t0 = Date.now(), timer = setInterval(function () { msg.textContent = 'Escolhendo a pauta e escrevendo… ' + Math.round((Date.now() - t0) / 1000) + ' s'; }, 1000);
    call('ia_auto_now').then(function (j) {
      clearInterval(timer); now.disabled = false;
      msg.innerHTML = j.ok ? esc(j.message) + ' — <a href="' + esc(j.edit) + '">abrir</a>' : '<span class="bad">' + esc(j.error) + '</span>';
    });
  });
  $$('[data-ia-images]').forEach(function (b) {
    b.addEventListener('click', function () {
      var msg = $('[data-ia-images-msg]'), inl = $('[data-ia-inline]');
      b.disabled = true; msg.textContent = 'Procurando fotos…';
      call('ia_images', { id: b.getAttribute('data-ia-images'), inline: inl && inl.checked ? 1 : '' }).then(function (j) {
        b.disabled = false; msg.textContent = j.ok ? j.message : j.error;
        if (j.ok && j.added) { window.onbeforeunload = null; setTimeout(function () { location.reload(); }, 900); }
      });
    });
  });
})();
