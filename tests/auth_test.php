<?php
/**
 * UC6 Register / Log in — unit test for the login module  (FR7, NFR2, NFR3).  Owner: Sethouday Prum.
 *
 * Checks validate_registration(), register_user(), attempt_login() and home_for_role()
 * from app/includes/auth.php: the registration rules (including a Khmer name), that only
 * a password hash is stored, that login ignores the letter case of the email, that wrong
 * passwords and unknown emails are refused, and that each role is sent to the right page.
 *
 * Run from the project root, after importing db/schema.sql and db/seed.sql:
 *     php tests/auth_test.php
 * Everything runs inside one transaction that is rolled back at the end, so the
 * database is left as it was.
 * Exit code 0 = every check passed, 1 = at least one failed.
 */
require_once __DIR__ . '/../app/includes/auth.php';

$failures = 0;

/** Print PASS or FAIL for one check, counting the failures. */
function check(string $description, $expected, $actual): void
{
    global $failures;
    if ($expected === $actual) {
        echo "PASS  $description\n";
        return;
    }
    $failures++;
    echo "FAIL  $description\n"
       . '      expected: ' . var_export($expected, true) . "\n"
       . '      actual:   ' . var_export($actual, true) . "\n";
}

/** PASS when $action is refused with an InvalidArgumentException. */
function check_rejected(string $description, callable $action): void
{
    global $failures;
    try {
        $action();
    } catch (InvalidArgumentException $ex) {
        echo "PASS  $description\n";
        return;
    }
    $failures++;
    echo "FAIL  $description\n      expected an InvalidArgumentException, none was thrown\n";
}

$valid = ['name' => 'Sokha Chan', 'email' => 'sokha@auth-test.example', 'password' => 'correct horse'];

// 1. Registration rules, no database needed.
check('a complete form is valid', [], validate_registration($valid));
check('a Khmer name is accepted', [], validate_registration(['name' => 'សុខា ចាន់'] + $valid));
check('a name of 120 Khmer characters fits', [],
    validate_registration(['name' => str_repeat('ក', MAX_NAME_LENGTH)] + $valid));
check('a name that is too long is refused', ['name'],
    array_keys(validate_registration(['name' => str_repeat('ក', MAX_NAME_LENGTH + 1)] + $valid)));
check('a blank name is refused', ['name'], array_keys(validate_registration(['name' => '   '] + $valid)));
check('an invalid email is refused', ['email'], array_keys(validate_registration(['email' => 'not-an-email'] + $valid)));
check('a short password is refused', ['password'],
    array_keys(validate_registration(['password' => str_repeat('x', MIN_PASSWORD_LENGTH - 1)] + $valid)));
check('an empty form gets a message for every field', ['name', 'email', 'password'],
    array_keys(validate_registration([])));

// 2. Where each role lands after logging in.
check('a supervisor goes to triage', '/supervisor/triage.php', home_for_role('supervisor'));
check('field staff go to their queue', '/staff/issues.php', home_for_role('staff'));
check('a resident goes to the tracking page', '/track.php', home_for_role('resident'));
check('an unknown role goes home', '/index.php', home_for_role('visitor'));

$pdo = db();
$pdo->beginTransaction();
try {
    // 3. Registering stores the account with a hash only.
    $khmer  = ['name' => 'សុខា ចាន់', 'email' => '  Sokha@Auth-Test.Example ', 'password' => 'correct horse'];
    $userId = register_user($pdo, $khmer);
    check('registering returns the new account id', true, is_int($userId) && $userId > 0);

    $stmt = $pdo->prepare('SELECT name, email, password_hash, role FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    check('the Khmer name is stored unchanged', 'សុខា ចាន់', $row['name']);
    check('the email is stored trimmed and in lower case', 'sokha@auth-test.example', $row['email']);
    check('a new account is a resident', 'resident', $row['role']);
    check('the password is not stored as plain text', false, strpos($row['password_hash'], 'correct horse') !== false);
    check('the stored hash matches the password', true, password_verify('correct horse', $row['password_hash']));

    check('the same email cannot register twice', null, register_user($pdo, $valid));
    check('...not even in another letter case', null,
        register_user($pdo, ['email' => 'SOKHA@auth-test.example'] + $valid));
    check_rejected('refuses input that failed validation', fn() => register_user($pdo, ['password' => 'short'] + $valid));

    // 4. Logging in.
    $expected = ['id' => $userId, 'name' => 'សុខា ចាន់', 'role' => 'resident'];
    check('the right email and password log in', $expected, attempt_login($pdo, 'sokha@auth-test.example', 'correct horse'));
    check('login works whatever the letter case of the email', $expected,
        attempt_login($pdo, '  SOKHA@Auth-Test.EXAMPLE', 'correct horse'));
    check('a wrong password is refused', null, attempt_login($pdo, 'sokha@auth-test.example', 'Correct horse'));
    check('an unknown email is refused', null, attempt_login($pdo, 'nobody@auth-test.example', 'correct horse'));

    // 5. A hash made with older settings is upgraded at the next login.
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash('correct horse', PASSWORD_BCRYPT, ['cost' => 4]), $userId]);
    attempt_login($pdo, 'sokha@auth-test.example', 'correct horse');
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();
    check('an outdated hash is upgraded at login', false, password_needs_rehash($hash, PASSWORD_DEFAULT));
    check('...and still matches the password', true, password_verify('correct horse', $hash));

    // 6. Each role, logged in for real, is sent to the right page.
    foreach (['supervisor', 'staff'] as $role) {
        $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')
            ->execute(["Test $role", "$role@auth-test.example", password_hash('role password', PASSWORD_DEFAULT), $role]);
        $user = attempt_login($pdo, "$role@auth-test.example", 'role password');
        check("a logged-in $role is sent to " . ROLE_HOME[$role], ROLE_HOME[$role], home_for_role($user['role'] ?? ''));
    }
} finally {
    $pdo->rollBack();
}

if ($failures > 0) {
    echo "\n$failures check(s) failed.\n";
    exit(1);
}
echo "\nAll checks passed.\n";
