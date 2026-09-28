<?php
/**
 * UC3 Triage & Assign  (FR3, FR8, FR9, FR12).  Owner: Oudom Thach.
 * Supervisor reviews new issues, confirms the category, sets the priority and assigns
 * each one to a field worker, then closes issues once field staff have resolved them.
 * The lifecycle rules live in app/src/issues.php (tested by tests/issues_test.php).
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../src/issues.php';
require_once __DIR__ . '/../../src/notify.php';
require_role('supervisor');

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Queues the resident's notification. The change is already saved, so a failure here must not undo it. */
function notify_quietly(int $issueId, string $status): void
{
    try {
        notify_status_change($issueId, $status);
    } catch (Throwable $ex) {
        error_log('triage.php notify: ' . $ex->getMessage());
    }
}

$pdo = db();
$me = current_user();
$error = null;

$categories = $pdo->query('SELECT id, name_en FROM categories ORDER BY sort_order')->fetchAll();
$staff = $pdo->query("SELECT id, name FROM users WHERE role = 'staff' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = (string)($_POST['action'] ?? '');
    $issueId = (int)($_POST['issue_id'] ?? 0);
    $note    = trim((string)($_POST['note'] ?? ''));

    if (mb_strlen($note) > MAX_NOTE_LENGTH) {
        $error = 'The note is too long (maximum 255 characters).';
    } elseif ($action === 'assign') {
        $staffId    = (int)($_POST['staff_id'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $priority   = (string)($_POST['priority'] ?? '');

        if (!in_array($staffId, array_map('intval', array_column($staff, 'id')), true)) {
            $error = 'Please choose a field worker.';
        } elseif (!in_array($categoryId, array_map('intval', array_column($categories, 'id')), true)) {
            $error = 'Please choose a category.';
        } elseif (!in_array($priority, PRIORITIES, true)) {
            $error = 'Please choose a priority.';
        } else {
            try {
                if (assign_issue($pdo, $issueId, $staffId, (int)$me['id'], $categoryId, $priority, $note !== '' ? $note : null)) {
                    notify_quietly($issueId, 'Assigned');
                    header('Location: /supervisor/triage.php?assigned=1');
                    exit;
                }
                $error = 'This issue has already been handled.';
            } catch (PDOException $ex) {
                error_log('triage.php assign: ' . $ex->getMessage());
                $error = 'Sorry, we could not assign the issue. Please try again.';
            }
        }
    } elseif ($action === 'close') {
        try {
            if (close_issue($pdo, $issueId, (int)$me['id'], $note !== '' ? $note : null)) {
                notify_quietly($issueId, 'Closed');
                header('Location: /supervisor/triage.php?closed=1');
                exit;
            }
            $error = 'This issue has already been handled.';
        } catch (PDOException $ex) {
            error_log('triage.php close: ' . $ex->getMessage());
            $error = 'Sorry, we could not close the issue. Please try again.';
        }
    } else {
        $error = 'That action is not recognised.';
    }
}

$issues = $pdo->query(
    "SELECT i.id, i.tracking_ref, i.category_id, c.name_en AS category, i.description,
            i.location_text, i.photo_path, i.created_at
       FROM issues i JOIN categories c ON c.id = i.category_id
      WHERE i.status = 'New'
      ORDER BY i.created_at"
)->fetchAll();

// Resolved issues waiting for the supervisor to confirm the work and close them (FR12).
$resolved = $pdo->query(
    "SELECT i.id, i.tracking_ref, c.name_en AS category, i.description, i.location_text,
            i.photo_path, i.priority, i.resolved_at, u.name AS worker,
            (SELECT h.note FROM status_history h
              WHERE h.issue_id = i.id AND h.new_status = 'Resolved'
              ORDER BY h.id DESC LIMIT 1) AS resolution_note
       FROM issues i
       JOIN categories c ON c.id = i.category_id
       LEFT JOIN assignments a ON a.id = (SELECT MAX(a2.id) FROM assignments a2 WHERE a2.issue_id = i.id)
       LEFT JOIN users u ON u.id = a.assigned_to_user_id
      WHERE i.status = 'Resolved'
      ORDER BY i.resolved_at"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>
<h1>Triage &amp; assign</h1>
<?php if (isset($_GET['assigned'])): ?><p class="ok">The issue was assigned.</p><?php endif; ?>
<?php if (isset($_GET['closed'])): ?><p class="ok">The issue was closed.</p><?php endif; ?>
<?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>

<h2>New issues (<?= count($issues) ?>)</h2>
<?php if (!$staff): ?>
  <p class="muted">There are no field staff accounts yet. Register an account and set its role to staff.</p>
<?php endif; ?>
<?php if (!$issues): ?>
  <p class="muted">There are no new issues waiting for triage.</p>
<?php endif; ?>
<?php foreach ($issues as $issue): ?>
  <hr>
  <h3><?= e($issue['tracking_ref']) ?> <span class="muted">(<?= e($issue['created_at']) ?>)</span></h3>
  <p><?= nl2br(e($issue['description'])) ?></p>
  <p class="muted">
    Location: <?= e($issue['location_text']) ?>
    <?php if ($issue['photo_path']): ?> &middot; <a href="/<?= e($issue['photo_path']) ?>">View photo</a><?php endif; ?>
  </p>
  <form method="post" class="form" data-submit-once>
    <input type="hidden" name="action" value="assign">
    <input type="hidden" name="issue_id" value="<?= (int)$issue['id'] ?>">
    <label>Category
      <select name="category_id">
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$issue['category_id'] ? 'selected' : '' ?>><?= e($c['name_en']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Priority
      <select name="priority" required>
        <option value="">Choose a priority</option>
        <?php foreach (PRIORITIES as $p): ?>
          <option value="<?= e($p) ?>"><?= e($p) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Assign to
      <select name="staff_id" required>
        <option value="">Choose a field worker</option>
        <?php foreach ($staff as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Instructions (optional)
      <input type="text" name="note" maxlength="255">
    </label>
    <button type="submit">Assign</button>
  </form>
<?php endforeach; ?>

<h2>Resolved, waiting to be closed (<?= count($resolved) ?>)</h2>
<?php if (!$resolved): ?>
  <p class="muted">There are no resolved issues waiting to be closed.</p>
<?php endif; ?>
<?php foreach ($resolved as $issue): ?>
  <hr>
  <h3><?= e($issue['tracking_ref']) ?>
    <span class="muted">(<?= e($issue['category']) ?><?php if ($issue['priority']): ?> &middot; <?= e($issue['priority']) ?><?php endif; ?>)</span>
  </h3>
  <p><?= nl2br(e($issue['description'])) ?></p>
  <p class="muted">
    Location: <?= e($issue['location_text']) ?>
    <?php if ($issue['photo_path']): ?> &middot; <a href="/<?= e($issue['photo_path']) ?>">View photo</a><?php endif; ?>
  </p>
  <p>
    Resolved<?php if ($issue['worker']): ?> by <?= e($issue['worker']) ?><?php endif; ?>
    <?php if ($issue['resolved_at']): ?> on <?= e($issue['resolved_at']) ?><?php endif; ?>
  </p>
  <?php if ($issue['resolution_note']): ?><p>Resolution note: <?= e($issue['resolution_note']) ?></p><?php endif; ?>
  <form method="post" class="form" data-submit-once
        data-confirm="Close <?= e($issue['tracking_ref']) ?>? The resident will be told the report is complete.">
    <input type="hidden" name="action" value="close">
    <input type="hidden" name="issue_id" value="<?= (int)$issue['id'] ?>">
    <label>Closing note (optional)
      <input type="text" name="note" maxlength="255">
    </label>
    <button type="submit">Close issue</button>
  </form>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
