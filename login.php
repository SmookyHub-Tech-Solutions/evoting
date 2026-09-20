<?php
require __DIR__ . '/includes/bootstrap.php';

if ($cu = current_user()) {
    redirect(home_for($cu));
}
db_run('DELETE FROM login_attempts WHERE created_at < ?', [time() - 86400]);

$error = '';
$sid = '';
if (is_post()) {
    csrf_check();
    $sid = post('student_id');
    $pw = (string) ($_POST['password'] ?? '');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $since = time() - 900;
    $key = strtolower($sid);
    $failsId = (int) db_val('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND created_at > ?', [$key, $since]);
    $failsIp = (int) db_val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > ?', [$ip, $since]);

    if ($failsId >= 5 || $failsIp >= 20) {
        audit('LOGIN_BLOCKED', 'Rate limit reached', $sid !== '' ? $sid : 'UNKNOWN');
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $user = db_one('SELECT * FROM users WHERE student_id = ?', [$sid]);
        // Always run a hash check so response time does not reveal valid IDs.
        $ok = password_verify($pw, $user['password'] ?? password_hash('placeholder', PASSWORD_DEFAULT));
        if ($user && $ok && $user['status'] === 'active') {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int) $user['id'];
            $_SESSION['sid'] = $user['student_id'];
            $_SESSION['last'] = time();
            db_run('DELETE FROM login_attempts WHERE identifier = ?', [$key]);
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                db_run('UPDATE users SET password = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $user['id']]);
            }
            audit('LOGIN_SUCCESS');
            redirect(home_for($user));
        }
        db_run('INSERT INTO login_attempts(identifier,ip,created_at) VALUES (?,?,?)', [$key, $ip, time()]);
        audit('LOGIN_FAILED', $user && $user['status'] !== 'active' ? 'Inactive account' : '', $sid !== '' ? $sid : 'UNKNOWN');
        $error = 'Invalid User ID or password.';
    }
}
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

  <section class="flex items-center justify-center bg-slate-50 bg-grid-slate p-6 sm:p-10">
    <div class="w-full max-w-md animate-fade-up">
      <p class="mb-6 flex items-center gap-2 text-[15px] font-bold text-navy lg:hidden">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-navy text-white"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path d="M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
        <?= e($inst) ?>
      </p>
      <div class="card p-7 shadow-soft sm:p-8">
        <h2 class="text-2xl font-extrabold tracking-tight text-navy">Welcome back</h2>
        <p class="mt-1.5 text-sm text-slate-500">Use your User ID and password to continue.</p>

        <?php foreach ($flashes as $f): ?>
          <div class="alert mt-5 border-blue-200/80 bg-blue-50/90 text-blue-900" role="status"><?= e($f['m']) ?></div>
        <?php endforeach; ?>
        <?php if ($error): ?>
          <div class="alert mt-5 border-red-200/80 bg-red-50/90 text-red-900" role="alert">
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>
            <span><?= e($error) ?></span>
          </div>
        <?php endif; ?>

        <form method="post" class="mt-6 space-y-4" autocomplete="off">
          <?= csrf_field() ?>
          <div>
            <label class="label" for="student_id">User ID</label>
            <div class="relative">
              <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"><svg class="h-4.5 w-4.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path d="M12 12a4 4 0 100-8 4 4 0 000 8zM4 21v-1a6 6 0 016-6h4a6 6 0 016 6v1"/></svg></span>
              <input class="input !pl-11" id="student_id" name="student_id" value="<?= e($sid) ?>" required maxlength="40" autofocus placeholder="e.g. STU001 or admin" autocapitalize="off" spellcheck="false">
            </div>
          </div>
          <div>
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
