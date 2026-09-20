document.addEventListener('DOMContentLoaded', function () {
  var sb = document.getElementById('sidebar');
  var ov = document.getElementById('overlay');
  var btn = document.getElementById('menuBtn');
  function toggle() {
    if (sb) sb.classList.toggle('-translate-x-full');
    if (ov) ov.classList.toggle('hidden');
  }
  if (btn) btn.addEventListener('click', toggle);
  if (ov) ov.addEventListener('click', toggle);

  document.querySelectorAll('[data-print]').forEach(function (b) {
    b.addEventListener('click', function () { window.print(); });
  });
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
  });
  document.querySelectorAll('select[data-autosubmit]').forEach(function (s) {
    s.addEventListener('change', function () { s.form.submit(); });
  });

  document.querySelectorAll('[data-toggle-password]').forEach(function (t) {
    t.addEventListener('click', function () {
      var input = document.getElementById(t.getAttribute('data-toggle-password'));
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      t.textContent = show ? 'Hide' : 'Show';
      input.focus();
    });
  });
});
