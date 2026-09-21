<?php
/**
 * Admin Elections page — create and run elections.
 * Handles the full election lifecycle: create a draft, schedule it,
 * open it for voting, close it when voting ends, plus edit or delete
 * elections that have not started. Every change is recorded in the audit log.
 */
// Load shared page tools (login checks, database helpers, page layout).
require __DIR__ . '/../includes/layout.php';
// Only signed-in admins may manage elections.
$u = require_login('admin');

// Handle any button pressed on this page (form submissions only, not page views).
if (is_post()) {
    // Safety check: confirm the form really came from our site (blocks forged requests).
    csrf_check();
    // Which button was pressed (save / open / schedule / close / delete) and which election.
    $a = post('action');
    $id = (int) post('id');
    try {
        // "Save" means create a new draft election or update a draft/upcoming one.
        if ($a === 'save') {
            $title = post('title');
            $desc = post('description');
            // Convert the dates typed in the form into database format.
            $st = to_db_dt(post('start_time'));
            $en = to_db_dt(post('end_time'));
            // Basic checks: a name is required, text must fit, and end must be after start.
            if ($title === '' || strlen($title) > 150) throw new RuntimeException('Election name is required (150 characters max).');
            if (strlen($desc) > 1000) throw new RuntimeException('Description is too long (1000 characters max).');
            if (!$st || !$en) throw new RuntimeException('Choose a start and end date and time.');
            if ($en <= $st) throw new RuntimeException('The end time must be after the start time.');
            if ($id) {
                // Editing: only drafts or upcoming elections may change; open/closed ones are final.
                $old = db_one('SELECT * FROM elections WHERE id = ?', [$id]);
                if (!$old || in_array($old['status'], ['OPEN', 'CLOSED'], true)) throw new RuntimeException('Only draft or upcoming elections can be edited.');
                db_run('UPDATE elections SET title=?, description=?, start_time=?, end_time=? WHERE id=?', [$title, $desc, $st, $en, $id]);
                // Write a line in the security log so there is a record of who changed what.
                audit('ELECTION_UPDATED', $title);
                flash('success', 'Election updated.');
            } else {
                // Creating: new elections always start as drafts so nothing goes live by accident.
                db_run("INSERT INTO elections(title,description,start_time,end_time,status,created_at) VALUES (?,?,?,?, 'DRAFT', ?)", [$title, $desc, $st, $en, date('Y-m-d H:i:s')]);
                audit('ELECTION_CREATED', $title);
                flash('success', 'Election created as a draft. Add positions and candidates next.');
            }
        } elseif (in_array($a, ['open', 'schedule', 'close', 'delete'], true)) {
            // Status buttons: look up the election first so we act on the right one.
            $el = db_one('SELECT * FROM elections WHERE id = ?', [$id]);
            if (!$el) throw new RuntimeException('Election not found.');
            $now = date('Y-m-d H:i:s');
            // Open / schedule: an election must be set up (positions + candidates) and not expired.
            if ($a === 'open' || $a === 'schedule') {
                if ($el['status'] === 'CLOSED') throw new RuntimeException('A closed election cannot be reopened.');
                if ($el['status'] === 'OPEN') throw new RuntimeException('This election is already open.');
                if ($err = election_ready_error($id)) throw new RuntimeException($err);
                if ($el['end_time'] <= $now) throw new RuntimeException('The end time has already passed. Edit the election and set a later end time.');
                if ($a === 'open') {
                    db_run("UPDATE elections SET status='OPEN' WHERE id=?", [$id]);
                    audit('ELECTION_OPENED', $el['title']);
                    flash('success', 'Election is now open for voting.');
                } else {
                    if ($el['status'] !== 'DRAFT') throw new RuntimeException('Only draft elections can be scheduled.');
                    db_run("UPDATE elections SET status='UPCOMING' WHERE id=?", [$id]);
                    audit('ELECTION_SCHEDULED', $el['title']);
                    flash('success', 'Election scheduled. It will open automatically at the start time.');
                }
            } elseif ($a === 'close') {
                // Close: stops voting immediately; a closed election can never be reopened.
                if ($el['status'] !== 'OPEN') throw new RuntimeException('Only open elections can be closed.');
                db_run("UPDATE elections SET status='CLOSED' WHERE id=?", [$id]);
                audit('ELECTION_CLOSED', $el['title']);
                flash('success', 'Election closed. Results are now final.');
            } else {
                // Delete: only allowed when nobody has voted, so no votes can ever be lost.
                if ($el['status'] === 'OPEN') throw new RuntimeException('Close the election before deleting it.');
                if ((int) db_val('SELECT COUNT(*) FROM ballots WHERE election_id = ?', [$id])) throw new RuntimeException('Elections with recorded votes cannot be deleted.');
                // Tidy up candidate photo files before removing the election record itself.
                foreach (db_all('SELECT c.photo FROM candidates c JOIN positions p ON p.id = c.position_id WHERE p.election_id = ?', [$id]) as $c) delete_photo($c['photo']);
                db_run('DELETE FROM elections WHERE id = ?', [$id]);
                audit('ELECTION_DELETED', $el['title']);
                flash('success', 'Election deleted.');
            }
        }
    } catch (RuntimeException $ex) {
        // If any check above failed, show its message instead of saving anything.
        flash('error', $ex->getMessage());
    }
    // Go back to the list so refreshing the page does not re-submit the form.
    redirect('admin/elections.php');
}

