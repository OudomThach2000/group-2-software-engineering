<?php
/**
 * UC2 Track Issue: the lookup behind app/public/track.php  (FR2, NFR3).
 *
 * Moved out of track.php so that it can be unit tested without a browser
 * (tests/tracking_test.php). It returns only what a resident may see: the reference, the
 * status, the dates, the category and the note of each change, never the reporter's
 * contact detail or the name of the staff member who made a change (NFR3).
 *
 * Moved here by Pichponleur Pen on 23 October 2026. The queries are the same ones the page
 * used before, and the page output was compared before and after the move.
 */

/** A tracking reference looks like CIR-2026-0001. */
const TRACKING_REF_PATTERN = '/^CIR-\d{4}-\d{4,}$/';

/** The reference as the page reads it: without surrounding spaces and in upper case. */
function normalise_tracking_ref(string $ref): string
{
    return strtoupper(trim($ref));
}

/** True when $ref has the shape of a tracking reference. */
function is_valid_tracking_ref(string $ref): bool
{
    return preg_match(TRACKING_REF_PATTERN, $ref) === 1;
}

/**
 * The issue with this reference and its history in date order, or null when there is none:
 * ['issue' => [tracking_ref, status, created_at, name_en, name_km],
 *  'history' => [[new_status, note, changed_at], ...]].
 */
function find_issue_by_reference(PDO $pdo, string $ref): ?array
{
    $stmt = $pdo->prepare(
        'SELECT i.id, i.tracking_ref, i.status, i.created_at, c.name_en, c.name_km
           FROM issues i JOIN categories c ON c.id = i.category_id
          WHERE i.tracking_ref = ?'
    );
    $stmt->execute([$ref]);
    $issue = $stmt->fetch();
    if (!$issue) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT new_status, note, changed_at FROM status_history
          WHERE issue_id = ? ORDER BY changed_at, id'
    );
    $stmt->execute([$issue['id']]);
    $history = $stmt->fetchAll();
    // Older reports may have no history rows; their first step is still known.
    if (!$history) {
        $history = [['new_status' => 'New', 'note' => null, 'changed_at' => $issue['created_at']]];
    }

    unset($issue['id']);   // the internal id is not shown to residents
    return ['issue' => $issue, 'history' => $history];
}

/** The reports a logged in user has made, newest first. */
function find_issues_by_reporter(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT i.tracking_ref, i.status, i.created_at, c.name_en
           FROM issues i JOIN categories c ON c.id = i.category_id
          WHERE i.reporter_user_id = ?
          ORDER BY i.created_at DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}
