/* Sev7n Xpress — dica dos gráficos | Autor: João Costa */
(function () {
  document.querySelectorAll('.rchart').forEach(function (fig) {
    var tip = fig.querySelector('.rchart__tip');
    var show = function (bar) {
      var r = bar.getBoundingClientRect(), f = fig.getBoundingClientRect();
      tip.textContent = bar.getAttribute('data-tip');
      tip.style.left = (r.left - f.left + r.width / 2) + 'px';
      var p = bar.querySelector('path');
      tip.style.top = ((p ? p.getBoundingClientRect().top : r.top) - f.top) + 'px';
      tip.hidden = false;
    };
    fig.querySelectorAll('.rbars__bar').forEach(function (bar) {
      bar.addEventListener('mouseenter', function () { show(bar); });
      bar.addEventListener('focus', function () { show(bar); });
      bar.addEventListener('mouseleave', function () { tip.hidden = true; });
      bar.addEventListener('blur', function () { tip.hidden = true; });
    });
  });
})();
