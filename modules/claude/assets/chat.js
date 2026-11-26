/* Sev7n Xpress — Assistente Claude no painel | Autor: João Costa */
(function () {
  'use strict';
  var root = document.querySelector('[data-chat]');
  if (!root) return;
  var log = root.querySelector('[data-chat-log]');
  var empty = root.querySelector('[data-chat-empty]');
  var form = root.querySelector('[data-chat-form]');
  var input = root.querySelector('[data-chat-input]');
  var sendBtn = root.querySelector('[data-chat-send]');
  var api = document.body.getAttribute('data-api');
  var csrf = (document.querySelector('meta[name=csrf]') || {}).content || '';
  var KEY = 's7x_chat_v1';
  var state = { messages: [], view: [] };
  try { state = JSON.parse(sessionStorage.getItem(KEY)) || state; } catch (e) {}
  var busy = false;

  function save() { try { sessionStorage.setItem(KEY, JSON.stringify(state)); } catch (e) {} }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  // Markdown simples: negrito, código, listas, quebras de linha
  function md(text) {
    var lines = esc(text).split('\n'), html = '', inList = false;
    lines.forEach(function (l) {
      var m = l.match(/^\s*(?:[-*•]|\d+\.)\s+(.*)$/);
      if (m) { if (!inList) { html += '<ul>'; inList = true; } html += '<li>' + m[1] + '</li>'; return; }
      if (inList) { html += '</ul>'; inList = false; }
      html += l.trim() === '' ? '<br>' : '<p>' + l + '</p>';
    });
    if (inList) html += '</ul>';
    return html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>').replace(/`([^`]+)`/g, '<code>$1</code>').replace(/(<br>)+/g, '');
  }
  function bubble(item) {
    var el = document.createElement('div');
    el.className = 'msg msg--' + item.who;
    if (item.who === 'actions') {
      el.innerHTML = item.actions.map(function (a) {
        return '<span class="chip ' + (a.ok ? (a.write ? 'chip--write' : '') : 'chip--error') + '">' + (a.ok ? (a.write ? '✎ ' : '· ') : '⚠ ') + esc(a.label) + '</span>';
      }).join('');
    } else if (item.who === 'error') {
      el.innerHTML = '<p>' + esc(item.text) + '</p><button type="button" class="btn btn--small btn--ghost" data-retry>Tentar de novo</button>';
    } else {
      el.innerHTML = item.who === 'user' ? '<p>' + esc(item.text).replace(/\n/g, '<br>') + '</p>' : md(item.text);
      if (item.undo) {
        el.insertAdjacentHTML('beforeend', '<div class="msg__undo"><button type="button" class="btn btn--small btn--ghost" data-undo="' + esc(item.undo) + '"' + (item.undone ? ' disabled' : '') + '>' + (item.undone ? 'Alterações desfeitas' : 'Desfazer estas alterações') + '</button> <a class="btn btn--small btn--ghost" href="' + esc(root.getAttribute('data-site') || '/') + '" target="_blank" rel="noopener">Ver site</a></div>');
      }
    }
    return el;
  }
  function render() {
    log.querySelectorAll('.msg, .typing').forEach(function (n) { n.remove(); });
    empty.hidden = state.view.length > 0;
    state.view.forEach(function (it) { log.appendChild(bubble(it)); });
    if (busy) {
      var t = document.createElement('div');
      t.className = 'typing';
      t.innerHTML = '<span></span><span></span><span></span> Claude está trabalhando…';
      log.appendChild(t);
    }
    log.scrollTop = log.scrollHeight;
  }
  function setBusy(b) { busy = b; sendBtn.disabled = b; input.disabled = b; render(); }
  function lastAssistantText(msgs) {
    var m = msgs[msgs.length - 1];
    for (var i = msgs.length - 1; i >= 0; i--) { if (msgs[i].role === 'assistant') { m = msgs[i]; break; } }
    if (!m || typeof m.content === 'string') return m ? m.content : '';
    return m.content.filter(function (b) { return b.type === 'text'; }).map(function (b) { return b.text; }).join('\n\n');
  }

  function step(turnUndo, rounds) {
    var fd = new FormData();
    fd.append('action', 'claude_chat');
    fd.append('_csrf', csrf);
    fd.append('messages', JSON.stringify(state.messages));
    fd.append('context', document.title);
    return fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Resposta inválida do servidor (código ' + r.status + ').' }; }); })
      .then(function (d) {
        if (!d.ok) throw new Error(d.error || 'Erro desconhecido.');
        state.messages = d.messages;
        var txt = lastAssistantText(d.messages);
        if (d.actions && d.actions.length) {
          if (txt && d.continue) state.view.push({ who: 'assistant', text: txt });
          state.view.push({ who: 'actions', actions: d.actions });
        }
        if (d.undo && !turnUndo) turnUndo = d.undo;
        if (d.continue && rounds < 15) { save(); render(); return step(turnUndo, rounds + 1); }
        state.view.push({ who: 'assistant', text: txt || (d.continue ? 'Parei por segurança depois de muitas etapas. Peça para continuar, se quiser.' : 'Pronto.'), undo: turnUndo });
        save();
      });
  }
  function send(text) {
    if (busy || !text.trim()) return;
    state.messages.push({ role: 'user', content: text.trim() });
    state.view.push({ who: 'user', text: text.trim() });
    input.value = '';
    setBusy(true);
    step(null, 0).catch(function (err) {
      state.view.push({ who: 'error', text: err.message });
    }).then(function () { setBusy(false); save(); render(); input.focus(); });
  }

  form.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(input.value); }
  });
  root.addEventListener('click', function (e) {
    var b = e.target.closest('button');
    if (!b) return;
    if (b.hasAttribute('data-chat-suggest')) send(b.textContent);
    if (b.hasAttribute('data-chat-reset')) {
      if (state.view.length && !window.confirm('Começar uma nova conversa? As alterações já feitas continuam no site.')) return;
      state = { messages: [], view: [] }; save(); render(); input.focus();
    }
    if (b.hasAttribute('data-retry')) {
      state.view.pop();
      // remove a última mensagem do usuário que falhou e reenvia
      var last = state.messages.pop();
      state.view.pop();
      if (last && last.role === 'user' && typeof last.content === 'string') send(last.content);
    }
    if (b.hasAttribute('data-undo')) {
      if (!window.confirm('Desfazer todas as alterações feitas pelo Claude nesta resposta?')) return;
      var fd = new FormData();
      fd.append('action', 'claude_undo'); fd.append('_csrf', csrf); fd.append('revision', b.getAttribute('data-undo'));
      b.disabled = true;
      fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
        if (!d.ok) { b.disabled = false; window.alert(d.error); return; }
        state.view.forEach(function (it) { if (it.undo === b.getAttribute('data-undo')) it.undone = true; });
        state.messages.push({ role: 'user', content: '[Aviso do painel: a pessoa desfez as alterações da sua última resposta. O site voltou ao estado anterior.]' });
        state.messages.push({ role: 'assistant', content: 'Entendido, as alterações foram desfeitas.' });
        save(); render();
      });
    }
  });
  render();
})();
