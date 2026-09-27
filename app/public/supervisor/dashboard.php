<?php
/**
 * UC5 View Dashboard  (FR6).  Owner: Pichponleur Pen.
 * Supervisor sees open issues, issues by category, and average resolution time.
 * The queries live in app/src/dashboard.php (unit-tested by tests/dashboard_test.php).
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../src/dashboard.php';
require_role('supervisor');

$error          = null;
$statusCounts   = array_fill_keys(ISSUE_STATUSES, 0);
$categoryCounts = [];
$resolution     = ['average_hours' => null, 'resolved_count' => 0];
try {
    $pdo            = db();
    $statusCounts   = count_issues_by_status($pdo);
    $categoryCounts = count_issues_by_category($pdo);
    $resolution     = average_resolution_time($pdo);
} catch (Throwable $e) {
    error_log('UC5 dashboard query failed: ' . $e->getMessage());
    $error = 'Could not load the dashboard figures — is the database set up? See README.';
}

$totalIssues      = array_sum($statusCounts);
$openIssues       = array_sum(array_intersect_key($statusCounts, array_flip(OPEN_STATUSES)));
$maxStatusCount   = max($statusCounts);                                      // longest bar, status table
$maxCategoryCount = max(array_column($categoryCounts, 'issue_count') ?: [0]); // longest bar, category table

// Note under the average, e.g. "About 1.6 days · 2 resolved issues"
$resolvedCount  = $resolution['resolved_count'];
$resolutionNote = $resolvedCount . ($resolvedCount === 1 ? ' resolved issue' : ' resolved issues');
if ($resolution['average_hours'] !== null && $resolution['average_hours'] >= 24) {
    $days           = round($resolution['average_hours'] / 24, 1);
    $resolutionNote = sprintf('About %s %s · %s', $days, $days === 1.0 ? 'day' : 'days', $resolutionNote);
}

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
    <div class="stat-tile">
      <span class="stat-label">Average resolution time</span>
      <?php if ($resolution['average_hours'] === null): ?>
        <span class="stat-value">—</span>
        <span class="stat-note">No resolved issues yet</span>
      <?php else: ?>
        <span class="stat-value"><?= number_format($resolution['average_hours'], 1) ?> h</span>
        <span class="stat-note"><?= htmlspecialchars($resolutionNote) ?></span>
      <?php endif; ?>
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
