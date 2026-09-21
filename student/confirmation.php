<?php
// Confirmation page (receipt): proves the student's vote was recorded, without revealing who they voted for.
// Flow: Review (submit) -> Confirmation (this page). Linked from Dashboard for elections already voted in.

require __DIR__ . '/../includes/layout.php';
// Security check: only a logged-in student can view receipts.
$u = require_login('student');
// Read which election's receipt to show from the page address.
$eid = (int) ($_GET['election'] ?? 0);
// Data fetch: find this student's ballot receipt for that election.
$b = db_one('SELECT b.*, e.title FROM ballots b JOIN elections e ON e.id = b.election_id WHERE b.election_id = ? AND b.voter_id = ?', [$eid, $u['id']]);
if (!$b) {
    // No vote found: show a message and send back to Dashboard. Prevents viewing others' or empty receipts.
    flash('warning', 'You have not voted in that election.');
    redirect('student/dashboard.php');
}
// Page header: shows the election name.
layout_start('Vote recorded', 'dashboard', $b['title']);
?>
<!-- Receipt card: success message with confirmation ID, election name, and time submitted. -->
<div class="mx-auto max-w-lg animate-fade-up">
  <div class="card overflow-hidden p-8 text-center shadow-soft sm:p-10">
    <div class="pointer-events-none absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-emerald-500 via-teal-600 to-blue-600"></div>
    <span class="relative mx-auto flex h-20 w-20 items-center justify-center rounded-3xl bg-emerald-600 text-white shadow-lift"><?= icon('check', 'h-10 w-10') ?></span>
    <h2 class="mt-5 text-2xl font-extrabold tracking-tight text-navy sm:text-3xl">Vote recorded</h2>
    <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-slate-600">Your vote was recorded successfully. You cannot vote again in this election.</p>

    <dl class="mt-7 space-y-4 rounded-2xl bg-slate-50 p-5 text-left text-sm ring-1 ring-slate-200/70 sm:p-6">
      <!-- Receipt details: election name, proof ID, and time. The ID proves recording but hides vote choices. -->
      <div class="flex items-center justify-between gap-4"><dt class="text-slate-500">Election</dt><dd class="text-right font-bold text-slate-900"><?= e($b['title']) ?></dd></div>
      <div class="border-t border-slate-200/70 pt-4"><dt class="text-xs font-bold uppercase tracking-wider text-slate-500">Confirmation ID</dt><dd class="mt-1 rounded-xl bg-white px-4 py-3 text-center font-mono text-2xl font-bold tracking-[0.18em] text-teal-700 ring-1 ring-teal-600/15"><?= e($b['confirmation_id']) ?></dd></div>
      <div class="flex items-center justify-between gap-4 border-t border-slate-200/70 pt-4"><dt class="text-slate-500">Submitted</dt><dd class="font-bold text-slate-900"><?= e(fmt_dt($b['created_at'])) ?></dd></div>
    </dl>
    <p class="mt-4 flex items-center justify-center gap-1.5 text-xs text-slate-500"><?= icon('lock', 'h-3.5 w-3.5') ?>This ID proves your vote was recorded. It does not show who you voted for.</p>
    <!-- Actions: return to Dashboard or print the receipt for personal records. -->
    <div class="mt-6 grid gap-2.5 sm:grid-cols-2">
      <a class="btn btn-primary w-full" href="<?= e(url('student/dashboard.php')) ?>">Back to dashboard</a>
      <button class="btn btn-outline w-full" type="button" data-print>Print receipt</button>
    </div>
  </div>
</div>
<?php // Page footer: closes the layout.
layout_end();
