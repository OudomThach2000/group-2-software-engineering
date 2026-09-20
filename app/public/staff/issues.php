<?php
/**
 * UC4 Update / Resolve Issue  (FR4).  Owner: Oudom Thach.
 * Field staff see their assigned issues and update status / add notes.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../src/notify.php';
require_role('staff', 'supervisor');

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// The only moves a field worker can make: Assigned -> In-progress -> Resolved.
const NEXT_STATUS = ['Assigned' => 'In-progress', 'In-progress' => 'Resolved'];

$pdo = db();
$me = current_user();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $issueId   = (int)($_POST['issue_id'] ?? 0);
    $newStatus = (string)($_POST['new_status'] ?? '');
    $note      = trim((string)($_POST['note'] ?? ''));
    $oldStatus = array_search($newStatus, NEXT_STATUS, true);

    if ($oldStatus === false) {
        $error = 'That status change is not allowed.';
    } elseif ($newStatus === 'Resolved' && $note === '') {
        $error = 'Please describe what was done before marking the issue as resolved.';
    } elseif (mb_strlen($note) > 255) {
        $error = 'The note is too long (maximum 255 characters).';
    } else {
        try {
            $pdo->beginTransaction();
            // Only the worker holding the latest assignment can change it, and only from the expected status.
            $set = $newStatus === 'Resolved' ? 'status = ?, resolved_at = NOW()' : 'status = ?';
            $stmt = $pdo->prepare(
                "UPDATE issues SET $set
                  WHERE id = ? AND status = ?
                    AND (SELECT a.assigned_to_user_id FROM assignments a
                          WHERE a.issue_id = issues.id ORDER BY a.id DESC LIMIT 1) = ?"
            );
            $stmt->execute([$newStatus, $issueId, $oldStatus, $me['id']]);
            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                $error = 'This issue is not assigned to you, or its status has already changed.';
            } else {
                $pdo->prepare(
                    'INSERT INTO status_history (issue_id, old_status, new_status, changed_by_user_id, note)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([$issueId, $oldStatus, $newStatus, $me['id'], $note !== '' ? $note : null]);
                $pdo->commit();

                // The change is saved; a notification problem must not undo it.
                try {
                    notify_status_change($issueId, $newStatus);
                } catch (Throwable $ex) {
                    error_log('staff/issues.php notify: ' . $ex->getMessage());
                }
                header('Location: /staff/issues.php?updated=1');
                exit;
            }
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('staff/issues.php: ' . $ex->getMessage());
            $error = 'Sorry, we could not update the issue. Please try again.';
        }
    }
}

// Open issues whose latest assignment is to the logged-in user.
$stmt = $pdo->prepare(
    "SELECT i.id, i.tracking_ref, c.name_en AS category, i.description, i.location_text,
            i.photo_path, i.status, a.assigned_at, a.note AS instructions
       FROM issues i
       JOIN categories c ON c.id = i.category_id
       JOIN assignments a ON a.issue_id = i.id
      WHERE a.id = (SELECT MAX(a2.id) FROM assignments a2 WHERE a2.issue_id = i.id)
        AND a.assigned_to_user_id = ?
        AND i.status IN ('Assigned', 'In-progress')
      ORDER BY a.assigned_at"
);
$stmt->execute([$me['id']]);
$issues = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>
<h1>My assigned issues</h1>
<?php if (isset($_GET['updated'])): ?><p class="ok">The issue was updated.</p><?php endif; ?>
<?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
<?php if (!$issues): ?>
  <p class="muted">You have no open issues assigned to you.</p>
<?php endif; ?>
<?php foreach ($issues as $issue): ?>
  <hr>
  <h2><?= e($issue['tracking_ref']) ?> <span class="muted">(<?= e($issue['category']) ?> &middot; <?= e($issue['status']) ?>)</span></h2>
  <p><?= nl2br(e($issue['description'])) ?></p>
  <p class="muted">
    Location: <?= e($issue['location_text']) ?>
    <?php if ($issue['photo_path']): ?> &middot; <a href="/<?= e($issue['photo_path']) ?>">View photo</a><?php endif; ?>
  </p>
  <?php if ($issue['instructions']): ?><p>Instructions: <?= e($issue['instructions']) ?></p><?php endif; ?>
  <form method="post" class="form">
    <input type="hidden" name="issue_id" value="<?= (int)$issue['id'] ?>">
    <input type="hidden" name="new_status" value="<?= e(NEXT_STATUS[$issue['status']]) ?>">
    <?php if ($issue['status'] === 'In-progress'): ?>
      <label>Resolution note *
        <textarea name="note" rows="3" maxlength="255" placeholder="What was done?" required></textarea>
      </label>
      <button type="submit">Mark as resolved</button>
    <?php else: ?>
      <button type="submit">Start work</button>
    <?php endif; ?>
  </form>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
