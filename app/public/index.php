<?php
/**
 * Application home.  Owner: Pichponleur Pen.
 * Public entry inside the app: residents report / track; staff log in.
 */
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Report a community issue</h1>
<p>Tell the Office about a local problem — a streetlight, a pothole, a drain, a water pipe — and follow its progress.</p>
<p class="actions">
  <a class="btn" href="/submit.php">Report an issue</a>
  <a class="btn btn-outline" href="/track.php">Track an existing report</a>
</p>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
