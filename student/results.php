<?php
require __DIR__ . '/../includes/layout.php';
$u = require_login('student');
$list = student_result_elections();
$id = (int) ($_GET['election'] ?? ($list[0]['id'] ?? 0));
$el = null;
foreach ($list as $x) { if ((int) $x['id'] === $id) $el = $x; }

layout_start('Results', 'results');
if (!$list): ?>
  <div class="card p-8 text-center">
    <p class="font-medium">No results to show yet.</p>
    <p class="mt-1 text-sm text-slate-500">Results are published after an election closes.</p>
  </div>
<?php else: ?>
  <form method="get" class="mb-6 max-w-sm">
    <label class="label" for="election">Election</label>
    <select class="input" id="election" name="election" data-autosubmit>
      <?php foreach ($list as $x): ?><option value="<?= (int) $x['id'] ?>" <?= $el && (int) $x['id'] === (int) $el['id'] ? 'selected' : '' ?>><?= e($x['title']) ?></option><?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-outline btn-sm mt-2" type="submit">Show</button></noscript>
  </form>
  <?php if ($el): render_results($el); else: ?>
    <div class="card p-8 text-center text-sm text-slate-500">Results for that election are not available.</div>
  <?php endif;
endif;
layout_end();
