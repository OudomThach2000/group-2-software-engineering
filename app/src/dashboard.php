<?php
/**
 * UC5 View Dashboard — statistics queries  (FR6).  Owner: Pichponleur Pen.
 *
 * Read-only queries over the `issues` table that feed the supervisor dashboard
 * (app/public/supervisor/dashboard.php). They live here, apart from the page, so
 * tests/dashboard_test.php can check them without a login or any HTML.
 * Each function takes the PDO connection, e.g. count_issues_by_status(db()).
 */

/** Every issue status, in workflow order (same as the ENUM in db/schema.sql). */
const ISSUE_STATUSES = ['New', 'Assigned', 'In-progress', 'Resolved', 'Closed'];

/** Statuses that still need work: together they make up the "open issues" figure. */
const OPEN_STATUSES = ['New', 'Assigned', 'In-progress'];

/**
 * Number of issues in each status, in workflow order,
 * e.g. ['New' => 1, 'Assigned' => 1, 'In-progress' => 0, 'Resolved' => 1, 'Closed' => 1].
 * A status that no issue has yet is still listed, with 0.
 */
function count_issues_by_status(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        'SELECT status, COUNT(*) AS issue_count
           FROM issues
          GROUP BY status'
    );
    $stmt->execute();

    $counts = array_fill_keys(ISSUE_STATUSES, 0);
    foreach ($stmt->fetchAll() as $row) {
        $counts[$row['status']] = (int) $row['issue_count'];
    }
    return $counts;
}
