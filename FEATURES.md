# E-Voting System — Features & Security

PHP 8.1+ · SQLite (`data/evoting.sqlite`, auto-created) · Tailwind CSS (pre-compiled in `assets/css/app.css`, works offline).
Single shared sign-in (`login.php`, User ID + password) for students and admins.

## 1. Student features

| Area | What it does | Where |
|---|---|---|
| Dashboard | Open elections with deadline + voted/not-voted state, upcoming elections, published results shortcut | `student/dashboard.php` |
| Browse elections | List of non-draft elections; detail page with dates, description, positions and candidate cards (photo, department, faculty, bio) | `student/election.php` |
| Vote (step 1/2) | One choice per position or **Abstain**; radio-card UI; selections staged in session, prior choices restored on back-navigation | `student/vote.php`, `ballot_positions()` |
| Review (step 2/2) | Read-only summary with avatars; explicit “cannot be changed” warning; back-to-edit vs secure-submit | `student/review.php` |
| Confirmation | Success screen with **Confirmation ID** (`EVT-XXXXXXXX`), election + timestamp, printable receipt; secrecy note (ID proves participation, not choices) | `student/confirmation.php` |
| Results | Election selector; total ballots + turnout % with progress bar; per-position bars, **Winner / Leading / Tied** badges, abstain count; provisional label while OPEN | `student/results.php`, `render_results()` in `includes/results.php` |
| Profile | View name, User ID, department, email; change password (current + new + confirm, 8-char letters+numbers rule) | `student/profile.php` |
| Landing page | Public election information (open/upcoming/closed cards with dates), sign-in CTA, secrecy guarantees | `index.php` |

Voting rules enforced in UI **and** server-side:
- Only `OPEN` elections accept votes (`election_for_voting()`).
- Already-voted users are redirected to confirmation.
- `validate_selection()` requires every position to be a valid candidate ID or `0` (abstain); forged IDs are rejected.
- `cast_ballot()` uses `BEGIN IMMEDIATE` so ballot + votes commit atomically.

## 2. Admin features

| Area | What it does | Where |
|---|---|---|
| Dashboard | Focus election, registered voters, votes cast, participation bar, election status, 6 quick actions, recent security activity | `admin/dashboard.php` |
| Elections | Create/edit (DRAFT, UPCOMING only), Schedule, Open now, Close voting, Delete; setup counters (positions / candidates / votes); readiness check blocks opening until every position has ≥1 active candidate; delete blocked when OPEN or when votes exist | `admin/elections.php`, `election_ready_error()`, `election_locked()` |
| Positions | Add/delete per election; locked once election is OPEN or has votes; candidate-count links to candidates | `admin/positions.php` |
| Candidates | Add/edit/delete with name, User/Student ID, department, faculty, bio, photo, active/withdrawn; withdrawn hidden from ballot; add/delete/withdraw locked once OPEN or voted; photo replace cleans up old file | `admin/candidates.php` |
| Voters | Add (blank password auto-generates a one-time temp password shown once), edit, enable/disable, reset password (clears lockout), delete (blocked if the voter has voted); search by ID/name, filter by status, “voted in” per-election filter; paginated (20/page); shows **voted Yes/No, never who they voted for** | `admin/voters.php` |
| Results | Election selector, provisional warning while OPEN, `render_results()` tallies, **Export CSV**, Print | `admin/results.php` |
| Audit logs | Search (actor/details/IP), filter by event, paginated (25/page), colour-coded event badges | `admin/audit-logs.php` |
| Admin accounts | Create admin, enable/disable (cannot disable self), reset password (one-time display), change own password | `admin/users.php` |
| Settings | Institution name, results visibility (`after_close` recommended vs `always` live), session idle timeout 5–120 min (default 15) | `admin/settings.php` |

Election lifecycle: `DRAFT` → `UPCOMING` → `OPEN` → `CLOSED`, plus `sync_elections()` auto-transitions by `start_time`/`end_time` on every request. Closed elections cannot be reopened.

## 3. Platform / UX features

- SQLite auto-schema + seed demo data; `SEED_DEMO=false` in `includes/config.php` + deleting `data/evoting.sqlite` gives a clean start.
- Works from any subfolder (`BASE_URL` detection in `includes/bootstrap.php`); `php -S localhost:8002` ready; XAMPP-ready.
- Responsive layout with mobile sidebar + overlay (`assets/js/app.js`), print styles for receipts/results, empty states everywhere, pagination (`pager()`), `data-confirm` dialogs for destructive actions, `data-autosubmit` selects, password show/hide toggle.
- Demo seed: admin `admin` / `Admin@12345`; students `STU001`, `STU002`, `STU004`–`STU006` / `Student@123`; `STU003` inactive; one open demo election with President / Vice President / Treasurer races.

## 4. Security features

