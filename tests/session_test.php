<?php
/**
 * UC6 sessions: unit test for the session functions in app/includes/auth.php
 * (start_session, login_user, current_user, logout_user)  (FR7, NFR3).
 *
 * Checks the session cookie settings, that logging in issues a new session id (no session
 * fixation), both sides of the 30 minute idle timeout, and that logging out ends the
 * session so the old id no longer logs anyone in. No database is needed: these functions
 * only use the PHP session.
 *
 * Run from the project root:
 *     php tests/session_test.php
 * Exit code 0 = every check passed, 1 = at least one failed.
 *
 * Added by Pichponleur Pen on 23 October 2026 to complete the v5 test plan.
 */
require_once __DIR__ . '/../app/includes/auth.php';

// On a real page the session starts before any output, so the PASS and FAIL lines are
// held back and printed at the end. The session files go to the system temp folder,
// which is always writable.
ob_start();
session_save_path(sys_get_temp_dir());

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

$user = ['id' => 7, 'name' => 'Test Worker', 'role' => 'staff'];

// 1. The session cookie and settings.
start_session();
check('start_session() starts a session', PHP_SESSION_ACTIVE, session_status());
$cookie = session_get_cookie_params();
check('the cookie cannot be read by scripts (HttpOnly)', true, $cookie['httponly']);
check('the cookie is not sent with requests from other sites (SameSite=Lax)', 'Lax', $cookie['samesite']);
check('the cookie ends when the browser closes', 0, $cookie['lifetime']);
check('only session ids made by the server are accepted (strict mode)', '1', ini_get('session.use_strict_mode'));
check('nobody is logged in yet', null, current_user());
session_destroy();

$_SERVER['HTTPS'] = 'on';
start_session();
check('on HTTPS the cookie is sent over HTTPS only (Secure)', true, session_get_cookie_params()['secure']);
session_destroy();
unset($_SERVER['HTTPS']);

// 2. Session fixation: an id planted by an attacker is never used for a logged in session.
$planted = 'planted0by0an0attacker0123456789';
session_id($planted);
start_session();
check('a planted session id is replaced when the session starts', true, session_id() !== $planted);
$beforeLogin = session_id();
login_user($user);
check('logging in issues a new session id', true, session_id() !== $beforeLogin);
check('the logged in user is returned', $user, current_user());

// 3. The 30 minute idle timeout, from both sides. The 30 minutes come from our requirement,
//    not from the constant in auth.php, so a changed constant is caught too.
$thirtyMinutes = 30 * 60;
$_SESSION['last_activity'] = time() - ($thirtyMinutes - 1);   // idle 29 min 59 s
check('a user idle for 29 minutes 59 seconds is still logged in', $user, current_user());
check('...and the activity time moves forward', true, $_SESSION['last_activity'] >= time() - 1);
$_SESSION['last_activity'] = time() - ($thirtyMinutes + 1);   // idle 30 min 1 s
check('a user idle for 30 minutes 1 second is logged out', null, current_user());
check('...and the session data is cleared', [], $_SESSION);

// 4. Logging out ends the session, and the old id no longer logs anyone in.
login_user($user);
$oldId = session_id();
logout_user();
check('logging out ends the session', PHP_SESSION_NONE, session_status());
check('...and clears the session data', [], $_SESSION);
session_id($oldId);   // the browser sends the old cookie again
start_session();
check('the old session id is not accepted again', true, session_id() !== $oldId);
check('...so it no longer logs anyone in', null, current_user());
session_destroy();   // leave no session file behind

ob_end_flush();
if ($failures > 0) {
    echo "\n$failures check(s) failed.\n";
    exit(1);
}
echo "\nAll checks passed.\n";
