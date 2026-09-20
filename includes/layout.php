<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/voting.php';
require_once __DIR__ . '/results.php';

function icon(string $n, string $c = 'h-5 w-5'): string
{
    static $p = [
        'home' => 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10',
        'calendar' => 'M8 3v4m8-4v4M4 10h16M5 5h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z',
        'briefcase' => 'M4 8h16v11H4zM9 8V5h6v3M4 13h16',
        'id' => 'M3 5h18v14H3zM7 10h4M7 14h3M15 10h2M15 14h2',
        'users' => 'M16 19v-1a4 4 0 00-4-4H8a4 4 0 00-4 4v1M10 11a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM20 19v-1a4 4 0 00-3-3.9M16 4.2a3.5 3.5 0 010 6.6',
        'user' => 'M12 12a4 4 0 100-8 4 4 0 000 8zM4 21v-1a6 6 0 016-6h4a6 6 0 016 6v1',
        'chart' => 'M4 20V10m6 10V4m6 16v-7m4 7H2',
        'shield' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3zM9 12l2 2 4-4',
        'cog' => 'M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0M14 4v4M8 10v4M16 16v4',
        'logout' => 'M15 12H3m0 0l4-4m-4 4l4 4M10 5V4a1 1 0 011-1h9a1 1 0 011 1v16a1 1 0 01-1 1h-9a1 1 0 01-1-1v-1',
        'check' => 'M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'lock' => 'M6 11h12v9H6zM8 11V8a4 4 0 118 0v3',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'vote' => 'M9 12l2 2 4-4m-8 8h10a2 2 0 002-2V6a2 2 0 00-2-2H7a2 2 0 00-2 2v12a2 2 0 002 2z',
        'spark' => 'M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9L12 3zM19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15z',
        'clock' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 7v5l3 2',
        'alert' => 'M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z',
        'info' => 'M12 22a10 10 0 100-20 10 10 0 000 20zM12 16v-4m0-4h.01',
    ];
    return '<svg class="' . $c . '" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . ($p[$n] ?? '') . '"/></svg>';
}

function nav_items(string $role): array
{
    if ($role === 'admin') {
        return [
            ['admin/dashboard.php', 'Dashboard', 'home', 'dashboard', 'Overview and turnout'],
            ['admin/elections.php', 'Elections', 'calendar', 'elections', 'Create and schedule'],
            ['admin/positions.php', 'Positions', 'briefcase', 'positions', 'Ballot structure'],
            ['admin/candidates.php', 'Candidates', 'id', 'candidates', 'People on ballot'],
            ['admin/voters.php', 'Voters', 'users', 'voters', 'Eligible students'],
            ['admin/results.php', 'Results', 'chart', 'results', 'Tallies and export'],
            ['admin/audit-logs.php', 'Audit logs', 'shield', 'audit', 'Security trail'],
            ['admin/users.php', 'Admin accounts', 'user', 'users', 'Staff access'],
            ['admin/settings.php', 'Settings', 'cog', 'settings', 'Institution setup'],
        ];
    }
    return [
        ['student/dashboard.php', 'Dashboard', 'home', 'dashboard', 'Your voting home'],
        ['student/election.php', 'Elections', 'vote', 'elections', 'Browse and vote'],
        ['student/results.php', 'Results', 'chart', 'results', 'Published tallies'],
        ['student/profile.php', 'Profile', 'user', 'profile', 'Account and password'],
    ];
}

function avatar(string $name, ?string $photo, string $size = 'h-12 w-12'): string
{
    if ($photo) {
        return '<img src="' . e(url('uploads/' . $photo)) . '" alt="" loading="lazy" class="' . $size . ' shrink-0 rounded-2xl object-cover ring-1 ring-slate-900/10 shadow-sm">';
    }
    $ini = '';
    foreach (array_slice(preg_split('/\s+/', trim($name)), 0, 2) as $w) {
        $ini .= strtoupper(substr($w, 0, 1));
    }
    return '<span class="' . $size . ' inline-flex shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-navy to-navy-light text-sm font-bold text-white shadow-sm ring-1 ring-navy-dark/20">' . e($ini ?: '•') . '</span>';
}

