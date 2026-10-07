<?php
/**
 * Authentication & session helpers  (FR7, UC6, NFR3).  Owner: Sethouday Prum.
 *
 * The login module's interface for the rest of the system:
 *   current_user()    the logged-in user ['id','name','role'], or null
 *   require_role()    protects a page; sends anyone else to the login page
 *   attempt_login()   checks an email and password, returns the user or null
 *   login_user()      starts the session for a user who passed attempt_login()
 *   logout_user()     ends the session
 * Registration lives here too: validate_registration() and register_user().
 *
 * Pages depend only on these functions, never on how sessions or password hashes work.
 * attempt_login() and register_user() do not touch the session, so tests/auth_test.php
 * can check them without a browser.
 */
require_once __DIR__ . '/db.php';

/** Settings that may change, kept in one place (Section 8, parameterisation). */
const SESSION_TIMEOUT_SECONDS = 30 * 60;   // log out after 30 minutes without activity
const MIN_PASSWORD_LENGTH     = 8;
const MAX_NAME_LENGTH         = 120;       // same as users.name
const MAX_EMAIL_LENGTH        = 190;       // same as users.email

/** Where each role lands after logging in. A new role means one new row. */
const ROLE_HOME = [
    'supervisor' => '/supervisor/triage.php',
    'staff'      => '/staff/issues.php',
    'resident'   => '/track.php',
];

/**
 * A valid hash of a password nobody uses. Checked when the email is unknown, so an
 * unknown email takes as long as a wrong password and accounts cannot be found by timing.
 */
const DUMMY_PASSWORD_HASH = '$2y$10$JeVoryPLSPqrCBI6WkbS7.2woXshvDTO4HlGVT/Wq/Zo1dRPL5Z.6';

/** Starts the session with a cookie scripts cannot read and other sites cannot send. */
function start_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');   // never accept a session id we did not create
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** The logged-in user ['id','name','role'], or null. Ends a session that has been idle too long. */
function current_user(): ?array
{
    start_session();
    if (!isset($_SESSION['user'])) {
        return null;
    }
    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT_SECONDS) {
        logout_user();
        return null;
    }
    $_SESSION['last_activity'] = time();
    return $_SESSION['user'];
}

/** Redirect to login unless the current user has one of the given roles. */
function require_role(string ...$roles): void
{
    $u = current_user();
    if (!$u || !in_array($u['role'], $roles, true)) {
        header('Location: /login.php');
        exit;
    }
}

/** The page a user of this role goes to after logging in. Unknown roles go home. */
function home_for_role(string $role): string
{
    return ROLE_HOME[$role] ?? '/index.php';
}

/** Emails are compared without case or surrounding spaces. */
function normalise_email(string $email): string
{
    return mb_strtolower(trim($email));
}

/**
 * Checks the registration form (FR7). Names may be in any script, e.g. Khmer (NFR2).
 * Returns one message per field in error, e.g. ['email' => 'Enter a valid email address.'];
 * an empty array means the input is valid. Does not check whether the email is taken.
 */
function validate_registration(array $input): array
{
    $errors   = [];
    $name     = trim((string)($input['name'] ?? ''));
    $email    = normalise_email((string)($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if ($name === '') {
        $errors['name'] = 'Please enter your name.';
    } elseif (mb_strlen($name) > MAX_NAME_LENGTH) {
        $errors['name'] = 'The name is too long (maximum ' . MAX_NAME_LENGTH . ' characters).';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif (mb_strlen($email) > MAX_EMAIL_LENGTH) {
        $errors['email'] = 'The email address is too long.';
    }
    if (mb_strlen($password) < MIN_PASSWORD_LENGTH) {
        $errors['password'] = 'The password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.';
    }
    return $errors;
}

/**
 * Creates a resident account and returns its id. Only the password hash is stored.
 * $input must already have passed validate_registration(). Returns null when the email
 * is already registered. A supervisor promotes staff later by changing the role.
 */
function register_user(PDO $pdo, array $input): ?int
{
    if (validate_registration($input)) {
        throw new InvalidArgumentException('register_user() was given input that failed validation.');
    }
    $email = normalise_email($input['email']);

    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) {
        return null;
    }
    try {
        $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)')
            ->execute([trim($input['name']), $email, password_hash($input['password'], PASSWORD_DEFAULT)]);
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {   // someone registered the same email a moment ago
            return null;
        }
        throw $ex;
    }
    return (int)$pdo->lastInsertId();
}

/**
 * Checks an email and password. Returns the user ['id','name','role'], or null for an
 * unknown email or a wrong password alike, so the page cannot tell them apart.
 * Upgrades the stored hash when PHP's default hashing standard has changed.
 */
function attempt_login(PDO $pdo, string $email, string $password): ?array
{
    $stmt = $pdo->prepare('SELECT id, name, role, password_hash FROM users WHERE email = ?');
    $stmt->execute([normalise_email($email)]);
    $row = $stmt->fetch();

    if (!$row) {
        password_verify($password, DUMMY_PASSWORD_HASH);
        return null;
    }
    if (!password_verify($password, $row['password_hash'])) {
        return null;
    }
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    }
    return ['id' => (int)$row['id'], 'name' => $row['name'], 'role' => $row['role']];
}

/** Starts the session for $user. A new session id is issued to prevent session fixation. */
function login_user(array $user): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => (int)$user['id'], 'name' => $user['name'], 'role' => $user['role']];
    $_SESSION['last_activity'] = time();
}

/** Ends the session and removes its cookie. */
function logout_user(): void
{
    start_session();
    $_SESSION = [];
    // If the page has already started its output the cookie cannot be removed, but the
    // session behind it is destroyed below, so the old cookie no longer logs anyone in.
    if (!headers_sent()) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'],
        ]);
    }
    session_destroy();
}
