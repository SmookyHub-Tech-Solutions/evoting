<?php
/**
 * Admin Dashboard — election overview page.
 * Shows at a glance: how many voters are registered, how many votes were
 * cast in the current election, turnout percentage, and recent security
 * activity. Also links to every other admin task (voters, candidates, etc.).
 */
// Load shared page tools (login checks, database helpers, page layout).
require __DIR__ . '/../includes/layout.php';
// Only signed-in admins may view this page; others are sent to the login screen.
$u = require_login('admin');

// Pick which election to spotlight: the open one if there is one, otherwise the newest.
$focus = db_one("SELECT * FROM elections WHERE status = 'OPEN' ORDER BY end_time LIMIT 1")
    ?: db_one('SELECT * FROM elections ORDER BY id DESC LIMIT 1');
// Count active student voters, votes cast in the spotlight election, and turnout %.
$voters = (int) db_val("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'");
$cast = $focus ? (int) db_val('SELECT COUNT(*) FROM ballots WHERE election_id = ?', [$focus['id']]) : 0;
$pct = $voters ? round($cast / $voters * 100, 1) : 0;
// Fetch the 8 most recent security log entries for the activity table below.
$recent = db_all('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 8');

// Draw the page frame (header, menu) and title shown in the browser tab.
layout_start('Dashboard', 'dashboard', $focus ? 'Focus: ' . $focus['title'] : 'Election overview');
?>
<?php if (!$focus): ?>
  <!-- Friendly empty state: shown only when no election exists yet. -->
  <div class="card animate-fade-up mb-6 flex flex-col items-center justify-between gap-4 p-6 sm:flex-row sm:p-7">
    <div><p class="font-bold text-slate-900">No elections yet</p><p class="mt-1 text-sm text-slate-500">Create your first election to start accepting votes.</p></div>
    <a class="btn btn-primary" href="<?= e(url('admin/elections.php')) ?>">Create election →</a>
  </div>
<?php endif; ?>

<!-- Four summary cards: voter total, votes cast, turnout bar, and election status. -->
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
  <div class="stat-card animate-fade-up">
    <div class="flex items-center justify-between"><p class="text-[13px] font-semibold text-slate-500">Registered voters</p><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-700 ring-1 ring-blue-600/10"><?= icon('users', 'h-5 w-5') ?></span></div>
    <p class="mt-2 text-3xl font-extrabold tracking-tight text-navy"><?= number_format($voters) ?></p>
    <p class="mt-1 text-xs text-slate-500">Active student accounts</p>
  </div>
  <div class="stat-card animate-fade-up" style="animation-delay:60ms">
    <div class="flex items-center justify-between"><p class="text-[13px] font-semibold text-slate-500">Votes cast</p><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-teal-50 text-teal-700 ring-1 ring-teal-600/10"><?= icon('vote', 'h-5 w-5') ?></span></div>
    <p class="mt-2 text-3xl font-extrabold tracking-tight text-navy"><?= number_format($cast) ?></p>
    <p class="mt-1 truncate text-xs text-slate-500"><?= $focus ? 'in ' . e($focus['title']) : 'No election selected' ?></p>
  </div>
  <div class="stat-card animate-fade-up" style="animation-delay:120ms">
    <div class="flex items-center justify-between"><p class="text-[13px] font-semibold text-slate-500">Participation</p><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-navy-50 text-navy ring-1 ring-navy-100"><?= icon('chart', 'h-5 w-5') ?></span></div>
    <p class="mt-2 text-3xl font-extrabold tracking-tight text-navy"><?= e($pct) ?>%</p>
    <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full animate-[grow-bar_.8s_ease-out_both] rounded-full bg-gradient-to-r from-teal-600 to-blue-600" style="width: <?= e(min(100, $pct)) ?>%"></div></div>
  </div>
  <div class="stat-card animate-fade-up" style="animation-delay:180ms">
    <div class="flex items-center justify-between"><p class="text-[13px] font-semibold text-slate-500">Election status</p><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-600"><?= icon('clock', 'h-5 w-5') ?></span></div>
    <p class="mt-3"><?= $focus ? status_badge($focus['status']) : '<span class="text-slate-400">—</span>' ?></p>
    <p class="mt-2 truncate text-xs text-slate-500"><?= $focus ? 'Ends ' . e(fmt_dt($focus['end_time'])) : '' ?></p>
  </div>
</div>

<div class="mb-3 mt-10 flex items-center justify-between">
  <h2 class="text-[15px] font-bold text-slate-900">Quick actions</h2>
  <span class="text-xs text-slate-500">Manage the full cycle</span>
</div>
<!-- Shortcut tiles linking to each admin task (voters, candidates, results, ...). -->
<div class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
  <?php foreach ([ // Each row below is: page link, title, short description, icon, tile colour.
      ['admin/voters.php', 'Manage voters', 'Add, disable and reset student accounts', 'users', 'bg-blue-50 text-blue-700 ring-blue-600/10'],
      ['admin/candidates.php', 'Manage candidates', 'Photos, bios and ballot placement', 'id', 'bg-teal-50 text-teal-700 ring-teal-600/10'],
      ['admin/positions.php', 'Manage positions', 'Define what students vote for', 'briefcase', 'bg-amber-50 text-amber-700 ring-amber-600/15'],
      ['admin/elections.php', 'Election settings', 'Schedule, open and close voting', 'calendar', 'bg-violet-50 text-violet-700 ring-violet-600/10'],
      ['admin/results.php', 'View results', 'Tallies, turnout and CSV export', 'chart', 'bg-emerald-50 text-emerald-700 ring-emerald-600/10'],
      ['admin/audit-logs.php', 'Security logs', 'Logins, votes and admin trail', 'shield', 'bg-slate-100 text-slate-700 ring-slate-500/10'],
  ] as [$href, $label, $desc, $ic, $chip]): ?>
    <a href="<?= e(url($href)) ?>" class="quick-action group">
      <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ring-1 <?= $chip ?>"><?= icon($ic, 'h-5 w-5') ?></span>
      <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold text-slate-900 group-hover:text-blue-700"><?= e($label) ?></span><span class="block truncate text-xs text-slate-500"><?= e($desc) ?></span></span>
      <span class="shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-blue-600">→</span>
    </a>
  <?php endforeach; ?>
</div>

<!-- Latest security events (logins, votes, admin changes) with a link to the full log. -->
<div class="mb-3 mt-10 flex items-center justify-between">
  <h2 class="text-[15px] font-bold text-slate-900">Recent security activity</h2>
  <a href="<?= e(url('admin/audit-logs.php')) ?>" class="text-[13px] font-semibold text-blue-700 hover:underline">View all →</a>
</div>
<div class="table-wrap">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-slate-100">
      <thead class="bg-slate-50/80"><tr><th class="th">Time</th><th class="th">User</th><th class="th">Event</th></tr></thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($recent as $r): ?>
        <tr class="transition hover:bg-slate-50/80"><td class="td whitespace-nowrap tabular-nums"><?= e(date('j M, H:i', strtotime($r['created_at']))) ?></td><td class="td font-medium text-slate-900"><?= e($r['actor']) ?></td><td class="td font-mono text-xs"><span class="rounded-md bg-slate-100 px-2 py-1 ring-1 ring-slate-200/70"><?= e($r['action']) ?></span></td></tr>
      <?php endforeach; ?>
      <?php if (!$recent): ?><tr><td class="td py-10 text-center text-slate-500" colspan="3">No activity yet — actions will appear here.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php layout_end();
