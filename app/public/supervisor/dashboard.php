<?php
/**
 * UC5 View Dashboard  (FR6).  Owner: Pichponleur Pen.
 * Supervisor sees open issues, issues by category, and average resolution time.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role('supervisor');
// TODO(Pichponleur): queries —
//   open counts by status; counts grouped by category;
//   AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) for resolved issues.
require_once __DIR__ . '/../../includes/header.php';
?>
<h1>Dashboard</h1>
<p class="muted">TODO: open-issue counts, issues by category, average resolution time.</p>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
