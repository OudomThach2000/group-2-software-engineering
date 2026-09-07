<?php
/**
 * Database connection (PDO).  Owner: Pichponleur Pen.
 * Used by every page that touches data. Reads app/config.php.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $configFile = __DIR__ . '/../config.php';
    if (!file_exists($configFile)) {
        die('Missing app/config.php — copy app/config.example.php to app/config.php and set your database credentials.');
    }
    $cfg = require $configFile;
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $cfg['db_host'], $cfg['db_port'] ?? 3306, $cfg['db_name'], $cfg['db_charset']
    );
    $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}
