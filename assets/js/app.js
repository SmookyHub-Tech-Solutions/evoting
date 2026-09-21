// Shared website behaviors — plain-language guide.
// This small script powers the menu, print buttons, confirmation
// popups, auto-submitting dropdowns, and the password Show/Hide button.
// It changes how pages act, not what data is stored.
document.addEventListener('DOMContentLoaded', function () {
  // Sidebar toggle: finds the side menu, its background overlay, and the menu button.
  var sb = document.getElementById('sidebar');
  var ov = document.getElementById('overlay');
  var btn = document.getElementById('menuBtn');
  // Menu open/close: slides the sidebar and shows or hides the overlay.
  function toggle() {
    if (sb) sb.classList.toggle('-translate-x-full');
    if (ov) ov.classList.toggle('hidden');
  }
  // Menu wiring: open/close when the button or overlay is clicked.
  if (btn) btn.addEventListener('click', toggle);
  if (ov) ov.addEventListener('click', toggle);

  // Print buttons: any button marked "print" opens the print dialog.
  document.querySelectorAll('[data-print]').forEach(function (b) {
    b.addEventListener('click', function () { window.print(); });
  });
  // Confirm dialogs: forms marked "confirm" ask "Are you sure?" before sending.
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
  });
  // Autosubmit dropdowns: choosing an option submits the form right away.
  document.querySelectorAll('select[data-autosubmit]').forEach(function (s) {
    s.addEventListener('change', function () { s.form.submit(); });
  });

  // Password toggle: Show/Hide buttons reveal or mask the password for readability.
  document.querySelectorAll('[data-toggle-password]').forEach(function (t) {
    t.addEventListener('click', function () {
      var input = document.getElementById(t.getAttribute('data-toggle-password'));
      if (!input) return;
      // Switch between hidden dots and readable text, then update the label.
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      t.textContent = show ? 'Hide' : 'Show';
      input.focus();
    });
  });
});
