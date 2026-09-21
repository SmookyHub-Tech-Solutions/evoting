<?php
/**
 * Sign-out page (logout.php) — plain-language guide.
 *
 * What this does: safely ends the current visit.
 * If the request is a form submission from a signed-in user,
 * it checks the security token, writes a sign-out note in the
 * activity log, clears the session, and prepares a confirmation
 * message. Everyone is then sent back to the sign-in page.
 */
// Load shared setup: sessions, security checks, and helpers.
require __DIR__ . '/includes/bootstrap.php';
// Auth + form check: only act on a genuine signed-in sign-out request.
if (is_post() && current_user()) {
    // Security token check: proves the request came from our own site.
    csrf_check();
    // Write a sign-out note in the activity log for auditing.
    audit('LOGOUT');
    // Clear the session so the next visitor starts fresh.
    end_session();
    // One-time message shown on the next page ("You have been signed out.").
    flash('success', 'You have been signed out.');
}
// Send everyone to the sign-in page after signing out.
redirect('login.php');