// Load data for display: the election being edited (if any) plus every election with counts.
$edit = isset($_GET['edit']) ? db_one("SELECT * FROM elections WHERE id = ? AND status IN ('DRAFT','UPCOMING')", [(int) $_GET['edit']]) : null;
$rows = db_all("SELECT e.*,
    (SELECT COUNT(*) FROM positions p WHERE p.election_id = e.id) AS n_pos,
    (SELECT COUNT(*) FROM candidates c JOIN positions p ON p.id = c.position_id WHERE p.election_id = e.id) AS n_cand,
    (SELECT COUNT(*) FROM ballots b WHERE b.election_id = e.id) AS n_votes
    FROM elections e ORDER BY e.id DESC");

// Draw the page frame, then define a tiny helper that builds each action button safely.
layout_start('Elections', 'elections');

function act_form(string $action, int $id, string $label, string $cls, string $confirm = ''): string
{
    return '<form method="post" class="inline" ' . ($confirm ? 'data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field()
        . '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="id" value="' . $id . '">'
        . '<button class="btn btn-sm ' . $cls . '" type="submit">' . e($label) . '</button></form>';
}
?>
<!-- Main content: election table on the left, create/edit form on the right. -->
<div class="grid gap-6 xl:grid-cols-3">
  <div class="card overflow-x-auto xl:col-span-2">
    <table class="min-w-full divide-y divide-slate-100">
      <thead class="bg-slate-50"><tr><th class="th">Election</th><th class="th">Status</th><th class="th">Setup</th><th class="th">Actions</th></tr></thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($rows as $r): $s = $r['status']; ?>
        <tr>
          <td class="td">
            <p class="font-medium text-slate-900"><?= e($r['title']) ?></p>
            <p class="text-xs text-slate-500"><?= e(fmt_dt($r['start_time'])) ?> to <?= e(fmt_dt($r['end_time'])) ?></p>
          </td>
          <td class="td"><?= status_badge($s) ?></td>
          <td class="td text-xs text-slate-500"><?= (int) $r['n_pos'] ?> positions<br><?= (int) $r['n_cand'] ?> candidates<br><?= (int) $r['n_votes'] ?> votes</td>
          <td class="td">
            <div class="flex flex-wrap gap-1.5">
              <?php if ($s === 'DRAFT'): ?><?= act_form('schedule', (int) $r['id'], 'Schedule', 'btn-outline') ?><?php endif; ?>
              <?php if ($s === 'DRAFT' || $s === 'UPCOMING'): ?>
                <?= act_form('open', (int) $r['id'], 'Open now', 'btn-teal', 'Open this election for voting now?') ?>
                <a class="btn btn-outline btn-sm" href="?edit=<?= (int) $r['id'] ?>">Edit</a>
              <?php endif; ?>
              <?php if ($s === 'OPEN'): ?><?= act_form('close', (int) $r['id'], 'Close voting', 'btn-danger', 'Close this election? Voting stops immediately and cannot be reopened.') ?><?php endif; ?>
              <a class="btn btn-outline btn-sm" href="<?= e(url('admin/positions.php?election=' . $r['id'])) ?>">Positions</a>
              <a class="btn btn-outline btn-sm" href="<?= e(url('admin/results.php?election=' . $r['id'])) ?>">Results</a>
              <?php if ($s !== 'OPEN' && !(int) $r['n_votes']): ?><?= act_form('delete', (int) $r['id'], 'Delete', 'btn-outline text-red-700', 'Delete this election and its positions and candidates?') ?><?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td class="td py-8 text-center text-slate-500" colspan="4">No elections yet. Create one to get started.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Side form: creates a new election or edits the selected draft/upcoming one. -->
  <section class="card h-fit p-5">
    <h2 class="mb-4 font-semibold text-slate-900"><?= $edit ? 'Edit election' : 'New election' ?></h2>
    <form method="post" class="space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
      <div><label class="label" for="title">Election name</label><input class="input" id="title" name="title" required maxlength="150" value="<?= e($edit['title'] ?? '') ?>"></div>
      <div><label class="label" for="description">Description</label><textarea class="input" id="description" name="description" rows="3" maxlength="1000"><?= e($edit['description'] ?? '') ?></textarea></div>
      <div><label class="label" for="start_time">Starts</label><input class="input" id="start_time" name="start_time" type="datetime-local" required value="<?= e($edit ? to_input_dt($edit['start_time']) : date('Y-m-d\TH:i', time() + 3600)) ?>"></div>
      <div><label class="label" for="end_time">Ends</label><input class="input" id="end_time" name="end_time" type="datetime-local" required value="<?= e($edit ? to_input_dt($edit['end_time']) : date('Y-m-d\TH:i', time() + 8 * 86400)) ?>"></div>
      <div class="flex gap-2">
        <button class="btn btn-primary" type="submit"><?= $edit ? 'Save changes' : 'Create election' ?></button>
        <?php if ($edit): ?><a class="btn btn-outline" href="<?= e(url('admin/elections.php')) ?>">Cancel</a><?php endif; ?>
      </div>
      <p class="text-xs text-slate-500">New elections start as drafts. Only an open election accepts votes.</p>
    </form>
  </section>
</div>
<?php layout_end();
