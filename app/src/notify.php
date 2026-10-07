<?php
/**
 * Notification service  (FR5, UC7).  Owner: Rolando Labrador.
 *
 * notify_status_change() is called by the pages right after a status change has been
 * committed. It only queues a message in the notifications table. Delivery is done
 * separately by scripts/send_notifications.php, so a slow or failed delivery never
 * delays the page or undoes the status change (Section 7.2, items 2 and 5).
 */
require_once __DIR__ . '/../includes/db.php';

/** What the resident is told for each status (same wording as the tracking page, 7.4.3). */
const STATUS_MESSAGES = [
    'New'         => 'Your report has been received and is waiting to be reviewed.',
    'Assigned'    => 'A worker has been given your report.',
    'In-progress' => 'Work on your report has started.',
    'Resolved'    => 'The work is reported as finished.',
    'Closed'      => 'The report is complete and closed.',
];

/** 'email' for an email address, otherwise 'sms' (the submit form only accepts email or phone). */
function notification_channel(string $recipient): string
{
    return filter_var($recipient, FILTER_VALIDATE_EMAIL) ? 'email' : 'sms';
}

/** e.g. "Update on your report CIR-2026-0005: In-progress. Work on your report has started." */
function build_status_message(string $trackingRef, string $status): string
{
    return sprintf('Update on your report %s: %s. %s', $trackingRef, $status, STATUS_MESSAGES[$status] ?? '');
}

/**
 * Queues a message to the reporter of an issue about its new status and returns the
 * notification id, or null when the reporter left no way to reach them.
 * The contact typed on the form is used first, then the email of the reporter's account.
 */
function notify_status_change(int $issueId, string $newStatus, ?PDO $pdo = null): ?int
{
    if (!isset(STATUS_MESSAGES[$newStatus])) {
        throw new InvalidArgumentException("Unknown status: $newStatus");
    }
    $pdo = $pdo ?? db();

    $stmt = $pdo->prepare(
        'SELECT i.tracking_ref, i.reporter_contact, u.email AS account_email
           FROM issues i
           LEFT JOIN users u ON u.id = i.reporter_user_id
          WHERE i.id = ?'
    );
    $stmt->execute([$issueId]);
    $issue = $stmt->fetch();
    if (!$issue) {
        throw new InvalidArgumentException("No issue with id $issueId");
    }

    $recipient = trim((string)($issue['reporter_contact'] ?: $issue['account_email']));
    if ($recipient === '') {
        return null;
    }

    $pdo->prepare(
        "INSERT INTO notifications (issue_id, recipient, channel, message, status)
         VALUES (?, ?, ?, ?, 'queued')"
    )->execute([
        $issueId,
        $recipient,
        notification_channel($recipient),
        build_status_message($issue['tracking_ref'], $newStatus),
    ]);
    return (int)$pdo->lastInsertId();
}

/**
 * Hands queued notifications, oldest first, to $send and records the outcome of each.
 * $send receives one row (id, issue_id, recipient, channel, message) and returns true
 * when the message was delivered. A false return or an exception marks it failed, so it
 * can be retried later, and never stops the rest of the batch.
 * Returns the counts, e.g. ['sent' => 3, 'failed' => 1].
 */
function deliver_queued_notifications(PDO $pdo, callable $send, int $limit = 50): array
{
    $stmt = $pdo->prepare(
        "SELECT id, issue_id, recipient, channel, message
           FROM notifications
          WHERE status = 'queued'
          ORDER BY id
          LIMIT ?"
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $queued = $stmt->fetchAll();

    // "AND status = 'queued'" so two runs at the same time cannot both mark one message.
    $record = $pdo->prepare("UPDATE notifications SET status = ?, sent_at = ? WHERE id = ? AND status = 'queued'");
    $counts = ['sent' => 0, 'failed' => 0];

    foreach ($queued as $notification) {
        try {
            $delivered = (bool)$send($notification);
        } catch (Throwable $ex) {
            error_log('notify: delivery of notification ' . $notification['id'] . ' failed: ' . $ex->getMessage());
            $delivered = false;
        }
        $record->execute([
            $delivered ? 'sent' : 'failed',
            $delivered ? date('Y-m-d H:i:s') : null,
            $notification['id'],
        ]);
        $counts[$delivered ? 'sent' : 'failed']++;
    }
    return $counts;
}

/** Puts failed notifications back in the queue for another try. Returns how many were re-queued. */
function requeue_failed_notifications(PDO $pdo): int
{
    return $pdo->exec("UPDATE notifications SET status = 'queued' WHERE status = 'failed'");
}
