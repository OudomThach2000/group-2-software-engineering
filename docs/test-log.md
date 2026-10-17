# Test log

Each entry records when a test was run, who ran it, which commit was tested and the result (SWEBOK Chapter 4, 5.2.6).

| Date | Tester | Commit | What was run | Result |
|---|---|---|---|---|
| 2026-10-17 | Pichponleur Pen | 2f84e0e | php tests/run_tests.php | 5 scripts, 114 checks executed, 114 passed, 0 failed |
| 2026-10-17 | Pichponleur Pen | 2f84e0e | mutation run on app/src/dashboard.php | 11 mutants, 9 killed, M6 and M10 survived |
| 2026-10-17 | Pichponleur Pen | [new] | php tests/run_tests.php after the new checks | 5 scripts, 116 checks, 116 passed, 0 failed |
| 2026-10-17 | Pichponleur Pen | [new] | mutation rerun | 11 mutants, 11 killed |
| 2026-10-17 | Pichponleur Pen | [new] | back to back: dashboard against independent SQL | open, status, category and average all match |
| 2026-10-17 | Pichponleur Pen | [new] | recovery: MySQL stopped and restarted | no data lost; misleading message on submit.php (Issue 4) |