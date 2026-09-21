<?php
/**
 * App startup file — plain-English overview:
 * This file runs at the top of every page. It loads settings, figures out
 * the website address, turns on browser safety rules, starts the login
 * session, and provides small helper tools (links, messages, security checks,
 * election status updates) used everywhere else.
 * Non-technical meaning: it is the front-door + security desk — it prepares
 * everything before any page is shown.
 * Technical note: defines ROOT_DIR, DB_PATH, BASE_URL; sends security
 * headers; configures a hardened session; requires db.php; ends with idle
 * timeout handling + sync_elections().
 */
require_once __DIR__ . '/config.php';

// --- Timezone + paths ---
// Plain explanation: use Lagos time and remember key folder locations.
date_default_timezone_set('Africa/Lagos');
define('ROOT_DIR', dirname(__DIR__));
define('DB_PATH', ROOT_DIR . '/data/evoting.sqlite');

// --- Base URL ---
// Plain explanation: Work out the URL prefix (e.g. /evoting) so the app runs from any folder.
$__doc = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
$__app = realpath(ROOT_DIR);
$__base = '';
if ($__doc && $__app && strpos($__app, $__doc) === 0) {
    $__base = str_replace('\\', '/', substr($__app, strlen($__doc)));
}
define('BASE_URL', rtrim($__base, '/'));
// Plain explanation: clean up temporary variables so they cannot leak.
unset($__doc, $__app, $__base);

// ---- Security headers -------------------------------------------------
// --- Browser safety rules ---
// Plain explanation: tell browsers to block click-jacking, guessing file types,
// leaking referrers, loading outside content, and caching private pages.
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
header('Cache-Control: no-store');

// ---- Session ----------------------------------------------------------
// --- Login session setup ---
// Plain explanation: create a safe, private login session so the app remembers
// who is signed in without letting attackers steal or fixate it.
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

// Plain explanation: load the database tools now that paths/session exist.
require_once __DIR__ . '/db.php';

// ---- Small helpers ----------------------------------------------------
// --- Display + request helpers ---
/**
 * Escape text for safe display in HTML.
 * Plain English: makes user names/messages safe to show so they cannot run code.
 * Technical note: htmlspecialchars with ENT_QUOTES | ENT_SUBSTITUTE, UTF-8.
 */
function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/**
 * Build a full site link from a short path.
 * Plain English: turns 'login.php' into the correct address including folder prefix.
 * Technical note: prepends BASE_URL and trims slashes.
 */
function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }

/**
 * Send the visitor to another page and stop.
 * Plain English: jumps to a new page, e.g. back to login.
 * Technical note: sends Location header via url() then exit.
 */
function redirect(string $path)
{
    header('Location: ' . url($path));
    exit;
}

/**
 * Check if this visit submitted a form.
 * Plain English: returns true when the page was reached via a Save/Submit button.
 * Technical note: checks REQUEST_METHOD === 'POST'.
 */
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }

/**
 * Read a submitted form field as trimmed text.
 * Plain English: safely gets what the user typed, or a fallback if missing.
 * Technical note: reads $_POST[$k], trims strings, returns $d otherwise.
 */
function post(string $k, string $d = ''): string
{
    $v = $_POST[$k] ?? $d;
    return is_string($v) ? trim($v) : $d;
}

/**
 * Read a URL query value as trimmed text.
 * Plain English: safely gets ?page=2 style values from the address bar.
 * Technical note: reads $_GET[$k], trims strings, returns $d otherwise.
 */
function get_str(string $k, string $d = ''): string
{
    $v = $_GET[$k] ?? $d;
    return is_string($v) ? trim($v) : $d;
}

/**
 * Pick the home page for a user by role.
 * Plain English: admins go to the admin dashboard, students to theirs.
 * Technical note: checks $u['role'] === 'admin'.
 */