| Threat | Protection | Code reference |
|---|---|---|
| SQL injection | PDO prepared statements everywhere; `ATTR_EMULATE_PREPARES=false`, `ERRMODE_EXCEPTION`; no string-interpolated SQL except whitelisted `ORDER/LIMIT` ints | `includes/db.php` (`db_run`, `db_all`, `db_one`, `db_val`) |
| Password theft / weak passwords | `password_hash` / `password_verify`, `password_needs_rehash`; policy ≥8 chars with letters+numbers (`password_error()`); random temp passwords (`random_password()`); resets displayed once | `login.php`, `admin/voters.php`, `admin/users.php`, `student/profile.php`, `includes/bootstrap.php` |
| Brute force / credential stuffing | `login_attempts` table; **5 failures / 15 min per ID, 20 per IP**; 24 h cleanup; constant-time dummy hash check so timing doesn’t reveal valid IDs; `LOGIN_BLOCKED` audit | `login.php` |
| CSRF | Per-session 32-byte token; `csrf_field()` on every POST; `csrf_check()` aborts with **419** + `CSRF_FAILURE` audit; logout is POST-only with CSRF | `includes/bootstrap.php`, `logout.php`, all POST forms |
| XSS | `e()` (`htmlspecialchars`) on all output; Content-Security-Policy (`default-src 'self'`, `img self data:`, `style self 'unsafe-inline'`, `script self`, `form-action self`, `frame-ancestors 'none'`); no external scripts | `includes/bootstrap.php`, all templates |
| Clickjacking / sniffing / referrer leak | `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin`, `Cache-Control: no-store` | `includes/bootstrap.php` |
| Session hijacking / fixation | `session.use_strict_mode`, `use_only_cookies`, custom `evoting_sid`, HttpOnly, `SameSite=Lax`, `Secure` auto on HTTPS; `session_regenerate_id(true)` on login + password change; idle timeout (default 15 min) with `SESSION_TIMEOUT` audit; `end_session()` clears cookie + destroys session; inactive users force-logged-out | `includes/bootstrap.php` |
| Broken access control | `require_login(?role)`; wrong role → **403** + `UNAUTHORIZED_ACCESS` audit; student/admin navs and routes separated | `includes/bootstrap.php`, `includes/layout.php` |
| Double voting | `UNIQUE(election_id, voter_id)` on `ballots`; atomic `BEGIN IMMEDIATE` ballot insert; pre-check + `DOUBLE_VOTE_BLOCKED` audit; `23000` race handled gracefully | `includes/db.php`, `includes/voting.php`, `student/review.php` |
| Vote tampering (app + DB level) | Triggers: inserts into `ballots`/`votes` only when election is `OPEN`; **no UPDATE/DELETE** on `ballots`/`votes` (`SELECT RAISE(ABORT, …)`); `election_locked()` blocks structural edits after open/votes | `includes/db.php` (`trg_*`), `includes/bootstrap.php` |
| Ballot-stuffing via forged input | `validate_selection()` strict `ctype_digit` + allowlist per position; unknown election/position/candidate rejected | `includes/voting.php` |
| Malicious uploads | `finfo` MIME + `getimagesize` check; JPG/PNG/WebP only; 2 MB cap; random 24-hex filenames; `delete_photo()` regex-guarded cleanup; `Options -Indexes` | `includes/bootstrap.php` (`save_photo()`, `delete_photo()`), `.htaccess` |
| CSV formula injection | `csv_safe()` prefixes `= + - @ TAB CR` cells with `'` on results export | `includes/bootstrap.php`, `admin/results.php` |
| Missing accountability | `audit()` logs user_id, actor, action, details (500-char cap), IP, user agent, timestamp; logging wrapped in try/catch so it never breaks requests; indexed `audit_logs(created_at)` | `includes/bootstrap.php` |
| Enumeration / info leak | Generic “Invalid User ID or password” (inactive treated same as wrong); error pages without stack traces; `Cache-Control: no-store` | `login.php`, `includes/bootstrap.php` (`render_error()`) |

### 4.1 Privacy-by-design ballot storage (differs from naive spec)

- `ballots(election_id, voter_id, confirmation_id)` — **who voted, no choices**.
- `votes(election_id, position_id, candidate_id)` — **choices, no voter link**.
- Admin voter list and audit logs can prove participation but never reveal individual choices.

### 4.2 Audited events

`LOGIN_SUCCESS`, `LOGIN_FAILED`, `LOGIN_BLOCKED`, `LOGOUT`, `SESSION_TIMEOUT`, `CSRF_FAILURE`, `UNAUTHORIZED_ACCESS`, `VOTE_CAST`, `VOTE_FAILED`, `DOUBLE_VOTE_BLOCKED`, `ELECTION_CREATED/UPDATED/SCHEDULED/OPENED/CLOSED/DELETED`, `POSITION_CREATED/DELETED`, `CANDIDATE_CREATED/UPDATED/DELETED`, `VOTER_CREATED/UPDATED/ENABLED/DISABLED/PASSWORD_RESET/DELETED`, `ADMIN_CREATED/ENABLED/DISABLED/PASSWORD_RESET`, `PASSWORD_CHANGED`, `PASSWORD_CHANGE_FAILED`, `SETTINGS_UPDATED`, `RESULTS_EXPORTED`.

### 4.3 Try-it security tests

1. `' OR 1=1 --` in User ID → rejected, `LOGIN_FAILED` logged.
2. `<script>alert(1)</script>` as candidate name → stored escaped, rendered inert.
3. POST without `csrf_token` (devtools/curl) → 419 + `CSRF_FAILURE`.
4. Student GET `/admin/dashboard.php` → 403 + `UNAUTHORIZED_ACCESS`.
5. Vote twice → second attempt redirects to confirmation + `DOUBLE_VOTE_BLOCKED`.
6. 6 wrong passwords → lockout message for 15 min + `LOGIN_BLOCKED`.
7. `DELETE FROM votes;` in SQLite → `Votes cannot be deleted` (trigger); same for `ballots`.
8. Upload a `.php` renamed to `.png` → rejected by MIME/image check.

## 5. Known limitations (declare in your report)

- Someone with raw DB file access could theoretically correlate `ballots` vs `votes` row order/timing — production systems need mixnets, blind signatures, or end-to-end verifiable schemes.
- No email/OTP recovery; admins reset passwords out-of-band.
- Deploy behind HTTPS (cookie `Secure` flag enables automatically when `$_SERVER['HTTPS']` is set).
- SQLite + `BEGIN IMMEDIATE` suits a campus prototype, not heavy concurrent load; use Postgres/MySQL with row-level locking at scale.
- No per-voter verifiable receipt beyond the participation confirmation ID (receipt shows no choices, by design).
