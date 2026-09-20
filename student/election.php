<?php
require __DIR__ . '/../includes/layout.php';
$u = require_login('student');
$id = (int) ($_GET['id'] ?? 0);

if ($id) {
    $el = db_one("SELECT * FROM elections WHERE id = ? AND status != 'DRAFT'", [$id]);
    if (!$el) {
        flash('error', 'Election not found.');
        redirect('student/election.php');
    }
    $positions = ballot_positions($id);
    $has = (int) db_val('SELECT COUNT(*) FROM ballots WHERE election_id = ? AND voter_id = ?', [$id, $u['id']]) > 0;
    layout_start($el['title'], 'elections', fmt_dt($el['start_time']) . ' → ' . fmt_dt($el['end_time']));
    ?>
    <a href="<?= e(url('student/election.php')) ?>" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-700 hover:underline">← All elections</a>
    <div class="card animate-fade-up mt-3 overflow-hidden">
      <div class="h-1.5 bg-gradient-to-r from-navy via-blue-700 to-teal-600"></div>
      <div class="p-6 sm:p-7">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <h2 class="text-xl font-extrabold tracking-tight text-navy sm:text-2xl"><?= e($el['title']) ?></h2>
            <?php if ($el['description']): ?><p class="mt-1.5 max-w-2xl text-sm leading-relaxed text-slate-600"><?= e($el['description']) ?></p><?php endif; ?>
            <p class="mt-3 flex items-center gap-2 text-[13px] text-slate-500"><?= icon('clock', 'h-4 w-4') ?><?= e(fmt_dt($el['start_time'])) ?> → <?= e(fmt_dt($el['end_time'])) ?></p>
          </div>
          <?= status_badge($el['status']) ?>
        </div>
        <?php if ($el['status'] === 'OPEN'): ?>
          <?php if ($has): ?>
            <div class="mt-5 flex items-center gap-3 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900 ring-1 ring-emerald-600/15"><?= icon('check', 'h-5 w-5') ?>You have voted in this election.</div>
          <?php else: ?>
            <a class="btn btn-primary mt-5 !px-8 !py-3" href="<?= e(url('student/vote.php?election=' . $id)) ?>">Cast your vote →</a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <?php foreach ($positions as $p): ?>
      <h3 class="mb-3 mt-8 flex items-center gap-2 text-[15px] font-bold text-slate-900"><span class="h-5 w-1 rounded-full bg-teal-600"></span><?= e($p['name']) ?> <span class="text-xs font-medium text-slate-400">· <?= count($p['candidates']) ?></span></h3>
      <div class="grid gap-4 md:grid-cols-2">
        <?php foreach ($p['candidates'] as $c): ?>
          <article class="card card-hover flex gap-4 p-5">
            <?= avatar($c['name'], $c['photo'], 'h-16 w-16 !rounded-2xl') ?>
            <div class="min-w-0">
              <p class="font-bold text-slate-900"><?= e($c['name']) ?></p>
              <p class="text-[13px] text-slate-500"><?= e(trim(($c['department'] ?? '') . ($c['faculty'] ? ', ' . $c['faculty'] : ''), ', ')) ?: 'Candidate' ?></p>
              <?php if ($c['bio']): ?><p class="mt-2 text-sm leading-relaxed text-slate-600"><?= e($c['bio']) ?></p><?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
        <?php if (!$p['candidates']): ?><p class="text-sm text-slate-500">No candidates listed.</p><?php endif; ?>
      </div>
    <?php endforeach;
    layout_end();
    exit;
}

$list = db_all("SELECT * FROM elections WHERE status != 'DRAFT' ORDER BY CASE status WHEN 'OPEN' THEN 0 WHEN 'UPCOMING' THEN 1 ELSE 2 END, start_time DESC");
layout_start('Elections', 'elections', 'Browse open, upcoming and past elections');
?>
<?php if (!$list): ?><div class="empty-state text-sm text-slate-500">No elections have been announced yet.</div><?php endif; ?>
<div class="grid gap-5 md:grid-cols-2">
<?php foreach ($list as $el): ?>
  <a href="<?= e(url('student/election.php?id=' . $el['id'])) ?>" class="card card-hover group block p-6">
    <div class="flex items-start justify-between gap-3"><h2 class="font-bold tracking-tight text-navy"><?= e($el['title']) ?></h2><?= status_badge($el['status']) ?></div>
    <p class="mt-2 flex items-center gap-1.5 text-[13px] text-slate-500"><?= icon('clock', 'h-4 w-4') ?>Closes <?= e(fmt_dt($el['end_time'])) ?></p>
    <p class="mt-4 text-sm font-bold text-blue-700">View candidates <span class="inline-block transition group-hover:translate-x-1">→</span></p>
  </a>
<?php endforeach; ?>
</div>
<?php layout_end();
