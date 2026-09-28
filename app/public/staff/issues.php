<?php
/**
 * UC4 Update / Resolve Issue  (FR4, FR9, FR13).  Owner: Oudom Thach.
 * Field staff see only the issues assigned to them, most urgent first, and move each
 * one from Assigned to In-progress to Resolved with a note.
 * The lifecycle rules live in app/src/issues.php (tested by tests/issues_test.php).
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../src/issues.php';
require_once __DIR__ . '/../../src/notify.php';
require_role('staff', 'supervisor');

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$pdo = db();
$me = current_user();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $issueId   = (int)($_POST['issue_id'] ?? 0);
    $newStatus = (string)($_POST['new_status'] ?? '');
    $note      = trim((string)($_POST['note'] ?? ''));

    if (mb_strlen($note) > MAX_NOTE_LENGTH) {
        $error = 'The note is too long (maximum 255 characters).';
    } else {
        try {
            if (update_issue_status($pdo, $issueId, (int)$me['id'], $newStatus, $note)) {
                // The change is saved; a notification problem must not undo it.
                try {
                    notify_status_change($issueId, $newStatus);
                } catch (Throwable $ex) {
                    error_log('staff/issues.php notify: ' . $ex->getMessage());
                }
                header('Location: /staff/issues.php?updated=1');
                exit;
            }
            $error = 'This issue is not assigned to you, or its status has already changed.';
        } catch (InvalidArgumentException $ex) {
            $error = $ex->getMessage();
        } catch (PDOException $ex) {
            error_log('staff/issues.php: ' . $ex->getMessage());
            $error = 'Sorry, we could not update the issue. Please try again.';
        }
    }
}

// Open issues whose latest assignment is to the logged-in user, most urgent first (FR13).
$stmt = $pdo->prepare(
    "SELECT i.id, i.tracking_ref, c.name_en AS category, i.description, i.location_text,
            i.photo_path, i.priority, i.status, a.assigned_at, a.note AS instructions
       FROM issues i
       JOIN categories c ON c.id = i.category_id
       JOIN assignments a ON a.issue_id = i.id
      WHERE a.id = (SELECT MAX(a2.id) FROM assignments a2 WHERE a2.issue_id = i.id)
        AND a.assigned_to_user_id = ?
        AND i.status IN ('Assigned', 'In-progress')
      ORDER BY CASE i.priority
                 WHEN 'Urgent' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 WHEN 'Low' THEN 4 ELSE 5
               END,
               a.assigned_at"
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
  <h2><?= e($issue['tracking_ref']) ?>
    <span class="muted">(<?= e($issue['category']) ?> &middot; <?= e($issue['status']) ?><?php if ($issue['priority']): ?> &middot; <?= e($issue['priority']) ?> priority<?php endif; ?>)</span>
  </h2>
  <p><?= nl2br(e($issue['description'])) ?></p>
  <p class="muted">
    Location: <?= e($issue['location_text']) ?>
    <?php if ($issue['photo_path']): ?> &middot; <a href="/<?= e($issue['photo_path']) ?>">View photo</a><?php endif; ?>
  </p>
  <?php if ($issue['instructions']): ?><p>Instructions: <?= e($issue['instructions']) ?></p><?php endif; ?>
  <form method="post" class="form" data-submit-once>
    <input type="hidden" name="issue_id" value="<?= (int)$issue['id'] ?>">
    <input type="hidden" name="new_status" value="<?= e(WORKER_NEXT_STATUS[$issue['status']]) ?>">
    <?php if ($issue['status'] === 'In-progress'): ?>
      <label>Resolution note *
        <textarea name="note" rows="3" maxlength="255" placeholder="What was done?" required></textarea>
      </label>
      <button type="submit">Mark as resolved</button>
    <?php else: ?>
      <label>Note (optional)
        <input type="text" name="note" maxlength="255">
      </label>
      <button type="submit">Start work</button>
    <?php endif; ?>
  </form>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
