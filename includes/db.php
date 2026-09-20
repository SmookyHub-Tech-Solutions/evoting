<?php
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    init_schema($pdo);
    seed_defaults($pdo);
    return $pdo;
}

function db_run(string $sql, array $p = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st;
}
function db_all(string $sql, array $p = []): array { return db_run($sql, $p)->fetchAll(); }
function db_one(string $sql, array $p = []): ?array
{
    $r = db_run($sql, $p)->fetch();
    return $r === false ? null : $r;
}
function db_val(string $sql, array $p = []) { return db_run($sql, $p)->fetchColumn(); }
function db_id(): int { return (int) db()->lastInsertId(); }

function init_schema(PDO $pdo): void
{
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id TEXT NOT NULL UNIQUE COLLATE NOCASE,
        name TEXT NOT NULL,
        email TEXT,
        department TEXT,
        password TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'student' CHECK (role IN ('admin','student')),
        status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','inactive')),
        created_at TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS elections (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        start_time TEXT NOT NULL,
        end_time TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'DRAFT' CHECK (status IN ('DRAFT','UPCOMING','OPEN','CLOSED')),
        created_at TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS positions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        election_id INTEGER NOT NULL REFERENCES elections(id) ON DELETE CASCADE,
        name TEXT NOT NULL,
        description TEXT
    );
    CREATE TABLE IF NOT EXISTS candidates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        position_id INTEGER NOT NULL REFERENCES positions(id) ON DELETE CASCADE,
        name TEXT NOT NULL,
        student_id TEXT,
        department TEXT,
        faculty TEXT,
        photo TEXT,
        bio TEXT,
        status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','withdrawn'))
    );
    -- One row per voter per election: proves participation, holds NO choices.
    CREATE TABLE IF NOT EXISTS ballots (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        election_id INTEGER NOT NULL REFERENCES elections(id) ON DELETE CASCADE,
        voter_id INTEGER NOT NULL REFERENCES users(id),
        confirmation_id TEXT NOT NULL UNIQUE,
        created_at TEXT NOT NULL,
        UNIQUE (election_id, voter_id)
    );
    -- Anonymous choices: deliberately NOT linked to a voter.
    CREATE TABLE IF NOT EXISTS votes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        election_id INTEGER NOT NULL REFERENCES elections(id) ON DELETE CASCADE,
        position_id INTEGER NOT NULL REFERENCES positions(id) ON DELETE CASCADE,
        candidate_id INTEGER NOT NULL REFERENCES candidates(id) ON DELETE CASCADE
    );
    CREATE TABLE IF NOT EXISTS audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        actor TEXT,
        action TEXT NOT NULL,
        details TEXT,
        ip_address TEXT,
        user_agent TEXT,
        created_at TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        identifier TEXT NOT NULL,
        ip TEXT,
        created_at INTEGER NOT NULL
    );
    CREATE TABLE IF NOT EXISTS settings (
        name TEXT PRIMARY KEY,
        val TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_logs(created_at);
    CREATE INDEX IF NOT EXISTS idx_votes_candidate ON votes(candidate_id);
    CREATE INDEX IF NOT EXISTS idx_login_attempts ON login_attempts(identifier, created_at);
    ");

    // Database-level integrity rules (defence in depth beyond the PHP checks).
    $triggers = [
        "CREATE TRIGGER IF NOT EXISTS trg_ballot_open BEFORE INSERT ON ballots
         WHEN (SELECT status FROM elections WHERE id = NEW.election_id) != 'OPEN'
         BEGIN SELECT RAISE(ABORT, 'Election is not open'); END",
        "CREATE TRIGGER IF NOT EXISTS trg_vote_open BEFORE INSERT ON votes
         WHEN (SELECT status FROM elections WHERE id = NEW.election_id) != 'OPEN'
         BEGIN SELECT RAISE(ABORT, 'Election is not open'); END",
        "CREATE TRIGGER IF NOT EXISTS trg_votes_no_update BEFORE UPDATE ON votes
         BEGIN SELECT RAISE(ABORT, 'Votes are immutable'); END",
        "CREATE TRIGGER IF NOT EXISTS trg_votes_no_delete BEFORE DELETE ON votes
         BEGIN SELECT RAISE(ABORT, 'Votes cannot be deleted'); END",
        "CREATE TRIGGER IF NOT EXISTS trg_ballots_no_update BEFORE UPDATE ON ballots
         BEGIN SELECT RAISE(ABORT, 'Ballots are immutable'); END",
        "CREATE TRIGGER IF NOT EXISTS trg_ballots_no_delete BEFORE DELETE ON ballots
         BEGIN SELECT RAISE(ABORT, 'Ballots cannot be deleted'); END",
    ];
    foreach ($triggers as $t) {
        $pdo->exec($t);
    }
}

