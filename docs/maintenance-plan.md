# Maintenance plan

Community Issue Reporting System, Group 2, CSCI 841. This plan applies IEEE 14764 as summarised in SWEBOK Chapter 5 (Software Maintenance). Maintenance lead: Pichponleur Pen. First version: 23 October 2026.

## 1. Maintenance concept

- **Scope.** We maintain the code in `app/`, the database files in `db/`, the tests in `tests/`, the tools in `tools/`, the documents in `docs/` and the README.
- **Baseline.** Version 1 was delivered at Demo 1 on 8 October 2026 (commit 2f84e0e, tag `Version.1`). Every change after that date is maintenance and follows this plan.
- **Organization.** The team is the maintainer. Each module keeps its owner as first maintainer (see the README), the maintenance lead triages new requests and plans releases, and any member may take a request after reading the module and its tests. A second member reviews every change.
- **Cost.** Each request is estimated in hours (section 7). A release is small: about one weekend of team work.

## 2. How to ask for a change (help desk)

- Open a GitHub Issue with the **Problem report** template when something does not work, or the **Modification request** template for a change or an improvement.
- Residents and staff without a GitHub account tell the supervisor, who opens the issue for them in English or Khmer.
- The maintenance lead answers every new issue within the first response time in section 3.

## 3. Categories and severity

| Category | Meaning (SWEBOK 1.6) | Our example |
|---|---|---|
| Corrective | repairs a fault found after delivery; an emergency fix keeps the system running until the real fix | #9, a developer message shown when MySQL is down |
| Adaptive | keeps the system usable when its environment changes | the move from XAMPP to a hosted server (section 9) |
| Perfective | improves documentation, performance or maintainability | the tracking lookup moved to `app/src/tracking.php` (266d326) |
| Preventive | finds and removes latent faults before they cause failures | #11, forms without a token; the tests added on 23 October |

| Severity | Meaning | First response | Target |
|---|---|---|---|
| Critical | system down, data lost, or a security hole being used | same day | emergency fix within 2 days, then a normal fix |
| High | a use case fails for many users | 1 day | next release |
| Medium | a use case works with a workaround, or a weakness not yet used | 3 days | next or second release |
| Low | wording, layout or small improvements | 1 week | when capacity allows |

## 4. Process for each request (IEEE 14764)

1. **Problem and modification analysis.** The lead sets the category and severity and decides: accepted for a release, deferred, or rejected. A request larger than about one day of work, or one that changes the requirements, is rerouted to development as an enhancement for a later version. The assignee then reproduces the problem and comments an impact analysis on the issue: the files, functions, tests and documents affected; the options; the chosen option; the risk; and an estimate in hours.
2. **Modification implementation.** Branch from `main` as `fix/<issue>-short-name` (corrective) or `change/<issue>-short-name` (other categories). Change the code, add or update checks, and update the mutation list of each changed file if one of its lines no longer applies.
3. **Review and acceptance.** Run `php tests/run_tests.php` and the mutation lists of the changed files. Open a pull request with "Fixes #<issue>"; the template asks for the category, the checks and the actual hours. A second member reviews, and the change is merged only when every check passes. Add a line to `docs/test-log.md`.
4. **Migration** (section 9) and **retirement** (section 10) follow their own plans.

## 5. Release plan

| Release | Content | Target | Back out plan |
|---|---|---|---|
| Version.1 | the system shown at Demo 1 | delivered 8 October | baseline |
| v1.1 | #9 (corrective), #11 (preventive), #12 retire migration 001, #13 owner headers | 25 October | revert the merge commits; no data changes |
| v1.2 | #8 email activation, #10 limit on login attempts | before Demo 2 | revert, and run the reverse script of the schema change |

For each release the lead lists the requests, agrees the content with the team (our configuration control board is the team in the group chat and the pull request reviews), checks for conflicts between requests, writes the back out plan, and tells everyone when the tag is pushed.

## 6. Configuration management

- `main` always passes all tests. Work happens on branches and reaches `main` through reviewed pull requests.
- Each release is a Git tag (`Version.1`, `v1.1`, and so on). A tag is never moved.
- Commit subjects start with the use case or area (`UC2:`, `Tests:`, `Docs:`, `Schema:`), and a corrective commit names its issue.
- A schema change is a new file in `db/migrations` plus the same change in `schema.sql`, so old and new databases both keep working.
- `app/config.php` is never committed.

## 7. Cost estimation

- **Experience.** The assignee estimates the hours in the impact analysis and records the actual hours in the pull request.
- **Parametric model.** The lead also estimates a release from its change traffic (the share of application lines added or changed) times the development effort in Section 6.2 of the proposal.
- The actual hours of each release are used to check and calibrate both estimates.

## 8. Quality and measurement

Measured at each release and reported in the proposal (10.2.4):

- requests by category and by severity;
- size in non blank lines, and cyclomatic complexity per function (`php tools/complexity.php` with the files to measure);
- analysability: hours to reproduce a problem and write the impact analysis;
- changeability: hours, files and lines needed for a change;
- stability: checks or mutants that fail after a change;
- testability: time to run the tests, line coverage and mutation score.

## 9. Migration plan: XAMPP to a hosted server

- **Requirements.** PHP 8.2 or later with PDO MySQL, MySQL 8 or MariaDB 10.4 or later, and HTTPS.
- **Notification of intent.** One week before, tell users in the group chat and on the home page why the move happens, the new address and the date.
- **Conversion.** Export the database with `mysqldump`, import it on the server, copy `app/` without `app/config.php`, and write a new config with the server credentials.
- **Verification.** Run `php tests/run_tests.php` against a copy of the data, then AT1 to AT7 in a browser.
- **Parallel operation.** Keep the XAMPP copy readable for one week, while the server is the only place where new reports are saved.
- **Completion and review.** Announce that the move is complete, then hold a post operation review of the problems found in the first week.
- **Data archival.** Keep the last dump of the old database for one semester.

## 10. Retirement

- **In v1.1: `db/migrations/001_add_issue_priority.sql`.** It was needed only for databases created before 7 October; `schema.sql` has had the column since dedcc42. Before removing it we ask every member to confirm that their database was imported from the current `schema.sql`. The file stays in the history and can be restored with `git show Version.1:db/migrations/001_add_issue_priority.sql`.
- **Retiring the whole system.** If the system is ever replaced, export all issues and their history to CSV, hand the export to the municipality, tell residents who still have open reports, and keep the last database dump for one year.

## 11. Training

A member who takes a request in a module they did not write first reads the README, this plan, the module and its tests. The tests show what the module must do, which shortens program comprehension.
