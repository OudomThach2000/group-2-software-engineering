<?php
/**
 * Model checks for the issue lifecycle and the data it leaves behind  (Section 13).  Owner: Pichponleur Pen.
 *
 * The lifecycle model (Figure 7.10, the README state diagram and docs/models.md) is written
 * below as data: a start state and four transitions. The reopen step in Figure 7.10 (Resolved
 * back to Assigned) is planned for v1.2 and is not in the code yet, so it is not part of the
 * model that is checked here.
 *
 *   1. Consistency: the states of the model are the same, in the same order, as ISSUE_STATUSES
 *      in app/src/dashboard.php and the status ENUMs in db/schema.sql, and both moves in
 *      WORKER_NEXT_STATUS are transitions of the model.
 *   2. Reachability: every state can be reached from New, Closed can be reached from every
 *      state, and Closed is the only state with no way out.
 *   3. Model against code: each of the four actions is tried in each of the five states (20 pairs),
 *      and the code must allow exactly the transitions of the model.
 *   4. Invariants: rules from docs/models.md that must hold for every issue in the database,
 *      including the 20 issues made in part 3.
 *
 * Run from the project root, after importing db/schema.sql and db/seed.sql:
 *     php tests/model_test.php
 * Parts 3 and 4 run inside one transaction that is rolled back at the end, so the database is
 * left as it was. Exit code 0 = every check passed, 1 = at least one failed.
 */
require_once __DIR__ . '/../app/includes/db.php';
require_once __DIR__ . '/../app/src/issues.php';
require_once __DIR__ . '/../app/src/dashboard.php';
require_once __DIR__ . '/../app/src/tracking.php';

/** Where every issue starts: submit_issue() saves it as New (UC1). */
const MODEL_START = 'New';

/** The transitions of the lifecycle model: action => [from, to]. */
const MODEL_TRANSITIONS = [
    'assign'  => ['New', 'Assigned'],          // assign_issue(), supervisor (UC3, FR3, FR8)
    'start'   => ['Assigned', 'In-progress'],  // update_issue_status(), the assigned worker (UC4, FR4)
    'resolve' => ['In-progress', 'Resolved'],  // update_issue_status() with a note (UC4, FR4)
    'close'   => ['Resolved', 'Closed'],       // close_issue(), supervisor (FR12)
];

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

/** The states of the model in lifecycle order, found by following the transitions from the start. */
function model_states(): array
{
    $states = [MODEL_START];
    for ($i = 0; $i < count($states); $i++) {
        foreach (MODEL_TRANSITIONS as [$from, $to]) {
            if ($from === $states[$i] && !in_array($to, $states, true)) {
                $states[] = $to;
            }
        }
    }
    return $states;
}

/** Every state that can be reached from $state, including $state itself. */
function reachable_from(string $state): array
{
    $seen = [$state];
    for ($i = 0; $i < count($seen); $i++) {
        foreach (MODEL_TRANSITIONS as [$from, $to]) {
            if ($from === $seen[$i] && !in_array($to, $seen, true)) {
                $seen[] = $to;
            }
        }
    }
    return $seen;
}

/** The values of an ENUM column, in the order the schema lists them. */
function enum_values(PDO $pdo, string $table, string $column): array
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    preg_match_all("/'([^']*)'/", (string)$stmt->fetchColumn(), $matches);
    return $matches[1];
}

/** Inserts a user with the given role and returns its id. */
function make_user(PDO $pdo, string $name, string $role): int
{
    $email = strtolower(str_replace(' ', '.', $name)) . '@model-test.example';
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)')
        ->execute([$name, $email, password_hash('not-used', PASSWORD_DEFAULT), $role]);
    return (int)$pdo->lastInsertId();
}

/** Calls the code for one action of the model. Returns true if the code made the change. */
function run_action(PDO $pdo, string $action, int $issueId, array $people, int $categoryId): bool
{
    try {
        switch ($action) {
            case 'assign':
                return assign_issue($pdo, $issueId, $people['staff'], $people['supervisor'], $categoryId, 'High');
            case 'start':
                return update_issue_status($pdo, $issueId, $people['staff'], 'In-progress');
            case 'resolve':
                return update_issue_status($pdo, $issueId, $people['staff'], 'Resolved', 'Fixed during the model test.');
            case 'close':
                return close_issue($pdo, $issueId, $people['supervisor']);
        }
    } catch (InvalidArgumentException $ex) {
        return false;
    }
    throw new LogicException("Unknown action $action");
}

/** Submits a new issue and moves it to $state through the transitions of the model. Returns its id. */
function issue_in_state(PDO $pdo, string $state, array $people, int $categoryId): int
{
    $ref = submit_issue($pdo, [
        'category_id'   => (string)$categoryId,
        'description'   => "Model test issue that should end in $state.",
        'location_text' => 'Model test street',
    ], null, null);
    $issueId = (int)$pdo->query('SELECT id FROM issues WHERE tracking_ref = ' . $pdo->quote($ref))->fetchColumn();

    $current = MODEL_START;
    while ($current !== $state) {
        foreach (MODEL_TRANSITIONS as $action => [$from, $to]) {
            if ($from === $current && in_array($state, reachable_from($to), true)) {
                if (!run_action($pdo, $action, $issueId, $people, $categoryId)) {
                    throw new RuntimeException("Could not $action issue $ref on the way to $state");
                }
                $current = $to;
                continue 2;
            }
        }
        throw new RuntimeException("The model has no path from New to $state");
    }
    return $issueId;
}

