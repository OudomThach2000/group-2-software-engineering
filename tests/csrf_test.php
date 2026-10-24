<?php
/**
 * #11: the anti forgery token in app/includes/auth.php (csrf_token, csrf_field,
 * csrf_token_is_valid, reject_forged_post) and its use on every page  (NFR3).
 *
 * 1. The token: long and random, the same for the whole session, new after logging in,
 *    and only this session's token is accepted.
 * 2. reject_forged_post() lets GET requests and a POST with the token through, and stops
 *    a POST without it before the page can act (checked in a separate PHP process).
 * 3. Every POST form in the pages and the shared header carries the token, every page that
 *    handles a POST calls reject_forged_post(), and logging out needs a POST.
 *
 * No database is needed. Run from the project root:
 *     php tests/csrf_test.php
 * Exit code 0 = every check passed, 1 = at least one failed.
 *
 * Added by Pichponleur Pen on 24 October 2026 for #11.
 */
require_once __DIR__ . '/../app/includes/auth.php';

// On a real page the session starts before any output, so the PASS and FAIL lines are
// held back and printed at the end. The session files go to the system temp folder.
ob_start();
session_save_path(sys_get_temp_dir());

$failures = 0;
$finished = false;

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

// If reject_forged_post() stopped this script by mistake, say so and fail.
register_shutdown_function(function () {
    global $finished;
    if (!$finished) {
        echo "FAIL  the test stopped early: a fatal error, or reject_forged_post() ended a request it should let through\n";
        exit(1);
    }
});

$user = ['id' => 7, 'name' => 'Test Worker', 'role' => 'staff'];

// 1. The token.
start_session();
$token = csrf_token();
check('the token is 64 hexadecimal characters (32 random bytes)', 1, preg_match('/^[0-9a-f]{64}$/', $token));
check('the same token is used for the whole session', $token, csrf_token());
check('the form field is hidden and named csrf_token',
      '<input type="hidden" name="csrf_token" value="' . $token . '">', csrf_field());
check('the session token is accepted', true, csrf_token_is_valid($token));
check('a token with one character changed is refused', false,
      csrf_token_is_valid(substr($token, 0, -1) . ($token[63] === 'a' ? 'b' : 'a')));
check('an empty token is refused', false, csrf_token_is_valid(''));
check('a missing token is refused', false, csrf_token_is_valid(null));
check('a token sent as a list is refused', false, csrf_token_is_valid([$token]));

unset($_SESSION['csrf_token']);
check('a session without a token accepts nothing, not even an empty token', false, csrf_token_is_valid(''));
session_destroy();

start_session();
check("another session's token is refused", false, csrf_token_is_valid($token));
check('another session gets a different token', true, csrf_token() !== $token);

$beforeLogin = csrf_token();
login_user($user);
check('after logging in, the token from before is refused', false, csrf_token_is_valid($beforeLogin));
check('...and a new token is made', true, csrf_token() !== $beforeLogin);

$loggedIn = csrf_token();
logout_user();
start_session();
check('after logging out, the old token is refused', false, csrf_token_is_valid($loggedIn));
session_destroy();

// 2. reject_forged_post(). A request it lets through simply continues.
start_session();
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = [];
reject_forged_post();
check('a GET request passes', true, true);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [CSRF_FIELD => csrf_token()];
reject_forged_post();
check('a POST with the session token passes', true, true);
session_destroy();

// A forged POST must stop the request, so it runs in its own PHP process.
$code = 'session_save_path(sys_get_temp_dir()); require ' . var_export(realpath(__DIR__ . '/../app/includes/auth.php'), true) . ';'
      . ' $_SERVER["REQUEST_METHOD"] = "POST"; $_POST = ["csrf_token" => "forged", "action" => "close"];'
      . ' reject_forged_post(); echo "THE PAGE WENT ON";';
$output = (string)shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>&1');
check('a POST with a wrong token is stopped before the page acts', false, str_contains($output, 'THE PAGE WENT ON'));
check('...and the user is told to reload the page', true, str_contains($output, 'reload the page'));

// 3. The pages. Every POST form carries the token, and every page that reads a POST
//    calls reject_forged_post() first.
$root  = realpath(__DIR__ . '/..');
$files = array_merge(glob("$root/app/public/*.php"), glob("$root/app/public/*/*.php"), ["$root/app/includes/header.php"]);
sort($files);
$forms = 0;
foreach ($files as $file) {
    $source = file_get_contents($file);
    $name   = substr($file, strlen($root) + 1);
    $posts  = preg_match_all('/<form\b[^>]*\bmethod="post"/i', $source);
    $forms += $posts;
    if ($posts > 0) {
        check("$name: each of its $posts POST form(s) carries the token", $posts, substr_count($source, 'csrf_field()'));
    }
    if (str_contains($source, "\$_SERVER['REQUEST_METHOD']") && !str_contains($file, '/includes/')) {
        check("$name: checks the token before it reads a POST", true, str_contains($source, 'reject_forged_post();'));
    }
}
check('all seven POST forms were found (login, register, submit, assign, close, update, log out)', 7, $forms);

$logout = file_get_contents("$root/app/public/logout.php");
check('logout.php logs out only on a POST', 1,
      preg_match("/if \(\\\$_SERVER\['REQUEST_METHOD'\] === 'POST'\) \{\s*reject_forged_post\(\);\s*logout_user\(\);/", $logout));
check('the header has no plain logout link any more', false,
      str_contains(file_get_contents("$root/app/includes/header.php"), 'href="/logout.php"'));

$finished = true;
ob_end_flush();
if ($failures > 0) {
    echo "\n$failures check(s) failed.\n";
    exit(1);
}
echo "\nAll checks passed.\n";
