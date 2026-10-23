<?php
/**
 * UC1, UC3, UC4 — unit test for the issue lifecycle  (FR1-FR4, FR8, FR9, FR12, FR16).  Owner: Oudom Thach.
 *
 * Checks validate_submission(), submit_issue(), assign_issue(), update_issue_status()
 * and close_issue() from app/src/issues.php by taking one issue through its whole
 * lifecycle, New -> Assigned -> In-progress -> Resolved -> Closed, and trying the
 * moves that must be refused along the way.
 *
 * Run from the project root, after importing db/schema.sql and db/seed.sql
 * (the priority column is part of the schema, so no migration is needed):
 *     php tests/issues_test.php
 * Sections 2 to 7 run inside one transaction that is rolled back at the end. Section 8 tests
 * the rollback itself, so it runs outside that transaction and deletes the issue it made.
 * Either way the database is left as it was. Exit code 0 = every check passed, 1 = at least one failed.
 *
 * Section 7, section 8 and the exact boundary checks in section 1 were added by
 * Pichponleur Pen on 23 October 2026 to complete the v5 test plan.
 */
require_once __DIR__ . '/../app/includes/db.php';
require_once __DIR__ . '/../app/src/issues.php';

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

/** First column of the first row, or false when there is no row. */
function scalar(PDO $pdo, string $sql, array $params = [])
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

/** Inserts a user with the given role and returns its id. */
function make_user(PDO $pdo, string $name, string $role): int
{
    $email = strtolower(str_replace(' ', '.', $name)) . '@issues-test.example';
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')
        ->execute([$name, $email, password_hash('not-used', PASSWORD_DEFAULT), $role]);
    return (int)$pdo->lastInsertId();
}

/** A valid email address of exactly $length characters (each domain label is at most 63 characters). */
function email_of_length(int $length): string
{
    $domain = str_repeat('a', 63) . '.' . str_repeat('b', 63) . '.';
    return 'r@' . $domain . str_repeat('c', $length - 2 - strlen($domain) - 4) . '.com';
}

$pdo = db();
$categoryIds = array_map('intval', $pdo->query('SELECT id FROM categories ORDER BY sort_order')->fetchAll(PDO::FETCH_COLUMN));
$validInput = [
    'category_id'      => (string)$categoryIds[0],
    'description'      => 'Streetlight flickering outside number 14.',
    'location_text'    => 'Street 21, near the pagoda',
    'reporter_contact' => 'resident@example.com',
];

// 1. Validation of the submit form (FR1, FR15, FR16).
check('accepts a complete report', [], validate_submission($validInput, $categoryIds));
check(
    'an empty report needs a category, a description and a location',
    ['category_id', 'description', 'location_text'],
    array_keys(validate_submission(
        ['category_id' => '', 'description' => '', 'location_text' => '', 'reporter_contact' => ''],
        $categoryIds
    ))
);
check('rejects a category that does not exist', ['category_id'],
    array_keys(validate_submission(['category_id' => '999999'] + $validInput, $categoryIds)));
check('rejects a description over 2000 characters', ['description'],
    array_keys(validate_submission(['description' => str_repeat('a', 2001)] + $validInput, $categoryIds)));
check('rejects a location over 255 characters', ['location_text'],
    array_keys(validate_submission(['location_text' => str_repeat('a', 256)] + $validInput, $categoryIds)));
check('accepts a description of exactly 2000 characters', [],
    validate_submission(['description' => str_repeat('a', 2000)] + $validInput, $categoryIds));
check('accepts a location of exactly 255 characters', [],
    validate_submission(['location_text' => str_repeat('a', 255)] + $validInput, $categoryIds));
check('accepts a contact of exactly 190 characters', [],
    validate_submission(['reporter_contact' => email_of_length(190)] + $validInput, $categoryIds));
check('rejects a contact of 191 characters as too long', 'The contact detail is too long.',
    validate_submission(['reporter_contact' => email_of_length(191)] + $validInput, $categoryIds)['reporter_contact'] ?? null);
check('the contact is optional (FR15)', [],
    validate_submission(['reporter_contact' => ''] + $validInput, $categoryIds));
check('accepts a phone number as the contact', [],
    validate_submission(['reporter_contact' => '+855 12 345 678'] + $validInput, $categoryIds));
check('rejects a contact that is neither an email nor a phone number', ['reporter_contact'],
    array_keys(validate_submission(['reporter_contact' => 'call me'] + $validInput, $categoryIds)));

