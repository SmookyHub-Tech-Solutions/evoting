<?php
// Results page: shows published election results (vote counts and winners) to students.
// Flow: standalone page linked from Dashboard. Only elections the office has published appear here.
// Privacy: results show totals only, never who any individual voted for.

require __DIR__ . '/../includes/layout.php';
// Security check: only a logged-in student can view results.
$u = require_login('student');
// Data fetch: list of elections with results cleared for students to see.
$list = student_result_elections();
// Read which election's results to show. Defaults to the first one in the list.
$id = (int) ($_GET['election'] ?? ($list[0]['id'] ?? 0));
$el = null;
// Find the requested election in the allowed list. Stays empty if the student asked for an unpublished one.
foreach ($list as $x) { if ((int) $x['id'] === $id) $el = $x; }

// Page header.
layout_start('Results', 'results');
if (!$list): ?>
  <!-- Empty message: shown when no results have been published yet. -->
  <div class="card p-8 text-center">
    <p class="font-medium">No results to show yet.</p>
    <p class="mt-1 text-sm text-slate-500">Results are published after an election closes.</p>
  </div>
<?php else: ?>
  <!-- Election picker: dropdown to switch between published elections. Auto-submits when changed. -->
  <form method="get" class="mb-6 max-w-sm">
    <label class="label" for="election">Election</label>
    <select class="input" id="election" name="election" data-autosubmit>
      <?php foreach ($list as $x): ?><option value="<?= (int) $x['id'] ?>" <?= $el && (int) $x['id'] === (int) $el['id'] ? 'selected' : '' ?>><?= e($x['title']) ?></option><?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-outline btn-sm mt-2" type="submit">Show</button></noscript>
  </form>
  <?php if ($el): render_results($el); else: ?>
    <!-- Fallback: shown if the requested election has no published results (e.g. edited page address). -->
    <div class="card p-8 text-center text-sm text-slate-500">Results for that election are not available.</div>
  <?php endif;
endif;
// Page footer: closes the layout.
layout_end();
