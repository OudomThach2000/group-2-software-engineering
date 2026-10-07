<?php
/**
 * Runs every test script in this folder  (Section 8.4, testing).  Owner: Rolando Labrador.
 *
 * Each *_test.php runs in its own PHP process, so one script's helper functions or a
 * fatal error cannot affect another. Run from the project root:
 *     php tests/run_tests.php
 * Exit code 0 = every script passed, 1 = at least one failed.
 */
$scripts = glob(__DIR__ . '/*_test.php');
sort($scripts);

$failed = [];
foreach ($scripts as $script) {
    $name = basename($script);
    echo "=== $name\n";
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script), $exitCode);
    if ($exitCode !== 0) {
        $failed[] = $name;
    }
    echo "\n";
}

$total = count($scripts);
if ($failed) {
    echo count($failed) . " of $total test script(s) failed: " . implode(', ', $failed) . "\n";
    exit(1);
}
echo "All $total test script(s) passed.\n";
