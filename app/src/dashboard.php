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

/**
 * Number of issues in each category, busiest first,
 * e.g. [['category' => 'Streetlight', 'issue_count' => 1], ...].
 * Categories with no issues yet are included with 0 (hence the LEFT JOIN).
 */
function count_issues_by_category(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        'SELECT c.name_en AS category, COUNT(i.id) AS issue_count
           FROM categories c
           LEFT JOIN issues i ON i.category_id = c.id
          GROUP BY c.id, c.name_en, c.sort_order
          ORDER BY issue_count DESC, c.sort_order'
    );
    $stmt->execute();

    return array_map(
        fn(array $row) => ['category' => $row['category'], 'issue_count' => (int) $row['issue_count']],
        $stmt->fetchAll()
    );
}

/**
 * Average time from report (created_at) to resolution (resolved_at), in hours,
 * e.g. ['average_hours' => 39.25, 'resolved_count' => 2].
 * average_hours is null when no issue has been resolved yet.
 *
 * Closed issues count too: they were resolved before being closed, and leaving
 * them out would make the average jump each time an issue is closed.
 */
function average_resolution_time(PDO $pdo): array
{
    // Minutes / 60, not TIMESTAMPDIFF(HOUR, ...), which would drop part-hours
    // (a 90-minute fix would count as 1 hour).
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS resolved_count,
                AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at)) / 60 AS average_hours
           FROM issues
          WHERE status IN (:resolved, :closed)
            AND resolved_at IS NOT NULL'
    );
    $stmt->execute([':resolved' => 'Resolved', ':closed' => 'Closed']);
    $row = $stmt->fetch();

    return [
        'average_hours'  => $row['average_hours'] === null ? null : (float) $row['average_hours'],
        'resolved_count' => (int) $row['resolved_count'],
    ];
}
