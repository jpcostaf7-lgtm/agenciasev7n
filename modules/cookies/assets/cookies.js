/* Sev7n Xpress — módulo Cookies LGPD | Autor: João Costa */
(function () {
  'use strict';
  var box = document.querySelector('[data-s7x-cookie]');
  if (!box) return;
  var days = parseInt(box.getAttribute('data-days'), 10) || 180;
  var read = function () { var m = document.cookie.match(/(?:^|; )s7x_consent=([^;]+)/); return m ? m[1] : ''; };
  var write = function (v) {
    document.cookie = 's7x_consent=' + v + '; Max-Age=' + (days * 86400) + '; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
  };
  // Ativa os scripts que estavam aguardando consentimento
  var activate = function () {
    document.querySelectorAll('script[type="text/plain"][data-s7x-consent]').forEach(function (old) {
      var s = document.createElement('script');
      for (var i = 0; i < old.attributes.length; i++) {
        var a = old.attributes[i];
        if (a.name !== 'type' && a.name !== 'data-s7x-consent') s.setAttribute(a.name, a.value);
      }
      s.text = old.text;
      old.parentNode.replaceChild(s, old);
    });
  };
  var show = function () { box.hidden = false; };
  var hide = function () { box.hidden = true; };

  var state = read();
  if (state === 'aceito') activate();
  else if (state !== 'recusado') show();

  box.querySelector('[data-s7x-cookie-accept]').addEventListener('click', function () {
    write('aceito'); hide(); activate();
  });
  box.querySelector('[data-s7x-cookie-reject]').addEventListener('click', function () {
    var was = read();
    write('recusado'); hide();
    if (was === 'aceito') location.reload(); // descarrega os scripts já ativos
  });
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-s7x-cookie-prefs]');
    if (!a) return;
    e.preventDefault(); show();
    var btn = box.querySelector('button'); if (btn) btn.focus();
  });
})();
