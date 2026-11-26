/* Sev7n Xpress — painel | Autor: João Costa */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var api = document.body.getAttribute('data-api') || '';
  var csrfMeta = $('meta[name=csrf]');
  var csrf = csrfMeta ? csrfMeta.content : '';
  var icons = {};
  try { icons = JSON.parse(($('#cmsIcons') || {}).textContent || '{}'); } catch (e) {}

  /* Menu no celular */
  var menuBtn = $('[data-menu]'), side = $('#side');
  if (menuBtn && side) {
    menuBtn.addEventListener('click', function () {
      var open = side.classList.toggle('is-open');
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
      if (side.classList.contains('is-open') && !side.contains(e.target) && e.target !== menuBtn) {
        side.classList.remove('is-open');
        menuBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* Contador de caracteres */
  function setupCounter(input) {
    if (input.dataset.counterReady) return;
    input.dataset.counterReady = '1';
    var max = parseInt(input.dataset.max, 10);
    var c = document.createElement('span');
    c.className = 'counter';
    input.insertAdjacentElement('afterend', c);
    var update = function () {
      var n = input.value.length;
      c.textContent = n + ' / ' + max + ' caracteres';
      c.className = 'counter ' + (n === 0 ? '' : n > max ? 'is-bad' : n > max * 0.75 ? 'is-ok' : 'is-warn');
    };
    input.addEventListener('input', update);
    update();
  }
  $$('[data-max]').forEach(setupCounter);

  /* Editor de texto */
  function setupRte(root) {
    if (root.dataset.ready) return;
    root.dataset.ready = '1';
    var area = $('.rte__area', root), store = $('textarea', root);
    var sync = function () { store.value = area.innerHTML; store.dispatchEvent(new Event('input', { bubbles: true })); };
    area.addEventListener('input', sync);
    area.addEventListener('paste', function (e) {
      // Cola como texto puro para não trazer estilos do Word
      e.preventDefault();
      var text = (e.clipboardData || window.clipboardData).getData('text/plain');
      document.execCommand('insertText', false, text);
    });
    $$('[data-cmd]', root).forEach(function (b) {
      b.addEventListener('mousedown', function (e) { e.preventDefault(); });
      b.addEventListener('click', function () {
        var cmd = b.dataset.cmd, val = null;
        if (cmd === 'createLink') {
          val = window.prompt('Endereço do link (https://..., tel:..., mailto:... ou #secao):', 'https://');
          if (!val) return;
        }
        area.focus();
        document.execCommand(cmd, false, val);
        sync();
      });
    });
  }
  $$('[data-rte]').forEach(setupRte);
  try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}

  /* Cor */
  document.addEventListener('input', function (e) {
    var t = e.target;
    if (t.matches('[data-color]')) {
      var txt = t.parentNode.querySelector('[data-color-text]');
      txt.value = t.value;
      txt.dispatchEvent(new Event('input', { bubbles: true }));
    } else if (t.matches('[data-color-text]') && /^#[0-9a-f]{6}$/i.test(t.value)) {
      t.parentNode.querySelector('[data-color]').value = t.value;
    }
  });

  /* Prévia de ícone */
  document.addEventListener('change', function (e) {
    var t = e.target;
    if (t.matches('[data-icon-select]')) {
      var p = t.parentNode.querySelector('[data-icon-preview]');
      if (p && icons[t.value]) p.innerHTML = icons[t.value];
    }
  });

  /* Repetidores */
  var uid = Date.now();
  function refreshRepeater(rep) {
    var n = $$(':scope > [data-list] > [data-item]', rep).length;
    var c = $('[data-count]', rep);
    if (c) c.textContent = n + (n === 1 ? ' item' : ' itens');
    var max = parseInt(rep.dataset.maxItems || '0', 10);
    var add = $(':scope > [data-add]', rep);
    if (add && max) add.hidden = n >= max;
  }
  function initFields(scope) {
    $$('[data-max]', scope).forEach(setupCounter);
    $$('[data-rte]', scope).forEach(setupRte);
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('button');
    if (!t) return;
    var rep = t.closest('[data-repeater]');
    if (t.matches('[data-add]') && rep) {
      var tpl = $(':scope > template[data-template]', rep);
      var html = tpl.innerHTML.replace(/__INDEX__/g, 'n' + (uid++));
      var wrap = document.createElement('div');
      wrap.innerHTML = html;
      var item = wrap.firstElementChild;
      $(':scope > [data-list]', rep).appendChild(item);
      initFields(item);
      refreshRepeater(rep);
      markDirty(rep);
      var first = $('input, textarea, select', $('.repeater__body', item));
      if (first) first.focus();
      return;
    }
    var item = t.closest('[data-item]');
    if (!item) return;
    if (t.matches('[data-remove]')) {
      if (!window.confirm('Excluir este item? Ele só sai do site depois que você salvar.')) return;
      item.remove(); refreshRepeater(rep); markDirty(rep);
    } else if (t.matches('[data-up]') && item.previousElementSibling) {
      item.parentNode.insertBefore(item, item.previousElementSibling); markDirty(rep); t.focus();
    } else if (t.matches('[data-down]') && item.nextElementSibling) {
      item.parentNode.insertBefore(item.nextElementSibling, item); markDirty(rep); t.focus();
    } else if (t.matches('[data-toggle]')) {
      var body = $(':scope > .repeater__body', item);
      var open = body.hidden;
      body.hidden = !open;
      t.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
  });
  // Título do item acompanha o campo principal
  document.addEventListener('input', function (e) {
    var item = e.target.closest('[data-item]');
    if (!item) return;
    var rep = item.closest('[data-repeater]');
    var key = rep && rep.dataset.itemLabel;
    if (key && e.target.name && e.target.name.slice(-(key.length + 2)) === '[' + key + ']') {
      $('[data-title]', item).textContent = e.target.value || 'Novo item';
    }
  });
  $$('[data-repeater]').forEach(refreshRepeater);

  /* Seletor de imagem (modal) */
  var modal = $('#mediaModal'), grid = modal && $('[data-grid]', modal), status = modal && $('[data-status]', modal);
  var currentField = null, lastFocus = null;

  function setImage(field, item) {
    $('[data-src]', field).value = item.path;
    var alt = $('[data-alt]', field);
    if (alt && !alt.value && item.alt) alt.value = item.alt;
    $('.imgfield__preview', field).innerHTML = '<img src="' + item.url + '" alt="">';
    $('[data-clear]', field).hidden = false;
    markDirty(field);
  }
  function loadMedia(selectPath) {
    grid.innerHTML = '<p class="muted">Carregando imagens…</p>';
    fetch(api + '&action=media', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) throw new Error(d.error || 'Erro');
        grid.innerHTML = '';
        if (!d.items.length) grid.innerHTML = '<p class="muted">Nenhuma imagem. Envie a primeira acima.</p>';
        d.items.forEach(function (it) {
          var b = document.createElement('button');
          b.type = 'button';
          b.title = it.path;
          b.innerHTML = '<img src="' + it.url + '" alt="" loading="lazy"><span></span>';
          b.querySelector('span').textContent = (it.theme ? 'Tema · ' : '') + it.name;
          b.addEventListener('click', function () { setImage(currentField, it); closeModal(); });
          grid.appendChild(b);
          if (selectPath && it.path === selectPath) { setImage(currentField, it); closeModal(); }
        });
      })
      .catch(function (err) { grid.innerHTML = '<p class="muted">Não foi possível carregar as imagens: ' + err.message + '</p>'; });
  }
  function openModal(field) {
    currentField = field; lastFocus = document.activeElement;
    modal.hidden = false; status.textContent = ''; status.className = 'modal__status';
    loadMedia();
    $('[data-close]', modal).focus();
  }
  function closeModal() {
    modal.hidden = true;
    if (lastFocus) lastFocus.focus();
  }
  function upload(file) {
    if (!file) return;
    var fd = new FormData();
    fd.append('file', file); fd.append('action', 'upload'); fd.append('_csrf', csrf);
    status.className = 'modal__status'; status.textContent = 'Enviando ' + file.name + '…';
    fetch(api, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) throw new Error(d.error || 'Falha no envio');
        status.textContent = 'Imagem enviada.';
        loadMedia(d.path);
      })
      .catch(function (err) { status.className = 'modal__status is-error'; status.textContent = err.message; });
  }
  if (modal) {
    document.addEventListener('click', function (e) {
      var b = e.target.closest('button');
      if (!b) return;
      var f = b.closest('[data-imgfield]');
      if (b.matches('[data-pick]') && f) openModal(f);
      if (b.matches('[data-clear]') && f) {
        $('[data-src]', f).value = '';
        $('.imgfield__preview', f).innerHTML = '<span>Sem imagem</span>';
        b.hidden = true; markDirty(f);
      }
    });
    $('[data-close]', modal).addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) closeModal(); });
    $('[data-modal-file]', modal).addEventListener('change', function () { upload(this.files[0]); this.value = ''; });
  }

  /* Arrastar e soltar */
  $$('[data-drop]').forEach(function (drop) {
    var input = $('input[type=file]', drop);
    ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-over'); }); });
    drop.addEventListener('drop', function (e) {
      if (!e.dataTransfer.files.length) return;
      if (input.hasAttribute('data-modal-file')) { upload(e.dataTransfer.files[0]); return; }
      input.files = e.dataTransfer.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });
  $$('[data-autosubmit]').forEach(function (inp) {
    inp.addEventListener('change', function () { if (inp.files.length) inp.form.submit(); });
  });

  /* Confirmações e copiar link */
  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!window.confirm(f.dataset.confirm)) e.preventDefault(); });
  });
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]');
    if (!b) return;
    var txt = b.dataset.copy, old = b.textContent;
    (navigator.clipboard ? navigator.clipboard.writeText(txt) : Promise.reject()).then(function () {
      b.textContent = 'Link copiado';
    }, function () { window.prompt('Copie o link:', txt); }).then(function () {
      setTimeout(function () { b.textContent = old; }, 1600);
    });
  });

  /* Alterações não salvas */
  var dirty = false;
  function markDirty(el) {
    var form = el && el.closest ? el.closest('[data-dirty-check]') : null;
    if (!form) return;
    dirty = true;
    var h = $('[data-dirty-hint]', form);
    if (h) h.hidden = false;
  }
  $$('[data-dirty-check]').forEach(function (form) {
    form.addEventListener('input', function (e) { markDirty(e.target); });
    form.addEventListener('change', function (e) { markDirty(e.target); });
    form.addEventListener('submit', function () {
      $$('[data-rte]', form).forEach(function (r) { $('textarea', r).value = $('.rte__area', r).innerHTML; });
      dirty = false;
    });
  });
  window.addEventListener('beforeunload', function (e) {
    if (dirty) { e.preventDefault(); e.returnValue = ''; }
  });


  /* ---------------------------------------------------------------
   * Editor de blocos
   * ------------------------------------------------------------- */
  var blocksList = $('[data-blocks]');
  var library = $('#blockLibrary');
  var newId = function () { return 'b' + Math.random().toString(16).slice(2, 10).padEnd(8, '0'); };

  function refreshBlocks() {
    if (!blocksList) return;
    var n = blocksList.children.length;
    var c = $('[data-block-count]');
    if (c) c.textContent = n + (n === 1 ? ' bloco' : ' blocos');
    var empty = $('[data-blocks-empty]');
    if (empty) empty.hidden = n > 0;
  }
  function blockSummary(block) {
    var fields = $$('.blockitem__body input[type=text], .blockitem__body textarea:not([hidden])', block)
      .filter(function (f) { return f.name.indexOf('[anchor]') === -1 && f.name.indexOf('[alt]') === -1 && !f.closest('template') && !f.closest('[data-repeater]'); });
    for (var i = 0; i < fields.length; i++) {
      var v = fields[i].value.trim();
      if (v) return v.length > 70 ? v.slice(0, 69) + '…' : v;
    }
    return '';
  }
  // Copia o que foi digitado para os atributos, para clonar sem perder nada
  function freeze(el) {
    $$('input', el).forEach(function (i) {
      if (i.type === 'checkbox' || i.type === 'radio') { i.checked ? i.setAttribute('checked', '') : i.removeAttribute('checked'); }
      else if (i.type !== 'file') i.setAttribute('value', i.value);
    });
    $$('textarea', el).forEach(function (t) { t.textContent = t.value; });
    $$('select', el).forEach(function (s) { $$('option', s).forEach(function (o) { o.selected ? o.setAttribute('selected', '') : o.removeAttribute('selected'); }); });
  }
  function prepareClone(el) {
    $$('.counter', el).forEach(function (c) { c.remove(); });
    $$('[data-counter-ready]', el).forEach(function (x) { x.removeAttribute('data-counter-ready'); });
    $$('[data-ready]', el).forEach(function (x) { x.removeAttribute('data-ready'); });
    initFields(el);
    $$('[data-repeater]', el).forEach(refreshRepeater);
  }
  function openBlock(block, open) {
    var body = $(':scope > .blockitem__body', block);
    var btn = $(':scope > .blockitem__bar [data-block-toggle]', block);
    body.hidden = !open;
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  function addBlock(type) {
    var tpl = $('template[data-block-tpl="' + type + '"]');
    if (!tpl || !blocksList) return;
    var wrap = document.createElement('div');
    wrap.innerHTML = tpl.innerHTML.replace(/__BID__/g, newId());
    var block = wrap.firstElementChild;
    blocksList.appendChild(block);
    prepareClone(block);
    openBlock(block, true);
    refreshBlocks();
    markDirty(blocksList);
    block.scrollIntoView({ behavior: 'smooth', block: 'start' });
    var first = $('.blockitem__body input[type=text]:not([name*="[anchor]"]), .blockitem__body textarea', block);
    if (first) setTimeout(function () { first.focus({ preventScroll: true }); }, 350);
  }

  if (blocksList) {
    document.addEventListener('click', function (e) {
      var t = e.target.closest('button');
      if (!t) return;
      var block = t.closest('[data-block]');
      if (t.matches('[data-block-toggle]') && block) {
        openBlock(block, $(':scope > .blockitem__body', block).hidden);
      } else if (t.matches('[data-block-up]') && block && block.previousElementSibling) {
        block.parentNode.insertBefore(block, block.previousElementSibling); markDirty(block); t.focus();
      } else if (t.matches('[data-block-down]') && block && block.nextElementSibling) {
        block.parentNode.insertBefore(block.nextElementSibling, block); markDirty(block); t.focus();
      } else if (t.matches('[data-block-remove]') && block) {
        var name = $('.blockitem__name strong', block).textContent;
        if (!window.confirm('Excluir o bloco “' + name + '”? Ele só sai do site depois que você salvar. Para esconder sem apagar, use a chave “Visível”.')) return;
        block.remove(); refreshBlocks(); markDirty(blocksList);
      } else if (t.matches('[data-block-dup]') && block) {
        freeze(block);
        var old = (($('input[name$="[type]"]', block) || {}).name || '').match(/^blocks\[([a-z0-9]+)\]/);
        if (!old) return;
        var html = block.outerHTML.split(old[1]).join(newId());
        var w = document.createElement('div');
        w.innerHTML = html;
        var copy = w.firstElementChild;
        block.parentNode.insertBefore(copy, block.nextSibling);
        prepareClone(copy);
        refreshBlocks(); markDirty(blocksList);
        copy.scrollIntoView({ behavior: 'smooth', block: 'center' });
      } else if (t.matches('[data-open-library]') && library) {
        lastFocus = t; library.hidden = false;
        var firstItem = $('[data-add-block]', library); if (firstItem) firstItem.focus();
      } else if (t.matches('[data-add-block]')) {
        library.hidden = true;
        addBlock(t.getAttribute('data-add-block'));
      } else if (t.matches('[data-close]') && library && library.contains(t)) {
        library.hidden = true; if (lastFocus) lastFocus.focus();
      }
    });
    if (library) {
      library.addEventListener('click', function (e) { if (e.target === library) library.hidden = true; });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !library.hidden) { library.hidden = true; if (lastFocus) lastFocus.focus(); } });
    }
    // Chave Visível/Oculto e resumo do bloco
    document.addEventListener('change', function (e) {
      if (!e.target.matches('[data-block-visible]')) return;
      var block = e.target.closest('[data-block]');
      block.classList.toggle('is-hidden', !e.target.checked);
      $('.minitoggle__label', block).textContent = e.target.checked ? 'Visível' : 'Oculto';
    });
    document.addEventListener('input', function (e) {
      var block = e.target.closest('[data-block]');
      if (!block || e.target.closest('[data-repeater]')) return;
      var sum = $('[data-block-summary]', block);
      var v = blockSummary(block);
      if (sum && v) sum.textContent = v;
    });
    refreshBlocks();
  }

  /* Endereço da página: só letras minúsculas, números e hífen */
  var slugInput = $('[data-page-slug]');
  if (slugInput) {
    var toSlug = function (s) {
      return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    };
    slugInput.addEventListener('blur', function () { slugInput.value = toSlug(slugInput.value); });
  }


  /* Tela de permissões: mostra o que vale para Editor */
  var permForm = $('[data-perm-form]');
  if (permForm) {
    var syncPerm = function () {
      var isEditor = ($('[data-role]:checked', permForm) || {}).value === 'editor';
      $$('[data-editor-only]', permForm).forEach(function (el) { el.hidden = !isEditor; });
      var pe = $('[data-pages-edit]', permForm);
      var box = $('[data-pages-box]', permForm);
      if (box && isEditor) box.hidden = !(pe && pe.checked);
      var some = ($('[data-pages-mode]:checked', permForm) || {}).value === 'some';
      var list = $('[data-pages-list]', permForm);
      if (list) list.hidden = !some;
    };
    permForm.addEventListener('change', syncPerm);
    syncPerm();
  }


  /* Nome do arquivo escolhido no envio de tema */
  $$('[data-theme-file]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var l = inp.parentNode.querySelector('[data-theme-file-label]');
      if (l && inp.files.length) l.innerHTML = '<strong>' + inp.files[0].name.replace(/[<>&]/g, '') + '</strong> pronto para instalar';
    });
  });

  /* Prévia do Google */
  var serp = $('[data-serp]');
  if (serp) {
    var ti = $('[name="seo[title]"]'), de = $('[name="seo[description]"]');
    var cut = function (s, n) { return s.length > n ? s.slice(0, n - 1).trim() + '…' : s; };
    var upd = function () {
      $('[data-serp-title]', serp).textContent = cut(ti.value || 'Título da página', 62);
      $('[data-serp-desc]', serp).textContent = cut(de.value || 'Escreva uma meta descrição para controlar este texto.', 160);
    };
    ti.addEventListener('input', upd); de.addEventListener('input', upd); upd();
  }


  /* ---------------------------------------------------------------
   * Arrastar e soltar: blocos da página e itens de listas (repeater)
   * Funciona com mouse e toque; pelo teclado, setas ↑ ↓ na alça.
   * ------------------------------------------------------------- */
  var live = document.createElement('div');
  live.className = 'sr-only'; live.id = 's7xSortLive'; live.setAttribute('aria-live', 'polite');
  document.body.appendChild(live);

  function sortItem(handle) { return handle.closest('[data-item], [data-block]'); }
  function siblingsOf(item) {
    var attr = item.hasAttribute('data-block') ? 'data-block' : 'data-item';
    return $$(':scope > [' + attr + ']', item.parentNode);
  }
  function announce(item) {
    var list = siblingsOf(item);
    var kind = item.hasAttribute('data-block') ? 'Bloco' : 'Item';
    live.textContent = kind + ' na posição ' + (list.indexOf(item) + 1) + ' de ' + list.length + '.';
  }
  function settle(item) {
    item.classList.add('is-dropped');
    setTimeout(function () { item.classList.remove('is-dropped'); }, 700);
    markDirty(item);
    announce(item);
  }

  var drag = null;
  document.addEventListener('pointerdown', function (e) {
    var handle = e.target.closest('[data-drag-handle]');
    if (!handle || (e.pointerType === 'mouse' && e.button !== 0)) return;
    var item = sortItem(handle);
    if (!item || siblingsOf(item).length < 2) return;
    e.preventDefault();
    var r = item.getBoundingClientRect();
    drag = { item: item, handle: handle, id: e.pointerId, grabY: e.clientY - r.top, y: e.clientY, from: siblingsOf(item).indexOf(item), raf: 0, scroll: 0 };
    try { handle.setPointerCapture(e.pointerId); } catch (err) {}
    item.classList.add('is-dragging');
    document.body.classList.add('is-sorting');
    position();
  });

  function position() {
    if (!drag) return;
    var item = drag.item;
    item.style.transform = '';
    var natural = item.getBoundingClientRect().top;
    var want = drag.y - drag.grabY;
    var list = siblingsOf(item);
    var idx = list.indexOf(item);
    // Troca com o vizinho quando o centro do item arrastado passa do centro dele
    var center = want + item.offsetHeight / 2;
    var prev = list[idx - 1], next = list[idx + 1];
    if (prev) {
      var pr = prev.getBoundingClientRect();
      if (center < pr.top + pr.height / 2) { item.parentNode.insertBefore(item, prev); return position(); }
    }
    if (next) {
      var nr = next.getBoundingClientRect();
      if (center > nr.top + nr.height / 2) { item.parentNode.insertBefore(next, item); return position(); }
    }
    natural = item.getBoundingClientRect().top;
    item.style.transform = 'translateY(' + (want - natural) + 'px)';
  }

  function autoScroll() {
    if (!drag) return;
    var edge = 70, v = 0, h = window.innerHeight;
    if (drag.y < edge) v = -Math.ceil((edge - drag.y) / 4);
    else if (drag.y > h - edge) v = Math.ceil((drag.y - (h - edge)) / 4);
    if (v) { window.scrollBy(0, v); position(); drag.raf = requestAnimationFrame(autoScroll); }
    else drag.raf = 0;
  }

  document.addEventListener('pointermove', function (e) {
    if (!drag || e.pointerId !== drag.id) return;
    e.preventDefault();
    drag.y = e.clientY;
    position();
    if (!drag.raf) drag.raf = requestAnimationFrame(autoScroll);
  }, { passive: false });

  function endDrag(e) {
    if (!drag || (e && e.pointerId !== drag.id)) return;
    var d = drag; drag = null;
    if (d.raf) cancelAnimationFrame(d.raf);
    d.item.classList.remove('is-dragging');
    d.item.style.transform = '';
    document.body.classList.remove('is-sorting');
    if (siblingsOf(d.item).indexOf(d.item) !== d.from) settle(d.item);
    d.handle.focus({ preventScroll: true });
  }
  document.addEventListener('pointerup', endDrag);
  document.addEventListener('pointercancel', endDrag);

  // Teclado: na alça, ↑ ↓ movem uma posição; Home/End levam ao início/fim
  document.addEventListener('keydown', function (e) {
    var handle = e.target.closest && e.target.closest('[data-drag-handle]');
    if (!handle) return;
    var item = sortItem(handle);
    if (!item) return;
    var list = siblingsOf(item), idx = list.indexOf(item), to = null;
    if (e.key === 'ArrowUp' && idx > 0) to = list[idx - 1];
    else if (e.key === 'ArrowDown' && idx < list.length - 1) to = list[idx + 1].nextElementSibling || 'end';
    else if (e.key === 'Home' && idx > 0) to = list[0];
    else if (e.key === 'End' && idx < list.length - 1) to = 'end';
    else return;
    e.preventDefault();
    if (to === 'end') item.parentNode.appendChild(item); else item.parentNode.insertBefore(item, to);
    handle.focus();
    item.scrollIntoView({ block: 'nearest' });
    settle(item);
  });
})();
