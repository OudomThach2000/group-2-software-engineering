<?php
/**
 * UC1 Submit Issue  (FR1, FR2).  Owner: Oudom Thach.
 * Resident submits an issue; the system stores it and returns a tracking reference.
 */
require_once __DIR__ . '/../includes/db.php';

$trackingRef = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // TODO(Oudom):
    //  1. validate inputs (category_id + description required)
    //  2. handle optional photo upload into app/public/uploads/
    //  3. generate a unique tracking_ref, e.g. 'CIR-' . date('Y') . '-' . str_pad(...)
    //  4. INSERT into issues (status 'New'); INSERT a status_history row (new_status 'New')
    //  5. set $trackingRef to display to the resident
}

$categories = [];
try {
    $categories = db()->query('SELECT id, name_en, name_km FROM categories ORDER BY sort_order')->fetchAll();
} catch (Throwable $e) {
    $error = 'Database is not set up yet — see README (import db/schema.sql and db/seed.sql).';
}

require_once __DIR__ . '/../includes/header.php';
?>
<h1>Report an issue</h1>
<?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($trackingRef): ?>
  <p class="ok">Thank you. Your tracking reference is <strong><?= htmlspecialchars($trackingRef) ?></strong> — keep it to check progress.</p>
<?php else: ?>
<form method="post" enctype="multipart/form-data" class="form">
  <label>Category
    <select name="category_id" required>
      <?php foreach ($categories as $c): ?>
        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name_en']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Description <textarea name="description" rows="4" required></textarea></label>
  <label>Location <input type="text" name="location_text" placeholder="Street or landmark"></label>
  <label>Photo (optional) <input type="file" name="photo" accept="image/*"></label>
  <button type="submit">Submit</button>
</form>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
