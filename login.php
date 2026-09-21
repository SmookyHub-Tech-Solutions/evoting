<?php
/**
 * Sign-in page (login.php) — plain-language guide.
 *
 * What this page is: the door where students and staff enter
 * with their User ID and password.
 *
 * How it works, step by step:
 * 1. If someone is already signed in, send them to their dashboard.
 * 2. Clean up old login-attempt records (older than one day).
 * 3. When the form is submitted, check the security token, then
 *    apply rate limits (locks out repeated guessing), verify the
 *    password, start a fresh session, and record the result.
 * 4. Show the split-screen design: explanation on the left,
 *    sign-in form on the right.
 */

// Load shared setup: database, sessions, and security helpers.
require __DIR__ . '/includes/bootstrap.php';

// Auth check: already signed in means no need to see this page again.
if ($cu = current_user()) {
    redirect(home_for($cu));
}
// Housekeeping: delete login-attempt notes older than 24 hours.
db_run('DELETE FROM login_attempts WHERE created_at < ?', [time() - 86400]);

// These hold the error message and the typed User ID (so it can be re-shown).
$error = '';
$sid = '';
// Form submitted: this block checks the login details.
if (is_post()) {
    // Security token check: proves the form came from our own site.
    csrf_check();
    // Read what the visitor typed (User ID and password).
    $sid = post('student_id');
    $pw = (string) ($_POST['password'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $since = time() - 900;
    $key = strtolower($sid);
    // Rate-limit counts: how many recent failures for this ID and this network address.
    $failsId = (int) db_val('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND created_at > ?', [$key, $since]);
    $failsIp = (int) db_val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > ?', [$ip, $since]);

    // Rate-limit block: too many guesses means a 15-minute pause.
    if ($failsId >= 5 || $failsIp >= 20) {
        audit('LOGIN_BLOCKED', 'Rate limit reached', $sid !== '' ? $sid : 'UNKNOWN');
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        // Look up the account matching the typed User ID.
        $user = db_one('SELECT * FROM users WHERE student_id = ?', [$sid]);
        // Always run a password check, even if no account exists,
        // so outsiders cannot guess valid IDs from response speed.
        $ok = password_verify($pw, $user['password'] ?? password_hash('placeholder', PASSWORD_DEFAULT));
        // Success path: correct password plus an active account signs the user in.
        if ($user && $ok && $user['status'] === 'active') {
            // Give the session a fresh ID (protects against session fixation).
            session_regenerate_id(true);
            // Remember who is signed in and when they last acted.
            $_SESSION['uid'] = (int) $user['id'];
            $_SESSION['sid'] = $user['student_id'];
            $_SESSION['last'] = time();
            // Clear past failed-attempt notes for this ID after a good login.
            db_run('DELETE FROM login_attempts WHERE identifier = ?', [$key]);
            // Upgrade the stored password format if it uses an older style.
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                db_run('UPDATE users SET password = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $user['id']]);
            }
            audit('LOGIN_SUCCESS');
            redirect(home_for($user));
        }
        // Failure path: record the attempt and show a generic message
        // (generic wording avoids revealing which IDs exist).
        db_run('INSERT INTO login_attempts(identifier,ip,created_at) VALUES (?,?,?)', [$key, $ip, time()]);
        audit('LOGIN_FAILED', $user && $user['status'] !== 'active' ? 'Inactive account' : '', $sid !== '' ? $sid : 'UNKNOWN');
        $error = 'Invalid User ID or password.';
    }
}
// Load the school name and any one-time notice messages for display.
$inst = setting('institution_name', 'University E-Voting');
$flashes = take_flash();
?><!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0F2A43">
<title>Sign in · <?= e($inst) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230F2A43'/%3E%3Cpath d='M10 16.5l4 4 8-9' stroke='%2314b8a6' stroke-width='3' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
<script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</head>
<body class="min-h-full bg-slate-100 font-sans text-slate-800 antialiased">
<div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
  <!-- Left panel (large screens): friendly explanation of safe voting. -->
  <section class="relative hidden flex-col justify-between overflow-hidden bg-navy p-10 text-white lg:flex xl:p-14">
    <div class="bg-dots-white pointer-events-none absolute inset-0 opacity-30"></div>
    <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-teal-500/20 blur-3xl"></div>
    <div class="pointer-events-none absolute -left-20 bottom-10 h-72 w-72 rounded-full bg-blue-500/20 blur-3xl"></div>
    <a href="<?= e(url('index.php')) ?>" class="relative flex items-center gap-2.5 text-[15px] font-bold">
      <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-teal-600 ring-1 ring-white/20"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
      <?= e($inst) ?>
    </a>
    <div class="relative">
      <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold text-teal-100 ring-1 ring-white/15"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>Secure · Audited · Secret ballot</p>
      <h1 class="mt-5 max-w-md text-4xl font-extrabold leading-[1.1] tracking-tight xl:text-[2.75rem]">One student,<br>one vote,<br>one clear record.</h1>
      <p class="mt-4 max-w-md leading-relaxed text-slate-300">Sign in to see the elections open to you, review your choices before you submit, and receive a confirmation ID for your records.</p>
      <div class="mt-8 grid max-w-md grid-cols-2 gap-3 text-sm">
        <div class="rounded-2xl bg-white/[.07] p-4 ring-1 ring-white/10"><p class="font-bold text-white">Secret ballot</p><p class="mt-1 text-[13px] leading-relaxed text-slate-300">Choices stored without your name.</p></div>
        <div class="rounded-2xl bg-white/[.07] p-4 ring-1 ring-white/10"><p class="font-bold text-white">No double votes</p><p class="mt-1 text-[13px] leading-relaxed text-slate-300">Enforced in the database.</p></div>
      </div>
    </div>
    <p class="relative text-[13px] text-slate-400">Your vote is stored without your name. Only the fact that you voted is recorded.</p>
  </section>

  <!-- Right panel: the actual sign-in card and form. -->
  <section class="flex items-center justify-center bg-slate-50 bg-grid-slate p-6 sm:p-10">
    <div class="w-full max-w-md animate-fade-up">
      <p class="mb-6 flex items-center gap-2 text-[15px] font-bold text-navy lg:hidden">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-navy text-white"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path d="M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
        <?= e($inst) ?>
      </p>
      <div class="card p-7 shadow-soft sm:p-8">
        <!-- Card heading: welcomes the visitor, plus a light/dark switch saved on this device. -->
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-2xl font-extrabold tracking-tight text-navy">Welcome back</h2>
            <p class="mt-1.5 text-sm text-slate-500">Use your User ID and password to continue.</p>
          </div>
          <button type="button" data-theme-toggle aria-pressed="false" aria-label="Switch dark mode on or off" title="Switch dark mode on or off" class="shrink-0 rounded-xl p-2 text-slate-500 ring-1 ring-transparent transition hover:bg-slate-100 hover:ring-slate-200 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:ring-slate-700">
            <span data-icon-moon><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/></svg></span>
            <span data-icon-sun class="hidden"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 17a5 5 0 100-10 5 5 0 000 10zM12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4l1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg></span>
          </button>
        </div>

        <?php foreach ($flashes as $f): ?>
          <!-- One-time notice, e.g. "You have been signed out." -->
          <div class="alert mt-5 border-blue-200/80 bg-blue-50/90 text-blue-900" role="status"><?= e($f['m']) ?></div>
        <?php endforeach; ?>
        <?php if ($error): ?>
          <!-- Error box: shown when sign-in fails or is temporarily blocked. -->
          <div class="alert mt-5 border-red-200/80 bg-red-50/90 text-red-900" role="alert">
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>
            <span><?= e($error) ?></span>
          </div>
        <?php endif; ?>

        <!-- Sign-in form: User ID + password + submit button. -->
        <form method="post" class="mt-6 space-y-4" autocomplete="off">
          <?= csrf_field() ?>
          <div>
            <!-- User ID field: keeps the typed value if the page reloads with an error. -->
            <label class="label" for="student_id">User ID</label>
            <div class="relative">
              <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"><svg class="h-4.5 w-4.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path d="M12 12a4 4 0 100-8 4 4 0 000 8zM4 21v-1a6 6 0 016-6h4a6 6 0 016 6v1"/></svg></span>
              <input class="input !pl-11" id="student_id" name="student_id" value="<?= e($sid) ?>" required maxlength="40" autofocus placeholder="e.g. STU001 or admin" autocapitalize="off" spellcheck="false">
            </div>
          </div>
          <div>
            <!-- Password field with a Show/Hide button for readability. -->
            <label class="label" for="password">Password</label>
            <div class="relative">
              <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path d="M6 11h12v9H6zM8 11V8a4 4 0 118 0v3"/></svg></span>
              <input class="input !pl-11 pr-12" id="password" name="password" type="password" required maxlength="200" autocomplete="current-password" placeholder="••••••••">
              <button type="button" data-toggle-password="password" class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-100 hover:text-slate-700">Show</button>
            </div>
          </div>
          <button class="btn btn-primary w-full !py-3 text-[15px]" type="submit">Sign in →</button>
        </form>
      </div>
      <p class="mt-5 text-center text-[13px] leading-relaxed text-slate-500">Forgot your password? Ask the election office to reset it.<br><a href="<?= e(url('index.php')) ?>" class="font-semibold text-blue-700 hover:underline">← Back to elections</a></p>
    </div>
  </section>
</div>
</body>
</html>
