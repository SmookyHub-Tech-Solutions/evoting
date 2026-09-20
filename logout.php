<?php
require __DIR__ . '/includes/bootstrap.php';
if (is_post() && current_user()) {
    csrf_check();
    audit('LOGOUT');
    end_session();
    flash('success', 'You have been signed out.');
}
redirect('login.php');
