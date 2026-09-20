<?php
require __DIR__ . '/../includes/layout.php';
$u = require_login('admin');

$elections = db_all('SELECT id,title,status FROM elections ORDER BY id DESC');
$eid = (int) ($_GET['election'] ?? $_POST['election_id'] ?? ($elections[0]['id'] ?? 0));
$locked = $eid ? election_locked($eid) : false;

if (is_post()) {
    csrf_check();
    $a = post('action');
    $newPhoto = null;
    try {
        if (!db_val('SELECT 1 FROM elections WHERE id = ?', [$eid])) throw new RuntimeException('Choose an election first.');
        if ($a === 'save') {
            $id = (int) post('id');
            $name = post('name');
            $sid = post('student_id');
            $dept = post('department');
            $fac = post('faculty');
            $bio = post('bio');
            if ($name === '' || strlen($name) > 100) throw new RuntimeException('Candidate name is required (100 characters max).');
            if (strlen($sid) > 30 || strlen($dept) > 100 || strlen($fac) > 100 || strlen($bio) > 1000) throw new RuntimeException('One of the fields is too long.');
            $newPhoto = save_photo($_FILES['photo'] ?? []);

            if ($id) {
                $old = db_one('SELECT c.* FROM candidates c JOIN positions p ON p.id = c.position_id WHERE c.id = ? AND p.election_id = ?', [$id, $eid]);
                if (!$old) throw new RuntimeException('Candidate not found.');
                $status = $locked ? $old['status'] : (post('status') === 'withdrawn' ? 'withdrawn' : 'active');
                db_run('UPDATE candidates SET name=?, student_id=?, department=?, faculty=?, bio=?, status=?, photo=? WHERE id=?',
                    [$name, $sid, $dept, $fac, $bio, $status, $newPhoto ?? $old['photo'], $id]);
                if ($newPhoto) delete_photo($old['photo']);
                audit('CANDIDATE_UPDATED', $name);
                flash('success', 'Candidate updated.');
            } else {
                if ($locked) throw new RuntimeException('Candidates cannot be added once an election is open or has votes.');
                $pid = (int) post('position_id');
                if (!db_val('SELECT 1 FROM positions WHERE id = ? AND election_id = ?', [$pid, $eid])) throw new RuntimeException('Choose a position for this candidate.');
                $status = post('status') === 'withdrawn' ? 'withdrawn' : 'active';
                db_run('INSERT INTO candidates(position_id,name,student_id,department,faculty,photo,bio,status) VALUES (?,?,?,?,?,?,?,?)',
                    [$pid, $name, $sid, $dept, $fac, $newPhoto, $bio, $status]);
                audit('CANDIDATE_CREATED', $name);
                flash('success', 'Candidate added.');
            }
        } elseif ($a === 'delete') {
            if ($locked) throw new RuntimeException('Candidates cannot be deleted once an election is open or has votes.');
            $c = db_one('SELECT c.* FROM candidates c JOIN positions p ON p.id = c.position_id WHERE c.id = ? AND p.election_id = ?', [(int) post('id'), $eid]);
            if (!$c) throw new RuntimeException('Candidate not found.');
            db_run('DELETE FROM candidates WHERE id = ?', [$c['id']]);
            delete_photo($c['photo']);
            audit('CANDIDATE_DELETED', $c['name']);
            flash('success', 'Candidate deleted.');
        }
    } catch (RuntimeException $ex) {
        if ($newPhoto) delete_photo($newPhoto);
        flash('error', $ex->getMessage());
    }
    redirect('admin/candidates.php?election=' . $eid);
}

$positions = $eid ? db_all('SELECT * FROM positions WHERE election_id = ? ORDER BY id', [$eid]) : [];
$edit = null;
if (isset($_GET['edit']) && $eid) {
    $edit = db_one('SELECT c.* FROM candidates c JOIN positions p ON p.id = c.position_id WHERE c.id = ? AND p.election_id = ?', [(int) $_GET['edit'], $eid]);
}

layout_start('Candidates', 'candidates');
if (!$elections): ?>
  <div class="card p-8 text-center text-sm text-slate-600">Create an election and its positions before adding candidates.</div>
