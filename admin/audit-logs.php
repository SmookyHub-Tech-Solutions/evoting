<?php
/**
 * Admin Audit Logs page — read-only security diary.
 * Lists every important event (logins, votes cast, admin changes) with who,
 * what, when and which computer (IP address). Used to investigate problems
 * or suspicious activity. Searching and paging keep long histories usable.
 */
// Load shared page tools (login checks, database helpers, page layout).
require __DIR__ . '/../includes/layout.php';
// Only signed-in admins may view the security log.
$u = require_login('admin');

// Read search/filter/page choices from the address bar.
$action = get_str('action');
$q = get_str('q');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = 25;
$where = '1=1';
$params = [];
if ($action !== '') { $where .= ' AND action = ?'; $params[] = $action; }
if ($q !== '') { $where .= ' AND (actor LIKE ? OR details LIKE ? OR ip_address LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%"); }
// Count matching entries for paging, then fetch just this page (25 newest first).
$total = (int) db_val("SELECT COUNT(*) FROM audit_logs WHERE $where", $params);
$rows = db_all("SELECT * FROM audit_logs WHERE $where ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params);
// Distinct event names for the filter dropdown (e.g. LOGIN_SUCCESS, VOTE_CAST).
$actions = array_column(db_all('SELECT DISTINCT action FROM audit_logs ORDER BY action'), 'action');

function log_badge(string $a): string
{
    // Tiny helper: colour each event label (red = failure, amber = warning, teal = vote/login).
    $cls = 'bg-slate-100 text-slate-700';
    if (preg_match('/FAILED|FAILURE|BLOCKED|UNAUTHORIZED/', $a)) $cls = 'bg-red-100 text-red-700';
    elseif (preg_match('/TIMEOUT|DELETED|DISABLED|RESET/', $a)) $cls = 'bg-amber-100 text-amber-800';
    elseif ($a === 'VOTE_CAST' || $a === 'LOGIN_SUCCESS') $cls = 'bg-teal-100 text-teal-800';
    return '<span class="badge font-mono ' . $cls . '">' . e($a) . '</span>';
}

// Draw the page frame (header, menu).
layout_start('Audit logs', 'audit');
?>
<!-- Filter row: text search plus event-type dropdown. -->
<form method="get" class="mb-4 grid gap-3 sm:grid-cols-4">
  <input class="input sm:col-span-2" type="search" name="q" value="<?= e($q) ?>" placeholder="Search user, details or IP address" aria-label="Search logs">
  <select class="input" name="action" aria-label="Filter by event">
    <option value="">All events</option>
    <?php foreach ($actions as $x): ?><option value="<?= e($x) ?>" <?= $x === $action ? 'selected' : '' ?>><?= e($x) ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn-outline" type="submit">Filter</button>
</form>

<!-- Log table: time, who, what happened, details, computer address. pager() splits long lists into pages. -->
<div class="card overflow-x-auto">
  <table class="min-w-full divide-y divide-slate-100">
    <thead class="bg-slate-50"><tr><th class="th">Time</th><th class="th">User</th><th class="th">Event</th><th class="th">Details</th><th class="th">IP address</th></tr></thead>
    <tbody class="divide-y divide-slate-100">
    <?php foreach ($rows as $r): ?>
      <tr>
        <td class="td whitespace-nowrap"><?= e(date('j M Y, H:i:s', strtotime($r['created_at']))) ?></td>
        <td class="td"><?= e($r['actor']) ?></td>
        <td class="td"><?= log_badge($r['action']) ?></td>
        <td class="td max-w-xs truncate" title="<?= e($r['details']) ?>"><?= e($r['details']) ?></td>
        <td class="td font-mono text-xs"><?= e($r['ip_address']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td class="td py-8 text-center text-slate-500" colspan="5">No matching events.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <?= pager($total, $per, $page, array_filter(['q' => $q, 'action' => $action])) ?>
</div>
<?php layout_end();
