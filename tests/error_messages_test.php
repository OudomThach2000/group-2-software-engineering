<?php
/**
 * #9: what users see when something fails, for example when MySQL is down (NFR3).
 *
 * 1. No page shows a developer message. No string in the pages under app/public mentions
 *    the README or an .sql file, as the report page did before #9 was fixed. Comments are
 *    not checked, only the text a page can show.
 * 2. handle_uncaught_exception() in app/includes/db.php shows the short message, with status
 *    503 for a database error and 500 for any other error, writes the details to the error
 *    log, and never shows them on the page.
 *
 * No database is needed. Run from the project root:
 *     php tests/error_messages_test.php
 * Exit code 0 = every check passed, 1 = at least one failed.
 *
 * Added by Pichponleur Pen on 24 October 2026 for #9.
 */
require_once __DIR__ . '/../app/includes/db.php';

// As on a real page, the status is set before any output, so the PASS and FAIL lines are
// held back and printed at the end.
ob_start();
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

// 1. The text each page can show: strings and HTML, not comments.
const DEVELOPER_HINTS = ['README', '.sql'];
$root  = realpath(__DIR__ . '/..');
$pages = array_merge(glob("$root/app/public/*.php"), glob("$root/app/public/*/*.php"));
sort($pages);
check('the pages are found', true, count($pages) >= 9);
foreach ($pages as $page) {
    $hints = [];
    foreach (token_get_all(file_get_contents($page)) as $token) {
        if (is_array($token) && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML], true)) {
            foreach (DEVELOPER_HINTS as $hint) {
                if (stripos($token[1], $hint) !== false) {
                    $hints[] = trim($token[1]);
                }
            }
        }
    }
    check(substr($page, strlen($root) + 1) . ' shows no developer message', [], $hints);
}

// 2. The handler for errors that no page caught. The error log goes to a temporary file.
$hints = array_values(array_filter(DEVELOPER_HINTS, fn($hint) => stripos(UNAVAILABLE_MESSAGE, $hint) !== false));
check('the message itself has no developer hint', [], $hints);
$log = tempnam(sys_get_temp_dir(), 'cir');
ini_set('error_log', $log);

ob_start();
handle_uncaught_exception(new PDOException('SQLSTATE[HY000] [2002] Connection refused'));
$page = ob_get_clean();
check('a database error shows the short message', true, str_contains($page, UNAVAILABLE_MESSAGE));
check('...with status 503, try again later', 503, http_response_code());
check('...without the error details', false, str_contains($page, 'SQLSTATE') || str_contains($page, 'refused'));
check('...without the file where it happened', false, str_contains($page, basename(__FILE__)));
check('...and the details go to the error log', true, str_contains((string)file_get_contents($log), 'Connection refused'));

ob_start();
handle_uncaught_exception(new RuntimeException('a fault in the page code'));
$page = ob_get_clean();
check('any other error shows the same message', true, str_contains($page, UNAVAILABLE_MESSAGE));
check('...with status 500', 500, http_response_code());
check('...without the error details', false, str_contains($page, 'fault in the page code'));
unlink($log);

ob_end_flush();
if ($failures > 0) {
    echo "\n$failures check(s) failed.\n";
    exit(1);
}
echo "\nAll checks passed.\n";
