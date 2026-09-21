<?php
// Dashboard page: the student's home screen after logging in.
// Flow: Dashboard -> Vote (choose) -> Review (confirm) -> Confirmation (receipt). Also links to Results.
// What you see here: open elections you can vote in, upcoming elections, and published results.

require __DIR__ . '/../includes/layout.php';
// Security check: only a logged-in student can view this page. Others are sent to login.
$u = require_login('student');

// Data fetch: find which elections this student already voted in (so we show "Voted" vs "Vote now").
// Then get the lists of open, upcoming, and finished elections with published results.
$voted = array_map('intval', array_column(db_all('SELECT election_id FROM ballots WHERE voter_id = ?', [$u['id']]), 'election_id'));
$open = db_all("SELECT * FROM elections WHERE status = 'OPEN' ORDER BY end_time");
$upcoming = db_all("SELECT * FROM elections WHERE status = 'UPCOMING' ORDER BY start_time");
$results = student_result_elections();

// Page header: sets the title and layout around the content below.
layout_start('Dashboard', 'dashboard', 'Your open elections and voting status');
?>
<!-- Welcome line: greets the student by first name. -->
<p class="mb-6 text-[15px] text-slate-600">Welcome back, <span class="font-bold text-navy"><?= e(explode(' ', $u['name'])[0]) ?></span> — here's what needs your vote.</p>

<!-- Empty message: shown only when there is nothing to vote on right now. -->
<?php if (!$open): ?>
  <div class="empty-state">
    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 ring-1 ring-blue-100"><?= icon('vote', 'h-7 w-7') ?></span>
    <p class="mt-4 font-bold text-slate-900">No election is open right now</p>
    <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">When voting opens, it will appear here with a countdown to the deadline.</p>
  </div>
<?php endif; ?>

<!-- Open elections: one card per election. Each card shows either "Vote now" or "Already voted". -->
<div class="grid gap-5 md:grid-cols-2">
<?php foreach ($open as $el): $has = in_array((int) $el['id'], $voted, true); // Check: did this student already vote in this election? ?>
  <section class="card animate-fade-up overflow-hidden">
    <div class="h-1.5 bg-gradient-to-r from-teal-500 via-blue-600 to-navy"></div>
    <div class="p-6">
      <div class="flex items-start justify-between gap-3">
        <div>
          <p class="page-eyebrow !text-teal-700">Current election</p>
          <h2 class="mt-1 text-lg font-bold tracking-tight text-navy"><?= e($el['title']) ?></h2>
        </div>
        <?= status_badge('OPEN') ?>
      </div>
      <p class="mt-3 flex items-center gap-2 text-sm text-slate-600"><?= icon('clock', 'h-4 w-4 text-slate-400') ?>Voting closes <?= e(fmt_dt($el['end_time'])) ?></p>

      <?php if ($has): ?>
        <!-- Already voted: show thank-you note and link to the receipt page. -->
        <div class="mt-5 flex items-center gap-3 rounded-xl bg-emerald-50 p-4 text-emerald-900 ring-1 ring-emerald-600/15">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white"><?= icon('check', 'h-5 w-5') ?></span>
          <div><p class="font-bold">Vote submitted — thank you</p><p class="text-sm opacity-80">You cannot vote again in this election.</p></div>
        </div>
        <a class="btn btn-outline mt-4 w-full" href="<?= e(url('student/confirmation.php?election=' . $el['id'])) ?>">View confirmation</a>
      <?php else: ?>
        <!-- Not voted yet: show reminder and button that starts the 2-step voting flow. -->
        <div class="mt-5 rounded-xl bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900 ring-1 ring-amber-600/15">You have not voted yet — it takes about a minute.</div>
        <a class="btn btn-primary mt-4 w-full !py-3" href="<?= e(url('student/vote.php?election=' . $el['id'])) ?>">Cast your vote →</a>
      <?php endif; ?>
    </div>
  </section>
<?php endforeach; ?>
</div>

<?php if ($upcoming): ?>
  <!-- Coming up: future elections the student cannot vote in yet, just for awareness. -->
  <div class="mb-4 mt-10 flex items-center justify-between">
    <h2 class="text-[15px] font-bold text-slate-900">Coming up</h2>
    <span class="text-xs text-slate-500"><?= count($upcoming) ?> scheduled</span>
  </div>
  <div class="grid gap-4 md:grid-cols-2">
    <?php foreach ($upcoming as $el): ?>
      <div class="card flex items-center gap-4 p-5">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700 ring-1 ring-amber-600/15"><?= icon('clock', 'h-5 w-5') ?></span>
        <div class="min-w-0 flex-1">
          <div class="flex items-center justify-between gap-3"><p class="truncate font-bold text-slate-900"><?= e($el['title']) ?></p><?= status_badge('UPCOMING') ?></div>
          <p class="mt-1 text-[13px] text-slate-500">Opens <?= e(fmt_dt($el['start_time'])) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($results): ?>
  <!-- Published results: finished elections whose results the office has made public. -->
  <h2 class="mb-4 mt-10 text-[15px] font-bold text-slate-900">Published results</h2>
  <div class="card divide-y divide-slate-100 overflow-hidden">
    <?php foreach (array_slice($results, 0, 5) as $el): ?>
      <a class="group flex items-center justify-between gap-3 px-5 py-4 transition hover:bg-slate-50" href="<?= e(url('student/results.php?election=' . $el['id'])) ?>">
        <span class="min-w-0"><span class="block truncate text-sm font-bold text-slate-900 group-hover:text-blue-700"><?= e($el['title']) ?></span><span class="text-xs text-slate-500">Tap to see the breakdown</span></span>
        <span class="flex shrink-0 items-center gap-2"><?= status_badge($el['status']) ?><span class="text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-blue-600">→</span></span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php // Page footer: closes the layout opened above.
layout_end();
