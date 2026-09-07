<?php
/**
 * Notification service  (FR5, UC7).  Owner: Rolando Labrador.
 * Called whenever an issue's status changes, to notify the reporter.
 */
require_once __DIR__ . '/../includes/db.php';

function notify_status_change(int $issueId, string $newStatus): void
{
    // TODO(Rolando): look up the issue's reporter_contact, build a short message,
    // and INSERT a row into `notifications` (recipient, channel, message, status='queued').
    // For Version 1, a queued row is enough to demonstrate FR5; real sending comes later.
}
