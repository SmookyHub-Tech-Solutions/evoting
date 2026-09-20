<?php
require_once __DIR__ . '/config.php';

date_default_timezone_set('Africa/Lagos');
define('ROOT_DIR', dirname(__DIR__));
define('DB_PATH', ROOT_DIR . '/data/evoting.sqlite');

// Work out the URL prefix (e.g. /evoting) so the app runs from any folder.
$__doc = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
$__app = realpath(ROOT_DIR);
$__base = '';
if ($__doc && $__app && strpos($__app, $__doc) === 0) {
    $__base = str_replace('\\', '/', substr($__app, strlen($__doc)));
}
define('BASE_URL', rtrim($__base, '/'));
unset($__doc, $__app, $__base);

// ---- Security headers -------------------------------------------------
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
header('Cache-Control: no-store');

// ---- Session ----------------------------------------------------------
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('evoting_sid');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/db.php';

// ---- Small helpers ----------------------------------------------------
function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }
function redirect(string $path)
{
    header('Location: ' . url($path));
    exit;
}
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function post(string $k, string $d = ''): string
{
    $v = $_POST[$k] ?? $d;
    return is_string($v) ? trim($v) : $d;
}
function get_str(string $k, string $d = ''): string
{
    $v = $_GET[$k] ?? $d;
    return is_string($v) ? trim($v) : $d;
}
function home_for(array $u): string { return $u['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php'; }

function flash(string $type, string $msg): void { $_SESSION['flash'][] = ['t' => $type, 'm' => $msg]; }
function take_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void
{
    $t = $_POST['csrf_token'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        audit('CSRF_FAILURE', $_SERVER['REQUEST_URI'] ?? '');
        render_error(419, 'Request expired', 'Your form session expired or the request could not be verified. Go back, refresh the page and try again.');
    }
}

function setting(string $name, string $default = ''): string
{
    $v = db_val('SELECT val FROM settings WHERE name = ?', [$name]);
    return $v === false ? $default : (string) $v;
}
function set_setting(string $name, string $val): void
{
    db_run('INSERT INTO settings(name,val) VALUES (?,?) ON CONFLICT(name) DO UPDATE SET val = excluded.val', [$name, $val]);
}

function audit(string $action, string $details = '', ?string $actor = null): void
{
    try {
        db_run(
            'INSERT INTO audit_logs(user_id,actor,action,details,ip_address,user_agent,created_at) VALUES (?,?,?,?,?,?,?)',
            [
                $_SESSION['uid'] ?? null,
                $actor ?? ($_SESSION['sid'] ?? 'UNKNOWN'),
                $action,
                substr($details, 0, 500),
                $_SERVER['REMOTE_ADDR'] ?? '',
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                date('Y-m-d H:i:s'),
            ]
        );
    } catch (Throwable $ex) {
        // Logging must never break the request.
    }
}

function end_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

function current_user(): ?array
{
    static $loaded = false, $user = null;
    if (!isset($_SESSION['uid'])) {
        return null;
    }
    if (!$loaded) {
        $loaded = true;
        $user = db_one('SELECT id,student_id,name,email,department,role,status FROM users WHERE id = ?', [$_SESSION['uid']]);
        if (!$user || $user['status'] !== 'active') {
            $user = null;
            end_session();
        }
    }
    return $user;
}

function require_login(?string $role = null): array
{
    $u = current_user();
    if (!$u) {
        redirect('login.php');
    }
    if ($role !== null && $u['role'] !== $role) {
        audit('UNAUTHORIZED_ACCESS', $_SERVER['REQUEST_URI'] ?? '');
        render_error(403, 'Unauthorized Access', 'You do not have permission to view this page.');
    }
    return $u;
}

function render_error(int $code, string $title, string $msg)
{
    http_response_code($code);
    $home = url(($u = current_user()) ? home_for($u) : 'index.php');
    ?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($code . ' · ' . $title) ?></title><link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>"></head>
<body class="flex min-h-screen items-center justify-center bg-gradient-to-br from-navy via-navy-light to-slate-900 p-6 font-sans text-slate-800">
<div class="card w-full max-w-md p-8 text-center shadow-lift animate-fade-up sm:p-10">
  <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-red-50 text-red-600 ring-1 ring-red-100">
    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>
  </span>
  <p class="mt-4 text-5xl font-extrabold tracking-tight text-navy"><?= (int) $code ?></p>
  <h1 class="mt-2 text-xl font-bold"><?= e($title) ?></h1>
  <p class="mt-2 text-sm leading-relaxed text-slate-600"><?= e($msg) ?></p>
  <a href="<?= e($home) ?>" class="btn btn-primary mt-6 w-full">Back to safety</a>
</div></body></html><?php
    exit;
}

function password_error(string $p): ?string
{
    if (strlen($p) < 8) return 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) return 'Password must contain letters and numbers.';
    return null;
}
function random_password(int $len = 10): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $p = '';
        for ($i = 0; $i < $len; $i++) {
            $p .= $chars[random_int(0, strlen($chars) - 1)];
        }
    } while (password_error($p) !== null);
    return $p;
}