$pdo->beginTransaction();
try {
    $supervisorId  = make_user($pdo, 'Test Supervisor', 'supervisor');
    $workerId      = make_user($pdo, 'Test Worker', 'staff');
    $otherWorkerId = make_user($pdo, 'Other Worker', 'staff');

    // 2. Submit (UC1).
    $ref = submit_issue($pdo, $validInput, null, null);
    check('returns a reference like CIR-' . date('Y') . '-0005', 1, preg_match('/^CIR-\d{4}-\d{4}$/', $ref));
    $issueId = (int)scalar($pdo, 'SELECT id FROM issues WHERE tracking_ref = ?', [$ref]);
    check('stores the new issue with status New', 'New', scalar($pdo, 'SELECT status FROM issues WHERE id = ?', [$issueId]));
    check('keeps the contact detail for notifications', 'resident@example.com',
        scalar($pdo, 'SELECT reporter_contact FROM issues WHERE id = ?', [$issueId]));

    $secondRef = submit_issue($pdo, ['reporter_contact' => ''] + $validInput, null, null);
    $secondId  = (int)scalar($pdo, 'SELECT id FROM issues WHERE tracking_ref = ?', [$secondRef]);
    check('gives the next report the next number', (int)substr($ref, -4) + 1, (int)substr($secondRef, -4));
    check('stores an empty contact as NULL', null,
        scalar($pdo, 'SELECT reporter_contact FROM issues WHERE id = ?', [$secondId]));

    // 3. Triage and assign (UC3, FR8).
    check_rejected('refuses a priority that is not Low, Medium, High or Urgent',
        fn() => assign_issue($pdo, $issueId, $workerId, $supervisorId, $categoryIds[0], 'Whenever'));
    check('assigns a New issue', true,
        assign_issue($pdo, $issueId, $workerId, $supervisorId, $categoryIds[1], 'High', 'Bring a ladder'));
    check('the issue is now Assigned', 'Assigned', scalar($pdo, 'SELECT status FROM issues WHERE id = ?', [$issueId]));
    check('the priority is saved', 'High', scalar($pdo, 'SELECT priority FROM issues WHERE id = ?', [$issueId]));
    check('the corrected category is saved', $categoryIds[1],
        (int)scalar($pdo, 'SELECT category_id FROM issues WHERE id = ?', [$issueId]));
    check('refuses to assign it a second time', false,
        assign_issue($pdo, $issueId, $otherWorkerId, $supervisorId, $categoryIds[0], 'Low'));
    check('keeps only the first assignment', 1,
        (int)scalar($pdo, 'SELECT COUNT(*) FROM assignments WHERE issue_id = ?', [$issueId]));

    // 4. Field work (UC4, FR13).
    check_rejected('refuses a status a field worker may not set',
        fn() => update_issue_status($pdo, $issueId, $workerId, 'Closed'));
    check('another worker cannot start this issue', false,
        update_issue_status($pdo, $issueId, $otherWorkerId, 'In-progress'));
    check('cannot jump straight from Assigned to Resolved', false,
        update_issue_status($pdo, $issueId, $workerId, 'Resolved', 'Done'));
    check('the assigned worker starts work', true,
        update_issue_status($pdo, $issueId, $workerId, 'In-progress'));
    check_rejected('resolving needs a note',
        fn() => update_issue_status($pdo, $issueId, $workerId, 'Resolved', '   '));
    check('the assigned worker resolves it with a note', true,
        update_issue_status($pdo, $issueId, $workerId, 'Resolved', 'Replaced the bulb'));
    check('resolved_at is set for the dashboard average', true,
        scalar($pdo, 'SELECT resolved_at FROM issues WHERE id = ?', [$issueId]) !== null);

    // 5. Close (FR12).
    check('a New issue cannot be closed', false, close_issue($pdo, $secondId, $supervisorId));
    check('the supervisor closes the resolved issue', true, close_issue($pdo, $issueId, $supervisorId));
    check('the issue is now Closed', 'Closed', scalar($pdo, 'SELECT status FROM issues WHERE id = ?', [$issueId]));
    check('closing it again is refused', false, close_issue($pdo, $issueId, $supervisorId));

    // 6. Audit trail (FR9): one entry per change, in order, with who made it.
    $stmt = $pdo->prepare('SELECT old_status, new_status, changed_by_user_id FROM status_history WHERE issue_id = ? ORDER BY id');
    $stmt->execute([$issueId]);
    $history = $stmt->fetchAll();
    check(
        'records every status change in order',
        [[null, 'New'], ['New', 'Assigned'], ['Assigned', 'In-progress'], ['In-progress', 'Resolved'], ['Resolved', 'Closed']],
        array_map(fn(array $row) => [$row['old_status'], $row['new_status']], $history)
    );
    check(
        'records who made each change (anonymous submission has none)',
        [null, $supervisorId, $workerId, $workerId, $supervisorId],
        array_map(fn(array $row) => $row['changed_by_user_id'] === null ? null : (int)$row['changed_by_user_id'], $history)
    );

    // 7. Every invalid move (state based testing): take a new issue through the lifecycle and,
    //    in each state, try every action that the state must refuse. The issue must stay put.
    $actions = [
        'assign'  => fn(int $id) => assign_issue($pdo, $id, $workerId, $supervisorId, $categoryIds[0], 'Low'),
        'start'   => fn(int $id) => update_issue_status($pdo, $id, $workerId, 'In-progress'),
        'resolve' => fn(int $id) => update_issue_status($pdo, $id, $workerId, 'Resolved', 'Done'),
        'close'   => fn(int $id) => close_issue($pdo, $id, $supervisorId),
    ];
    $validMove = ['New' => 'assign', 'Assigned' => 'start', 'In-progress' => 'resolve', 'Resolved' => 'close', 'Closed' => null];

    $loopRef = submit_issue($pdo, $validInput, null, null);
    $loopId  = (int)scalar($pdo, 'SELECT id FROM issues WHERE tracking_ref = ?', [$loopRef]);
    $invalidMoves = 0;
    foreach ($validMove as $state => $allowed) {
        foreach ($actions as $name => $action) {
            if ($name === $allowed) {
                continue;
            }
            $invalidMoves++;
            check("refuses $name on a $state issue, which stays $state", [false, $state],
                [$action($loopId), scalar($pdo, 'SELECT status FROM issues WHERE id = ?', [$loopId])]);
        }
        if ($allowed !== null) {
            check("$allowed moves the issue on from $state", true, $actions[$allowed]($loopId));
        }
    }
    check('tried all 16 invalid moves', 16, $invalidMoves);
} finally {
    $pdo->rollBack();
}

