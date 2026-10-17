# Test log

Each entry records when a test was run, who ran it, which commit was tested and the result (SWEBOK Chapter 4, 5.2.6).

| Date | Tester | Commit | What was run | Result |
|---|---|---|---|---|
| 2026-10-17 | Pichponleur Pen | 2f84e0e | php tests/run_tests.php | 5 scripts, 114 checks executed, 114 passed, 0 failed |
| 2026-10-17 | Pichponleur Pen | 2f84e0e | mutation run on app/src/dashboard.php | 11 mutants, 9 killed, M6 and M10 survived |
| 2026-10-17 | Pichponleur Pen | f0ba138 | php tests/run_tests.php after the new checks | 5 scripts, 116 checks, 116 passed, 0 failed |
| 2026-10-17 | Pichponleur Pen | f0ba138 | mutation rerun | 11 mutants, 11 killed |
| 2026-10-17 | Pichponleur Pen | f0ba138 | back to back: dashboard against independent SQL | open, status, category and average all match |
| 2026-10-17 | Pichponleur Pen | f0ba138 | recovery: MySQL stopped and restarted | no data lost; misleading message on submit.php ([#9](https://github.com/OudomThach2000/group-2-software-engineering/issues/9)) |

Defects found and logged in GitHub Issues: #6 sample data (closed), #7 priority column (closed), #8 register page reveals registered emails (open), #9 misleading message when MySQL is down (open).