function fmt_dt(?string $s): string { return $s ? date('j M Y, g:i A', strtotime($s)) : '—'; }
function to_db_dt(string $in): ?string
{
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $in);
    return $d ? $d->format('Y-m-d H:i:s') : null;
}
function to_input_dt(?string $s): string { return $s ? date('Y-m-d\TH:i', strtotime($s)) : ''; }

function status_badge(string $s): string
{
    $map = [
        'OPEN' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', 'CLOSED' => 'bg-slate-100 text-slate-600 ring-slate-500/20',
        'UPCOMING' => 'bg-amber-50 text-amber-800 ring-amber-600/25', 'DRAFT' => 'bg-slate-100 text-slate-500 ring-slate-500/15',
        'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', 'inactive' => 'bg-red-50 text-red-700 ring-red-600/20',
        'withdrawn' => 'bg-amber-50 text-amber-800 ring-amber-600/25',
    ];
    $dot = [
        'OPEN' => 'bg-emerald-500', 'active' => 'bg-emerald-500',
        'UPCOMING' => 'bg-amber-500', 'withdrawn' => 'bg-amber-500',
        'CLOSED' => 'bg-slate-400', 'DRAFT' => 'bg-slate-400', 'inactive' => 'bg-red-500',
    ][$s] ?? 'bg-slate-400';
    return '<span class="badge ' . ($map[$s] ?? 'bg-slate-100 text-slate-600 ring-slate-500/15') . '"><span class="h-1.5 w-1.5 rounded-full ' . $dot . '"></span>' . e(strtoupper($s)) . '</span>';
}

function pager(int $total, int $per, int $page, array $query = []): string
{
    $pages = max(1, (int) ceil($total / $per));
    if ($pages <= 1) return '';
    $link = fn(int $p, string $label, bool $on) => $on
        ? '<a class="btn btn-outline btn-sm !shadow-none" href="?' . e(http_build_query($query + ['page' => $p])) . '">' . $label . '</a>'
        : '<span class="btn btn-outline btn-sm !shadow-none opacity-40">' . $label . '</span>';
    return '<div class="flex flex-col items-center justify-between gap-3 border-t border-slate-200/70 bg-slate-50/60 px-4 py-3 text-sm text-slate-600 sm:flex-row">'
        . '<span><span class="font-semibold text-slate-900">Page ' . $page . '</span> of ' . $pages . ' · ' . $total . ' records</span><div class="flex gap-2">'
        . $link($page - 1, '← Previous', $page > 1) . $link($page + 1, 'Next →', $page < $pages) . '</div></div>';
}

function save_photo(array $f): ?string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Photo upload failed. Try a smaller image.');
    if ($f['size'] > 2 * 1024 * 1024) throw new RuntimeException('Photo must be 2 MB or smaller.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext || @getimagesize($f['tmp_name']) === false) throw new RuntimeException('Photo must be a JPG, PNG or WebP image.');
    $dir = ROOT_DIR . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Could not save the photo.');
    return $name;
}
function delete_photo(?string $name): void
{
    if ($name && preg_match('/^[a-f0-9]{24}\.(jpg|png|webp)$/', $name)) {
        @unlink(ROOT_DIR . '/uploads/' . $name);
    }
}

function csv_safe(string $v): string { return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v; }

// ---- Election helpers -------------------------------------------------
function sync_elections(): void
{
    $now = date('Y-m-d H:i:s');
    db_run("UPDATE elections SET status='OPEN' WHERE status='UPCOMING' AND start_time <= ? AND end_time > ?", [$now, $now]);
    db_run("UPDATE elections SET status='CLOSED' WHERE status IN ('OPEN','UPCOMING') AND end_time <= ?", [$now]);
}
function election_locked(int $eid): bool
{
    $s = db_val('SELECT status FROM elections WHERE id = ?', [$eid]);
    return in_array($s, ['OPEN', 'CLOSED'], true) || (int) db_val('SELECT COUNT(*) FROM ballots WHERE election_id = ?', [$eid]) > 0;
}
function election_ready_error(int $eid): ?string
{
    $pos = db_all('SELECT id,name FROM positions WHERE election_id = ?', [$eid]);
    if (!$pos) return 'Add at least one position before opening this election.';
    foreach ($pos as $p) {
        if (!(int) db_val("SELECT COUNT(*) FROM candidates WHERE position_id = ? AND status='active'", [$p['id']])) {
            return 'Position "' . $p['name'] . '" has no active candidates.';
        }
    }
    return null;
}

// ---- Runtime: idle timeout + status sync ------------------------------
if (isset($_SESSION['uid'])) {
    $__limit = max(5, (int) setting('session_timeout', '15')) * 60;
    if (time() - (int) ($_SESSION['last'] ?? 0) > $__limit) {
        audit('SESSION_TIMEOUT');
        end_session();
        flash('warning', 'You were signed out after a period of inactivity.');
    } else {
        $_SESSION['last'] = time();
    }
}
sync_elections();
