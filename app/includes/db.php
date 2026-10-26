<?php
/**
 * Database connection (PDO).  Owner: Pichponleur Pen.
 * Used by every page that touches data. Reads app/config.php.
 *
 * Also the last line of defence for errors that no page catches, for example MySQL being
 * down while a page loads its data (#9): the details go to the PHP error log, and the user
 * sees a short message instead of PHP's error, which can show file names and the
 * database user (NFR3).
 */

/** What a user sees when a page fails with an error that no code caught. */
const UNAVAILABLE_MESSAGE = 'Sorry, the system is not available right now. Please try again later.';

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

/**
 * Logs an exception that no page caught and shows UNAVAILABLE_MESSAGE instead.
 * Status 503 (try again later) for a database error, 500 for any other error.
 */
function handle_uncaught_exception(Throwable $ex): void
{
    error_log(sprintf('Uncaught %s: %s in %s:%d', get_class($ex), $ex->getMessage(), $ex->getFile(), $ex->getLine()));
    $message = '<p class="error" role="alert">' . htmlspecialchars(UNAVAILABLE_MESSAGE) . '</p>';
    if (headers_sent()) {
        echo $message;   // the page has started, so add the message where it stopped
        return;
    }
    http_response_code($ex instanceof PDOException ? 503 : 500);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
       . '<title>Community Issue Reporting System</title><link rel="stylesheet" href="/assets/css/style.css">'
       . '</head><body><main class="container">' . $message
       . '<p><a href="/index.php">Back to the home page</a></p></main></body></html>';
}

// Web pages only: the command line scripts and tests keep PHP's normal error output.
if (PHP_SAPI !== 'cli') {
    set_exception_handler('handle_uncaught_exception');
}