<?php else: ?>
  <form method="get" class="mb-6 max-w-sm">
    <label class="label" for="election">Election</label>
    <select class="input" id="election" name="election" data-autosubmit>
      <?php foreach ($elections as $x): ?><option value="<?= (int) $x['id'] ?>" <?= (int) $x['id'] === $eid ? 'selected' : '' ?>><?= e($x['title']) ?> (<?= e($x['status']) ?>)</option><?php endforeach; ?>
    </select>
  </form>
  <?php if ($locked): ?><div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">This election is open or has votes. You can edit candidate details, but not add, delete or withdraw candidates.</div><?php endif; ?>

  <div class="grid gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
      <?php foreach ($positions as $p):
          $cands = db_all('SELECT * FROM candidates WHERE position_id = ? ORDER BY name', [$p['id']]); ?>
        <section class="card">
          <h2 class="border-b border-slate-100 px-5 py-3 font-semibold text-slate-900"><?= e($p['name']) ?></h2>
          <?php if (!$cands): ?><p class="px-5 py-4 text-sm text-slate-500">No candidates yet.</p><?php endif; ?>
          <ul class="divide-y divide-slate-100">
            <?php foreach ($cands as $c): ?>
              <li class="flex flex-wrap items-center gap-4 px-5 py-4">
                <?= avatar($c['name'], $c['photo'], 'h-12 w-12') ?>
                <div class="min-w-0 flex-1">
                  <p class="font-medium text-slate-900"><?= e($c['name']) ?> <?= $c['status'] === 'withdrawn' ? status_badge('withdrawn') : '' ?></p>
                  <p class="text-sm text-slate-500"><?= e(trim(($c['department'] ?? '') . ($c['faculty'] ? ', ' . $c['faculty'] : ''), ', ')) ?></p>
                </div>
                <div class="flex gap-2">
                  <a class="btn btn-outline btn-sm" href="?election=<?= $eid ?>&edit=<?= (int) $c['id'] ?>">Edit</a>
                  <?php if (!$locked): ?>
                  <form method="post" data-confirm="Delete this candidate?">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="election_id" value="<?= $eid ?>">
                    <button class="btn btn-outline btn-sm text-red-700" type="submit">Delete</button>
                  </form>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endforeach; ?>
      <?php if (!$positions): ?><div class="card p-8 text-center text-sm text-slate-600">This election has no positions. <a class="font-medium text-blue-700 hover:underline" href="<?= e(url('admin/positions.php?election=' . $eid)) ?>">Add positions first.</a></div><?php endif; ?>
    </div>

    <?php if ($positions && ($edit || !$locked)): ?>
    <section class="card h-fit p-5">
      <h2 class="mb-4 font-semibold text-slate-900"><?= $edit ? 'Edit candidate' : 'Add candidate' ?></h2>
      <form method="post" enctype="multipart/form-data" class="space-y-4">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="election_id" value="<?= $eid ?>"><input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
        <?php if (!$edit): ?>
        <div><label class="label" for="position_id">Position</label>
          <select class="input" id="position_id" name="position_id" required>
            <?php foreach ($positions as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
          </select></div>
        <?php endif; ?>
        <div><label class="label" for="name">Full name</label><input class="input" id="name" name="name" required maxlength="100" value="<?= e($edit['name'] ?? '') ?>"></div>
        <div><label class="label" for="student_id">Student ID</label><input class="input" id="student_id" name="student_id" maxlength="30" value="<?= e($edit['student_id'] ?? '') ?>"></div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
          <div><label class="label" for="department">Department</label><input class="input" id="department" name="department" maxlength="100" value="<?= e($edit['department'] ?? '') ?>"></div>
          <div><label class="label" for="faculty">Faculty</label><input class="input" id="faculty" name="faculty" maxlength="100" value="<?= e($edit['faculty'] ?? '') ?>"></div>
        </div>
        <div><label class="label" for="bio">Short biography</label><textarea class="input" id="bio" name="bio" rows="3" maxlength="1000"><?= e($edit['bio'] ?? '') ?></textarea></div>
        <div><label class="label" for="photo">Photo</label><input class="input" id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp"><p class="mt-1 text-xs text-slate-500">JPG, PNG or WebP, up to 2 MB.<?= $edit && $edit['photo'] ? ' Uploading a new photo replaces the current one.' : '' ?></p></div>
        <?php if (!$locked): ?>
        <div><label class="label" for="status">Status</label>
          <select class="input" id="status" name="status">
            <option value="active" <?= ($edit['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="withdrawn" <?= ($edit['status'] ?? '') === 'withdrawn' ? 'selected' : '' ?>>Withdrawn (hidden from ballot)</option>
          </select></div>
        <?php endif; ?>
        <div class="flex gap-2">
          <button class="btn btn-primary" type="submit"><?= $edit ? 'Save changes' : 'Add candidate' ?></button>
          <?php if ($edit): ?><a class="btn btn-outline" href="<?= e(url('admin/candidates.php?election=' . $eid)) ?>">Cancel</a><?php endif; ?>
        </div>
      </form>
    </section>
    <?php endif; ?>
  </div>
<?php endif;
layout_end();
