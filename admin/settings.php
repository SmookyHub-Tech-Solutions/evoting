<?php
require __DIR__ . '/../includes/layout.php';
$u = require_login('admin');

if (is_post()) {
    csrf_check();
    $inst = post('institution_name');
    $vis = post('results_visibility') === 'always' ? 'always' : 'after_close';
    $timeout = (int) post('session_timeout');
    if ($inst === '' || strlen($inst) > 80) {
        flash('error', 'Institution name is required (80 characters max).');
    } elseif ($timeout < 5 || $timeout > 120) {
        flash('error', 'Session timeout must be between 5 and 120 minutes.');
    } else {
        set_setting('institution_name', $inst);
        set_setting('results_visibility', $vis);
        set_setting('session_timeout', (string) $timeout);
        audit('SETTINGS_UPDATED', "results=$vis, timeout={$timeout}m");
        flash('success', 'Settings saved.');
    }
    redirect('admin/settings.php');
}
layout_start('Settings', 'settings');
?>
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
