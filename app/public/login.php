<?php
/**
 * UC6 Log In  (FR7, NFR3).  Owner: Sethouday Prum.
 * Checks the email and password, starts the session, and sends the user to the page for
 * their role (ROLE_HOME in app/includes/auth.php).
 */
require_once __DIR__ . '/../includes/auth.php';

if ($u = current_user()) {
    header('Location: ' . home_for_role($u['role']));
    exit;
}

$email = '';
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    try {
        $user = ($email !== '' && $password !== '') ? attempt_login(db(), $email, $password) : null;
        if ($user) {
            login_user($user);
            header('Location: ' . home_for_role($user['role']));
            exit;
        }
        // The same message for an unknown email and a wrong password (NFR3).
        $error = 'The email or password is not correct.';
    } catch (PDOException $ex) {
        error_log('login.php: ' . $ex->getMessage());
        $error = 'Sorry, we could not log you in. Please try again.';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<h1>Log in</h1>
<?php if ($error): ?><p class="error" role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post" class="form" data-submit-once>
  <label>Email <input type="email" name="email" maxlength="<?= MAX_EMAIL_LENGTH ?>" autocomplete="email"
    value="<?= htmlspecialchars($email) ?>" required></label>
  <label>Password <input type="password" name="password" autocomplete="current-password" required></label>
  <button type="submit" data-busy-text="Logging in…">Log in</button>
</form>
<p><a href="/register.php">Create an account</a></p>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
