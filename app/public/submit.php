<?php
/**
 * UC1 Submit Issue  (FR1, FR2).  Owner: Oudom Thach.
 * Resident submits an issue; the system stores it and returns a tracking reference.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

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

/** Next free reference for this year, e.g. CIR-2026-0004. */
function next_tracking_ref(PDO $pdo): string
{
    $prefix = 'CIR-' . date('Y') . '-';
    $stmt = $pdo->prepare(
        'SELECT COALESCE(MAX(CAST(SUBSTRING(tracking_ref, ?) AS UNSIGNED)), 0) + 1
           FROM issues WHERE tracking_ref LIKE ?'
    );
    $stmt->bindValue(1, strlen($prefix) + 1, PDO::PARAM_INT);
    $stmt->bindValue(2, $prefix . '%');
    $stmt->execute();
    return $prefix . str_pad((string)$stmt->fetchColumn(), 4, '0', STR_PAD_LEFT);
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
    $error = 'Database is not set up yet — see README (import db/schema.sql and db/seed.sql).';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    foreach ($input as $key => $_) {
        $input[$key] = trim((string)($_POST[$key] ?? ''));
    }

    // 1. Validate the text fields (FR1, FR16).
    $validCategories = array_map('intval', array_column($categories, 'id'));
    if (!in_array((int)$input['category_id'], $validCategories, true)) {
        $errors['category_id'] = 'Please choose a category.';
    }
    if ($input['description'] === '') {
        $errors['description'] = 'Please describe the problem.';
    } elseif (mb_strlen($input['description']) > 2000) {
        $errors['description'] = 'The description is too long (maximum 2000 characters).';
    }
    if ($input['location_text'] === '') {
        $errors['location_text'] = 'Please tell us where the problem is.';
    } elseif (mb_strlen($input['location_text']) > 255) {
        $errors['location_text'] = 'The location is too long (maximum 255 characters).';
    }
    $contact = $input['reporter_contact'];
    if (mb_strlen($contact) > 190) {
        $errors['reporter_contact'] = 'The contact detail is too long.';
    } elseif ($contact !== '' && !filter_var($contact, FILTER_VALIDATE_EMAIL)
        && !preg_match('/^\+?[0-9][0-9 \-()]{5,19}$/', $contact)) {
        $errors['reporter_contact'] = 'Enter a valid email address or phone number, or leave it empty.';
    }

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
        $pdo = db();
        $user = current_user();
        for ($attempt = 1; $attempt <= 5 && $trackingRef === null; $attempt++) {
            try {
                $pdo->beginTransaction();
                $ref = next_tracking_ref($pdo);
                $pdo->prepare(
                    'INSERT INTO issues (tracking_ref, category_id, description, location_text, photo_path,
                                         status, reporter_user_id, reporter_contact)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $ref, (int)$input['category_id'], $input['description'], $input['location_text'],
                    $photoPath, 'New', $user['id'] ?? null, $contact !== '' ? $contact : null,
                ]);
                $issueId = (int)$pdo->lastInsertId();
                $pdo->prepare(
                    'INSERT INTO status_history (issue_id, old_status, new_status, changed_by_user_id, note)
                     VALUES (?, NULL, ?, ?, ?)'
                )->execute([$issueId, 'New', $user['id'] ?? null, 'Issue submitted']);
                $pdo->commit();
                $trackingRef = $ref;
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                // 1062 = duplicate tracking_ref (two people submitted at once): try the next number.
                if (($ex->errorInfo[1] ?? 0) === 1062 && $attempt < 5) {
                    continue;
                }
                error_log('submit.php: ' . $ex->getMessage());
                break;
            }
        }
        if ($trackingRef === null) {
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
  <p class="ok">Thank you. Your tracking reference is <strong><?= e($trackingRef) ?></strong> — keep it to check progress.</p>
  <p class="actions">
    <a class="btn" href="/track.php?ref=<?= e(urlencode($trackingRef)) ?>">Track this report</a>
    <a class="btn btn-outline" href="/submit.php">Report another issue</a>
  </p>
<?php else: ?>
<form method="post" enctype="multipart/form-data" class="form">
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
    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
    <?php if (isset($errors['photo'])): ?><span class="error"><?= e($errors['photo']) ?></span><?php endif; ?>
  </label>
  <label>Contact for updates (optional)
    <input type="text" name="reporter_contact" maxlength="190" placeholder="Email or phone" value="<?= e($input['reporter_contact']) ?>">
    <?php if (isset($errors['reporter_contact'])): ?><span class="error"><?= e($errors['reporter_contact']) ?></span><?php endif; ?>
  </label>
  <button type="submit">Submit</button>
</form>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