function home_for(array $u): string { return $u['role'] === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php'; }

// --- Flash messages ---
/**
 * Save a one-time pop-up notice (success/error/info).
 * Plain English: queues a message to show on the next page, e.g. "Vote saved".
 * Technical note: appends ['t','m'] to $_SESSION['flash'].
 */
function flash(string $type, string $msg): void { $_SESSION['flash'][] = ['t' => $type, 'm' => $msg]; }

/**
 * Fetch and clear queued pop-up notices.
 * Plain English: grabs waiting messages and removes them so they show only once.
 * Technical note: reads then unsets $_SESSION['flash']; used by layout_start().
 */
function take_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// --- Form forgery protection ---
/**
 * Get (or create) this login's secret form token.
 * Plain English: makes a private code that proves forms came from our site.
 * Technical note: 32 random bytes hex-encoded, stored in $_SESSION['csrf'].
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * Print the hidden form field carrying the secret token.
 * Plain English: adds the invisible security code to every form.
 * Technical note: returns <input type="hidden" name="csrf_token" ...> escaped via e().
 */
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }

/**
 * Verify the submitted form token, blocking fakes.
 * Plain English: stops forged requests; shows an "expired" page if the code is wrong.
 * Technical note: hash_equals() compare; audits CSRF_FAILURE; renders 419 error.
 */
function csrf_check(): void
{
    $t = $_POST['csrf_token'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        audit('CSRF_FAILURE', $_SERVER['REQUEST_URI'] ?? '');
        render_error(419, 'Request expired', 'Your form session expired or the request could not be verified. Go back, refresh the page and try again.');
    }
}

// --- Site settings ---
/**
 * Read a site setting (e.g. school name) from the database.
 * Plain English: looks up a saved preference, or returns a default if missing.
 * Technical note: SELECT val FROM settings WHERE name = ? via db_val().
 */
function setting(string $name, string $default = ''): string
{
    $v = db_val('SELECT val FROM settings WHERE name = ?', [$name]);
    return $v === false ? $default : (string) $v;
}

/**
 * Save a site setting to the database.
 * Plain English: stores a preference such as the institution name.
 * Technical note: INSERT ... ON CONFLICT(name) DO UPDATE (upsert).
 */
function set_setting(string $name, string $val): void
{
    db_run('INSERT INTO settings(name,val) VALUES (?,?) ON CONFLICT(name) DO UPDATE SET val = excluded.val', [$name, $val]);
}

// --- Audit trail ---
/**
 * Write a line to the security log.
 * Plain English: records who did what and when, for later review by admins.
 * Technical note: INSERT INTO audit_logs with user id, actor, IP, user-agent;
 * wrapped in try/catch so logging never breaks the page.
 */
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

// --- Session handling ---
/**
 * Log the current user out completely and start fresh.
 * Plain English: wipes the login, deletes the cookie, and gives a clean session.
 * Technical note: clears $_SESSION, expires cookie, session_destroy + fresh
 * session_start + regenerate_id(true).
 */
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

/**
 * Get the currently signed-in user, or null if guest.
 * Plain English: tells pages who is logged in; signs out deactivated accounts.
 * Technical note: cached per-request via static; SELECT from users by
 * $_SESSION['uid']; calls end_session() if missing/inactive.
 */
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

/**
 * Require a login (and optionally a role) for a page.
 * Plain English: sends guests to login; blocks wrong roles with an error page.
 * Technical note: redirects to login.php when guest; audits
 * UNAUTHORIZED_ACCESS and renders 403 when $role mismatches.
 */
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

/**
 * Show a friendly error page and stop (e.g. 403, 404, 419).
 * Plain English: displays a neat error card with a "Back to safety" button.
 * Technical note: sets HTTP status code, picks home link by role, echoes HTML + exit.
 */
function render_error(int $code, string $title, string $msg)
{
    http_response_code($code);
    $home = url(($u = current_user()) ? home_for($u) : 'index.php');
    ?><!-- Error card: big code, title, message, and back-to-safety button --><!DOCTYPE html>
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

// --- Password helpers ---
/**
 * Check a password meets the strength rule.
 * Plain English: must be 8+ characters with both letters and numbers.
 * Technical note: strlen + regex checks; returns error text or null if OK.
 */
function password_error(string $p): ?string
{
    if (strlen($p) < 8) return 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) return 'Password must contain letters and numbers.';
    return null;
}

/**
 * Create a random readable password.
 * Plain English: invents a safe temporary password avoiding confusing letters.
 * Technical note: random_int() picks from unambiguous chars; loops until
 * password_error() passes.
 */
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

// --- Date + badge helpers ---
/**
 * Format a database date nicely for display.
 * Plain English: turns '2026-01-01 10:00:00' into '1 Jan 2026, 10:00 AM'.
 * Technical note: date('j M Y, g:i A', strtotime($s)); '—' when empty.
 */
