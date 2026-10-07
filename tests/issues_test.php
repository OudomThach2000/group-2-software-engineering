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
 * Everything that writes runs inside one transaction that is rolled back at the end,
 * so the database is left as it was. Exit code 0 = every check passed, 1 = at least one failed.
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
} finally {
    $pdo->rollBack();
}

if ($failures > 0) {
    echo "\n$failures check(s) failed.\n";
    exit(1);
}
echo "\nAll checks passed.\n";
