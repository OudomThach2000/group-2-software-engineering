<?php
/**
 * UC5 View Dashboard  (FR6).  Owner: Pichponleur Pen.
 * Supervisor sees open issues, issues by category, and average resolution time.
 * The queries live in app/src/dashboard.php (unit-tested by tests/dashboard_test.php).
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../src/dashboard.php';
require_role('supervisor');
// TODO(Pichponleur): average resolution time.

$error          = null;
$statusCounts   = array_fill_keys(ISSUE_STATUSES, 0);
$categoryCounts = [];
try {
    $pdo            = db();
    $statusCounts   = count_issues_by_status($pdo);
    $categoryCounts = count_issues_by_category($pdo);
} catch (Throwable $e) {
    error_log('UC5 dashboard query failed: ' . $e->getMessage());
    $error = 'Could not load the dashboard figures — is the database set up? See README.';
}

$totalIssues      = array_sum($statusCounts);
$openIssues       = array_sum(array_intersect_key($statusCounts, array_flip(OPEN_STATUSES)));
$maxStatusCount   = max($statusCounts);                                      // longest bar, status table
$maxCategoryCount = max(array_column($categoryCounts, 'issue_count') ?: [0]); // longest bar, category table

require_once __DIR__ . '/../../includes/header.php';
?>
<link rel="stylesheet" href="/assets/css/dashboard.css">
<h1>Dashboard</h1>

<?php if ($error): ?>
  <p class="error"><?= htmlspecialchars($error) ?></p>
<?php else: ?>
  <section class="stat-tiles" aria-label="Summary">
    <div class="stat-tile stat-tile-lead">
      <span class="stat-label">Open issues</span>
      <span class="stat-value"><?= $openIssues ?></span>
      <span class="stat-note">New, Assigned or In-progress</span>
    </div>
    <div class="stat-tile">
      <span class="stat-label">Total reported</span>
      <span class="stat-value"><?= $totalIssues ?></span>
      <span class="stat-note">All statuses</span>
    </div>
  </section>

  <?php if ($totalIssues === 0): ?>
    <p class="empty-state">No data yet — no issues have been reported. Figures will appear here as residents submit reports.</p>
  <?php else: ?>
    <div class="dashboard-tables">
      <section>
        <h2>Issues by status</h2>
        <table class="count-table">
          <thead>
            <tr><th scope="col">Status</th><th scope="col" class="num">Issues</th><td aria-hidden="true"></td></tr>
          </thead>
          <tbody>
            <?php foreach ($statusCounts as $status => $count): ?>
              <tr>
                <th scope="row"><?= htmlspecialchars($status) ?></th>
                <td class="num"><?= $count ?></td>
                <td class="bar-cell" aria-hidden="true">
                  <?php if ($count > 0): ?><span class="bar" style="width: <?= round($count / $maxStatusCount * 100) ?>%"></span><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>

      <section>
        <h2>Issues by category</h2>
        <table class="count-table">
          <thead>
            <tr><th scope="col">Category</th><th scope="col" class="num">Issues</th><td aria-hidden="true"></td></tr>
          </thead>
          <tbody>
            <?php foreach ($categoryCounts as $row): ?>
              <tr>
                <th scope="row"><?= htmlspecialchars($row['category']) ?></th>
                <td class="num"><?= $row['issue_count'] ?></td>
                <td class="bar-cell" aria-hidden="true">
                  <?php if ($row['issue_count'] > 0): ?><span class="bar" style="width: <?= round($row['issue_count'] / $maxCategoryCount * 100) ?>%"></span><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
