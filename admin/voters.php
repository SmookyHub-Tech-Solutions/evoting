<?php
/**
 * Admin Voters page — manage student voter accounts.
 * Admins add voters (with a temporary password), edit details, disable or
 * re-enable sign-in, reset passwords, and delete accounts that never voted.
 * The table shows who has voted (never who they voted for) and supports
 * search, status filter, and paging through large lists.
 */
// Load shared page tools (login checks, database helpers, page layout).
require __DIR__ . '/../includes/layout.php';
// Only signed-in admins may manage voters.
$u = require_login('admin');

// Handle the Add / Edit / Disable / Reset-password / Delete buttons.
if (is_post()) {
    // Safety check: confirm the form really came from our site (blocks forged requests).
    csrf_check();
    $a = post('action');
    $id = (int) post('id');
    try {
        // "Save" covers both adding a new voter and editing an existing one.
        if ($a === 'save') {
            $sid = post('student_id');
            $name = post('name');
            $email = post('email');
            $dept = post('department');
            $pw = (string) ($_POST['password'] ?? '');
            // Checks: valid student ID format, name present, valid email, no duplicate ID.
            if (!preg_match('~^[A-Za-z0-9/_.\-]{3,30}$~', $sid)) throw new RuntimeException('Student ID must be 3–30 characters: letters, numbers and / _ . - only.');
            if ($name === '' || strlen($name) > 100) throw new RuntimeException('Name is required (100 characters max).');
            if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150)) throw new RuntimeException('Enter a valid email address or leave it blank.');
            if (strlen($dept) > 100) throw new RuntimeException('Department is too long.');
            if (db_val('SELECT 1 FROM users WHERE student_id = ? AND id != ?', [$sid, $id])) throw new RuntimeException('That student ID is already registered.');
            if ($id) {
                // Editing: update the existing student record.
                if (!db_val("SELECT 1 FROM users WHERE id = ? AND role = 'student'", [$id])) throw new RuntimeException('Voter not found.');
                db_run('UPDATE users SET student_id=?, name=?, email=?, department=? WHERE id=?', [$sid, $name, $email, $dept, $id]);
                // audit() writes a line in the security log: who did what and when.
                audit('VOTER_UPDATED', $sid);
                flash('success', 'Voter updated.');
            } else {
                // Adding: create the account with a password (typed or auto-generated) stored scrambled.
                $generated = $pw === '';
                if ($generated) $pw = random_password();
                elseif ($err = password_error($pw)) throw new RuntimeException($err);
                db_run("INSERT INTO users(student_id,name,email,department,password,role,status,created_at) VALUES (?,?,?,?,?, 'student', 'active', ?)",
                    [$sid, $name, $email, $dept, password_hash($pw, PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
                audit('VOTER_CREATED', $sid);
                flash('success', 'Voter added.' . ($generated ? " Temporary password for $sid: $pw — copy it now, it will not be shown again." : ''));
            }
        } else {
            // Other buttons act on one existing voter: look them up first.
            $v = db_one("SELECT * FROM users WHERE id = ? AND role = 'student'", [$id]);
            if (!$v) throw new RuntimeException('Voter not found.');
            if ($a === 'toggle') {
                // Disable/Enable: switches whether the student can sign in.
                $new = $v['status'] === 'active' ? 'inactive' : 'active';
                db_run('UPDATE users SET status = ? WHERE id = ?', [$new, $id]);
                audit($new === 'active' ? 'VOTER_ENABLED' : 'VOTER_DISABLED', $v['student_id']);
                flash('success', $v['name'] . ($new === 'active' ? ' can now sign in.' : ' has been disabled.'));
            } elseif ($a === 'reset') {
                // Reset password: set a new temporary password and clear lockouts from failed logins.
                $pw = random_password();
                db_run('UPDATE users SET password = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
                db_run('DELETE FROM login_attempts WHERE identifier = ?', [strtolower($v['student_id'])]);
                audit('VOTER_PASSWORD_RESET', $v['student_id']);
                flash('success', "New temporary password for {$v['student_id']}: $pw — copy it now, it will not be shown again.");
            } elseif ($a === 'delete') {
                // Delete: only allowed if the voter never voted, so ballot history is never lost.
                if ((int) db_val('SELECT COUNT(*) FROM ballots WHERE voter_id = ?', [$id])) throw new RuntimeException('This voter has voted, so the record must be kept. Disable the account instead.');
                db_run('DELETE FROM users WHERE id = ?', [$id]);
                audit('VOTER_DELETED', $v['student_id']);
                flash('success', 'Voter deleted.');
            }
        }
    } catch (RuntimeException $ex) {
        // If any check failed, show its message instead of saving anything.
        flash('error', $ex->getMessage());
    }
    // Go back to the list so refreshing does not re-submit the form.
    redirect('admin/voters.php');
}

// Read search/filter/page choices from the address bar, then build the voter query.
$q = get_str('q');
$status = in_array(get_str('status'), ['active', 'inactive'], true) ? get_str('status') : '';
$elections = db_all('SELECT id,title FROM elections ORDER BY id DESC');
$open = db_val("SELECT id FROM elections WHERE status = 'OPEN' ORDER BY end_time LIMIT 1");
$eid = (int) ($_GET['election'] ?? ($open ?: ($elections[0]['id'] ?? 0)));
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 20;

$where = "role = 'student'";
$params = [];
if ($q !== '') { $where .= ' AND (student_id LIKE ? OR name LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($status !== '') { $where .= ' AND status = ?'; $params[] = $status; }
// Count matching voters for paging, then fetch just this page (20 rows) with a voted flag.
$total = (int) db_val("SELECT COUNT(*) FROM users WHERE $where", $params);
$rows = db_all("SELECT u.*, (SELECT COUNT(*) FROM ballots b WHERE b.election_id = ? AND b.voter_id = u.id) AS voted
    FROM users u WHERE $where ORDER BY student_id LIMIT $per OFFSET " . (($page - 1) * $per), array_merge([$eid], $params));
$edit = isset($_GET['edit']) ? db_one("SELECT * FROM users WHERE id = ? AND role = 'student'", [(int) $_GET['edit']]) : null;

function mini_form(string $action, int $id, string $label, string $cls = 'btn-outline', string $confirm = ''): string
{
    // Tiny helper that builds each row button (Enable/Reset/Delete) with a safety token.
    // csrf_field() adds a hidden token proving the form came from our site.
    return '<form method="post" class="inline" ' . ($confirm ? 'data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field()
        . '<input type="hidden" name="action" value="' . e($action) . '"><input type="hidden" name="id" value="' . $id . '">'
        . '<button class="btn btn-sm ' . $cls . '" type="submit">' . e($label) . '</button></form>';
}

// Draw the page frame (header, menu).
layout_start('Voters', 'voters');
?>
<!-- Collapsible add/edit voter form; opens automatically when editing. -->
<details class="card mb-6" <?= $edit ? 'open' : '' ?>>
  <summary class="cursor-pointer px-5 py-4 font-semibold text-slate-900"><?= $edit ? 'Edit voter' : 'Add voter' ?></summary>
  <form method="post" class="grid gap-4 border-t border-slate-100 p-5 sm:grid-cols-2 lg:grid-cols-4">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
    <div><label class="label" for="student_id">Student ID</label><input class="input" id="student_id" name="student_id" required maxlength="30" value="<?= e($edit['student_id'] ?? '') ?>"></div>
    <div><label class="label" for="name">Full name</label><input class="input" id="name" name="name" required maxlength="100" value="<?= e($edit['name'] ?? '') ?>"></div>
    <div><label class="label" for="email">Email</label><input class="input" id="email" name="email" type="email" maxlength="150" value="<?= e($edit['email'] ?? '') ?>"></div>
    <div><label class="label" for="department">Department</label><input class="input" id="department" name="department" maxlength="100" value="<?= e($edit['department'] ?? '') ?>"></div>
    <?php if (!$edit): ?>
    <div class="sm:col-span-2"><label class="label" for="password">Temporary password</label><input class="input" id="password" name="password" type="text" autocomplete="off" placeholder="Leave blank to generate one"></div>
    <?php endif; ?>
    <div class="flex items-end gap-2 sm:col-span-2">
      <button class="btn btn-primary" type="submit"><?= $edit ? 'Save changes' : 'Add voter' ?></button>
      <?php if ($edit): ?><a class="btn btn-outline" href="<?= e(url('admin/voters.php')) ?>">Cancel</a><?php endif; ?>
    </div>
  </form>
</details>

<!-- Search and filter row: text search, active/inactive, and which election the Voted column refers to. -->
<form method="get" class="mb-4 grid gap-3 sm:grid-cols-4">
  <input class="input sm:col-span-2" type="search" name="q" value="<?= e($q) ?>" placeholder="Search by student ID or name" aria-label="Search voters">
  <select class="input" name="status" aria-label="Filter by status">
    <option value="">All accounts</option>
    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
  </select>
  <select class="input" name="election" aria-label="Election for voting status">
    <?php foreach ($elections as $x): ?><option value="<?= (int) $x['id'] ?>" <?= (int) $x['id'] === $eid ? 'selected' : '' ?>>Voted in: <?= e($x['title']) ?></option><?php endforeach; ?>
    <?php if (!$elections): ?><option value="0">No elections yet</option><?php endif; ?>
  </select>
  <button class="btn btn-outline sm:col-span-4 sm:w-fit" type="submit">Apply filters</button>
</form>

<!-- Voter table with per-row actions; pager() below splits long lists into pages. -->
<div class="card overflow-x-auto">
  <table class="min-w-full divide-y divide-slate-100">
    <thead class="bg-slate-50"><tr><th class="th">Student ID</th><th class="th">Name</th><th class="th">Department</th><th class="th">Status</th><th class="th">Voted</th><th class="th">Actions</th></tr></thead>
    <tbody class="divide-y divide-slate-100">
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="td font-mono text-xs"><?= e($r['student_id']) ?></td>
        <td class="td font-medium text-slate-900"><?= e($r['name']) ?></td>
        <td class="td"><?= e($r['department'] ?: '—') ?></td>
        <td class="td"><?= status_badge($r['status']) ?></td>
        <td class="td"><?= (int) $r['voted'] ? '<span class="font-medium text-teal-700">Yes</span>' : '<span class="text-slate-500">No</span>' ?></td>
        <td class="td">
          <div class="flex flex-wrap gap-1.5">
            <a class="btn btn-outline btn-sm" href="?edit=<?= (int) $r['id'] ?>">Edit</a>
            <?= mini_form('toggle', (int) $r['id'], $r['status'] === 'active' ? 'Disable' : 'Enable') ?>
            <?= mini_form('reset', (int) $r['id'], 'Reset password', 'btn-outline', 'Generate a new temporary password for this voter?') ?>
            <?= mini_form('delete', (int) $r['id'], 'Delete', 'btn-outline text-red-700', 'Delete this voter permanently?') ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td class="td py-8 text-center text-slate-500" colspan="6">No voters match. Add a voter above.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <?= pager($total, $per, $page, array_filter(['q' => $q, 'status' => $status, 'election' => $eid])) ?>
</div>
<p class="mt-3 text-xs text-slate-500">The list shows whether a student has voted, never who they voted for.</p>
<?php layout_end();
