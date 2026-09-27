<?php
/**
 * UC5 View Dashboard — unit test for the dashboard queries  (FR6).  Owner: Pichponleur Pen.
 *
 * Checks count_issues_by_status(), count_issues_by_category() and
 * average_resolution_time() from app/src/dashboard.php against the sample data
 * in db/seed.sql, then checks the "no data yet" case on an empty issues table.
 *
 * Run from the project root, right after importing db/schema.sql and db/seed.sql:
 *     php tests/dashboard_test.php
 * (XAMPP on macOS: /Applications/XAMPP/bin/php tests/dashboard_test.php)
 * Exit code 0 = every check passed, 1 = at least one failed.
 */
require_once __DIR__ . '/../app/includes/db.php';
require_once __DIR__ . '/../app/src/dashboard.php';

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

$pdo = db();

// Category names from db/seed.sql, in sort_order.
$seedCategories = ['Streetlight', 'Road / Pothole', 'Water / Drainage', 'Waste', 'Other'];

// 1. Issues by status: the seed has one issue in every status except In-progress.
check(
    'counts issues by status, listing In-progress with 0',
    ['New' => 1, 'Assigned' => 1, 'In-progress' => 0, 'Resolved' => 1, 'Closed' => 1],
    count_issues_by_status($pdo)
);

// 2. Issues by category: one issue in each of the first four, none in Other.
check(
    'counts issues by category, listing Other with 0',
    array_combine($seedCategories, [1, 1, 1, 1, 0]),
    array_column(count_issues_by_category($pdo), 'issue_count', 'category')
);

// 3. Average resolution time: CIR-2026-0003 took 54.5 h, CIR-2026-0004 took 24 h.
$resolution   = average_resolution_time($pdo);
$averageHours = $resolution['average_hours'];
check(
    'averages resolution time over Resolved + Closed issues: (54.5 + 24) / 2 = 39.25 h',
    39.25,
    $averageHours === null ? null : round($averageHours, 2)
);
check('counts 2 resolved issues in that average', 2, $resolution['resolved_count']);

// 4. No data yet: empty the issues table inside a transaction and roll it back
//    afterwards, so the seed data is left exactly as it was.
$pdo->beginTransaction();
try {
    $pdo->exec('DELETE FROM issues');
    check('no issues -> every status count is 0', array_fill_keys(ISSUE_STATUSES, 0), count_issues_by_status($pdo));
    check(
        'no issues -> every category still listed, with 0',
        array_fill_keys($seedCategories, 0),
        array_column(count_issues_by_category($pdo), 'issue_count', 'category')
    );
    check(
        'no issues -> no average yet (null)',
        ['average_hours' => null, 'resolved_count' => 0],
        average_resolution_time($pdo)
    );
} finally {
    $pdo->rollBack();
}

if ($failures > 0) {
    echo "\n$failures check(s) failed.\n"
       . "If you have added issues through the app, re-import db/schema.sql then db/seed.sql and run again.\n";
    exit(1);
}
echo "\nAll checks passed.\n";
