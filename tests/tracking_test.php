<?php
/**
 * UC2 Track Issue: unit test for the tracking lookup in app/src/tracking.php  (FR2, NFR3).
 *
 * Checks which tracking references are accepted (equivalence classes and boundaries), that
 * a resident sees the current status and every step of the history in date order, that an
 * older report with no history rows still shows its first step, that nothing private is
 * returned (no contact detail, no staff name, no internal id), and that a logged in user
 * sees only their own reports, newest first.
 *
 * Run from the project root, after importing db/schema.sql and db/seed.sql:
 *     php tests/tracking_test.php
 * Everything that writes runs inside one transaction that is rolled back at the end, so the
 * database is left as it was. The test makes its own reports, so it does not depend on the
 * sample issues. Exit code 0 = every check passed, 1 = at least one failed.
 *
 * Added by Pichponleur Pen on 23 October 2026 to complete the v5 test plan.
 */
require_once __DIR__ . '/../app/includes/db.php';
require_once __DIR__ . '/../app/src/issues.php';
require_once __DIR__ . '/../app/src/tracking.php';

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
    $email = strtolower(str_replace(' ', '.', $name)) . '@tracking-test.example';
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')
        ->execute([$name, $email, password_hash('not-used', PASSWORD_DEFAULT), $role]);
    return (int)$pdo->lastInsertId();
}

/** Inserts an issue directly, with a fixed date and no history rows, and returns its id. */
function insert_issue(PDO $pdo, string $ref, int $categoryId, string $status, string $createdAt): int
{
    $pdo->prepare(
        "INSERT INTO issues (tracking_ref, category_id, description, location_text, status, created_at)
         VALUES (?, ?, 'Older report', 'Market', ?, ?)"
    )->execute([$ref, $categoryId, $status, $createdAt]);
    return (int)$pdo->lastInsertId();
}

// 1. Which references are accepted: one value from each class, and both sides of the
//    4 digit boundary (FR2). The page reads the reference in upper case without spaces.
$references = [
    'CIR-2026-0001'               => true,    // the usual form: 4 digit number
    'CIR-2026-12345'              => true,    // 5 digits, so numbering can go past 9999
    'CIR-2026-001'                => false,   // 3 digits, one below the boundary
    'CIR-26-0001'                 => false,   // 2 digit year
    'XCIR-2026-0001'              => false,   // extra text in front
    'CIR-2026-0001X'              => false,   // extra text at the end
    "CIR-2026-0001' OR '1'='1"    => false,   // SQL injection attempt
    '<script>alert(1)</script>'   => false,   // script injection attempt
    ''                            => false,   // nothing typed
];
foreach ($references as $ref => $accepted) {
    check(($accepted ? 'accepts ' : 'refuses ') . ($ref === '' ? 'an empty reference' : $ref),
        $accepted, is_valid_tracking_ref($ref));
}
check('a reference typed in lower case with spaces is read as CIR-2026-0003',
    'CIR-2026-0003', normalise_tracking_ref(' cir-2026-0003 '));

$pdo = db();
$category = $pdo->query('SELECT id, name_en, name_km FROM categories ORDER BY sort_order LIMIT 1')->fetch();
$categoryId = (int)$category['id'];

