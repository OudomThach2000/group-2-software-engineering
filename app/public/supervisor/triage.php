<?php
/**
 * UC3 Triage & Assign  (FR3).  Owner: Oudom Thach.
 * Supervisor reviews new issues, confirms category/priority, assigns to field staff.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../src/notify.php';
require_role('supervisor');

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$pdo = db();
$me = current_user();
$error = null;

$categories = $pdo->query('SELECT id, name_en FROM categories ORDER BY sort_order')->fetchAll();
$staff = $pdo->query("SELECT id, name FROM users WHERE role = 'staff' ORDER BY name")->fetchAll();

// NOTE: priority (FR8) needs an issues.priority column; add the control here once the schema has it.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $issueId    = (int)($_POST['issue_id'] ?? 0);
    $staffId    = (int)($_POST['staff_id'] ?? 0);
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $note       = trim((string)($_POST['note'] ?? ''));

    if (!in_array($staffId, array_map('intval', array_column($staff, 'id')), true)) {
        $error = 'Please choose a field worker.';
    } elseif (!in_array($categoryId, array_map('intval', array_column($categories, 'id')), true)) {
        $error = 'Please choose a category.';
    } elseif (mb_strlen($note) > 255) {
        $error = 'The note is too long (maximum 255 characters).';
    } else {
        try {
            $pdo->beginTransaction();
            // Only succeeds while the issue is still New, so two supervisors cannot both assign it.
            $stmt = $pdo->prepare("UPDATE issues SET status = 'Assigned', category_id = ? WHERE id = ? AND status = 'New'");
            $stmt->execute([$categoryId, $issueId]);
            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                $error = 'This issue has already been handled.';
            } else {
                $pdo->prepare(
                    'INSERT INTO assignments (issue_id, assigned_to_user_id, assigned_by_user_id, note) VALUES (?, ?, ?, ?)'
                )->execute([$issueId, $staffId, $me['id'], $note !== '' ? $note : null]);
                $pdo->prepare(
                    'INSERT INTO status_history (issue_id, old_status, new_status, changed_by_user_id, note)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([$issueId, 'New', 'Assigned', $me['id'], 'Assigned by supervisor']);
                $pdo->commit();

                // The assignment is saved; a notification problem must not undo it.
                try {
                    notify_status_change($issueId, 'Assigned');
                } catch (Throwable $ex) {
                    error_log('triage.php notify: ' . $ex->getMessage());
                }
                header('Location: /supervisor/triage.php?assigned=1');
                exit;
            }
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('triage.php: ' . $ex->getMessage());
            $error = 'Sorry, we could not assign the issue. Please try again.';
        }
    }
}

$issues = $pdo->query(
    "SELECT i.id, i.tracking_ref, i.category_id, c.name_en AS category, i.description,
            i.location_text, i.photo_path, i.created_at
       FROM issues i JOIN categories c ON c.id = i.category_id
      WHERE i.status = 'New'
      ORDER BY i.created_at"
)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>
<h1>Triage &amp; assign</h1>
<?php if (isset($_GET['assigned'])): ?><p class="ok">The issue was assigned.</p><?php endif; ?>
<?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
<?php if (!$staff): ?>
  <p class="muted">There are no field staff accounts yet. Register an account and set its role to staff.</p>
<?php endif; ?>
<?php if (!$issues): ?>
  <p class="muted">There are no new issues waiting for triage.</p>
<?php endif; ?>
<?php foreach ($issues as $issue): ?>
  <hr>
  <h2><?= e($issue['tracking_ref']) ?> <span class="muted">(<?= e($issue['created_at']) ?>)</span></h2>
  <p><?= nl2br(e($issue['description'])) ?></p>
  <p class="muted">
    Location: <?= e($issue['location_text']) ?>
    <?php if ($issue['photo_path']): ?> &middot; <a href="/<?= e($issue['photo_path']) ?>">View photo</a><?php endif; ?>
  </p>
  <form method="post" class="form">
    <input type="hidden" name="issue_id" value="<?= (int)$issue['id'] ?>">
    <label>Category
      <select name="category_id">
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$issue['category_id'] ? 'selected' : '' ?>><?= e($c['name_en']) ?></option>
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
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
