<?php
/**
 * Issue lifecycle  (UC1, UC3, UC4; FR1-FR4, FR8, FR9, FR12, FR16).  Owner: Oudom Thach.
 *
 * The rules for submitting, assigning, updating and closing an issue. They live here,
 * apart from the pages, so tests/issues_test.php can check them without a login or HTML.
 *
 * Every status change is a conditional UPDATE that only succeeds while the issue is still
 * in the status we expect, so two users acting on the same issue cannot overwrite each
 * other (Section 7.2). The change and its status_history row are saved in one transaction.
 * The functions return false when someone else got there first, and throw
 * InvalidArgumentException when they are given values the page should have rejected.
 */

/** Priority levels a supervisor sets during triage (FR8), same as the ENUM on issues.priority. */
const PRIORITIES = ['Low', 'Medium', 'High', 'Urgent'];

/** The only moves a field worker can make: Assigned -> In-progress -> Resolved (FR4). */
const WORKER_NEXT_STATUS = ['Assigned' => 'In-progress', 'In-progress' => 'Resolved'];

const MAX_DESCRIPTION_LENGTH = 2000;
const MAX_LOCATION_LENGTH    = 255;
const MAX_CONTACT_LENGTH     = 190;
const MAX_NOTE_LENGTH        = 255;

/**
 * Runs $work in a transaction and returns its result. If the caller already has a
 * transaction open (the tests do), the work joins it and the caller decides the outcome.
 */
function run_in_transaction(PDO $pdo, callable $work)
{
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $result = $work();
        if ($ownTransaction) {
            $pdo->commit();
        }
        return $result;
    } catch (Throwable $ex) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/** Adds one entry to the audit trail (FR9). */
function add_status_history(PDO $pdo, int $issueId, ?string $oldStatus, string $newStatus, ?int $userId, ?string $note): void
{
    $pdo->prepare(
        'INSERT INTO status_history (issue_id, old_status, new_status, changed_by_user_id, note)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$issueId, $oldStatus, $newStatus, $userId, $note]);
}

/**
 * Checks the text fields of the submit form (FR1, FR15, FR16). The photo is checked by
 * the page, because it is a file upload.
 * Returns one message per field in error, e.g. ['description' => 'Please describe the problem.'];
 * an empty array means the input is valid.
 */
function validate_submission(array $input, array $validCategoryIds): array
{
    $errors      = [];
    $description = trim((string)($input['description'] ?? ''));
    $location    = trim((string)($input['location_text'] ?? ''));
    $contact     = trim((string)($input['reporter_contact'] ?? ''));

    if (!in_array((int)($input['category_id'] ?? 0), $validCategoryIds, true)) {
        $errors['category_id'] = 'Please choose a category.';
    }
    if ($description === '') {
        $errors['description'] = 'Please describe the problem.';
    } elseif (mb_strlen($description) > MAX_DESCRIPTION_LENGTH) {
        $errors['description'] = 'The description is too long (maximum 2000 characters).';
    }
    if ($location === '') {
        $errors['location_text'] = 'Please tell us where the problem is.';
    } elseif (mb_strlen($location) > MAX_LOCATION_LENGTH) {
        $errors['location_text'] = 'The location is too long (maximum 255 characters).';
    }
    if (mb_strlen($contact) > MAX_CONTACT_LENGTH) {
        $errors['reporter_contact'] = 'The contact detail is too long.';
    } elseif ($contact !== '' && !filter_var($contact, FILTER_VALIDATE_EMAIL)
        && !preg_match('/^\+?[0-9][0-9 \-()]{5,19}$/', $contact)) {
        $errors['reporter_contact'] = 'Enter a valid email address or phone number, or leave it empty.';
    }
    return $errors;
}

/** Next free reference for this year, e.g. CIR-2026-0005. */
function next_tracking_ref(PDO $pdo): string
{
    $prefix = 'CIR-' . date('Y') . '-';
    $stmt = $pdo->prepare(
        'SELECT COALESCE(MAX(CAST(SUBSTRING(tracking_ref, ?) AS UNSIGNED)), 0) + 1
           FROM issues WHERE tracking_ref LIKE ?'
    );
    $stmt->bindValue(1, strlen($prefix) + 1, PDO::PARAM_INT);
    $stmt->bindValue(2, $prefix . '%');
    $stmt->execute();
    return $prefix . str_pad((string)$stmt->fetchColumn(), 4, '0', STR_PAD_LEFT);
}

/**
 * Saves a new issue with status New and its first history entry, and returns the
 * tracking reference (FR1, FR2, FR9). The issue is committed before the reference is
 * returned, so a reference the resident holds always exists (NFR4).
 * $input must already have passed validate_submission().
 */
function submit_issue(PDO $pdo, array $input, ?string $photoPath, ?int $userId): string
{
    $contact = trim((string)($input['reporter_contact'] ?? ''));

    for ($attempt = 1; ; $attempt++) {
        try {
            return run_in_transaction($pdo, function () use ($pdo, $input, $photoPath, $userId, $contact): string {
                $ref = next_tracking_ref($pdo);
                $pdo->prepare(
                    'INSERT INTO issues (tracking_ref, category_id, description, location_text, photo_path,
                                         status, reporter_user_id, reporter_contact)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $ref, (int)$input['category_id'], trim($input['description']), trim($input['location_text']),
                    $photoPath, 'New', $userId, $contact !== '' ? $contact : null,
                ]);
                add_status_history($pdo, (int)$pdo->lastInsertId(), null, 'New', $userId, 'Issue submitted');
                return $ref;
            });
        } catch (PDOException $ex) {
            // 1062 = duplicate tracking_ref (two people submitted at once): try the next number.
            if ((int)($ex->errorInfo[1] ?? 0) !== 1062 || $attempt >= 5) {
                throw $ex;
            }
        }
    }
}

