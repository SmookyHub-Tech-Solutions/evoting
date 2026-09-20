<?php
function election_for_voting(int $eid, array $u): array
{
    $el = db_one('SELECT * FROM elections WHERE id = ?', [$eid]);
    if (!$el) {
        flash('error', 'Election not found.');
        redirect('student/dashboard.php');
    }
    if ($el['status'] !== 'OPEN') {
        flash('warning', 'This election is not open for voting.');
        redirect('student/dashboard.php');
    }
    if (db_val('SELECT COUNT(*) FROM ballots WHERE election_id = ? AND voter_id = ?', [$eid, $u['id']])) {
        flash('info', 'You have already voted in this election.');
        redirect('student/confirmation.php?election=' . $eid);
    }
    return $el;
}

// Positions of an election, each with its active candidates.
function ballot_positions(int $eid): array
{
    $pos = db_all('SELECT * FROM positions WHERE election_id = ? ORDER BY id', [$eid]);
    foreach ($pos as &$p) {
        $p['candidates'] = db_all("SELECT * FROM candidates WHERE position_id = ? AND status='active' ORDER BY name", [$p['id']]);
    }
    return $pos;
}

// Returns [position_id => candidate_id (0 = abstain)] or null when invalid.
function validate_selection(array $positions, $input): ?array
{
    $input = is_array($input) ? $input : [];
    $sel = [];
    foreach ($positions as $p) {
        if (!$p['candidates']) continue;
        $raw = $input[$p['id']] ?? null;
        if (is_int($raw)) $raw = (string) $raw;
        if (!is_string($raw) || !ctype_digit($raw)) return null;
        $cid = (int) $raw;
        $valid = array_map('intval', array_column($p['candidates'], 'id'));
        if ($cid !== 0 && !in_array($cid, $valid, true)) return null;
        $sel[(int) $p['id']] = $cid;
    }
    return $sel;
}

function cast_ballot(array $el, array $u, array $sel): string
{
    $pdo = db();
    $conf = 'EVT-' . strtoupper(bin2hex(random_bytes(4)));
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $pdo->prepare('INSERT INTO ballots(election_id,voter_id,confirmation_id,created_at) VALUES (?,?,?,?)')
            ->execute([$el['id'], $u['id'], $conf, date('Y-m-d H:i:s')]);
        $ins = $pdo->prepare('INSERT INTO votes(election_id,position_id,candidate_id) VALUES (?,?,?)');
        foreach ($sel as $pid => $cid) {
            if ($cid > 0) $ins->execute([$el['id'], $pid, $cid]);
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable $ignore) {}
        throw $ex;
    }
    return $conf;
}
