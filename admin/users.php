<?php
require __DIR__ . '/../includes/layout.php';
$u = require_login('admin');

if (is_post()) {
    csrf_check();
    $a = post('action');
    $id = (int) post('id');
    try {
        if ($a === 'add') {
            $sid = post('student_id');
            $name = post('name');
            $email = post('email');
            $pw = (string) ($_POST['password'] ?? '');
            if (!preg_match('~^[A-Za-z0-9/_.\-]{3,30}$~', $sid)) throw new RuntimeException('Username must be 3–30 characters: letters, numbers and / _ . - only.');
            if ($name === '' || strlen($name) > 100) throw new RuntimeException('Name is required (100 characters max).');
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email address or leave it blank.');
            if ($err = password_error($pw)) throw new RuntimeException($err);
            if (db_val('SELECT 1 FROM users WHERE student_id = ?', [$sid])) throw new RuntimeException('That ID is already in use.');
            db_run("INSERT INTO users(student_id,name,email,department,password,role,status,created_at) VALUES (?,?,?,?,?, 'admin', 'active', ?)",
                [$sid, $name, $email, 'Electoral Commission', password_hash($pw, PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
            audit('ADMIN_CREATED', $sid);
            flash('success', 'Admin account created.');
        } else {
            $t = db_one("SELECT * FROM users WHERE id = ? AND role = 'admin'", [$id]);
            if (!$t) throw new RuntimeException('Account not found.');
            if ($a === 'toggle') {
                if ($id === (int) $u['id']) throw new RuntimeException('You cannot disable your own account.');
                $new = $t['status'] === 'active' ? 'inactive' : 'active';
                db_run('UPDATE users SET status = ? WHERE id = ?', [$new, $id]);
                audit($new === 'active' ? 'ADMIN_ENABLED' : 'ADMIN_DISABLED', $t['student_id']);
                flash('success', 'Account ' . ($new === 'active' ? 'enabled.' : 'disabled.'));
            } elseif ($a === 'reset') {
                $pw = random_password(12);
                db_run('UPDATE users SET password = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
                db_run('DELETE FROM login_attempts WHERE identifier = ?', [strtolower($t['student_id'])]);
                audit('ADMIN_PASSWORD_RESET', $t['student_id']);
                flash('success', "New password for {$t['student_id']}: $pw — copy it now, it will not be shown again.");
            } elseif ($a === 'password') {
                // Change own password
                $cur = (string) ($_POST['current'] ?? '');
                $new = (string) ($_POST['new'] ?? '');
                if ($id !== (int) $u['id']) throw new RuntimeException('You can only change your own password here.');
                $row = db_one('SELECT password FROM users WHERE id = ?', [$id]);
                if (!password_verify($cur, $row['password'])) { audit('PASSWORD_CHANGE_FAILED'); throw new RuntimeException('Your current password is incorrect.'); }
                if ($err = password_error($new)) throw new RuntimeException($err);
                db_run('UPDATE users SET password = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $id]);
                session_regenerate_id(true);
                audit('PASSWORD_CHANGED');
                flash('success', 'Your password was updated.');
            }
        }
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('admin/users.php');
}

$rows = db_all("SELECT * FROM users WHERE role = 'admin' ORDER BY id");
layout_start('Admin accounts', 'users');
?>
<div class="grid gap-6 lg:grid-cols-3">
  <div class="card overflow-x-auto lg:col-span-2">
    <table class="min-w-full divide-y divide-slate-100">
      <thead class="bg-slate-50"><tr><th class="th">Username</th><th class="th">Name</th><th class="th">Status</th><th class="th">Actions</th></tr></thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($rows as $r): $me = (int) $r['id'] === (int) $u['id']; ?>
        <tr>
          <td class="td font-mono text-xs"><?= e($r['student_id']) ?><?= $me ? ' (you)' : '' ?></td>
          <td class="td font-medium text-slate-900"><?= e($r['name']) ?></td>
          <td class="td"><?= status_badge($r['status']) ?></td>
          <td class="td">
            <?php foreach ([['toggle', $r['status'] === 'active' ? 'Disable' : 'Enable', ''], ['reset', 'Reset password', 'Generate a new password for this account?']] as [$act, $lbl, $cf]):
                if ($me && $act === 'toggle') continue; ?>
              <form method="post" class="inline" <?= $cf ? 'data-confirm="' . e($cf) . '"' : '' ?>>
                <?= csrf_field() ?><input type="hidden" name="action" value="<?= e($act) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button class="btn btn-outline btn-sm" type="submit"><?= e($lbl) ?></button>
              </form>
            <?php endforeach; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="space-y-6">
    <section class="card p-5">
      <h2 class="mb-4 font-semibold text-slate-900">Add admin account</h2>
      <form method="post" class="space-y-4">
        <?= csrf_field() ?><input type="hidden" name="action" value="add">
        <div><label class="label" for="a_sid">Username</label><input class="input" id="a_sid" name="student_id" required maxlength="30"></div>
        <div><label class="label" for="a_name">Full name</label><input class="input" id="a_name" name="name" required maxlength="100"></div>
        <div><label class="label" for="a_email">Email</label><input class="input" id="a_email" name="email" type="email" maxlength="150"></div>
        <div><label class="label" for="a_pw">Password</label><input class="input" id="a_pw" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
        <button class="btn btn-primary" type="submit">Create account</button>
      </form>
    </section>
    <section class="card p-5">
      <h2 class="mb-4 font-semibold text-slate-900">Change your password</h2>
      <form method="post" class="space-y-4">
        <?= csrf_field() ?><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
        <div><label class="label" for="c_cur">Current password</label><input class="input" id="c_cur" name="current" type="password" required autocomplete="current-password"></div>
        <div><label class="label" for="c_new">New password</label><input class="input" id="c_new" name="new" type="password" required minlength="8" autocomplete="new-password"></div>
        <button class="btn btn-outline" type="submit">Update password</button>
      </form>
    </section>
  </div>
</div>
<?php layout_end();
