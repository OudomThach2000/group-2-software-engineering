# Software quality assurance plan

Community Issue Reporting System, Group 2, CSCI 841. This plan applies SWEBOK Chapter 10 (Software Quality), and its sections follow the contents of a quality assurance plan in IEEE 730. Quality lead: Pichponleur Pen. First version: 17 November 2026 (v1.2).

It works together with `docs/maintenance-plan.md` (how a change is made), `docs/process.md` (entry and exit criteria and counting rules) and Sections 9 (testing), 11 (configuration management), 12 (process) and 14 (software quality) of the proposal.

## 1. Purpose and scope

This plan says what quality means for our system, which activities give us confidence that the product and our process meet it, who does them, and which records show that they were done. It covers everything under configuration control: the code in `app/`, the database files in `db/`, the tests, the tools, the documents and the README. Quality assurance is more than testing (SWEBOK 10, 2.1): it also covers the plans, the reviews and audits, the measures and the way we correct problems. The plan is consistent with our SCM plan (Section 11 of the proposal and `docs/maintenance-plan.md`, section 6), and service after delivery (the help desk and the maintenance releases) follows `docs/maintenance-plan.md`.

## 2. Quality goals

Our quality requirements are the non functional requirements in Section 3 of the proposal. The table maps them to the product quality characteristics of ISO/IEC 25010 and gives a measure, a target and the way each one is checked.

| Characteristic (ISO/IEC 25010) | Our requirement | Measure and target | How and when it is checked |
|---|---|---|---|
| Functional suitability | FR1 to FR17, through UC1 to UC7 | AT1 to AT7 all pass; every unit and integration check passes | acceptance tests on a fresh database before each demo (9.2); the test scripts on every pull request |
| Reliability | NFR4: no report is lost | the rollback test passes; from Demo 2, failures per 1,000 operations of the operational profile (9.4) | part 8 of `tests/issues_test.php` in every run; the reliability run planned for Demo 2 |
| Security | NFR3: only authorised staff can triage or resolve | no open Critical or High security problem at a release; every security check passes and every mutant of `app/includes/auth.php` is killed | the auth, session and form token tests and their mutation lists in every run; the review; an exploratory session as each role before each demo (9.3) |
| Usability | NFR1 (basic smartphone) and NFR2 (Khmer and English) | a first time user submits a report in no more than 3 screens and under 2 minutes; the layout works from 320 px wide; text contrast of at least 4.5:1 (Section 3) | a think aloud test with three people on a phone, planned for Demo 2 (9.2) |
| Performance efficiency | NFR5: common pages load within a few seconds | under 3 seconds on a 3G connection (Section 3), with 500 sample issues | the timing test planned for Demo 2 (9.2) |
| Maintainability | analyzability, changeability, stability and testability (10.2.4) | every check passes; every mutant is killed; line coverage not lower than the last measurement (94.7% on 23 October); no new function with V(G) above 10 | the quality checks on every pull request; coverage with PCOV at each version |
| Portability (installability) | the move from XAMPP to a hosted server (`docs/maintenance-plan.md`, section 9) | a fresh install from `db/schema.sql` and `db/seed.sql` works on a clean machine | every run of the quality checks installs the database from nothing on a clean Ubuntu machine |

Our process has quality goals too: every change reaches `main` through a pull request with an approving review (12.5), and the capability levels in Table 12.3 of the proposal should not fall.

## 3. Organization, roles and independence

| Role | Member | Quality tasks |
|---|---|---|
| Quality lead (also SCM lead) | Pichponleur | Keeps this plan, the test log and the dashboard (12.5); checks the measures at each proposal version |
| Change authority (repository owner) | Oudom | Keeps branch protection on `main` and makes the quality checks a required check; reviews and merges |
| Status and release manager | Sethouday | Status reports, labels on issues, release notes |
| Auditor | Rolando | The functional and physical audit of each release before its tag (11.5) |
| Author | every member | Writes the checks together with the code and runs them before opening a pull request |
| Reviewer | every member | Reviews another member's pull request with the checklist in the template |