function seed_defaults(PDO $pdo): void
{
    $pdo->exec("INSERT OR IGNORE INTO settings(name,val) VALUES
        ('institution_name','University E-Voting'),
        ('results_visibility','after_close'),
        ('session_timeout','15')");

    if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
        return;
    }
    $now = date('Y-m-d H:i:s');
    $user = $pdo->prepare('INSERT INTO users(student_id,name,email,department,password,role,status,created_at) VALUES (?,?,?,?,?,?,?,?)');
    $user->execute(['admin', 'Election Officer', 'admin@university.edu', 'Electoral Commission', password_hash('Admin@12345', PASSWORD_DEFAULT), 'admin', 'active', $now]);

    if (!SEED_DEMO) {
        return;
    }
    $studentHash = password_hash('Student@123', PASSWORD_DEFAULT);
    foreach ([
        ['STU001', 'Ahmed Bello', 'Computer Science', 'active'],
        ['STU002', 'John Okafor', 'Business Administration', 'active'],
        ['STU003', 'Mary Adeyemi', 'Engineering', 'inactive'],
        ['STU004', 'Fatima Yusuf', 'Law', 'active'],
        ['STU005', 'Chinedu Eze', 'Medicine', 'active'],
        ['STU006', 'Amina Sani', 'Accounting', 'active'],
    ] as [$sid, $name, $dept, $status]) {
        $user->execute([$sid, $name, strtolower($sid) . '@student.university.edu', $dept, $studentHash, 'student', $status, $now]);
    }

    $pdo->prepare("INSERT INTO elections(title,description,start_time,end_time,status,created_at) VALUES (?,?,?,?, 'OPEN', ?)")
        ->execute([
            'Student Leadership Election 2026',
            'Annual election of the Students\' Union executive council.',
            date('Y-m-d H:i:s', time() - 3600),
            date('Y-m-d H:i:s', time() + 7 * 86400),
            $now,
        ]);
    $eid = (int) $pdo->lastInsertId();
    $pos = $pdo->prepare('INSERT INTO positions(election_id,name,description) VALUES (?,?,?)');
    $cand = $pdo->prepare('INSERT INTO candidates(position_id,name,student_id,department,faculty,bio,status) VALUES (?,?,?,?,?,?, \'active\')');
    $data = [
        'President' => [
            ['John Smith', 'C101', 'Computer Science', 'Computing', 'Committed to better lab access and reliable campus Wi-Fi.'],
            ['Sarah Williams', 'C102', 'Business Administration', 'Management Sciences', 'Focused on transparent union finances and student welfare.'],
            ['Michael Brown', 'C103', 'Engineering', 'Engineering', 'Will push for improved workshops and hostel maintenance.'],
        ],
        'Vice President' => [
            ['David Johnson', 'C104', 'Law', 'Law', 'Advocate for student rights and fair representation.'],
            ['Emily Anderson', 'C105', 'Accounting', 'Management Sciences', 'Aims to grow student-led enterprise programmes.'],
        ],
        'Treasurer' => [
            ['James Wilson', 'C106', 'Accounting', 'Management Sciences', 'Will publish a quarterly union budget report.'],
            ['Olivia Davis', 'C107', 'Economics', 'Social Sciences', 'Pledges strict, open expense tracking.'],
        ],
    ];
    foreach ($data as $pname => $cands) {
        $pos->execute([$eid, $pname, null]);
        $pid = (int) $pdo->lastInsertId();
        foreach ($cands as [$n, $s, $d, $f, $b]) {
            $cand->execute([$pid, $n, $s, $d, $f, $b]);
        }
    }
}
