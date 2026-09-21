<?php
// Profile page: shows the student's registered details and lets them change their password.
// Flow: standalone account page, not part of the voting steps. Details can only be corrected by the election office.

require __DIR__ . '/../includes/layout.php';
// Security check: only a logged-in student can view or change their own profile.
$u = require_login('student');

// Form handling: runs when the change-password form is submitted.
if (is_post()) {
    // Security check: verify the form really came from our site before trusting it.
    csrf_check();
    // Read the three password boxes.
    $cur = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['new'] ?? '');
    $conf = (string) ($_POST['confirm'] ?? '');
    // Data fetch: get the current stored password to compare against.
    $row = db_one('SELECT password FROM users WHERE id = ?', [$u['id']]);
    if (!password_verify($cur, $row['password'])) {
        // Wrong current password: log the failed attempt and show a message. Nothing is changed.
        audit('PASSWORD_CHANGE_FAILED');
        flash('error', 'Your current password is incorrect.');
    } elseif ($err = password_error($new)) {
        // New password too weak: show the rule it broke (e.g. too short).
        flash('error', $err);
    } elseif ($new !== $conf) {
        // Typo check: new password and repeat box must match exactly.
        flash('error', 'New password and confirmation do not match.');
    } else {
        // Save: store the new password in scrambled (hashed) form, never as plain text.
        db_run('UPDATE users SET password = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        // Security: give the session a fresh ID so an old stolen session cannot be reused.
        session_regenerate_id(true);
        // Log the change and confirm success.
        audit('PASSWORD_CHANGED');
        flash('success', 'Password updated.');
    }
    // Redirect back to this page: shows the message and stops the form from resubmitting on refresh.
    redirect('student/profile.php');
}
// Page header.
layout_start('Profile', 'profile', 'Your details and security');
?>
<!-- Account sections: left shows registered details (read-only), right is the password change form. -->
<div class="grid gap-5 lg:grid-cols-2">
  <section class="card animate-fade-up overflow-hidden">
    <div class="border-b border-slate-100 bg-slate-50/60 px-6 py-4">
      <h2 class="font-bold text-slate-900">Your details</h2>
      <p class="text-[13px] text-slate-500">As registered by the election office</p>
    </div>
    <div class="p-6">
      <div class="flex items-center gap-4">
        <?= avatar($u['name'], null, 'h-14 w-14 !rounded-2xl text-base') ?>
        <div><p class="font-bold text-slate-900"><?= e($u['name']) ?></p><p class="font-mono text-xs text-slate-500"><?= e($u['student_id']) ?></p></div>
      </div>
      <dl class="mt-5 space-y-3 rounded-2xl bg-slate-50 p-4 text-sm ring-1 ring-slate-200/60">
        <div class="flex justify-between gap-4"><dt class="text-slate-500">Name</dt><dd class="text-right font-semibold"><?= e($u['name']) ?></dd></div>
        <div class="flex justify-between gap-4 border-t border-slate-200/60 pt-3"><dt class="text-slate-500">Student ID</dt><dd class="font-mono text-[13px] font-semibold"><?= e($u['student_id']) ?></dd></div>
        <div class="flex justify-between gap-4 border-t border-slate-200/60 pt-3"><dt class="text-slate-500">Department</dt><dd class="text-right font-semibold"><?= e($u['department'] ?: '—') ?></dd></div>
        <div class="flex justify-between gap-4 border-t border-slate-200/60 pt-3"><dt class="text-slate-500">Email</dt><dd class="text-right font-semibold"><?= e($u['email'] ?: '—') ?></dd></div>
      </dl>
      <p class="mt-4 text-xs leading-relaxed text-slate-500">To correct these details, contact the election office with your student ID.</p>
    </div>
  </section>
  <section class="card animate-fade-up p-6 sm:p-7">
    <!-- Password form: asks for current password plus the new one twice to catch typos. -->
    <!-- The rule ticks and the match line below update live as you type (see app.js);
         the server re-checks the same 4 rules on submit, so they can never be bypassed. -->
    <h2 class="font-bold text-slate-900">Change password</h2>
    <p class="mt-1 text-[13px] text-slate-500">Use at least 8 characters with an uppercase letter, a lowercase letter and a number.</p>
    <form method="post" class="mt-5 space-y-4">
      <?= csrf_field() // Security token: proves this form really came from our site. ?>
      <div>
        <label class="label" for="current">Current password</label>
        <!-- Relative wrapper: holds the Show button inside the right edge of the box (same pattern as sign-in). -->
        <div class="relative">
          <input class="input pr-12" id="current" name="current" type="password" required autocomplete="current-password" placeholder="••••••••">
          <button type="button" data-toggle-password="current" class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-100 hover:text-slate-700">Show</button>
        </div>
      </div>
      <div>
        <label class="label" for="pw_new">New password</label>
        <div class="relative">
          <input class="input pr-12" id="pw_new" name="new" type="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters" aria-describedby="pw_checklist">
          <button type="button" data-toggle-password="pw_new" class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-100 hover:text-slate-700">Show</button>
        </div>
        <!-- Live strength checklist: each rule ticks (●) the moment the typed password meets it. -->
        <ul id="pw_checklist" class="mt-2 space-y-1 text-xs" aria-live="polite">
          <li data-check="length" class="flex items-center gap-2 text-slate-400"><span data-mark aria-hidden="true">○</span>At least 8 characters</li>
          <li data-check="upper" class="flex items-center gap-2 text-slate-400"><span data-mark aria-hidden="true">○</span>One uppercase letter (A–Z)</li>
          <li data-check="lower" class="flex items-center gap-2 text-slate-400"><span data-mark aria-hidden="true">○</span>One lowercase letter (a–z)</li>
          <li data-check="digit" class="flex items-center gap-2 text-slate-400"><span data-mark aria-hidden="true">○</span>One number (0–9)</li>
        </ul>
      </div>
      <div>
        <label class="label" for="pw_confirm">Confirm new password</label>
        <div class="relative">
          <input class="input pr-12" id="pw_confirm" name="confirm" type="password" required minlength="8" autocomplete="new-password" placeholder="Repeat new password" aria-describedby="pw_match">
          <button type="button" data-toggle-password="pw_confirm" class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-100 hover:text-slate-700">Show</button>
        </div>
        <!-- Live match indicator: confirms whether the two new-password boxes agree. -->
        <p id="pw_match" class="mt-1.5 text-xs text-slate-400" aria-live="polite">Repeat the new password above.</p>
      </div>
      <button class="btn btn-primary w-full !py-3" type="submit">Save new password</button>
    </form>
  </section>
</div>
<?php // Page footer: closes the layout.
layout_end();
