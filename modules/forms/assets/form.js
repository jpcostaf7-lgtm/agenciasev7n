/* Sev7n Xpress — módulo Formulário e leads | Autor: João Costa */
(function () {
  'use strict';
  // Máscara de telefone brasileiro
  document.querySelectorAll('[data-mask-phone]').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var d = inp.value.replace(/\D/g, '').slice(0, 11);
      var out = d;
      if (d.length > 2) out = '(' + d.slice(0, 2) + ') ' + d.slice(2);
      if (d.length > 7) out = '(' + d.slice(0, 2) + ') ' + d.slice(2, d.length - 4) + '-' + d.slice(d.length - 4);
      inp.value = out;
    });
  });

  document.querySelectorAll('[data-s7x-form]').forEach(function (form) {
    var msg = form.querySelector('[data-msg]');
    var btn = form.querySelector('button[type=submit]');
    var show = function (text, ok) {
      msg.textContent = text;
      msg.className = 's7x-form__msg ' + (ok ? 'is-ok' : 'is-error');
    };
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      // Validação do navegador, com mensagem em português
      var invalid = form.querySelector(':invalid');
      if (invalid) {
        var label = form.querySelector('label[for="' + invalid.id + '"]');
        var name = label ? label.textContent.replace('(opcional)', '').trim() : '';
        show(invalid.type === 'checkbox' ? 'Marque o aceite para enviar.' : 'Preencha o campo “' + name + '” corretamente.', false);
        invalid.focus();
        return;
      }
      btn.disabled = true;
      btn.textContent = 'Enviando…';
      fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          show(d.message || (d.ok ? 'Enviado.' : 'Não foi possível enviar.'), d.ok);
          if (d.ok) {
            form.reset();
            if (d.download) {
              var a = document.createElement('a');
              a.href = d.download;
              if (d.filename) { a.setAttribute('download', d.filename); }
              a.rel = 'noopener';
              document.body.appendChild(a);
              a.click();
              a.remove();
              var box = form.querySelector('[data-download]');
              if (box) {
                var link = box.querySelector('a');
                link.href = d.download;
                if (d.filename) { link.setAttribute('download', d.filename); }
                box.hidden = false;
              }
              if (window.gtag) { window.gtag('event', 'file_download', { file_name: d.filename || d.download }); }
            }
            if (window.gtag) {
              window.gtag('event', 'generate_lead');
              if (window.s7xAds && window.s7xAds.lead) window.gtag('event', 'conversion', { send_to: window.s7xAds.lead });
            } else if (window.dataLayer) {
              window.dataLayer.push({ event: 'generate_lead' });
            }
            if (window.fbq) window.fbq('track', 'Lead');
          }
        })
        .catch(function () { show('Sem conexão. Verifique a internet e tente de novo.', false); })
        .then(function () { btn.disabled = false; btn.textContent = btn.getAttribute('data-label'); });
    });
  });
})();
