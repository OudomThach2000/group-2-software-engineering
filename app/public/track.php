<?php
/**
 * UC2 Track Issue  (FR2).  Owner: Sethouday Prum.
 * Resident enters a tracking reference and sees the current status and history.
 */
require_once __DIR__ . '/../includes/db.php';

$issue = null; $history = []; $notFound = false;
if (isset($_GET['ref']) && $_GET['ref'] !== '') {
    // TODO(Sethouday): look up the issue by tracking_ref; load its status_history
    //   ordered by changed_at; set $issue / $history, or $notFound = true.
}

require_once __DIR__ . '/../includes/header.php';
?>
<h1>Track your report</h1>
<form method="get" class="form">
  <label>Tracking reference <input type="text" name="ref" placeholder="CIR-2026-0001" required></label>
  <button type="submit">Check status</button>
</form>
<?php if ($notFound): ?><p class="error">No report found with that reference.</p><?php endif; ?>
<?php // TODO(Sethouday): if $issue, show its status and a timeline built from $history ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