**Independence.** SWEBOK asks quality assurance to be objective. We are four students with no separate quality group, so we get objectivity in three ways. First, the quality checks run on a clean machine with the same steps for every change, so nobody can skip them or run them differently. Second, a member who is not the author approves each pull request, and branch protection makes GitHub enforce this. Third, from v1.2 the auditor of a release is a member who did not make its changes. The v1.1 audit (#20) was not independent, which is why the third rule exists.

## 4. Standards, practices and conventions

- **Standards.** SWEBOK V3 as our body of knowledge; ISO/IEC 25010 for the quality characteristics; IEEE 730 for the contents of this plan; IEEE 14764 for maintenance (`docs/maintenance-plan.md`); IEEE 1028 for the kinds of reviews and audits; IEEE 1012 for integrity levels.
- **Code.** Every query that contains user input uses a prepared statement; user text is escaped before it is shown; every POST form carries the anti forgery token (#11); users never see a developer message (#9); passwords are stored only as hashes; each file has an owner header and each function a comment block.
- **Repository.** Branch names, commit subjects, the issue and pull request templates, and `app/config.php` kept out of the repository (`docs/maintenance-plan.md`, section 6).
- **How they are checked.** A convention that a tool can check is checked on every pull request (section 5); the reviewer checks the others.
- **Media.** The repository on GitHub is the master copy. `main` is protected, a tag never moves, and `app/config.php` and uploaded photos stay out of the repository.
- **Training.** A member who works on a module for the first time reads the README, the maintenance plan, this plan, the module and its tests (`docs/maintenance-plan.md`, section 11).

## 5. Reviews, audits and V&V activities

| Activity (SWEBOK 10, 2.2 and 2.3) | When | Who | Record |
|---|---|---|---|
| Quality checks: `app/config.php` not committed, `php -l`, every test script on a fresh database, every mutation list, a complexity report | every pull request to `main`, every push to `main`, and by hand on a release tag | GitHub Actions (`.github/workflows/quality.yml`) | the run, linked from the pull request |
| Technical review | every pull request | a member who is not the author | an Approved review on GitHub |
| Management review | the weekly meeting (6.2); the retrospective at the end of each iteration (6.4); the dashboard at each proposal version (12.5) | the team; the leader | the group chat and the proposal |
| Acceptance tests AT1 to AT7 (validation) | before each demo and each release | the team, on a fresh database | `docs/test-log.md` |
| Demo with the client (validation and walkthrough) | Demo 1 (8 October) and Demo 2 (week 16) | the team and the instructor, who acts for the client | the proposal |
| Exploratory sessions and the browser check of the role table | before each demo | a member, once in each role | `docs/test-log.md` and 9.3 |
| Functional and physical configuration audit | each release, before the tag | the auditor | one issue per release (#20 for v1.1) |
| Process assurance | each proposal version | the quality lead | the dashboard (12.5), counted with the rules in `docs/process.md` |
| Inspection | not used | | A formal inspection needs a trained leader and a meeting with a reader and a recorder, which a part time team of four cannot staff. The checklist review is our nearest practice. |

The quality checks run the same commands that a member runs on XAMPP before a pull request (`docs/maintenance-plan.md`, section 4). They fail when any command fails, and the run summary shows the number of checks, the mutation score and the complexity. When they fail, the reviewer does not approve until a new commit makes them pass.

**Acceptance criteria.** A change is accepted when the quality checks pass (every check and every mutant) and a member who is not the author approves it (`docs/process.md`). A release is accepted when AT1 to AT7 pass on its tag, its audit is accepted, and no Critical or High problem is open.

## 6. Integrity levels

A part whose failure does more harm gets more verification and validation (IEEE 1012). Every level gets the quality checks and a review by a member who is not the author.

| Level | Parts | Why | Extra verification and validation |
|---|---|---|---|
| 3 (high) | `app/includes/auth.php` (login, roles, sessions and form tokens), `app/src/tracking.php` (the public lookup) and `app/includes/db.php` (the error handler) | A fault can let someone act as staff or show personal data (NFR3) | Everything for level 2, plus a negative check for every rule (wrong password, wrong role, missing token, data that must never be shown), a mutation list for each group of rules, and an exploratory session as each role before each demo |
| 2 (medium) | `app/src/issues.php`, `app/src/notify.php` and `scripts/send_notifications.php` | A fault can lose or corrupt a report or its history (NFR4) | Everything for level 1, plus a check for every action in every state (9.3), a rollback test for each change to data, and the acceptance tests |
| 1 (low) | `app/src/dashboard.php`, the page templates and the styles | A fault shows wrong figures or a wrong layout, but changes no data | Unit checks of the logic and a mutation list |

Today `app/includes/auth.php` has three mutation lists (30 mutants), `app/src/tracking.php` 13 mutants, `app/includes/db.php` 6, `app/src/issues.php` 9 and `app/src/dashboard.php` 11. `app/src/notify.php` has no mutation list yet, which is a gap.

## 7. Problem reporting and corrective action

1. Anyone who finds a problem opens a Problem report issue, or tells the supervisor, who opens it (`docs/maintenance-plan.md`, section 2).
2. The lead sets the category, severity, decision and release, and the assignee writes the impact analysis on the issue (`docs/maintenance-plan.md`, sections 3 and 4).
3. The fix starts with a check that fails because of the fault. The code change then makes it pass, so the same fault cannot come back unnoticed.
4. The pull request says "Fixes #n". The quality checks and the reviewer verify the fix, and the merge closes the issue.
5. The issue records the technique that found the problem and the process where it started (requirements, design, code or data). These feed the defect profile (Table 12.6) and a simple root cause analysis.
6. When problems of one type keep coming from the same process, the retrospective plans a change to that process (Plan, Do, Check, Act, Table 12.4). For example, 12.4 traces the security group (#8, #10 and #11) to the design of login and forms, so the next improvement belongs in the design review.

## 8. Measures

| Measure | Target | Source | Reported |
|---|---|---|---|
| Checks passing | all | the quality checks | every pull request; the test log at each release |
| Mutants killed | all, and every mutant still applies | the quality checks | every pull request; the test log at each release |
| Syntax errors | none | the quality checks | every pull request |
| Line coverage of `app/src` and `app/includes` | not lower than the last measurement | PCOV | each version (9.4) |
| Cyclomatic complexity | no new function above 10 | `tools/complexity.php`, in every run | each release (10.2.4) |
| Faults by type, by technique and by origin; fault density | followed as a trend | GitHub Issues | each version (9.4 and Table 12.6) |
| Open problems by severity | no Critical or High problem open at a release | GitHub Issues and the register | each release |
| Changes through a pull request with an approving review | all | `git log --first-parent main` and the GitHub reviews (`docs/process.md`) | each proposal version (12.5) |
| Time from request to close | followed as a trend | the register | each release |
| Acceptance tests passed | AT1 to AT7 | the test log | each demo |

We use a Pareto table and a run chart (12.4) to read these measures. Control charts need more data points than we have, so we compare against the targets above instead (12.5).

## 9. Tools

| Tool type (SWEBOK 10, 4) | Our tools |
|---|---|
| Static analysis | `php -l` (syntax), `tools/complexity.php` (cyclomatic complexity), and the parts of `tests/model_test.php` (pull request #24) that compare the lifecycle model with the code and the schema without running them |
| Testing and its automation | the test scripts and `tests/run_tests.php`; `tests/mutation/mutate.sh`; GitHub Actions runs them on every change |
| Review support | GitHub pull requests with the template checklist; branch protection on `main` |
| Problem tracking | GitHub Issues with the Problem report and Modification request templates; the register (`docs/modification-requests.md`) |
| Data analysis and visualization | the tables and charts of the proposal (Table 12.6, Figure 12.2) and the dashboard (12.5) |
| Safety hazard analysis (FMEA, FTA) | not used, because of the low safety criticality of the system (12.2) |

**Checking the tools.** A check that cannot fail gives no confidence. On 17 November 2026 we ran the steps of the quality checks on a copy of `main` with four planted faults, one at a time: a PHP file with a syntax error, a failing check, a mutant that survives, and a committed `app/config.php`. Each one made its step fail. The mutation script also refuses to start when a test already fails on the original code.

**Supplier software.** The quality checks use PHP 8.2 and MariaDB 10.11, close to XAMPP on our computers, and two published actions (`actions/checkout` and `shivammathur/setup-php`) pinned to a major version. We update them when GitHub warns that a version is old.

## 10. Cost of quality

| Category (SWEBOK 10, 1.2) | Our activities |
|---|---|
| Prevention | process improvement (Table 12.4); quality infrastructure and tools: this plan, the maintenance plan, `docs/process.md`, the templates, branch protection and the quality checks themselves; training; audits; management reviews |
| Appraisal | activities that find defects: pull request reviews, and testing (the test scripts and mutation lists in every run, the acceptance tests and the exploratory sessions), with the effort estimates in 9.5 |
| Internal failure | fixing faults that appraisal found before delivery, for example #6 and #7 before Version.1 |
| External failure | responding to problems found after delivery, for example #9 and #11 after Version.1, which needed the maintenance release v1.1 |

Our rule from 9.5 is to automate any check that we expect to rerun, because an automated check costs effort once and almost nothing afterwards. The quality checks follow this rule: building them was a prevention cost, and each run is an appraisal that costs a few minutes of machine time on GitHub instead of a member's time.

## 11. Records

| Record | Where | How long |
|---|---|---|
| Runs of the quality checks | GitHub Actions, linked from each pull request | GitHub keeps the logs for 90 days, so the test log records the run of each release |
| Test runs that count for a release | `docs/test-log.md` | in the repository |
| Reviews | the pull requests | on GitHub |
| Requests and problems | GitHub Issues and `docs/modification-requests.md` | on GitHub and in the repository |
| Audits | one issue per release (#20 for v1.1) | on GitHub |
| Measures | the proposal (9.4 and 12.5) | in the history of the Google Doc |

## 12. Known gaps

- The quality checks are not yet a required check in the branch protection rule of `main`; the change authority adds it after this plan is merged.
- `app/src/notify.php` has no mutation list.
- Line coverage is measured by hand with PCOV, not in the quality checks.
- The usability, performance and reliability targets are not measured yet; their tests are planned for Demo 2.
- The acceptance tests and the browser checks are run by hand; Playwright is planned for Version 2 (9.6).

## 13. Changes to this plan

This plan changes only through a pull request, like any other change. At each release the quality lead compares it with what the team really does and proposes changes.
