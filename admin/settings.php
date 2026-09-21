<?php
/**
 * Admin Settings page — site-wide preferences.
 * Controls the institution name shown across the site, when students may
 * see results (only after closing, or live during voting), and how quickly
 * idle users are signed out. Changes are validated and logged.
 */
// Load shared page tools (login checks, database helpers, page layout).
require __DIR__ . '/../includes/layout.php';
// Only signed-in admins may change settings.
$u = require_login('admin');

// Handle the Save button (form submissions only).
if (is_post()) {
    // Safety check: confirm the form really came from our site (blocks forged requests).
    csrf_check();
    $inst = post('institution_name');
    // results_visibility is either "always" (live) or "after_close" (recommended default).
    $vis = post('results_visibility') === 'always' ? 'always' : 'after_close';
    $timeout = (int) post('session_timeout');
    // Checks: institution name must fit, timeout must be 5–120 minutes.
    if ($inst === '' || strlen($inst) > 80) {
        flash('error', 'Institution name is required (80 characters max).');
    } elseif ($timeout < 5 || $timeout > 120) {
        flash('error', 'Session timeout must be between 5 and 120 minutes.');
    } else {
        // Save all three settings, note the change in the security log, and confirm.
        set_setting('institution_name', $inst);
        set_setting('results_visibility', $vis);
        set_setting('session_timeout', (string) $timeout);
        audit('SETTINGS_UPDATED', "results=$vis, timeout={$timeout}m");
        flash('success', 'Settings saved.');
    }
    // Go back to the form so refreshing does not re-submit it.
    redirect('admin/settings.php');
}
// Draw the page frame, then show the single settings form below.
layout_start('Settings', 'settings');
?>
<!-- Settings form: institution name, results timing, and auto sign-out delay. -->
<form method="post" class="card max-w-xl space-y-5 p-6">
  <?= csrf_field() ?>
  <div><label class="label" for="institution_name">Institution name</label><input class="input" id="institution_name" name="institution_name" required maxlength="80" value="<?= e(setting('institution_name')) ?>"></div>
  <fieldset>
    <legend class="label">When can students see results?</legend>
    <label class="mt-2 flex items-start gap-3 text-sm"><input class="mt-0.5" type="radio" name="results_visibility" value="after_close" <?= setting('results_visibility') !== 'always' ? 'checked' : '' ?>><span><span class="font-medium">After the election closes</span><br><span class="text-slate-500">Recommended. Live counts stay hidden while voting is open.</span></span></label>
    <label class="mt-3 flex items-start gap-3 text-sm"><input class="mt-0.5" type="radio" name="results_visibility" value="always" <?= setting('results_visibility') === 'always' ? 'checked' : '' ?>><span><span class="font-medium">Live, while voting is open</span><br><span class="text-slate-500">Students can watch running totals.</span></span></label>
  </fieldset>
  <div><label class="label" for="session_timeout">Sign out after inactivity (minutes)</label><input class="input max-w-[8rem]" id="session_timeout" name="session_timeout" type="number" min="5" max="120" required value="<?= e(setting('session_timeout', '15')) ?>"></div>
  <button class="btn btn-primary" type="submit">Save settings</button>
</form>
<?php layout_end();
