<?php
// TEMPORARY DEBUG — delete after fixing
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo '<h2>Step 1: PHP OK — version ' . PHP_VERSION . '</h2>';

echo '<h2>Step 2: Loading config/app.php</h2>';
require_once __DIR__ . '/config/app.php';
echo 'OK<br>';

echo '<h2>Step 3: Loading config/database.php</h2>';
require_once __DIR__ . '/config/database.php';
echo 'OK<br>';

echo '<h2>Step 4: Loading includes/auth.php</h2>';
require_once __DIR__ . '/includes/auth.php';
echo 'OK<br>';

echo '<h2>Step 5: Loading includes/functions.php</h2>';
require_once __DIR__ . '/includes/functions.php';
echo 'OK<br>';

echo '<h2>Step 6: DB Connection test</h2>';
try {
    $db = get_db();
    echo 'Connected to DB OK<br>';
    $ver = $db->query('SELECT VERSION()')->fetchColumn();
    echo 'MySQL version: ' . $ver . '<br>';
} catch (Exception $e) {
    echo '<b style="color:red">DB Error: ' . htmlspecialchars($e->getMessage()) . '</b><br>';
}

echo '<h2>Step 7: PDO MySQL extension</h2>';
echo extension_loaded('pdo_mysql') ? 'pdo_mysql: ENABLED' : '<b style="color:red">pdo_mysql: MISSING — enable in cPanel PHP Extensions</b>';

echo '<br><br><b style="color:green">All steps passed — delete debug.php</b>';
