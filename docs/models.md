# Models of the Community Issue Reporting System

This file lists the models we use, what each one is for and how we check it (proposal Section 13, SWEBOK Chapter 9). No single model describes the whole system. Each one shows one view, and together they make up our model of the system. Owner: Pichponleur Pen.

## 1. Model inventory

| Model | Type and view | Notation | Where it is | How it is checked |
|---|---|---|---|---|
| Glossary of terms | Information: shared vocabulary | Text | Proposal Section 2 | Review |
| Use case diagram | Behavior: functional view | UML use case diagram | Proposal Figure 4.1 | Review; use case matrix (4.3.3) |
| Use case to requirement matrix | Traceability | Table | Proposal 4.3.3 | Review |
| Fully dressed use case UC1 | Behavior, with preconditions and postconditions | Structured text | Proposal 4.3.4 | Acceptance test AT1 (9.2) |
| System sequence diagram for UC1 | Interaction with the system | UML sequence diagram | Proposal Figure 4.2 | Review |
| Sequence of one UC1 request | Interaction inside the system | Mermaid sequence diagram | README, "Sequence — UC1 Submit Issue" | GitHub draws it (syntax); tests/issues_test.php |
| Class diagram | Structure; conceptual information model | UML class diagram (draw.io) | Proposal Figure 7.9; docs/class-diagram.png | Review; tests/model_test.php compares the statuses with the schema |
| Enumerations | Information: logical model | Table | Proposal 7.6 | tests/model_test.php (status values) |
| Database schema | Information: physical model | SQL | db/schema.sql | MySQL on import; tests/model_test.php (invariants) |
| Issue lifecycle | Behavior: state machine | UML state diagram and transition table | Proposal Figure 7.10 and 7.6 | tests/model_test.php (reachability, model against code) |
| Lifecycle states | Behavior: state machine | Mermaid state diagram | README, "Lifecycle states" | GitHub draws it (syntax); tests/model_test.php |
| State transition test table | Behavior: test model | Table | Proposal 9.3 | tests/issues_test.php; tests/model_test.php part 3 |
| Lifecycle flowchart | Behavior: control flow | Mermaid flowchart | README | GitHub draws it (syntax) |
| Control flow graph of attempt_login() | Behavior: control flow | Graph | Proposal Figure 9.1 | tests/auth_test.php (one check per path) |
| Screen navigation map | Interaction with the user | Diagram | Proposal Figure 7.2 | Review |
| Screen wireframes | User interface prototype | Wireframes | Proposal Figures 7.3 to 7.8 | Review; acceptance tests AT1 to AT7 (9.2) |
| Architecture | Structure: logical, development and physical views | Diagram | Proposal 7.3 | Review |
| Change process | Process: activity | UML activity diagram written in Graphviz DOT | Proposal Figure 12.1; docs/change-process.dot | Graphviz draws it (syntax); review |

## 2. Preconditions, postconditions and invariants of the issue lifecycle

The functions are in app/src/issues.php. When a precondition does not hold, the function changes nothing: it returns false, or it throws InvalidArgumentException for values the page should have rejected.

| Operation | Preconditions | Postconditions |
|---|---|---|
| submit_issue() (UC1) | The input has passed validate_submission() (FR16). | A new issue in New with a unique tracking reference, and one history entry (no status to New). The reference is returned only after the issue is saved (NFR4). |
| assign_issue() (UC3) | The issue is New. The priority is one of PRIORITIES. | The issue is Assigned, with its category and priority set, one assignment and one history entry (New to Assigned). |
| update_issue_status() (UC4) | The issue is Assigned (to start) or In-progress (to resolve). The caller holds the latest assignment. A note is given to resolve. | The issue has moved one step, resolved_at is set when it becomes Resolved, and there is one more history entry. |
| close_issue() (FR12) | The issue is Resolved. | The issue is Closed, with one history entry (Resolved to Closed). |

Invariants: these rules hold for every issue before and after every operation. tests/model_test.php checks them for every issue in the database.

1. The history of an issue starts with its submission (no status to New).
2. The latest history entry matches the current status.
3. Every history entry is a transition of the lifecycle model.
4. resolved_at is set exactly when the issue is Resolved or Closed.
5. An issue has an assignment exactly when it has left New.
6. A priority is set exactly when the issue has left New.
7. Every tracking reference has the form CIR-YYYY-NNNN (and the schema keeps it unique).

## 3. Known gaps

- Reopen: Figure 7.10 has a reopen step (Resolved back to Assigned), but the requirements, the code, the README state diagram and the 9.3 table do not. It is planned for v1.2. Until then, the model checked by tests/model_test.php leaves it out.
- Reassignment: the class diagram allows several assignments per issue, but assign_issue() only assigns a New issue, so today each issue has at most one assignment.
- The use case matrix (4.3.3) covers FR1 to FR7. FR8 to FR17 are not in any use case.
- FR10 (duplicates) and FR11 (rejection) are not part of the lifecycle. FR11 would need one more state in the schema and in Figure 7.10.
- We have no data flow diagram.
- Before this change, the four sample issues in db/seed.sql broke invariants 1, 2, 5 and 6: they had no history, and the three that had left New had no assignment and no priority. So CIR-2026-0002 was in no worker's queue (FR13), and tracking showed no history for the samples (FR2). The seed now follows the lifecycle.

## 4. Keeping the models consistent

A change to the lifecycle must update, in the same pull request, Figure 7.10 and its table in the proposal, the README state diagram, MODEL_TRANSITIONS in tests/model_test.php and this file. The reviewer checks this as part of the change process (docs/process.md).
