<?php
require __DIR__ . '/includes/bootstrap.php';
$u = current_user();
$inst = setting('institution_name', 'University E-Voting');
$elections = db_all("SELECT * FROM elections WHERE status != 'DRAFT' ORDER BY CASE status WHEN 'OPEN' THEN 0 WHEN 'UPCOMING' THEN 1 ELSE 2 END, start_time DESC");
$openCount = count(array_filter($elections, fn($e) => $e['status'] === 'OPEN'));
?><!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0F2A43">
<title><?= e($inst) ?> — Secure student elections</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230F2A43'/%3E%3Cpath d='M10 16.5l4 4 8-9' stroke='%2314b8a6' stroke-width='3' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body class="min-h-full bg-slate-50 font-sans text-slate-800 antialiased">
<header class="relative overflow-hidden bg-navy text-white">
  <div class="bg-dots-white pointer-events-none absolute inset-0 opacity-30"></div>
  <div class="pointer-events-none absolute -right-32 -top-32 h-96 w-96 rounded-full bg-teal-500/20 blur-3xl"></div>
  <div class="pointer-events-none absolute -left-24 bottom-0 h-72 w-72 rounded-full bg-blue-500/20 blur-3xl"></div>
  <div class="relative mx-auto max-w-6xl px-6">
    <nav class="flex items-center justify-between py-5">
      <span class="flex items-center gap-2.5">
        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-teal-600 shadow-lg ring-1 ring-white/20">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </span>
        <span class="text-[15px] font-bold tracking-tight"><?= e($inst) ?></span>
      </span>
      <a class="btn btn-outline !border-white/20 !bg-white/10 !text-white backdrop-blur hover:!bg-white/20 btn-sm sm:!px-4 sm:!py-2 sm:!text-sm" href="<?= e(url($u ? home_for($u) : 'login.php')) ?>"><?= $u ? 'Go to dashboard →' : 'Sign in' ?></a>
    </nav>
    <div class="grid items-center gap-10 pb-16 pt-8 lg:grid-cols-[1.1fr_.9fr] lg:pb-24 lg:pt-12">
      <div class="animate-fade-up">
        <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-xs font-semibold tracking-wide text-teal-100 ring-1 ring-white/15">
          <span class="h-2 w-2 rounded-full <?= $openCount ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400' ?>"></span>
          <?= $openCount ? $openCount . ' election' . ($openCount === 1 ? '' : 's') . ' open now' : 'Secure campus voting' ?>
        </p>
        <h1 class="text-balance mt-5 text-4xl font-extrabold leading-[1.08] tracking-tight sm:text-5xl">Vote in your student elections from anywhere on campus.</h1>
        <p class="mt-5 max-w-xl text-[15px] leading-relaxed text-slate-300 sm:text-base">Sign in with your student ID, choose your candidates, review your ballot, and submit. You get a confirmation ID that proves your vote counted — without revealing who you chose.</p>
        <div class="mt-8 flex flex-wrap gap-3">
          <?php if (!$u): ?><a class="btn btn-teal btn-lg shadow-lift" href="<?= e(url('login.php')) ?>">Sign in to vote</a><?php else: ?><a class="btn btn-teal btn-lg shadow-lift" href="<?= e(url(home_for($u))) ?>">Go to dashboard</a><?php endif; ?>
          <a class="btn btn-lg !border-white/20 !bg-white/10 !text-white backdrop-blur hover:!bg-white/20" href="#elections">View elections</a>
        </div>
        <dl class="mt-10 grid max-w-lg grid-cols-3 gap-6 border-t border-white/10 pt-6">
          <div><dt class="text-xs font-medium uppercase tracking-wider text-slate-400">Ballot secrecy</dt><dd class="mt-1 text-lg font-bold">100%</dd></div>
          <div><dt class="text-xs font-medium uppercase tracking-wider text-slate-400">One student</dt><dd class="mt-1 text-lg font-bold">One vote</dd></div>
          <div><dt class="text-xs font-medium uppercase tracking-wider text-slate-400">Audit trail</dt><dd class="mt-1 text-lg font-bold">Every action</dd></div>
        </dl>
      </div>
      <div class="relative hidden lg:block">
        <div class="card !border-white/10 !bg-white/[.07] p-6 shadow-lift backdrop-blur-md">
          <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-white">Live ballot status</p>
            <span class="badge bg-emerald-500/15 text-emerald-300 ring-emerald-400/20"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>Secure</span>
          </div>
          <div class="mt-4 space-y-3">
            <div class="rounded-xl bg-white/[.06] p-4 ring-1 ring-white/10"><p class="text-xs text-slate-400">Step 1 — Authenticate</p><p class="mt-1 text-sm font-semibold text-white">Student ID + password, rate-limited</p></div>
            <div class="rounded-xl bg-white/[.06] p-4 ring-1 ring-white/10"><p class="text-xs text-slate-400">Step 2 — Review</p><p class="mt-1 text-sm font-semibold text-white">Confirm every choice before submit</p></div>
            <div class="rounded-xl bg-teal-500/15 p-4 ring-1 ring-teal-400/25"><p class="text-xs text-teal-200">Step 3 — Confirmation ID</p><p class="mt-1 font-mono text-lg font-bold tracking-widest text-white">EVT-••••-••••</p></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="relative h-6 bg-slate-50 [clip-path:ellipse(60%_100%_at_50%_100%)]"></div>
