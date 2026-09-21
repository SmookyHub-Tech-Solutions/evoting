<?php
// Vote page (Step 1 of 2 — Choose): the student picks one candidate per position, or abstains.
// Flow: Dashboard / Election -> Vote (this page) -> Review (confirm) -> Confirmation (receipt).
// Nothing is saved yet on this page; choices are sent to review.php for checking.

require __DIR__ . '/../includes/layout.php';
// Security check: only a logged-in student can vote.
$u = require_login('student');
// Read which election to vote in from the page address.
$eid = (int) ($_GET['election'] ?? 0);
// Security + eligibility check: blocks voting if election is closed, already voted, or not allowed. Sends student away if invalid.
$el = election_for_voting($eid, $u);
// Data fetch: load positions and candidates to show as choices.
$positions = ballot_positions($eid);
// Remember previous picks if the student went back from Review to edit, so their choices stay ticked.
$prev = (($_SESSION['pending']['election_id'] ?? 0) === $eid) ? ($_SESSION['pending']['sel'] ?? []) : [];
// Count only positions that actually have candidates, for the "Choose for each of X positions" message.
$totalPositions = count(array_filter($positions, fn($p) => (bool) $p['candidates']));

// Page header.
layout_start('Cast your vote', 'elections', $el['title']);
?>
<div class="mx-auto max-w-3xl">
  <div class="card animate-fade-up overflow-hidden">
    <div class="bg-gradient-to-r from-navy via-navy-light to-blue-800 px-6 py-5 text-white">
      <p class="text-xs font-bold uppercase tracking-[0.14em] text-teal-200">Step 1 of 2 — Choose</p>
      <h2 class="mt-1 text-xl font-extrabold tracking-tight"><?= e($el['title']) ?></h2>
      <p class="mt-1 text-sm text-slate-300">Choose one candidate for each of the <?= $totalPositions ?> position<?= $totalPositions === 1 ? '' : 's' ?>, or abstain. You'll review everything before it is submitted.</p>
    </div>
    <div class="flex items-center gap-2 border-b border-slate-100 bg-slate-50/70 px-6 py-3 text-[13px] text-slate-600">
      <?= icon('lock', 'h-4 w-4 text-slate-400') ?> Secret ballot · You can only submit once
      <span class="ml-auto hidden sm:inline">Closes <?= e(fmt_dt($el['end_time'])) ?></span>
    </div>
  </div>

  <?php if (!array_filter($positions, fn($p) => $p['candidates'])): ?>
    <!-- Empty message: this election has no candidates to choose from yet. -->
    <div class="empty-state mt-6"><p class="text-sm text-slate-500">This election has no candidates yet.</p></div>
  <?php else: ?>
  <!-- Voting form: sends the student's picks to review.php. Nothing is saved until they confirm there. -->
  <form method="post" action="<?= e(url('student/review.php')) ?>" class="mt-6 space-y-6">
    <?= csrf_field() // Security token: proves this form really came from our site, blocks fake submissions. ?>
    <input type="hidden" name="action" value="review">
    <input type="hidden" name="election_id" value="<?= (int) $eid // Tells review.php which election these picks belong to. ?>">

    <?php foreach ($positions as $idx => $p): if (!$p['candidates']) continue; $chosen = $prev[$p['id']] ?? null; // Skip empty positions; recall any earlier pick for this one. ?>
      <!-- One position block: the student must pick exactly one option (a candidate or Abstain). -->
      <fieldset class="card animate-fade-up p-5 sm:p-6" style="animation-delay: <?= $idx * 60 ?>ms">
        <legend class="sr-only"><?= e($p['name']) ?></legend>
        <div class="mb-4 flex items-center justify-between gap-3">
          <h3 class="flex items-center gap-2.5 text-[15px] font-bold text-slate-900"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-navy-50 text-[13px] font-extrabold text-navy ring-1 ring-navy-100"><?= $idx + 1 ?></span><?= e($p['name']) ?></h3>
          <span class="text-xs font-medium text-slate-500"><?= count($p['candidates']) ?> candidate<?= count($p['candidates']) === 1 ? '' : 's' ?></span>
        </div>
        <div class="grid gap-3 md:grid-cols-2">
          <?php foreach ($p['candidates'] as $c): ?>
            <!-- One candidate option: clicking the card selects that person for this position. -->
            <label class="block cursor-pointer">
              <input type="radio" class="peer sr-only" name="sel[<?= (int) $p['id'] ?>]" value="<?= (int) $c['id'] ?>" required <?= (int) $chosen === (int) $c['id'] && $chosen !== null ? 'checked' : '' ?>>
              <div class="flex h-full items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 transition-all duration-150 hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-soft peer-checked:border-blue-600 peer-checked:bg-blue-50/70 peer-checked:shadow-soft peer-checked:ring-4 peer-checked:ring-blue-600/10 peer-focus-visible:ring-4 peer-focus-visible:ring-blue-500/30">
                <?= avatar($c['name'], $c['photo'], 'h-14 w-14 !rounded-2xl') ?>
                <div class="min-w-0 flex-1">
                  <p class="truncate font-bold text-slate-900"><?= e($c['name']) ?></p>
                  <p class="truncate text-[13px] text-slate-500"><?= e($c['department'] ?: 'Candidate') ?></p>
                </div>
                <span class="peer-check-indicator flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 border-slate-200 text-transparent transition peer-checked:hidden"></span>
              </div>
            </label>
          <?php endforeach; ?>
          <!-- Abstain option: choose this to skip voting for this position. Still counts as answering it. -->
          <label class="block cursor-pointer">
            <input type="radio" class="peer sr-only" name="sel[<?= (int) $p['id'] ?>]" value="0" required <?= $chosen !== null && (int) $chosen === 0 ? 'checked' : '' ?>>
            <div class="flex h-full items-center justify-center gap-2 rounded-2xl border border-dashed border-slate-300 bg-slate-50/60 p-4 text-sm font-medium text-slate-500 transition hover:border-slate-400 hover:bg-slate-100 peer-checked:border-slate-500 peer-checked:bg-slate-100 peer-checked:text-slate-700 peer-checked:ring-4 peer-checked:ring-slate-500/10">
              Abstain — no selection for this position
            </div>
          </label>
        </div>
      </fieldset>
    <?php endforeach; ?>

    <div class="card flex flex-col items-center justify-between gap-3 p-4 sm:flex-row sm:p-5">
      <!-- Next-step bar: moves to Step 2 (Review) where the student double-checks before submitting. -->
      <p class="text-[13px] text-slate-500">Next: review your <?= $totalPositions ?> selection<?= $totalPositions === 1 ? '' : 's' ?> before submitting.</p>
      <button class="btn btn-primary w-full !py-3 sm:w-auto sm:!px-8" type="submit">Review my vote →</button>
    </div>
  </form>
  <?php endif; ?>
</div>
<?php // Page footer: closes the layout.
layout_end();
