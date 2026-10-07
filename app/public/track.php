<?php
/**
 * UC2 Track Issue  (FR2, NFR2, NFR3).  Owner: Sethouday Prum.
 * Resident enters a tracking reference and sees the current status first, with its icon
 * and meaning, then the history in date order. A logged-in user also sees their own reports.
 *
 * Privacy (NFR3): only the status, the date and the note of each change are shown, never
 * the reporter's contact detail or the name of the staff member who made the change.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/notify.php';   // STATUS_MESSAGES: same wording as the notifications

/**
 * How each status is shown (7.4.3). The sentence comes from STATUS_MESSAGES, so the page and
 * the resident's notification always say the same thing. A new status means one new row.
 */
const STATUS_ICONS = [
    'New'         => '○',    // open circle
    'Assigned'    => '👤',   // person
    'In-progress' => '◐',    // half filled circle
    'Resolved'    => '✓',    // tick
    'Closed'      => '●',    // filled circle
];

/** A tracking reference looks like CIR-2026-0001. */
const TRACKING_REF_PATTERN = '/^CIR-\d{4}-\d{4,}$/';

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** The status as an icon plus a text label, never colour alone. */
function status_badge(string $status): string
{
    $slug = strtolower($status);
    return '<span class="status status-' . e($slug) . '"><span aria-hidden="true">'
         . (STATUS_ICONS[$status] ?? '•') . '</span> ' . e($status) . '</span>';
}

/** Dates are written with the month in words, e.g. 20 September 2026. */
function long_date(string $datetime, bool $withTime = false): string
{
    return date($withTime ? 'j F Y, H:i' : 'j F Y', strtotime($datetime));
}

$ref      = strtoupper(trim((string)($_GET['ref'] ?? '')));
$issue    = null;
$history  = [];
$notFound = false;
$refError = null;
$error    = null;
$myIssues = [];
$user     = current_user();

try {
    if ($ref !== '') {
        if (!preg_match(TRACKING_REF_PATTERN, $ref)) {
            $refError = 'A tracking reference looks like CIR-2026-0001.';
        } else {
            $pdo  = db();
            $stmt = $pdo->prepare(
                'SELECT i.id, i.tracking_ref, i.status, i.created_at, c.name_en, c.name_km
                   FROM issues i JOIN categories c ON c.id = i.category_id
                  WHERE i.tracking_ref = ?'
            );
            $stmt->execute([$ref]);
            $issue = $stmt->fetch() ?: null;
            if ($issue) {
                $stmt = $pdo->prepare(
                    'SELECT new_status, note, changed_at FROM status_history
                      WHERE issue_id = ? ORDER BY changed_at, id'
                );
                $stmt->execute([$issue['id']]);
                $history = $stmt->fetchAll();
                // Older reports may have no history rows; their first step is still known.
                if (!$history) {
                    $history = [['new_status' => 'New', 'note' => null, 'changed_at' => $issue['created_at']]];
                }
            } else {
                $notFound = true;
            }
        }
    }
    if ($user) {
        $stmt = db()->prepare(
            'SELECT i.tracking_ref, i.status, i.created_at, c.name_en
               FROM issues i JOIN categories c ON c.id = i.category_id
              WHERE i.reporter_user_id = ?
              ORDER BY i.created_at DESC'
        );
        $stmt->execute([$user['id']]);
        $myIssues = $stmt->fetchAll();
    }
} catch (PDOException $ex) {
    error_log('track.php: ' . $ex->getMessage());
    $error = 'Sorry, we could not look up the report. Please try again.';
}

require_once __DIR__ . '/../includes/header.php';
?>
<h1>Track your report</h1>
<?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="get" class="form">
  <label>Tracking reference
    <input type="text" name="ref" placeholder="CIR-2026-0001" maxlength="20" autocapitalize="characters"
      value="<?= e($ref) ?>" required>
    <?php if ($refError): ?><span class="error"><?= e($refError) ?></span><?php endif; ?>
  </label>
  <button type="submit">Check status</button>
</form>
<?php if ($notFound): ?>
  <p class="error" role="alert">No report found with the reference <?= e($ref) ?>. Please check it and try again.</p>
<?php endif; ?>

<?php if ($issue): ?>
  <section class="track-result" aria-labelledby="current-status">
    <h2 id="current-status"><?= e($issue['tracking_ref']) ?></h2>
    <p class="current"><?= status_badge($issue['status']) ?></p>
    <p><?= e(STATUS_MESSAGES[$issue['status']] ?? '') ?></p>
    <p class="muted">
      <?= e($issue['name_en']) ?><?php if ($issue['name_km']): ?> / <span lang="km"><?= e($issue['name_km']) ?></span><?php endif; ?>
      &middot; Reported on <?= e(long_date($issue['created_at'])) ?>
    </p>
    <p><button type="button" class="btn btn-outline" data-copy="<?= e($issue['tracking_ref']) ?>">Copy reference</button></p>

    <h3>History</h3>
    <ol class="timeline">
      <?php foreach ($history as $h): ?>
        <li>
          <?= status_badge($h['new_status']) ?>
          <time datetime="<?= e(date('c', strtotime($h['changed_at']))) ?>"><?= e(long_date($h['changed_at'], true)) ?></time>
          <?php if ($h['note']): ?><p><?= e($h['note']) ?></p><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>
<?php endif; ?>

<?php if ($user): ?>
  <section aria-labelledby="my-reports">
    <h2 id="my-reports">Your reports</h2>
    <?php if (!$myIssues): ?>
      <p class="muted">You have not reported any issues while logged in. <a href="/submit.php">Report an issue</a></p>
    <?php else: ?>
      <ul class="my-reports">
        <?php foreach ($myIssues as $m): ?>
          <li>
            <a href="/track.php?ref=<?= e(urlencode($m['tracking_ref'])) ?>"><?= e($m['tracking_ref']) ?></a>
            <?= status_badge($m['status']) ?>
            <span class="muted"><?= e($m['name_en']) ?> &middot; <?= e(long_date($m['created_at'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
