<?php
/**
 * UC6 Register  (FR7, NFR2, NFR3).  Owner: Sethouday Prum.
 * Creates a resident account and logs it in. A supervisor promotes staff later.
 * The rules live in validate_registration() / register_user() in app/includes/auth.php.
 */
require_once __DIR__ . '/../includes/auth.php';

if ($u = current_user()) {
    header('Location: ' . home_for_role($u['role']));
    exit;
}

$input  = ['name' => '', 'email' => ''];
$errors = [];
$error  = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = [
        'name'     => trim((string)($_POST['name'] ?? '')),
        'email'    => trim((string)($_POST['email'] ?? '')),
        'password' => (string)($_POST['password'] ?? ''),
    ];
    $errors = validate_registration($input);
    if (!$errors) {
        try {
            $pdo    = db();
            $userId = register_user($pdo, $input);
            if ($userId === null) {
                $errors['email'] = 'An account with this email already exists. Try logging in instead.';
            } else {
                login_user(['id' => $userId, 'name' => $input['name'], 'role' => 'resident']);
                header('Location: ' . home_for_role('resident'));
                exit;
            }
        } catch (PDOException $ex) {
            error_log('register.php: ' . $ex->getMessage());
            $error = 'Sorry, we could not create your account. Please try again.';
        }
    }
    unset($input['password']);   // never send the password back to the page
}

/** The field's error message as HTML, or nothing. */
function field_error(array $errors, string $field): string
{
    return isset($errors[$field])
        ? '<span class="error">' . htmlspecialchars($errors[$field]) . '</span>'
        : '';
}

require_once __DIR__ . '/../includes/header.php';
?>
<h1>Create an account</h1>
<p class="muted">An account lets you see all your reports in one place. You can report and track an issue without one.</p>
<?php if ($error): ?><p class="error" role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" class="form" data-submit-once>
  <label>Name
    <input type="text" name="name" maxlength="<?= MAX_NAME_LENGTH ?>" autocomplete="name"
      value="<?= htmlspecialchars($input['name']) ?>" required>
    <?= field_error($errors, 'name') ?>
  </label>
  <label>Email
    <input type="email" name="email" maxlength="<?= MAX_EMAIL_LENGTH ?>" autocomplete="email"
      value="<?= htmlspecialchars($input['email']) ?>" required>
    <?= field_error($errors, 'email') ?>
  </label>
  <label>Password
    <input type="password" name="password" minlength="<?= MIN_PASSWORD_LENGTH ?>" autocomplete="new-password" required>
    <span class="muted">At least <?= MIN_PASSWORD_LENGTH ?> characters.</span>
    <?= field_error($errors, 'password') ?>
  </label>
  <button type="submit" data-busy-text="Creating account…">Register</button>
</form>
<p>Already have an account? <a href="/login.php">Log in</a></p>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
