<?php
/**
 * UC3 Triage & Assign  (FR3).  Owner: Oudom Thach.
 * Supervisor reviews new issues, confirms category/priority, assigns to field staff.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../src/notify.php';
require_role('supervisor');
// TODO(Oudom): list issues with status 'New'; on POST INSERT an assignment,
//   set issues.status = 'Assigned', INSERT status_history, call notify_status_change().
require_once __DIR__ . '/../../includes/header.php';
?>
<h1>Triage &amp; assign</h1>
<p class="muted">TODO: list of new issues, each with a "assign to staff" control.</p>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
