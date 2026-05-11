<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ob_implicit_flush(true);

function step($n, $msg) {
    echo "<h3>Step $n: $msg</h3>";
    flush();
}

step(1, 'PHP OK — version ' . PHP_VERSION);

step(2, 'Loading config/app.php');
require_once __DIR__ . '/config/app.php';
echo 'OK'; flush();

step(3, 'Loading config/database.php');
require_once __DIR__ . '/config/database.php';
echo 'OK'; flush();

step(4, 'Loading includes/auth.php');
require_once __DIR__ . '/includes/auth.php';
echo 'OK'; flush();

step(5, 'Scanning functions.php for PHP 8 syntax');
$src = file_get_contents(__DIR__ . '/includes/functions.php');
$checks = [
    'mixed type hint'  => '/function\s+\w+\s*\(\s*mixed\s/',
    'union types'      => '/int\|string|float\|int|string\|int/',
    'catch no var'     => '/catch\s*\(\s*\w+\s*\)/',
];
foreach ($checks as $label => $pattern) {
    $found = preg_match($pattern, $src);
    echo "$label: " . ($found ? '<b style="color:red">FOUND (PHP 8 only — upload the new file)</b>' : 'clean') . '<br>';
}
flush();

step(6, 'Loading includes/functions.php');
require_once __DIR__ . '/includes/functions.php';
echo 'OK'; flush();

step(7, 'DB connection test');
try {
    $db = get_db();
    echo 'Connected OK — MySQL ' . $db->query('SELECT VERSION()')->fetchColumn();
} catch (Exception $e) {
    echo '<b style="color:red">DB Error: ' . htmlspecialchars($e->getMessage()) . '</b>';
}
flush();

step(8, 'pdo_mysql extension');
echo extension_loaded('pdo_mysql') ? '<b style="color:green">ENABLED</b>' : '<b style="color:red">MISSING</b>';

echo '<br><br><b style="color:green">Done.</b>';
