<?php
require __DIR__ . '/../includes/layout.php';
$u = require_login('student');

if (is_post()) {
    csrf_check();
    $action = post('action');

    if ($action === 'review') {
        $eid = (int) post('election_id');
        election_for_voting($eid, $u);
        $sel = validate_selection(ballot_positions($eid), $_POST['sel'] ?? []);
        if ($sel === null) {
            flash('error', 'Choose a candidate, or abstain, for every position.');
            redirect('student/vote.php?election=' . $eid);
        }
        $_SESSION['pending'] = ['election_id' => $eid, 'sel' => $sel];
        redirect('student/review.php');
    }

    if ($action === 'submit') {
        $pending = $_SESSION['pending'] ?? null;
        if (!$pending) {
            redirect('student/dashboard.php');
        }
        $eid = (int) $pending['election_id'];
        $el = election_for_voting($eid, $u);
        $sel = validate_selection(ballot_positions($eid), $pending['sel']);
        if ($sel === null) {
            flash('error', 'Your selections are no longer valid. Please vote again.');
            redirect('student/vote.php?election=' . $eid);
        }
        try {
            $conf = cast_ballot($el, $u, $sel);
        } catch (PDOException $ex) {
            unset($_SESSION['pending']);
            if ($ex->getCode() === '23000') {
                audit('DOUBLE_VOTE_BLOCKED', 'Election #' . $eid);
                flash('info', 'You have already voted in this election.');
                redirect('student/confirmation.php?election=' . $eid);
            }
            audit('VOTE_FAILED', 'Election #' . $eid);
            flash('error', 'Your vote could not be recorded. Nothing was saved. Try again or contact the election office.');
            redirect('student/dashboard.php');
        }
        unset($_SESSION['pending']);
        audit('VOTE_CAST', 'Election #' . $eid . ' · ' . $conf);
        redirect('student/confirmation.php?election=' . $eid);
    }
    redirect('student/dashboard.php');
}

$pending = $_SESSION['pending'] ?? null;
if (!$pending) {
    redirect('student/dashboard.php');
}
$eid = (int) $pending['election_id'];
$el = election_for_voting($eid, $u);
$positions = ballot_positions($eid);

layout_start('Review your vote', 'elections', 'Step 2 of 2 — Confirm and submit');
?>
<div class="mx-auto max-w-2xl animate-fade-up">
  <div class="card overflow-hidden">
    <div class="bg-gradient-to-r from-navy to-navy-light px-6 py-5 text-white">
      <p class="text-xs font-bold uppercase tracking-[0.14em] text-teal-200">Step 2 of 2 — Review</p>
      <h2 class="mt-1 text-xl font-extrabold tracking-tight"><?= e($el['title']) ?></h2>
      <p class="mt-1 text-sm text-slate-300">Check your choices carefully — this is your last chance to change them.</p>
    </div>
    <div class="divide-y divide-slate-100">
      <?php foreach ($positions as $p): if (!$p['candidates']) continue;
          $cid = (int) ($pending['sel'][$p['id']] ?? 0);
          $name = null; $photo = null;
          foreach ($p['candidates'] as $c) { if ((int) $c['id'] === $cid) { $name = $c['name']; $photo = $c['photo']; } }
          ?>
        <div class="flex items-center gap-4 px-6 py-4">
          <?php if ($name): ?><?= avatar($name, $photo, 'h-10 w-10 !rounded-xl') ?><?php else: ?><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-400">—</span><?php endif; ?>
          <span class="min-w-0 flex-1"><span class="block text-[13px] font-medium text-slate-500"><?= e($p['name']) ?></span><span class="block truncate font-bold <?= $name ? 'text-slate-900' : 'text-slate-400' ?>"><?= $name ? e($name) : 'Abstain' ?></span></span>
          <?php if ($name): ?><span class="text-emerald-600"><?= icon('check', 'h-5 w-5') ?></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="alert mt-4 border-amber-200/80 bg-amber-50/90 text-amber-900">
    <?= icon('lock', 'mt-0.5 h-5 w-5 shrink-0') ?>
    <p><span class="font-bold">Final warning:</span> once you submit, your vote cannot be changed or viewed again.</p>
  </div>

  <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-between">
    <a class="btn btn-outline" href="<?= e(url('student/vote.php?election=' . $eid)) ?>">← Go back and edit</a>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="submit">
      <button class="btn btn-teal w-full !px-8 !py-3 sm:w-auto" type="submit">Submit vote securely</button>
    </form>
  </div>
</div>
<?php layout_end();
