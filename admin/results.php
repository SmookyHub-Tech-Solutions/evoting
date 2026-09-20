<?php
require __DIR__ . '/../includes/layout.php';
$u = require_login('admin');

$elections = db_all('SELECT * FROM elections ORDER BY id DESC');
$id = (int) ($_GET['election'] ?? ($elections[0]['id'] ?? 0));
$el = $id ? db_one('SELECT * FROM elections WHERE id = ?', [$id]) : null;

if ($el && get_str('export') === 'csv') {
    $r = election_results($id);
    audit('RESULTS_EXPORTED', $el['title']);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="results-election-' . $id . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Election', csv_safe($el['title']), 'Status', $el['status'], 'Total votes', $r['ballots']]);
    fputcsv($out, ['Position', 'Candidate', 'Votes']);
    foreach ($r['positions'] as $blk) {
        foreach ($blk['candidates'] as $c) fputcsv($out, [csv_safe($blk['position']['name']), csv_safe($c['name']), $c['votes']]);
        fputcsv($out, [csv_safe($blk['position']['name']), 'Abstained / no selection', $blk['abstain']]);
    }
    exit;
}

layout_start('Results', 'results');
if (!$elections): ?>
  <div class="card p-8 text-center text-sm text-slate-600">No elections yet.</div>
<?php else: ?>
  <div class="mb-6 flex flex-wrap items-end justify-between gap-4 print:hidden">
    <form method="get" class="w-full max-w-sm">
      <label class="label" for="election">Election</label>
      <select class="input" id="election" name="election" data-autosubmit>
        <?php foreach ($elections as $x): ?><option value="<?= (int) $x['id'] ?>" <?= (int) $x['id'] === $id ? 'selected' : '' ?>><?= e($x['title']) ?> (<?= e($x['status']) ?>)</option><?php endforeach; ?>
      </select>
    </form>
    <div class="flex gap-2">
      <a class="btn btn-outline" href="?election=<?= $id ?>&export=csv">Export CSV</a>
      <button class="btn btn-outline" type="button" data-print>Print</button>
    </div>
  </div>
  <?php if ($el && $el['status'] === 'OPEN'): ?>
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 print:hidden">Voting is still open. These counts are provisional and hidden from students unless you allow live results in Settings.</div>
  <?php endif; ?>
  <?php if ($el) render_results($el);
endif;
layout_end();
