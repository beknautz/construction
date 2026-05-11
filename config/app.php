<?php
// ============================================================
// Application Configuration
// ============================================================

define('APP_NAME',    'Construction OS');
define('APP_VERSION', '1.0.0');
define('APP_ENV',     getenv('APP_ENV') ?: 'production');
define('APP_URL',     getenv('APP_URL') ?: 'https://tradeflex.ai');

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
