<?php
/**
 * Voting helpers — plain-English overview:
 * This file handles the actual act of voting: checking you are allowed to
 * vote, showing the ballot choices, checking your selections are valid, and
 * saving your vote safely so it cannot be linked back to you.
 * Non-technical meaning: it is the ballot box + the official who checks
 * your eligibility before you drop your vote in.
 * Technical note: enforces OPEN status + one-vote-per-voter, then stores
 * ballots (participation proof) separately from votes (anonymous choices)
 * inside an IMMEDIATE transaction.
 */

// --- Eligibility check ---
/**
 * Load an election for voting, or send the student away if not allowed.
 * Plain English: makes sure the election exists, is open, and you have not
 * voted already; otherwise shows a message and redirects.
 * Technical note: redirects to student/dashboard.php or confirmation page;
 * relies on db_one()/db_val() helpers.
 */
function election_for_voting(int $eid, array $u): array
{
    // Plain explanation: look up the election by its number.
    $el = db_one('SELECT * FROM elections WHERE id = ?', [$eid]);
    // Plain explanation: no such election — go back to the dashboard.
    if (!$el) {
        flash('error', 'Election not found.');
        redirect('student/dashboard.php');
    }
    // Plain explanation: voting is only allowed while the election is open.
    if ($el['status'] !== 'OPEN') {
        flash('warning', 'This election is not open for voting.');
        redirect('student/dashboard.php');
    }
    // Plain explanation: one person, one vote — already voted, show receipt.
    if (db_val('SELECT COUNT(*) FROM ballots WHERE election_id = ? AND voter_id = ?', [$eid, $u['id']])) {
        flash('info', 'You have already voted in this election.');
        redirect('student/confirmation.php?election=' . $eid);
    }
    return $el;
}

// --- Ballot loading ---
/**
 * Get every position with its active candidates for one election.
 * Plain English: builds the ballot paper — each job role plus the people running for it.
 * Technical note: SELECT positions ORDER BY id, then per-position SELECT
 * active candidates ORDER BY name; adds a 'candidates' key to each position.
 */
// Positions of an election, each with its active candidates.
function ballot_positions(int $eid): array
{
    // Plain explanation: fetch all roles (e.g. President) for this election.
    $pos = db_all('SELECT * FROM positions WHERE election_id = ? ORDER BY id', [$eid]);
    // Plain explanation: attach the list of running candidates to each role.
    foreach ($pos as &$p) {
        $p['candidates'] = db_all("SELECT * FROM candidates WHERE position_id = ? AND status='active' ORDER BY name", [$p['id']]);
    }
    return $pos;
}

// --- Choice validation ---
/**
 * Check the student's submitted choices are legal.
 * Plain English: makes sure every pick is a real candidate (or a deliberate
 * skip), and rejects anything tampered or mistyped.
 * Technical note: expects [position_id => candidate_id] with 0 = abstain;
 * returns null on any invalid digit/ID so the caller can reject the ballot.
 */
// Returns [position_id => candidate_id (0 = abstain)] or null when invalid.
function validate_selection(array $positions, $input): ?array
{
    // Plain explanation: treat missing/garbled form data as empty.
    $input = is_array($input) ? $input : [];
    $sel = [];
    // Plain explanation: check each role one by one.
    foreach ($positions as $p) {
        // Plain explanation: skip roles with nobody running.
        if (!$p['candidates']) continue;
        $raw = $input[$p['id']] ?? null;
        // Plain explanation: normalise numbers so "5" and 5 are treated alike.
        if (is_int($raw)) $raw = (string) $raw;
        // Plain explanation: must be digits only — anything else is rejected.
        if (!is_string($raw) || !ctype_digit($raw)) return null;
        $cid = (int) $raw;
        // Plain explanation: build the allowed list, plus 0 meaning "skip".
        $valid = array_map('intval', array_column($p['candidates'], 'id'));
        if ($cid !== 0 && !in_array($cid, $valid, true)) return null;
        $sel[(int) $p['id']] = $cid;
    }
    return $sel;
}

// --- Vote saving ---
/**
 * Save a validated ballot permanently.
 * Plain English: drops your anonymous choices in the box and gives you a
 * receipt code, while recording that you voted (without recording what you picked).
 * Technical note: BEGIN IMMEDIATE transaction inserts one ballots row plus
 * one votes row per non-abstain choice; ROLLBACK on error; confirmation ID
 * is EVT- + 4 random bytes.
 */
function cast_ballot(array $el, array $u, array $sel): string
{
    $pdo = db();
    // Plain explanation: make a random receipt code for the voter.
    $conf = 'EVT-' . strtoupper(bin2hex(random_bytes(4)));
    // Plain explanation: lock things so two votes at once cannot clash.
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        // Plain explanation: record WHO voted (no choices stored here).
        $pdo->prepare('INSERT INTO ballots(election_id,voter_id,confirmation_id,created_at) VALUES (?,?,?,?)')
            ->execute([$el['id'], $u['id'], $conf, date('Y-m-d H:i:s')]);
        // Plain explanation: record WHAT was chosen (no voter link here).
        $ins = $pdo->prepare('INSERT INTO votes(election_id,position_id,candidate_id) VALUES (?,?,?)');
        foreach ($sel as $pid => $cid) {
            // Plain explanation: skips (0) are not stored as votes.
            if ($cid > 0) $ins->execute([$el['id'], $pid, $cid]);
        }
        // Plain explanation: all good — make the save permanent.
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        // Plain explanation: something failed — undo everything, then re-throw.
        try { $pdo->exec('ROLLBACK'); } catch (Throwable $ignore) {}
        throw $ex;
    }
    return $conf;
}
