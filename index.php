<?php
// Root entry point — redirect to dashboard (or login if not authenticated)
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: ' . APP_URL . '/modules/dashboard/');
} else {
    header('Location: ' . APP_URL . '/login.php');
}
exit;