/** Tracking references returned by an invariant query, i.e. the issues that break the rule. */
function violations(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$pdo = db();
$states = model_states();

// 1. Consistency between the lifecycle model, the code and the physical data model.
check('the model has five states: New, Assigned, In-progress, Resolved, Closed',
    ['New', 'Assigned', 'In-progress', 'Resolved', 'Closed'], $states);
check('ISSUE_STATUSES in app/src/dashboard.php lists the same states in the same order',
    $states, ISSUE_STATUSES);
check('the ENUM of issues.status in db/schema.sql lists the same states in the same order',
    $states, enum_values($pdo, 'issues', 'status'));
check('the ENUM of status_history.old_status lists the same states in the same order',
    $states, enum_values($pdo, 'status_history', 'old_status'));
check('the ENUM of status_history.new_status lists the same states in the same order',
    $states, enum_values($pdo, 'status_history', 'new_status'));
foreach (WORKER_NEXT_STATUS as $from => $to) {
    check("the worker move $from -> $to in WORKER_NEXT_STATUS is a transition of the model",
        true, in_array([$from, $to], array_values(MODEL_TRANSITIONS), true));
}

// 2. Reachability (completeness of the state machine).
check('every state can be reached from New', $states, reachable_from(MODEL_START));
foreach ($states as $state) {
    check("Closed can be reached from $state", true, in_array('Closed', reachable_from($state), true));
}
$noWayOut = array_values(array_filter($states, function (string $state): bool {
    foreach (MODEL_TRANSITIONS as [$from]) {
        if ($from === $state) {
            return false;
        }
    }
    return true;
}));
check('Closed is the only state with no way out', ['Closed'], $noWayOut);

$pdo->beginTransaction();
try {
    // 3. Model against code: every action in every state (20 pairs).
    $people = [
        'supervisor' => make_user($pdo, 'Model Test Supervisor', 'supervisor'),
        'staff'      => make_user($pdo, 'Model Test Worker', 'staff'),
    ];
    $categoryId = (int)$pdo->query('SELECT id FROM categories ORDER BY sort_order LIMIT 1')->fetchColumn();
    $statusOf = $pdo->prepare('SELECT status FROM issues WHERE id = ?');

    foreach ($states as $state) {
        foreach (MODEL_TRANSITIONS as $action => [$from, $to]) {
            $issueId = issue_in_state($pdo, $state, $people, $categoryId);
            $changed = run_action($pdo, $action, $issueId, $people, $categoryId);
            $statusOf->execute([$issueId]);
            $after = $statusOf->fetchColumn();
            if ($from === $state) {
                check("$action in $state is allowed and moves the issue to $to", [true, $to], [$changed, $after]);
            } else {
                check("$action in $state is refused and the issue stays $state", [false, $state], [$changed, $after]);
            }
        }
    }

    // 4. Invariants (docs/models.md), for every issue in the database.
    $allowed = ['(h.old_status IS NULL AND h.new_status = ?)'];
    $params = [MODEL_START];
    foreach (MODEL_TRANSITIONS as [$from, $to]) {
        $allowed[] = '(h.old_status = ? AND h.new_status = ?)';
        $params[] = $from;
        $params[] = $to;
    }

    check('invariant: every issue has a history that starts with its submission (no status -> New)', [],
        violations($pdo,
            "SELECT i.tracking_ref FROM issues i
              WHERE NOT EXISTS (SELECT 1 FROM status_history h
                                 WHERE h.issue_id = i.id AND h.old_status IS NULL AND h.new_status = 'New'
                                   AND h.id = (SELECT MIN(h2.id) FROM status_history h2 WHERE h2.issue_id = i.id))
              ORDER BY i.tracking_ref"));

    check('invariant: the latest history entry of every issue matches its current status', [],
        violations($pdo,
            'SELECT i.tracking_ref FROM issues i
               LEFT JOIN status_history h
                      ON h.id = (SELECT MAX(h2.id) FROM status_history h2 WHERE h2.issue_id = i.id)
              WHERE h.id IS NULL OR h.new_status <> i.status
              ORDER BY i.tracking_ref'));

    check('invariant: every history entry is a transition of the model', [],
        violations($pdo,
            'SELECT DISTINCT i.tracking_ref FROM status_history h JOIN issues i ON i.id = h.issue_id
              WHERE NOT (' . implode(' OR ', $allowed) . ')
              ORDER BY i.tracking_ref', $params));

    check('invariant: resolved_at is set exactly when the issue is Resolved or Closed', [],
        violations($pdo,
            "SELECT tracking_ref FROM issues
              WHERE (resolved_at IS NOT NULL) <> (status IN ('Resolved', 'Closed'))
              ORDER BY tracking_ref"));

    check('invariant: an issue has an assignment exactly when it has left New', [],
        violations($pdo,
            "SELECT i.tracking_ref FROM issues i
              WHERE (i.status = 'New') = EXISTS (SELECT 1 FROM assignments a WHERE a.issue_id = i.id)
              ORDER BY i.tracking_ref"));

    check('invariant: a priority is set exactly when the issue has left New', [],
        violations($pdo,
            "SELECT tracking_ref FROM issues
              WHERE (priority IS NULL) <> (status = 'New')
              ORDER BY tracking_ref"));

    $badRefs = array_values(array_filter(
        $pdo->query('SELECT tracking_ref FROM issues ORDER BY tracking_ref')->fetchAll(PDO::FETCH_COLUMN),
        fn (string $ref): bool => !preg_match(TRACKING_REF_PATTERN, $ref)
    ));
    check('invariant: every tracking reference has the form CIR-YYYY-NNNN', [], $badRefs);
} finally {
    $pdo->rollBack();
}

echo $failures === 0 ? "All model checks passed.\n" : "$failures model check(s) failed.\n";
exit($failures === 0 ? 0 : 1);