// 8. Rollback (NFR4). This part runs outside the test transaction, so submit_issue() and
//    assign_issue() open and close their own transactions, as they do on the live pages.
//    An assignment that fails half way must leave no half saved change. The issue made here
//    is deleted at the end, so the sample data is left as it was.
$rollbackId = 0;
try {
    $rollbackRef = submit_issue($pdo, $validInput, null, null);
    $rollbackId  = (int)scalar($pdo, 'SELECT id FROM issues WHERE tracking_ref = ?', [$rollbackRef]);
    check('outside a transaction, submit_issue() commits the new issue', 'New',
        scalar($pdo, 'SELECT status FROM issues WHERE id = ?', [$rollbackId]));

    $seedSupervisorId = (int)scalar($pdo, "SELECT id FROM users WHERE role = 'supervisor' ORDER BY id LIMIT 1");
    $failed = false;
    try {
        // No user has id 999999, so saving the assignment breaks a foreign key
        // after the issue row has already been changed to Assigned.
        assign_issue($pdo, $rollbackId, 999999, $seedSupervisorId, $categoryIds[1], 'Urgent');
    } catch (PDOException $ex) {
        $failed = true;
    }
    check('an assignment that fails half way reports an error', true, $failed);
    check('...and is rolled back: the issue is still New, with no priority and its old category',
        ['New', null, $categoryIds[0]],
        [scalar($pdo, 'SELECT status FROM issues WHERE id = ?', [$rollbackId]),
         scalar($pdo, 'SELECT priority FROM issues WHERE id = ?', [$rollbackId]),
         (int)scalar($pdo, 'SELECT category_id FROM issues WHERE id = ?', [$rollbackId])]);
    check('...with no assignment and only the first history entry', [0, 1],
        [(int)scalar($pdo, 'SELECT COUNT(*) FROM assignments WHERE issue_id = ?', [$rollbackId]),
         (int)scalar($pdo, 'SELECT COUNT(*) FROM status_history WHERE issue_id = ?', [$rollbackId])]);
    check('...and no transaction is left open', false, $pdo->inTransaction());
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($rollbackId > 0) {
        $pdo->prepare('DELETE FROM issues WHERE id = ?')->execute([$rollbackId]);   // also removes its history
    }
}

if ($failures > 0) {
    echo "\n$failures check(s) failed.\n";
    exit(1);
}
echo "\nAll checks passed.\n";
