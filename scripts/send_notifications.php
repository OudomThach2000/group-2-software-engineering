<?php
/**
 * Delivers queued notifications  (FR5, UC7).  Owner: Rolando Labrador.
 *
 * Run from the project root, by hand or on a schedule (Windows Task Scheduler or cron):
 *     php scripts/send_notifications.php
 *     php scripts/send_notifications.php --retry-failed    (re-queue failed messages first)
 *
 * Version 1 has no email or SMS provider, so each message is written to
 * app/logs/notifications.log instead of being sent. Connecting a real provider only
 * means replacing log_delivery() with a function that calls it.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../app/includes/db.php';
require_once __DIR__ . '/../app/src/notify.php';

const NOTIFICATION_LOG = __DIR__ . '/../app/logs/notifications.log';

/** Stand-in for a real provider: appends the message to the log file. */
function log_delivery(array $notification): bool
{
    $dir = dirname(NOTIFICATION_LOG);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Cannot create $dir");
    }
    $line = sprintf(
        "[%s] #%d %s to %s: %s\n",
        date('Y-m-d H:i:s'),
        $notification['id'],
        strtoupper($notification['channel']),
        $notification['recipient'],
        $notification['message']
    );
    return file_put_contents(NOTIFICATION_LOG, $line, FILE_APPEND | LOCK_EX) !== false;
}

try {
    $pdo = db();
    if (in_array('--retry-failed', $argv, true)) {
        echo 'Re-queued ' . requeue_failed_notifications($pdo) . " failed notification(s).\n";
    }
    $counts = deliver_queued_notifications($pdo, 'log_delivery');
    echo "Sent {$counts['sent']}, failed {$counts['failed']}.\n";
    exit($counts['failed'] > 0 ? 1 : 0);
} catch (PDOException $ex) {
    fwrite(STDERR, 'Database error: ' . $ex->getMessage() . "\n");
    exit(2);
}
