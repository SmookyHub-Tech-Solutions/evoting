<?php
/**
 * App configuration — plain-English overview:
 * This tiny file holds switches that control how the voting app starts up.
 * Non-technical meaning: it decides whether a fresh install should come
 * with a demo admin account, sample students, and a sample election so you
 * can try things out immediately.
 * Technical note: constants defined here are loaded first via bootstrap.php
 * and read by includes/db.php when it seeds a new database.
 */

// --- Demo data switch ---
// Plain explanation: turn sample data on/off for first-time setup.
// Set to false on a real live server so no demo accounts are created.
const SEED_DEMO = true;
