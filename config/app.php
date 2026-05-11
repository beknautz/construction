<?php
// ============================================================
// Application Configuration
// ============================================================

define('APP_NAME',    'Construction OS');
define('APP_VERSION', '1.0.0');
define('APP_ENV',     getenv('APP_ENV') ?: 'production');

// Auto-detect APP_URL from the request when not set via environment.
// Works on localhost, cPanel VPS, subdomain, or subfolder installs.
if (!defined('APP_URL')) {
    if (getenv('APP_URL')) {
        define('APP_URL', rtrim(getenv('APP_URL'), '/'));
    } else {
        $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Derive subfolder path: strip /index.php and everything after from SCRIPT_NAME
        $script   = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $base     = rtrim(dirname($script), '/\\');
        // Walk up until we hit the app root (where config/ lives)
        $appRoot  = rtrim(str_replace($_SERVER['DOCUMENT_ROOT'] ?? '', '', __DIR__ . '/..'), '/');
        define('APP_URL', $scheme . '://' . $host . $appRoot);
    }
}

// Session
define('SESSION_NAME',     'construction_os');
define('SESSION_LIFETIME', 7200); // 2 hours

// Uploads
define('UPLOAD_PATH',     __DIR__ . '/../assets/uploads');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_IMAGES',  ['jpg','jpeg','png','gif','webp']);
define('ALLOWED_DOCS',    ['pdf','doc','docx','xls','xlsx','csv']);

// Pagination
define('ITEMS_PER_PAGE', 25);

// Timezone
date_default_timezone_set(getenv('APP_TZ') ?: 'America/Chicago');

// Error display
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => APP_ENV === 'production',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
