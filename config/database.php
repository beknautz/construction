<?php
// ============================================================
// Database Configuration — PDO
// ============================================================

define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')    ?: '3306');
define('DB_NAME',    getenv('DB_NAME')    ?: 'flexdb');
define('DB_USER',    getenv('DB_USER')    ?: 'flexuser');
define('DB_PASS',    getenv('DB_PASS')    ?: 'YOUR_PASSWORD_HERE'); // ← fill in before FTP
define('DB_CHARSET', 'utf8mb4');

function get_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // In production, log and show a friendly error; never expose credentials.
            if (APP_ENV === 'development') {
                die('<pre>DB Connection Error: ' . htmlspecialchars($e->getMessage()) . '</pre>');
            }
            die('<p>A database error occurred. Please try again later.</p>');
        }
    }

    return $pdo;
}