function fmt_dt(?string $s): string { return $s ? date('j M Y, g:i A', strtotime($s)) : '—'; }

/**
 * Convert a form date-time to database format.
 * Plain English: turns the calendar picker's value into storable date text.
 * Technical note: DateTime::createFromFormat('Y-m-d\TH:i'); null if invalid.
 */
function to_db_dt(string $in): ?string
{
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $in);
    return $d ? $d->format('Y-m-d H:i:s') : null;
}

/**
 * Convert a database date to form-picker format.
 * Plain English: turns stored dates back into the calendar field's format.
 * Technical note: date('Y-m-d\TH:i', strtotime($s)); '' when empty.
 */
function to_input_dt(?string $s): string { return $s ? date('Y-m-d\TH:i', strtotime($s)) : ''; }

/**
 * Build a coloured status label (OPEN, CLOSED, active, etc.).
 * Plain English: returns a small pill badge with a dot, e.g. green for OPEN.
 * Technical note: $map/$dot lookup tables of Tailwind classes + e(strtoupper()).
 */
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

/**
 * Build Previous/Next page buttons for long lists.
 * Plain English: shows "Page 1 of 5" plus back/forward buttons keeping filters.
 * Technical note: ceil($total/$per) pages; http_build_query($query+page);
 * disabled span when at the ends; '' when only one page.
 */
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

// --- Photo uploads ---
/**
 * Save an uploaded candidate photo safely.
 * Plain English: checks size/type is a real image, then stores it with a random name.
 * Technical note: 2MB max; finfo MIME + getimagesize checks; jpg/png/webp only;
 * random 12-byte name in ROOT_DIR/uploads; throws RuntimeException on failure.
 */
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

/**
 * Delete a candidate photo file safely.
 * Plain English: removes an old picture when replaced, only if the name looks valid.
 * Technical note: regex whitelist /^[a-f0-9]{24}\.(jpg|png|webp)$/ + @unlink.
 */
function delete_photo(?string $name): void
{
    if ($name && preg_match('/^[a-f0-9]{24}\.(jpg|png|webp)$/', $name)) {
        @unlink(ROOT_DIR . '/uploads/' . $name);
    }
}

/**
 * Make text safe for spreadsheet export.
 * Plain English: stops Excel treating "=1+1" style text as a formula.
 * Technical note: prefixes with ' when starting with = + - @ tab/CR.
 */
function csv_safe(string $v): string { return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v; }

// ---- Election helpers -------------------------------------------------
// --- Election status helpers ---
/**
 * Auto-move elections to OPEN/CLOSED based on current time.
 * Plain English: flips UPCOMING to OPEN when start passes, and OPEN/UPCOMING
 * to CLOSED when the end passes — so admins need not click anything.
 * Technical note: two UPDATEs comparing start_time/end_time to now (Y-m-d H:i:s).
 */
function sync_elections(): void
{
    $now = date('Y-m-d H:i:s');
    db_run("UPDATE elections SET status='OPEN' WHERE status='UPCOMING' AND start_time <= ? AND end_time > ?", [$now, $now]);
    db_run("UPDATE elections SET status='CLOSED' WHERE status IN ('OPEN','UPCOMING') AND end_time <= ?", [$now]);
}

/**
 * Check if an election can no longer be edited.
 * Plain English: returns true once voting has started or any ballot exists.
 * Technical note: locked when status is OPEN/CLOSED or COUNT(ballots) > 0.
 */
function election_locked(int $eid): bool
{
    $s = db_val('SELECT status FROM elections WHERE id = ?', [$eid]);
    return in_array($s, ['OPEN', 'CLOSED'], true) || (int) db_val('SELECT COUNT(*) FROM ballots WHERE election_id = ?', [$eid]) > 0;
}

/**
 * Check an election is ready to open, with a reason if not.
 * Plain English: makes sure every role has at least one active candidate;
 * returns the problem text or null when ready.
 * Technical note: SELECT positions then per-position COUNT(active candidates).
 */
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
// --- Auto sign-out + status refresh on every page ---
// Plain explanation: sign out inactive users after the admin's timeout, and
// refresh election OPEN/CLOSED states so lists are always current.
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
// Plain explanation: update election statuses on every page load.
sync_elections();
