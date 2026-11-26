/* Analisador de SEO — Sev7n Xpress · Autor: João Costa */
(function () {
  'use strict';

  function post(endpoint, action, data) {
    var body = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData)) {
      Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
    }
    return fetch(endpoint + action, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) {
        return r.json().catch(function () { return { ok: false, error: 'O servidor demorou demais ou respondeu com erro. Tente de novo.' }; });
      })
      .catch(function () { return { ok: false, error: 'Sem conexão. Confira a internet e tente de novo.' }; });
  }

  function track(name, params) {
    try { window.dataLayer = window.dataLayer || []; window.dataLayer.push(Object.assign({ event: name }, params || {})); } catch (e) {}
    try { if (window.gtag) { window.gtag('event', name, params || {}); } } catch (e) {}
  }

  function utm() {
    try {
      var q = new URLSearchParams(location.search), out = [];
      ['utm_source', 'utm_medium', 'utm_campaign'].forEach(function (k) { if (q.get(k)) { out.push(k.replace('utm_', '') + '=' + q.get(k)); } });
      if (out.length) { sessionStorage.setItem('s7x_seo_utm', out.join('&')); }
      return sessionStorage.getItem('s7x_seo_utm') || (document.referrer ? 'ref=' + new URL(document.referrer).hostname : '');
    } catch (e) { return ''; }
  }

  function initTool(tool) {
    var endpoint = tool.getAttribute('data-endpoint');
    var form = tool.querySelector('[data-seo-form]');
    var progress = tool.querySelector('[data-seo-progress]');
    var result = tool.querySelector('[data-seo-result]');
    if (form) {
      var u = form.querySelector('[name="utm"]');
      if (u) { u.value = utm(); }
      // ?site=exemplo.com.br já preenche (link a partir de outras páginas)
      try {
        var qs = new URLSearchParams(location.search);
        var pre = qs.get('site');
        if (pre) {
          form.querySelector('[name="url"]').value = pre;
          // Veio do campo da home (?site=...&auto=1): começa sozinho
          if (qs.get('auto') === '1' && form.querySelector('[name="aceite"]') === null) {
            setTimeout(function () { start(tool, form, progress, result, endpoint); }, 1200);
          }
        }
      } catch (e) {}
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        start(tool, form, progress, result, endpoint);
      });
    }
    bindReport(tool, endpoint);
  }

  function start(tool, form, progress, result, endpoint) {
    var msg = form.querySelector('.seo-msg');
    var btn = form.querySelector('button[type="submit"]');
    var url = form.querySelector('[name="url"]');
    msg.textContent = '';
    if (!url.value.trim()) {
      msg.textContent = 'Digite o endereço do site.';
      url.focus();
      return;
    }
    btn.disabled = true;
    post(endpoint, 'iniciar', new FormData(form)).then(function (res) {
      btn.disabled = false;
      if (!res.ok) {
        msg.textContent = res.error || 'Não foi possível começar a análise.';
        return;
      }
      track('seo_analise_inicio', { site: res.host });
      form.hidden = true;
      result.innerHTML = '';
      progress.hidden = false;
      tool.querySelector('[data-seo-host]').textContent = 'Analisando ' + res.host;
      tool.querySelector('[data-seo-pagetitle]').textContent = '';
      var list = tool.querySelector('[data-seo-steps]');
      list.innerHTML = '';
      var keys = Object.keys(res.steps);
      keys.forEach(function (k) {
        var li = document.createElement('li');
        li.setAttribute('data-step', k);
        li.textContent = res.steps[k];
        list.appendChild(li);
      });
      progress.scrollIntoView({ behavior: 'smooth', block: 'center' });
      runSteps(tool, endpoint, res.id, keys, 0, form, progress, result);
    });
  }

  function runSteps(tool, endpoint, id, keys, i, form, progress, result) {
    var list = tool.querySelector('[data-seo-steps]');
    var li = list.querySelector('[data-step="' + keys[i] + '"]');
    li.className = 'is-run';
    post(endpoint, 'etapa', { id: id, etapa: keys[i] }).then(function (res) {
      if (!res.ok) {
        li.className = 'is-err';
        progress.hidden = true;
        form.hidden = false;
        var msg = form.querySelector('.seo-msg');
        msg.textContent = res.error || 'A análise não pôde ser concluída.';
        return;
      }
      li.className = 'is-done';
      if (res.preview) {
        tool.querySelector('[data-seo-pagetitle]').textContent = res.preview.title || '';
      }
      if (res.status === 'pronto' && res.html) {
        progress.hidden = true;
        result.innerHTML = res.html;
        bindReport(tool, endpoint);
        track('seo_analise_concluida', { nota: res.score });
        try { history.replaceState(null, '', location.pathname + '#relatorio-' + id); } catch (e) {}
        result.focus({ preventScroll: true });
        result.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
      }
      if (i + 1 < keys.length) {
        runSteps(tool, endpoint, id, keys, i + 1, form, progress, result);
      }
    });
  }

  function bindReport(tool, endpoint) {
    var rep = tool.querySelector('[data-seo-report]');
    if (!rep || rep.getAttribute('data-bound')) { return; }
    rep.setAttribute('data-bound', '1');
    var lead = rep.querySelector('[data-seo-lead]');
    if (lead) {
      lead.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var msg = lead.querySelector('.seo-msg');
        var btn = lead.querySelector('button[type="submit"]');
        msg.textContent = '';
        btn.disabled = true;
        post(endpoint, 'contato', new FormData(lead)).then(function (res) {
          btn.disabled = false;
          if (!res.ok) { msg.textContent = res.error || 'Não foi possível enviar.'; return; }
          track('generate_lead', { form: 'analisador_seo' });
          var wrap = rep.parentNode;
          wrap.innerHTML = res.html;
          bindReport(tool, endpoint);
          var top = wrap.querySelector('.seo-ai, .seo-sec');
          if (top) { top.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        });
      });
    }
    rep.addEventListener('click', function (ev) {
      var t = ev.target.closest('[data-seo-print],[data-seo-share],[data-seo-again],[data-seo-wa]');
      if (!t) { return; }
      if (t.hasAttribute('data-seo-print')) {
        track('file_download', { file: 'relatorio_seo_pdf' });
        preparePrint();
        window.print();
      } else if (t.hasAttribute('data-seo-share')) {
        var url = t.getAttribute('data-url'), title = t.getAttribute('data-title');
        if (navigator.share) {
          navigator.share({ title: title, url: url }).catch(function () {});
        } else if (navigator.clipboard) {
          navigator.clipboard.writeText(url).then(function () {
            var old = t.lastChild.textContent;
            t.lastChild.textContent = ' Link copiado';
            setTimeout(function () { t.lastChild.textContent = old; }, 2200);
          });
        } else {
          window.prompt('Copie o link do relatório:', url);
        }
      } else if (t.hasAttribute('data-seo-again')) {
        var form = tool.querySelector('[data-seo-form]');
        tool.querySelector('[data-seo-result]').innerHTML = '';
        if (form) {
          form.hidden = false;
          form.reset();
          var u = form.querySelector('[name="utm"]'); if (u) { u.value = utm(); }
          form.querySelector('[name="url"]').focus();
          tool.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      } else if (t.hasAttribute('data-seo-wa')) {
        track('whatsapp_click', { origem: 'relatorio_seo' });
      }
    });
  }

  // PDF: imprime só o relatório (também no Ctrl+P), numa cópia no topo da página
  function preparePrint() {
    var rep = document.querySelector('.seo-result [data-seo-report]');
    if (!rep || document.querySelector('.seo-print-root')) { return; }
    var root = document.createElement('div');
    root.className = 'seo-tool seo-print-root';
    var wrap = document.createElement('div');
    wrap.className = 'seo-wrap';
    var copy = rep.cloneNode(true);
    copy.querySelectorAll('details').forEach(function (d) { d.open = true; });
    wrap.appendChild(copy);
    root.appendChild(wrap);
    document.body.appendChild(root);
    document.documentElement.classList.add('seo-print');
  }
  function cleanPrint() {
    var root = document.querySelector('.seo-print-root');
    if (root) { root.parentNode.removeChild(root); }
    document.documentElement.classList.remove('seo-print');
  }
  window.addEventListener('beforeprint', preparePrint);
  window.addEventListener('afterprint', cleanPrint);

  document.querySelectorAll('[data-seo-tool]').forEach(initTool);
})();
