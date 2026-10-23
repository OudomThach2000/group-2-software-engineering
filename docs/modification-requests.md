# Modification requests

The register of maintenance requests since Version 1 (Demo 1, 8 October 2026). The maintenance lead updates it at each triage and each merge (see `docs/maintenance-plan.md`). Estimates are the triage estimates; the assignee may change them in the impact analysis.

| # | Request | Category | Severity | Received | Decision | Release | Owner | Estimate | Actual | Status |
|---|---|---|---|---|---|---|---|---|---|---|
| 8 | Register page reveals which emails are registered | Preventive | Medium | 17 Oct | Accepted, rerouted to development: changing the message cannot close the leak; the fix needs email activation | v1.2 | Sethouday | 7 h | | Open |
| 9 | Report page shows a developer message when MySQL is down (the dashboard has the same kind of message) | Corrective | Medium | 17 Oct | Accepted | v1.1 | Oudom | 1 h | | Open |
| 10 | Login has no limit on failed attempts | Preventive | Medium | 23 Oct | Accepted, deferred: needs a new table, and should be tested together with #8 | v1.2 | Sethouday | 3 h | | Open |
| 11 | Forms have no anti forgery token, and logout works with a plain link | Preventive | Medium | 23 Oct | Accepted | v1.1 | Sethouday | 3 h | | Open |
| 12 | Retire `db/migrations/001_add_issue_priority.sql` | Perfective | Low | 23 Oct | Accepted once every member confirms a current database | v1.1 | Pichponleur | 0.5 h | | Open |
| 13 | Correct the Owner headers of `notify.php`, `notify_test.php`, `run_tests.php` and `send_notifications.php` | Perfective | Low | 23 Oct | Accepted | v1.1 | Oudom | 0.5 h | | Open |
