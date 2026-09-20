<?php
require __DIR__ . '/../includes/layout.php';
$u = require_login('admin');

$elections = db_all('SELECT id,title,status FROM elections ORDER BY id DESC');
$eid = (int) ($_GET['election'] ?? $_POST['election_id'] ?? ($elections[0]['id'] ?? 0));

if (is_post()) {
    csrf_check();
    $a = post('action');
    try {
        if (!db_val('SELECT 1 FROM elections WHERE id = ?', [$eid])) throw new RuntimeException('Choose an election first.');
        if (election_locked($eid)) throw new RuntimeException('Positions cannot be changed once an election is open or has votes.');
        if ($a === 'add') {
            $name = post('name');
            if ($name === '' || strlen($name) > 100) throw new RuntimeException('Position name is required (100 characters max).');
            db_run('INSERT INTO positions(election_id,name,description) VALUES (?,?,?)', [$eid, $name, substr(post('description'), 0, 300)]);
            audit('POSITION_CREATED', $name);
            flash('success', 'Position added.');
        } elseif ($a === 'delete') {
            $p = db_one('SELECT * FROM positions WHERE id = ? AND election_id = ?', [(int) post('id'), $eid]);
            if (!$p) throw new RuntimeException('Position not found.');
            foreach (db_all('SELECT photo FROM candidates WHERE position_id = ?', [$p['id']]) as $c) delete_photo($c['photo']);
            db_run('DELETE FROM positions WHERE id = ?', [$p['id']]);
            audit('POSITION_DELETED', $p['name']);
            flash('success', 'Position deleted.');
        }
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('admin/positions.php?election=' . $eid);
}

$positions = $eid ? db_all("SELECT p.*, (SELECT COUNT(*) FROM candidates c WHERE c.position_id = p.id) AS n FROM positions p WHERE election_id = ? ORDER BY p.id", [$eid]) : [];
$locked = $eid ? election_locked($eid) : false;

layout_start('Positions', 'positions');
if (!$elections): ?>
  <div class="card p-8 text-center text-sm text-slate-600">Create an election before adding positions. <a class="font-medium text-blue-700 hover:underline" href="<?= e(url('admin/elections.php')) ?>">Go to elections</a></div>
<?php else: ?>
  <form method="get" class="mb-6 max-w-sm">
    <label class="label" for="election">Election</label>
    <select class="input" id="election" name="election" data-autosubmit>
      <?php foreach ($elections as $x): ?><option value="<?= (int) $x['id'] ?>" <?= (int) $x['id'] === $eid ? 'selected' : '' ?>><?= e($x['title']) ?> (<?= e($x['status']) ?>)</option><?php endforeach; ?>
    </select>
  </form>
  <?php if ($locked): ?><div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">This election is open or has votes, so its positions are locked.</div><?php endif; ?>

  <div class="grid gap-6 lg:grid-cols-3">
    <div class="card lg:col-span-2">
      <table class="min-w-full divide-y divide-slate-100">
        <thead class="bg-slate-50"><tr><th class="th">Position</th><th class="th">Candidates</th><th class="th"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($positions as $p): ?>
          <tr>
            <td class="td"><p class="font-medium text-slate-900"><?= e($p['name']) ?></p><?php if ($p['description']): ?><p class="text-xs text-slate-500"><?= e($p['description']) ?></p><?php endif; ?></td>
            <td class="td"><a class="text-blue-700 hover:underline" href="<?= e(url('admin/candidates.php?election=' . $eid)) ?>"><?= (int) $p['n'] ?></a></td>
            <td class="td text-right">
              <?php if (!$locked): ?>
              <form method="post" data-confirm="Delete this position and its candidates?">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="election_id" value="<?= $eid ?>">
                <button class="btn btn-outline btn-sm text-red-700" type="submit">Delete</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$positions): ?><tr><td class="td py-8 text-center text-slate-500" colspan="3">No positions yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if (!$locked): ?>
    <section class="card h-fit p-5">
      <h2 class="mb-4 font-semibold text-slate-900">Add position</h2>
      <form method="post" class="space-y-4">
        <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="election_id" value="<?= $eid ?>">
        <div><label class="label" for="name">Position name</label><input class="input" id="name" name="name" required maxlength="100" placeholder="e.g. Class Representative"></div>
        <div><label class="label" for="description">Description (optional)</label><input class="input" id="description" name="description" maxlength="300"></div>
        <button class="btn btn-primary" type="submit">Add position</button>
      </form>
    </section>
    <?php endif; ?>
  </div>
<?php endif;
layout_end();
