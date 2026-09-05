<?php
// Norbooz Crochet - database configuration for a standard XAMPP installation.
// Update DB_USER / DB_PASS if your MySQL setup is different.
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'norbooz_crochet_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_NAME', 'Norbooz Crochet');
define('BASE_URL', '/norbooz_crochet');

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
