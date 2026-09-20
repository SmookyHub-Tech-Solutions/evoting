<?php
function election_results(int $eid): array
{
    $ballots = (int) db_val('SELECT COUNT(*) FROM ballots WHERE election_id = ?', [$eid]);
    $out = [];
    foreach (db_all('SELECT * FROM positions WHERE election_id = ? ORDER BY id', [$eid]) as $p) {
        $cands = db_all(
            "SELECT c.id, c.name, c.department, c.photo,
                    (SELECT COUNT(*) FROM votes v WHERE v.candidate_id = c.id) AS votes
             FROM candidates c WHERE c.position_id = ? AND c.status = 'active'
             ORDER BY votes DESC, c.name",
            [$p['id']]
        );
        $sum = array_sum(array_map('intval', array_column($cands, 'votes')));
        $max = $cands ? (int) $cands[0]['votes'] : 0;
        $leaders = $max > 0 ? count(array_filter($cands, fn($c) => (int) $c['votes'] === $max)) : 0;
        $out[] = ['position' => $p, 'candidates' => $cands, 'max' => $max, 'leaders' => $leaders, 'abstain' => max(0, $ballots - $sum)];
    }
    return ['ballots' => $ballots, 'positions' => $out];
}

function student_result_elections(): array
{
    $where = setting('results_visibility', 'after_close') === 'always' ? "status IN ('OPEN','CLOSED')" : "status = 'CLOSED'";
    return db_all("SELECT * FROM elections WHERE $where ORDER BY end_time DESC");
}

function render_results(array $el): void
{
    $r = election_results((int) $el['id']);
    $final = $el['status'] === 'CLOSED';
    $eligible = (int) db_val("SELECT COUNT(*) FROM users WHERE role='student' AND status='active'");
    $turnout = $eligible ? round($r['ballots'] / $eligible * 100, 1) : 0;
    ?>
<div class="card animate-fade-up mb-6 overflow-hidden">
  <div class="h-1.5 bg-gradient-to-r <?= $final ? 'from-emerald-500 via-teal-600 to-navy' : 'from-amber-400 via-amber-500 to-orange-500' ?>"></div>
  <div class="flex flex-wrap items-center justify-between gap-4 p-5 sm:p-6">
    <div class="min-w-0">
      <div class="flex items-center gap-2.5">
        <h2 class="truncate text-lg font-extrabold tracking-tight text-navy"><?= e($el['title']) ?></h2>
        <?= status_badge($el['status']) ?>
      </div>
      <p class="mt-1 flex items-center gap-1.5 text-[13px] text-slate-500"><?= $final ? 'Final certified results' : 'Provisional tally — voting is still open' ?></p>
    </div>
    <div class="flex items-center gap-3">
      <div class="rounded-2xl bg-slate-50 px-5 py-3 text-center ring-1 ring-slate-200/70"><p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Ballots</p><p class="text-2xl font-extrabold text-navy"><?= number_format((int) $r['ballots']) ?></p></div>
      <div class="rounded-2xl bg-navy px-5 py-3 text-center text-white shadow-soft"><p class="text-[11px] font-bold uppercase tracking-wider text-slate-300">Turnout</p><p class="text-2xl font-extrabold"><?= e($turnout) ?>%</p></div>
    </div>
  </div>
  <div class="border-t border-slate-100 bg-slate-50/60 px-5 py-3 sm:px-6">
    <div class="h-2.5 overflow-hidden rounded-full bg-slate-200/70"><div class="h-full animate-[grow-bar_.8s_ease-out_both] rounded-full bg-gradient-to-r from-teal-600 to-blue-600" style="width: <?= e(min(100, $turnout)) ?>%"></div></div>
    <p class="mt-1.5 text-xs text-slate-500"><?= number_format((int) $r['ballots']) ?> of <?= number_format($eligible) ?> eligible voters participated</p>
  </div>
</div>
<?php if (!$r['positions']): ?>
  <div class="empty-state text-sm text-slate-500">This election has no positions yet.</div>
<?php endif; ?>
<div class="grid items-start gap-5 lg:grid-cols-2">
<?php foreach ($r['positions'] as $blk): ?>
  <section class="card animate-fade-up p-5 sm:p-6">
    <div class="mb-5 flex items-center justify-between gap-3">
      <h3 class="flex items-center gap-2 font-bold text-slate-900"><span class="h-5 w-1 rounded-full bg-navy"></span><?= e($blk['position']['name']) ?></h3>
      <span class="text-xs text-slate-500"><?= count($blk['candidates']) ?> running</span>
    </div>
    <div class="space-y-5">
    <?php foreach ($blk['candidates'] as $c):
        $v = (int) $c['votes'];
        $pct = $r['ballots'] ? $v / $r['ballots'] * 100 : 0;
        $lead = $blk['max'] > 0 && $v === $blk['max'];
        ?>
      <div>
        <div class="mb-1.5 flex items-center justify-between gap-2 text-sm">
          <span class="flex min-w-0 items-center gap-2.5">
            <?= avatar($c['name'], $c['photo'] ?? null, 'h-8 w-8 !rounded-lg text-[11px]') ?>
            <span class="truncate font-bold text-slate-800"><?= e($c['name']) ?></span>
            <?php if ($lead): ?>
              <span class="badge <?= $blk['leaders'] > 1 ? 'bg-amber-50 text-amber-800 ring-amber-600/25' : 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' ?>">
                <?= $blk['leaders'] > 1 ? 'Tied' : ($final ? '★ Winner' : 'Leading') ?>
              </span>
            <?php endif; ?>
          </span>
          <span class="shrink-0 text-[13px] tabular-nums text-slate-600"><span class="font-bold text-slate-900"><?= $v ?></span> · <?= e(round($pct, 1)) ?>%</span>
        </div>
        <div class="h-2.5 overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200/50">
          <div class="h-full animate-[grow-bar_.8s_ease-out_both] rounded-full <?= $lead ? 'bg-gradient-to-r from-teal-600 to-emerald-500' : 'bg-gradient-to-r from-blue-600 to-blue-500' ?>" style="width: <?= e(round($pct, 1)) ?>%"></div>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
    <p class="mt-5 border-t border-slate-100 pt-3.5 text-xs text-slate-500">Abstained / no selection: <span class="font-semibold text-slate-700"><?= (int) $blk['abstain'] ?></span></p>
  </section>
<?php endforeach; ?>
</div>
<?php
}
