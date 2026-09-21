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
    <h2 class="font-bold text-slate-900">Change password</h2>
    <p class="mt-1 text-[13px] text-slate-500">Use at least 8 characters with letters and numbers.</p>
    <form method="post" class="mt-5 space-y-4">
      <?= csrf_field() // Security token: proves this form really came from our site. ?>
      <div><label class="label" for="current">Current password</label><input class="input" id="current" name="current" type="password" required autocomplete="current-password" placeholder="••••••••"></div>
      <div><label class="label" for="new">New password</label><input class="input" id="new" name="new" type="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters"></div>
      <div><label class="label" for="confirm">Confirm new password</label><input class="input" id="confirm" name="confirm" type="password" required minlength="8" autocomplete="new-password" placeholder="Repeat new password"></div>
      <button class="btn btn-primary w-full !py-3" type="submit">Save new password</button>
    </form>
  </section>
</div>
<?php // Page footer: closes the layout.
layout_end();
