<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    log_activity('auth', 'logout', 'User logged out');
    logout_user();
}

header('Location: ' . APP_URL . '/login.php');
exit;
