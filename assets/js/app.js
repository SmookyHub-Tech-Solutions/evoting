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

  // Theme (dark mode): remembers the visitor's choice on this device, defaults to the device setting.
  // Plain explanation: the moon/sun button flips the whole site between light and dark; the choice is saved.
  (function () {
    var KEY = 'evoting-theme';
    // Apply a theme: toggles dark styles on the page and swaps every toggle button's icon.
    function apply(mode) {
      var dark = mode === 'dark';
      document.documentElement.classList.toggle('dark', dark);
      document.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
        b.setAttribute('aria-pressed', dark ? 'true' : 'false');
        var sun = b.querySelector('[data-icon-sun]');
        var moon = b.querySelector('[data-icon-moon]');
        // In dark mode show the sun (click to go light); in light mode show the moon.
        if (sun) sun.classList.toggle('hidden', dark);
        if (moon) moon.classList.toggle('hidden', !dark);
      });
    }
    var saved = null;
    try { saved = localStorage.getItem(KEY); } catch (e) { saved = null; }
    // First visit: follow the device (OS) dark-mode setting when nothing was saved yet.
    var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    apply(saved || (prefersDark ? 'dark' : 'light'));
    // Toggle wiring: flip the theme and remember the choice for next time.
    document.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
      b.addEventListener('click', function () {
        var next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
        try { localStorage.setItem(KEY, next); } catch (e) {}
        apply(next);
      });
    });
  })();

  // Password checklist: live strength ticks + confirm-password match on the change-password form.
  // Plain explanation: as the student types, each rule lights up when met, and the repeat box
  // reports match / no match. Advisory only — the server re-checks everything on submit.
  var pwNew = document.getElementById('pw_new');
  var pwConfirm = document.getElementById('pw_confirm');
  if (pwNew) {
    // The 4 rules, mirroring password_error() on the server.
    var rules = [
      { key: 'length', test: function (v) { return v.length >= 8; } },
      { key: 'upper', test: function (v) { return /[A-Z]/.test(v); } },
      { key: 'lower', test: function (v) { return /[a-z]/.test(v); } },
      { key: 'digit', test: function (v) { return /[0-9]/.test(v); } },
    ];
    var matchLine = document.getElementById('pw_match');
    // Match line: neutral hint, green match, or red mismatch.
    function paintMatch() {
      if (!pwConfirm || !matchLine) return;
      var a = pwNew.value || '', b = pwConfirm.value || '';
      if (!b) {
        matchLine.className = 'mt-1.5 text-xs text-slate-400';
        matchLine.textContent = 'Repeat the new password above.';
      } else if (a === b) {
        matchLine.className = 'mt-1.5 text-xs font-semibold text-teal-700';
        matchLine.textContent = '● Passwords match.';
      } else {
        matchLine.className = 'mt-1.5 text-xs font-semibold text-red-700';
        matchLine.textContent = '○ Passwords do not match yet.';
      }
    }
    // Checklist: tick each rule (●) when the typed password meets it.
    function paintChecklist() {
      var v = pwNew.value || '';
      rules.forEach(function (r) {
        var li = document.querySelector('#pw_checklist [data-check="' + r.key + '"]');
        if (!li) return;
        var ok = r.test(v);
        li.className = 'flex items-center gap-2 ' + (ok ? 'font-semibold text-teal-700' : 'text-slate-400');
        var mark = li.querySelector('[data-mark]');
        if (mark) mark.textContent = ok ? '●' : '○';
      });
      paintMatch();
    }
    pwNew.addEventListener('input', paintChecklist);
    if (pwConfirm) pwConfirm.addEventListener('input', paintMatch);
    paintChecklist();
  }
});