</header>

<main id="elections" class="mx-auto max-w-6xl px-6 py-12 lg:py-16">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="page-eyebrow">Election information</p>
      <h2 class="mt-1.5 text-2xl font-extrabold tracking-tight text-navy sm:text-3xl">What's on the ballot</h2>
    </div>
    <?php if (!$u): ?><a href="<?= e(url('login.php')) ?>" class="btn btn-outline">Sign in to participate</a><?php endif; ?>
  </div>

  <?php if (!$elections): ?>
    <div class="empty-state mt-8">
      <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path d="M8 3v4m8-4v4M4 10h16M5 5h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"/></svg>
      </span>
      <p class="mt-4 font-bold text-slate-900">No elections announced yet</p>
      <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">Check back soon. Open elections will appear here automatically.</p>
    </div>
  <?php endif; ?>

  <div class="mt-6 grid gap-5 md:grid-cols-2">
    <?php foreach ($elections as $el): ?>
      <article class="card card-hover animate-fade-up p-6">
        <div class="flex items-start justify-between gap-3">
          <h3 class="text-[17px] font-bold tracking-tight text-slate-900"><?= e($el['title']) ?></h3>
          <?= status_badge($el['status']) ?>
        </div>
        <?php if ($el['description']): ?><p class="mt-2 text-sm leading-relaxed text-slate-600"><?= e($el['description']) ?></p><?php endif; ?>
        <dl class="mt-5 space-y-2 rounded-xl bg-slate-50 p-4 text-sm text-slate-600 ring-1 ring-slate-100">
          <div class="flex gap-3"><dt class="w-16 shrink-0 font-semibold text-slate-500">Opens</dt><dd><?= e(fmt_dt($el['start_time'])) ?></dd></div>
          <div class="flex gap-3"><dt class="w-16 shrink-0 font-semibold text-slate-500">Closes</dt><dd><?= e(fmt_dt($el['end_time'])) ?></dd></div>
        </dl>
        <?php if ($el['status'] === 'OPEN' && !$u): ?>
          <a href="<?= e(url('login.php')) ?>" class="btn btn-primary mt-5 w-full">Sign in to vote</a>
        <?php elseif ($el['status'] === 'OPEN' && $u): ?>
          <a href="<?= e(url(home_for($u))) ?>" class="btn btn-primary mt-5 w-full">Go vote now</a>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>

  <section class="mt-14 grid gap-5 md:grid-cols-3">
    <?php foreach ([
      ['Secret by design', 'Your name is stored separately from your choices. Tallies never reveal individual ballots.', 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3zM9 12l2 2 4-4'],
      ['Double-vote proof', 'One ballot per student per election, enforced by the database — not just the interface.', 'M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
      ['Fully auditable', 'Logins, votes and admin actions leave a tamper-evident trail for review.', 'M4 20V10m6 10V4m6 16v-7m4 7H2'],
    ] as [$t, $d, $p]): ?>
      <div class="card p-6">
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-navy-50 text-navy ring-1 ring-navy-100"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="<?= $p ?>"/></svg></span>
        <h3 class="mt-4 font-bold text-slate-900"><?= $t ?></h3>
        <p class="mt-1.5 text-sm leading-relaxed text-slate-600"><?= $d ?></p>
      </div>
    <?php endforeach; ?>
  </section>
</main>

<footer class="border-t border-slate-200 bg-white">
  <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-6 py-6 text-[13px] text-slate-500 sm:flex-row">
    <p><span class="font-bold text-navy"><?= e($inst) ?></span> · Secure student elections</p>
    <p>Ballot secrecy protected · One student, one vote</p>
  </div>
</footer>
</body>
</html>