/**
 * Triage (UC3): confirms the category, sets the priority and assigns a New issue to a
 * field worker. Returns false if the issue is no longer New (another supervisor got there first).
 */
function assign_issue(PDO $pdo, int $issueId, int $staffId, int $supervisorId, int $categoryId,
                      string $priority, ?string $note = null): bool
{
    if (!in_array($priority, PRIORITIES, true)) {
        throw new InvalidArgumentException('Please choose a priority.');
    }

    return run_in_transaction($pdo, function () use ($pdo, $issueId, $staffId, $supervisorId, $categoryId, $priority, $note): bool {
        $stmt = $pdo->prepare(
            "UPDATE issues SET status = 'Assigned', category_id = ?, priority = ?
              WHERE id = ? AND status = 'New'"
        );
        $stmt->execute([$categoryId, $priority, $issueId]);
        if ($stmt->rowCount() !== 1) {
            return false;
        }
        $pdo->prepare(
            'INSERT INTO assignments (issue_id, assigned_to_user_id, assigned_by_user_id, note) VALUES (?, ?, ?, ?)'
        )->execute([$issueId, $staffId, $supervisorId, $note]);
        add_status_history($pdo, $issueId, 'New', 'Assigned', $supervisorId, 'Assigned by supervisor');
        return true;
    });
}

/**
 * Field work (UC4): moves an issue one step along Assigned -> In-progress -> Resolved.
 * Only the worker holding the latest assignment can do it, and a resolution needs a note.
 * Returns false if the issue is not assigned to this worker or is not in the expected status.
 */
function update_issue_status(PDO $pdo, int $issueId, int $workerId, string $newStatus, ?string $note = null): bool
{
    $oldStatus = array_search($newStatus, WORKER_NEXT_STATUS, true);
    if ($oldStatus === false) {
        throw new InvalidArgumentException('That status change is not allowed.');
    }
    $note = trim((string)$note);
    if ($newStatus === 'Resolved' && $note === '') {
        throw new InvalidArgumentException('Please describe what was done before marking the issue as resolved.');
    }

    return run_in_transaction($pdo, function () use ($pdo, $issueId, $workerId, $oldStatus, $newStatus, $note): bool {
        $set = $newStatus === 'Resolved' ? 'status = ?, resolved_at = NOW()' : 'status = ?';
        $stmt = $pdo->prepare(
            "UPDATE issues SET $set
              WHERE id = ? AND status = ?
                AND (SELECT a.assigned_to_user_id FROM assignments a
                      WHERE a.issue_id = issues.id ORDER BY a.id DESC LIMIT 1) = ?"
        );
        $stmt->execute([$newStatus, $issueId, $oldStatus, $workerId]);
        if ($stmt->rowCount() !== 1) {
            return false;
        }
        add_status_history($pdo, $issueId, $oldStatus, $newStatus, $workerId, $note !== '' ? $note : null);
        return true;
    });
}

/**
 * Closing (FR12): the supervisor confirms the work and closes a Resolved issue.
 * Returns false if the issue is no longer Resolved.
 */
function close_issue(PDO $pdo, int $issueId, int $supervisorId, ?string $note = null): bool
{
    $note = trim((string)$note);

    return run_in_transaction($pdo, function () use ($pdo, $issueId, $supervisorId, $note): bool {
        $stmt = $pdo->prepare("UPDATE issues SET status = 'Closed' WHERE id = ? AND status = 'Resolved'");
        $stmt->execute([$issueId]);
        if ($stmt->rowCount() !== 1) {
            return false;
        }
        add_status_history($pdo, $issueId, 'Resolved', 'Closed', $supervisorId, $note !== '' ? $note : 'Closed by supervisor');
        return true;
    });
}
