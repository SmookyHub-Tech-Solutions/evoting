# University E-Voting System (V1)

PHP 8.1+ · SQLite · Tailwind CSS (pre-compiled, works offline)

## Run on XAMPP
1. Copy this folder to `C:\xampp\htdocs\evoting`.
2. Start Apache. Make sure `pdo_sqlite`, `sqlite3` and `fileinfo` are enabled in `php.ini` (default in XAMPP).
3. Open `http://localhost/evoting/`. `data/evoting.sqlite` is created automatically on first run.

Quick test without XAMPP: run `php -S localhost:8000` inside this folder.

## Demo accounts (change after first login)
| Role | ID | Password |
|---|---|---|
| Admin | `admin` | `Admin@12345` |
| Student | `STU001`, `STU002`, `STU004`-`STU006` | `Student@123` |
| Disabled student | `STU003` | cannot sign in |

For a clean start with no demo data, set `SEED_DEMO` to `false` in `includes/config.php` and delete `data/evoting.sqlite`.

## Editing the design
`npm install`, then `npm run build:css` (or `watch:css`) regenerates `assets/css/app.css` from `assets/src/input.css`.

## Security controls
| Threat | Protection | Where |
|---|---|---|
| SQL injection | PDO prepared statements | `includes/db.php` |
| Password theft | `password_hash` / `password_verify` | `login.php` |
| CSRF | Token on every POST | `csrf_check()` |
| XSS | `e()` escaping + CSP header | `includes/bootstrap.php` |
| Brute force | 5 failures / 15 min per ID, 20 per IP | `login.php` |
| Session attacks | HttpOnly, SameSite, regenerate on login, idle timeout, POST-only logout | `bootstrap.php` |
| Unauthorised access | Role check gives 403 and an audit event | `require_login()` |
| Double voting | `UNIQUE(election_id, voter_id)` plus a transaction | `db.php`, `voting.php` |
| Tampering | DB triggers block edits/deletes of votes and ballots; audit log | `db.php` |
| Uploads | MIME + image check, random names, no PHP execution in `uploads/` | `save_photo()` |

## Design note (differs from the spec)
The spec's `votes` table stored `voter_id` beside `candidate_id`, letting anyone with DB access see how each student voted. Here `ballots` records who voted (no choices) and `votes` records choices (no voter).

## Known limitations (state these in your report)
- Row order in `ballots` and `votes` could in theory be correlated by someone with raw DB access; production systems need mixnets, blind signatures or verifiable schemes.
- No email/OTP recovery; admins reset passwords.
- Deploy behind HTTPS (the cookie `secure` flag turns on automatically).
- SQLite suits a campus prototype, not heavy concurrent load.

## Security tests to demonstrate
`' OR 1=1--` in login; `<script>` as a candidate name; POST without CSRF token (419); student opening `/admin/dashboard.php` (403); voting twice; 6 wrong passwords (lockout); `DELETE FROM votes` in SQLite (blocked by trigger). All events show in Admin > Audit logs.
