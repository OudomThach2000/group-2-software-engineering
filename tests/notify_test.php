<?php
/**
 * UC7 Notify Resident — unit test for the notification service  (FR5).  Owner: Rolando Labrador.
 *
 * Checks notify_status_change(), deliver_queued_notifications() and
 * requeue_failed_notifications() from app/src/notify.php: who receives a message,
 * over which channel, what it says, and how deliveries and failures are recorded.
 *
 * Run from the project root, after importing db/schema.sql and db/seed.sql:
 *     php tests/notify_test.php
 * Everything runs inside one transaction that is rolled back at the end, so the
 * database is left as it was. One "delivery ... failed: provider down" line on the
 * error output is expected: it comes from the check of a failing provider.
 * Exit code 0 = every check passed, 1 = at least one failed.
 */
require_once __DIR__ . '/../app/includes/db.php';
require_once __DIR__ . '/../app/src/notify.php';

$failures = 0;

/** Print PASS or FAIL for one check, counting the failures. */
function check(string $description, $expected, $actual): void
{
    global $failures;
    if ($expected === $actual) {
        echo "PASS  $description\n";
        return;
    }
    $failures++;
    echo "FAIL  $description\n"
       . '      expected: ' . var_export($expected, true) . "\n"
       . '      actual:   ' . var_export($actual, true) . "\n";
}

/** PASS when $action is refused with an InvalidArgumentException. */
function check_rejected(string $description, callable $action): void
{
    global $failures;
    try {
        $action();
    } catch (InvalidArgumentException $ex) {
        echo "PASS  $description\n";
        return;
    }
    $failures++;
    echo "FAIL  $description\n      expected an InvalidArgumentException, none was thrown\n";
}

/** Inserts a test issue and returns its id. */
function make_issue(PDO $pdo, string $ref, ?string $contact, ?int $userId = null): int
{
    $categoryId = (int)$pdo->query('SELECT id FROM categories ORDER BY sort_order LIMIT 1')->fetchColumn();
    $pdo->prepare(
        'INSERT INTO issues (tracking_ref, category_id, description, location_text, reporter_user_id, reporter_contact)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$ref, $categoryId, 'Notification test issue', 'Test street', $userId, $contact]);
    return (int)$pdo->lastInsertId();
}

/** The stored notification row, or false. */
function notification(PDO $pdo, ?int $id)
{
    $stmt = $pdo->prepare('SELECT recipient, channel, message, status, sent_at FROM notifications WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function count_notifications(PDO $pdo, string $status): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE status = ?');
    $stmt->execute([$status]);
    return (int)$stmt->fetchColumn();
}

// 1. Channel and wording, no database needed.
check('an email address goes by email', 'email', notification_channel('resident@example.com'));
check('a phone number goes by SMS', 'sms', notification_channel('+855 12 345 678'));
check(
    'the message names the report, the status and what it means',
    'Update on your report CIR-2026-0007: In-progress. Work on your report has started.',
    build_status_message('CIR-2026-0007', 'In-progress')
);

$pdo = db();
$pdo->beginTransaction();
try {
    // Start from an empty queue so the counts below only see this test's messages.
    $pdo->exec('DELETE FROM notifications');

    $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)')
        ->execute(['Notify Test', 'account@notify-test.example', password_hash('not-used', PASSWORD_DEFAULT)]);
    $userId = (int)$pdo->lastInsertId();

    $anonymous   = make_issue($pdo, 'TEST-NOTIFY-01', null);
    $byEmail     = make_issue($pdo, 'TEST-NOTIFY-02', 'resident@example.com');
    $byPhone     = make_issue($pdo, 'TEST-NOTIFY-03', '+855 12 345 678');
    $byAccount   = make_issue($pdo, 'TEST-NOTIFY-04', null, $userId);
    $typedWins   = make_issue($pdo, 'TEST-NOTIFY-05', 'typed@example.com', $userId);
    $brokenInbox = make_issue($pdo, 'TEST-NOTIFY-06', 'broken@example.com');

    // 2. Queuing (FR5).
    check('an anonymous report with no contact gets no message', null, notify_status_change($anonymous, 'Assigned', $pdo));
    check('...and nothing is queued for it', 0, count_notifications($pdo, 'queued'));

    $emailId = notify_status_change($byEmail, 'Assigned', $pdo);
    $row = notification($pdo, $emailId);
    check('queues a message for the contact typed on the form',
        ['resident@example.com', 'email', 'queued'], [$row['recipient'], $row['channel'], $row['status']]);
    check('the message is about the right report', true, strpos($row['message'], 'TEST-NOTIFY-02') !== false);

    $row = notification($pdo, notify_status_change($byPhone, 'In-progress', $pdo));
    check('a phone contact is queued as SMS', ['+855 12 345 678', 'sms'], [$row['recipient'], $row['channel']]);

    $row = notification($pdo, notify_status_change($byAccount, 'Resolved', $pdo));
    check('a registered reporter with no typed contact gets it at the account email',
        'account@notify-test.example', $row['recipient']);

    $row = notification($pdo, notify_status_change($typedWins, 'Closed', $pdo));
    check('the typed contact is used before the account email', 'typed@example.com', $row['recipient']);

    notify_status_change($brokenInbox, 'Assigned', $pdo);
    check('five messages are now queued', 5, count_notifications($pdo, 'queued'));

    check_rejected('refuses a status that does not exist', fn() => notify_status_change($byEmail, 'Finished', $pdo));
    check_rejected('refuses an issue that does not exist', fn() => notify_status_change(PHP_INT_MAX, 'Assigned', $pdo));

    // 3. Delivery: the provider accepts email, refuses SMS, and breaks on one address.
    $provider = function (array $n): bool {
        if ($n['recipient'] === 'broken@example.com') {
            throw new RuntimeException('provider down');
        }
        return $n['channel'] === 'email';
    };
    check('delivers what it can and counts the rest as failed', ['sent' => 3, 'failed' => 2],
        deliver_queued_notifications($pdo, $provider));
    $row = notification($pdo, $emailId);
    check('a delivered message is marked sent', 'sent', $row['status']);
    check('...with the time it was sent', true, $row['sent_at'] !== null);
    check('failures are kept for a retry', 2, count_notifications($pdo, 'failed'));
    check('the queue is empty afterwards', 0, count_notifications($pdo, 'queued'));
    check('a second run finds nothing to do', ['sent' => 0, 'failed' => 0],
        deliver_queued_notifications($pdo, $provider));

    // 4. Retry.
    check('re-queues the two failed messages', 2, requeue_failed_notifications($pdo));
    check('respects the batch limit', ['sent' => 1, 'failed' => 0],
        deliver_queued_notifications($pdo, fn(array $n) => true, 1));
    check('the other message waits for the next run', 1, count_notifications($pdo, 'queued'));
} finally {
    $pdo->rollBack();
}

if ($failures > 0) {
    echo "\n$failures check(s) failed.\n";
    exit(1);
}
echo "\nAll checks passed.\n";
