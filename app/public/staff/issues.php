<?php
/**
 * UC4 Update / Resolve Issue  (FR4).  Owner: Oudom Thach.
 * Field staff see their assigned issues and update status / add notes.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../src/notify.php';
require_role('staff', 'supervisor');
// TODO(Oudom): list issues assigned to the current user (join assignments);
//   on POST, update issues.status, INSERT status_history, set resolved_at when Resolved,
//   then call notify_status_change($issueId, $newStatus).
require_once __DIR__ . '/../../includes/header.php';
?>
<h1>My assigned issues</h1>
<p class="muted">TODO: table of assigned issues with a status control (New → In-progress → Resolved).</p>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