function layout_start(string $title, string $active = '', string $subtitle = ''): void
{
    $u = current_user();
    $inst = setting('institution_name', 'University E-Voting');
    $items = $u ? nav_items($u['role']) : [];
    $flashes = take_flash();
    $tone = [
        'success' => 'border-emerald-200/80 bg-emerald-50/90 text-emerald-900',
        'error' => 'border-red-200/80 bg-red-50/90 text-red-900',
        'warning' => 'border-amber-200/80 bg-amber-50/90 text-amber-900',
        'info' => 'border-blue-200/80 bg-blue-50/90 text-blue-900',
    ];
    $toneIcon = ['success' => 'check', 'error' => 'alert', 'warning' => 'clock', 'info' => 'info'];
    ?><!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0F2A43">
<title><?= e($title) ?> · <?= e($inst) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230F2A43'/%3E%3Cpath d='M10 16.5l4 4 8-9' stroke='%2314b8a6' stroke-width='3' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E">
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
<script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</head>
<body class="h-full font-sans text-slate-800 antialiased">
<div class="min-h-full lg:flex">
  <div id="overlay" class="fixed inset-0 z-30 hidden bg-navy-dark/60 backdrop-blur-sm lg:hidden"></div>
  <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-[17rem] -translate-x-full flex-col bg-navy text-slate-300 shadow-2xl transition-transform duration-200 print:hidden lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 lg:shrink-0">
    <div class="relative overflow-hidden border-b border-white/10 px-5 pb-5 pt-6">
      <div class="bg-dots-white pointer-events-none absolute inset-0 opacity-40"></div>
      <div class="relative flex items-center gap-3">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-600 text-white shadow-lg ring-1 ring-white/20">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </span>
        <div class="min-w-0">
          <p class="truncate text-[15px] font-bold leading-tight text-white"><?= e($inst) ?></p>
          <p class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-400"><span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-400"></span><?= $u && $u['role'] === 'admin' ? 'Election administration' : 'Student voting portal' ?></p>
        </div>
      </div>
    </div>
    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Primary">
      <?php foreach ($items as [$href, $label, $ic, $key, $desc]): $on = $active === $key; ?>
        <a href="<?= e(url($href)) ?>" class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition-all <?= $on ? 'bg-white font-semibold text-navy shadow-md' : 'font-medium text-slate-300 hover:bg-white/[.07] hover:text-white' ?>" <?= $on ? 'aria-current="page"' : '' ?>>
          <span class="<?= $on ? 'text-teal-700' : 'text-slate-400 group-hover:text-slate-200' ?>"><?= icon($ic) ?></span>
          <span class="min-w-0 flex-1 leading-tight"><?= e($label) ?><span class="block text-[11px] font-normal <?= $on ? 'text-slate-500' : 'text-slate-500 group-hover:text-slate-400' ?>"><?= e($desc) ?></span></span>
          <?php if ($on): ?><span class="h-2 w-2 shrink-0 rounded-full bg-teal-500"></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <?php if ($u): ?>
    <div class="border-t border-white/10 p-3">
      <div class="mb-2 flex items-center gap-3 rounded-xl bg-white/[.06] px-3 py-2.5 ring-1 ring-white/10">
        <?= avatar($u['name'], null, 'h-9 w-9 !rounded-full') ?>
        <div class="min-w-0 flex-1">
          <p class="truncate text-[13px] font-semibold text-white"><?= e($u['name']) ?></p>
          <p class="truncate text-[11px] text-slate-400"><?= e($u['student_id']) ?> · <?= $u['role'] === 'admin' ? 'Admin' : 'Student' ?></p>
        </div>
      </div>
      <form method="post" action="<?= e(url('logout.php')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-slate-300 transition hover:bg-white/[.07] hover:text-white"><?= icon('logout') ?>Log out</button>
      </form>
    </div>
    <?php endif; ?>
  </aside>

  <div class="flex min-w-0 flex-1 flex-col">
    <header class="sticky top-0 z-20 border-b border-slate-200/80 bg-white/85 px-4 py-3 shadow-[0_1px_12px_-6px_rgb(15_42_67/.15)] backdrop-blur-md print:hidden sm:px-6 lg:px-8">
      <div class="mx-auto flex w-full max-w-6xl items-center gap-3">
        <button id="menuBtn" type="button" class="rounded-xl p-2 text-slate-600 ring-1 ring-transparent transition hover:bg-slate-100 hover:ring-slate-200 lg:hidden" aria-label="Open menu"><?= icon('menu', 'h-6 w-6') ?></button>
        <div class="min-w-0">
          <h1 class="truncate text-[17px] font-bold tracking-tight text-navy sm:text-lg"><?= e($title) ?></h1>
          <?php if ($subtitle): ?><p class="truncate text-[13px] text-slate-500"><?= e($subtitle) ?></p><?php endif; ?>
        </div>
        <?php if ($u): ?>
          <div class="ml-auto flex items-center gap-3">
            <div class="hidden text-right sm:block">
              <p class="text-[13px] font-semibold leading-tight text-slate-900"><?= e($u['name']) ?></p>
              <p class="text-xs text-slate-500"><?= e($u['student_id']) ?> · <?= $u['role'] === 'admin' ? 'Administrator' : 'Student' ?></p>
            </div>
            <?= avatar($u['name'], null, 'h-9 w-9 !rounded-full ring-2 ring-white shadow') ?>
          </div>
        <?php endif; ?>
      </div>
    </header>
    <main class="mx-auto w-full max-w-6xl flex-1 p-4 sm:p-6 lg:p-8">
      <?php foreach ($flashes as $f): $t = $tone[$f['t']] ?? $tone['info']; $ic = $toneIcon[$f['t']] ?? 'info'; ?>
        <div class="alert <?= e($t) ?> animate-fade-up mb-4" role="status"><span class="mt-0.5 shrink-0 opacity-80"><?= icon($ic, 'h-5 w-5') ?></span><span><?= e($f['m']) ?></span></div>
      <?php endforeach; ?>
<?php
}

function layout_end(): void
{
    ?>
    </main>
    <footer class="border-t border-slate-200/80 bg-white/60 px-6 py-5 print:hidden">
      <div class="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-2 text-xs text-slate-500 sm:flex-row">
        <p><span class="font-semibold text-slate-700"><?= e(setting('institution_name', 'University E-Voting')) ?></span> · Secure student elections</p>
        <p class="flex items-center gap-1.5"><?= icon('lock', 'h-3.5 w-3.5') ?> Ballot secrecy protected · Audited access</p>
      </div>
    </footer>
  </div>
</div>
</body>
</html>
<?php
}