$pdo->beginTransaction();
try {
    $supervisorId = make_user($pdo, 'Track Supervisor', 'supervisor');
    $workerId     = make_user($pdo, 'Track Worker', 'staff');
    $residentId   = make_user($pdo, 'Track Resident', 'resident');
    $otherId      = make_user($pdo, 'Other Resident', 'resident');

    // 2. A reference that does not exist.
    check('an unknown reference finds nothing', null, find_issue_by_reference($pdo, 'CIR-1999-0001'));

    // 3. A report taken through its lifecycle with the real functions (UC1, UC3, UC4).
    $input = [
        'category_id'      => (string)$categoryId,
        'description'      => 'Drain blocked by rubbish.',
        'location_text'    => 'Street 5',
        'reporter_contact' => 'private.contact@example.com',
    ];
    $ref     = submit_issue($pdo, $input, null, $residentId);
    $issueId = (int)scalar($pdo, 'SELECT id FROM issues WHERE tracking_ref = ?', [$ref]);
    assign_issue($pdo, $issueId, $workerId, $supervisorId, $categoryId, 'High', 'Ask for Mr Dara at the gate');
    update_issue_status($pdo, $issueId, $workerId, 'In-progress');
    update_issue_status($pdo, $issueId, $workerId, 'Resolved', 'Cleared the drain');

    $found = find_issue_by_reference($pdo, $ref);
    check('finds the report with its current status and category in both languages',
        [$ref, 'Resolved', $category['name_en'], $category['name_km']],
        [$found['issue']['tracking_ref'] ?? null, $found['issue']['status'] ?? null,
         $found['issue']['name_en'] ?? null, $found['issue']['name_km'] ?? null]);
    check('shows every step of the history in order',
        ['New', 'Assigned', 'In-progress', 'Resolved'], array_column($found['history'] ?? [], 'new_status'));
    check('shows the note the worker left when resolving it', 'Cleared the drain', $found['history'][3]['note'] ?? null);

    // 4. Privacy (NFR3): only what a resident may see.
    check('the report shows only the reference, status, date and category',
        ['tracking_ref', 'status', 'created_at', 'name_en', 'name_km'], array_keys($found['issue'] ?? []));
    check('each history step shows only the status, the note and the date',
        ['new_status', 'note', 'changed_at'], array_keys($found['history'][0] ?? []));
    $shown = json_encode($found, JSON_UNESCAPED_UNICODE);
    check('the contact detail, the staff names and the assignment note are never shown',
        [false, false, false, false],
        [str_contains($shown, 'private.contact@example.com'), str_contains($shown, 'Track Worker'),
         str_contains($shown, 'Track Supervisor'), str_contains($shown, 'Mr Dara')]);

    // 5. History is in date order even when the rows were saved in another order, and an
    //    older report without history rows still shows its first step. (Year 1999 keeps
    //    these test references apart from real ones.)
    $olderId = insert_issue($pdo, 'CIR-1999-0002', $categoryId, 'Assigned', '1999-01-05 08:00:00');
    $addHistory = $pdo->prepare('INSERT INTO status_history (issue_id, old_status, new_status, note, changed_at) VALUES (?, ?, ?, ?, ?)');
    $addHistory->execute([$olderId, 'New', 'Assigned', 'Second step, saved first', '1999-01-06 09:00:00']);
    $addHistory->execute([$olderId, null, 'New', 'First step, saved second', '1999-01-05 08:00:00']);
    check('history is in date order, not in the order it was saved',
        ['New', 'Assigned'], array_column(find_issue_by_reference($pdo, 'CIR-1999-0002')['history'] ?? [], 'new_status'));

    insert_issue($pdo, 'CIR-1999-0003', $categoryId, 'New', '1999-02-01 10:00:00');
    check('a report with no history rows still shows when it was reported',
        [['new_status' => 'New', 'note' => null, 'changed_at' => '1999-02-01 10:00:00']],
        find_issue_by_reference($pdo, 'CIR-1999-0003')['history'] ?? null);

    // 6. A logged in user's own reports, newest first.
    $pdo->prepare('UPDATE issues SET created_at = ? WHERE id = ?')->execute(['2026-01-01 08:00:00', $issueId]);
    $newerRef = submit_issue($pdo, $input, null, $residentId);
    submit_issue($pdo, $input, null, $otherId);   // someone else's report
    submit_issue($pdo, $input, null, null);       // an anonymous report
    $mine = find_issues_by_reporter($pdo, $residentId);
    check('a user sees only their own reports, newest first', [$newerRef, $ref], array_column($mine, 'tracking_ref'));
    check('each line shows only the reference, status, date and category',
        ['tracking_ref', 'status', 'created_at', 'name_en'], array_keys($mine[0] ?? []));
    check('a user who has reported nothing sees an empty list', [],
        find_issues_by_reporter($pdo, make_user($pdo, 'New Resident', 'resident')));
} finally {
    $pdo->rollBack();
}

if ($failures > 0) {
    echo "\n$failures check(s) failed.\n";
    exit(1);
}
echo "\nAll checks passed.\n";
