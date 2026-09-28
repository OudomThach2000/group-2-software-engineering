<?php
/**
 * UC1 Submit Issue  (FR1, FR2, FR15, FR16).  Owner: Oudom Thach.
 * Resident submits an issue; the system stores it and returns a tracking reference.
 * Validation and saving live in app/src/issues.php (tested by tests/issues_test.php).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/issues.php';

const MAX_PHOTO_BYTES = 5 * 1024 * 1024;   // 5 MB
const PHOTO_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Checks and stores the optional photo. Returns [path or null, error message or null]. */
function save_photo(array $file): array
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
        || $file['size'] > MAX_PHOTO_BYTES) {
        return [null, 'The photo is too large (maximum 5 MB).'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'The photo could not be uploaded. Please try again.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(PHOTO_TYPES[$mime])) {
        return [null, 'The photo must be a JPEG, PNG or WebP image.'];
    }
    // Never keep the uploaded file name; use a random one.
    $name = bin2hex(random_bytes(8)) . '.' . PHOTO_TYPES[$mime];
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/uploads/' . $name)) {
        return [null, 'The photo could not be saved. Please try again.'];
    }
    return ['uploads/' . $name, null];
}

$trackingRef = null;
$error = null;
$errors = [];
$input = ['category_id' => '', 'description' => '', 'location_text' => '', 'reporter_contact' => ''];

$categories = [];
try {
    $categories = db()->query('SELECT id, name_en, name_km FROM categories ORDER BY sort_order')->fetchAll();
} catch (Throwable $ex) {
    error_log('submit.php categories: ' . $ex->getMessage());
    $error = 'Database is not set up yet — see README (import db/schema.sql and db/seed.sql).';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    foreach ($input as $key => $_) {
        $input[$key] = trim((string)($_POST[$key] ?? ''));
    }

    // 1. Validate the text fields (FR1, FR16).
    $errors = validate_submission($input, array_map('intval', array_column($categories, 'id')));

    // 2. Store the optional photo only when everything else is valid.
    $photoPath = null;
    if (!$errors) {
        [$photoPath, $photoError] = save_photo($_FILES['photo'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
        if ($photoError) {
            $errors['photo'] = $photoError;
        }
    }

    // 3. Save the issue and its first history row in one transaction.
    if (!$errors) {
        $user = current_user();
        try {
            $trackingRef = submit_issue(db(), $input, $photoPath, isset($user['id']) ? (int)$user['id'] : null);
        } catch (PDOException $ex) {
            error_log('submit.php: ' . $ex->getMessage());
            // Nothing was saved, so remove the photo we just stored.
            if ($photoPath) {
                @unlink(__DIR__ . '/' . $photoPath);
            }
            $error = 'Sorry, we could not save your report. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<h1>Report an issue</h1>
<?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
<?php if ($trackingRef): ?>
  <p class="ok">Thank you. Your report has been received.</p>
  <p>Your tracking reference is</p>
  <p class="ref"><strong><?= e($trackingRef) ?></strong></p>
  <p class="muted">Keep this reference. You can use it on the tracking page to see what is being done about your report.</p>
  <p class="actions">
    <button type="button" class="btn btn-outline" data-copy="<?= e($trackingRef) ?>">Copy reference</button>
    <a class="btn" href="/track.php?ref=<?= e(urlencode($trackingRef)) ?>">Track this report</a>
  </p>
  <p><a href="/submit.php">Report another issue</a></p>
<?php else: ?>
<form method="post" enctype="multipart/form-data" class="form" data-submit-once>
  <label>Category *
    <select name="category_id" required>
      <option value="">Choose a category</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= (string)$c['id'] === $input['category_id'] ? 'selected' : '' ?>><?= e($c['name_en']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if (isset($errors['category_id'])): ?><span class="error"><?= e($errors['category_id']) ?></span><?php endif; ?>
  </label>
  <label>Description *
    <textarea name="description" rows="4" maxlength="2000" required><?= e($input['description']) ?></textarea>
    <?php if (isset($errors['description'])): ?><span class="error"><?= e($errors['description']) ?></span><?php endif; ?>
  </label>
  <label>Location *
    <input type="text" name="location_text" maxlength="255" placeholder="Street or landmark" value="<?= e($input['location_text']) ?>" required>
    <?php if (isset($errors['location_text'])): ?><span class="error"><?= e($errors['location_text']) ?></span><?php endif; ?>
  </label>
  <label>Photo (optional, JPEG/PNG/WebP, up to 5 MB)
    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-max-bytes="<?= MAX_PHOTO_BYTES ?>">
    <?php if (isset($errors['photo'])): ?><span class="error"><?= e($errors['photo']) ?></span><?php endif; ?>
  </label>
  <label>Contact for updates (optional)
    <input type="text" name="reporter_contact" maxlength="190" placeholder="Email or phone" value="<?= e($input['reporter_contact']) ?>">
    <span class="muted">Only used to tell you when the status of your report changes. It is never shown publicly.</span>
    <?php if (isset($errors['reporter_contact'])): ?><span class="error"><?= e($errors['reporter_contact']) ?></span><?php endif; ?>
  </label>
  <button type="submit" data-busy-text="Sending…">Submit</button>
</form>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
