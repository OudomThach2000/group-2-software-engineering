
<?php
/**
 * UC7 Notification Integration Test
 * Author: Rolando Labrador
 *
 * Tests issue lifecycle transitions together with
 * notification queuing and database persistence.
 *
 * All changes are rolled back after testing.
 */

require_once __DIR__ . '/../app/includes/db.php';
require_once __DIR__ . '/../app/src/issues.php';
require_once __DIR__ . '/../app/src/notify.php';

$failures = 0;

function verify_integration(
    string $description,
    $expected,
    $actual
): void {
    global $failures;

    if ($expected === $actual) {
        echo "PASS  $description\n";
    } else {
        $failures++;
        echo "FAIL  $description\n";
        echo "Expected: " . var_export($expected, true) . "\n";
        echo "Actual: " . var_export($actual, true) . "\n";
    }
}

$pdo = db();
$pdo->beginTransaction();

try {
    // 1. Get a valid issue category.
    $categoryId = (int)$pdo->query(
        'SELECT id FROM categories ORDER BY id LIMIT 1'
    )->fetchColumn();

    if ($categoryId === 0) {
        throw new RuntimeException('No issue category found.');
    }

    // 2. Create test supervisor and field worker.
    $createUser = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role)
         VALUES (?, ?, ?, ?)'
    );

    $hash = password_hash('IntegrationTest123!', PASSWORD_DEFAULT);

    $createUser->execute([
        'UC7 Test Supervisor',
        'uc7-supervisor@example.com',
        $hash,
        'supervisor'
    ]);
    $supervisorId = (int)$pdo->lastInsertId();

    $createUser->execute([
        'UC7 Test Worker',
        'uc7-worker@example.com',
        $hash,
        'staff'
    ]);
    $workerId = (int)$pdo->lastInsertId();

    // 3. Submit an issue as an anonymous resident.
    $contact = 'uc7-resident@example.com';

    $trackingRef = submit_issue($pdo, [
        'category_id' => $categoryId,
        'description' => 'Broken streetlight near the park',
        'location_text' => 'Community Park Road',
        'reporter_contact' => $contact
    ], null, null);

    $findIssue = $pdo->prepare(
        'SELECT id, status FROM issues WHERE tracking_ref = ?'
    );
    $findIssue->execute([$trackingRef]);
    $issue = $findIssue->fetch(PDO::FETCH_ASSOC);

    if (!$issue) {
        throw new RuntimeException('Submitted issue not found.');
    }

    $issueId = (int)$issue['id'];

    verify_integration(
        'resident submission creates a New issue',
        'New',
        $issue['status']
    );

    // 4. Supervisor assigns the issue.
    $assigned = assign_issue(
        $pdo,
        $issueId,
        $workerId,
        $supervisorId,
        $categoryId,
        'Medium',
        'Assigned for inspection'
    );

    verify_integration(
        'supervisor assigns the issue',
        true,
        $assigned
    );

    // As in the application page, queue after a
    // successful lifecycle transition.
    if ($assigned) {
        notify_status_change($issueId, 'Assigned', $pdo);
    }

    // 5. Field worker starts work.
    $started = update_issue_status(
        $pdo, $issueId, $workerId, 'In-progress'
    );

    verify_integration(
        'assigned worker starts work',
        true,
        $started
    );

    if ($started) {
        notify_status_change($issueId, 'In-progress', $pdo);
    }

    // 6. Field worker resolves the issue.
    $resolved = update_issue_status(
        $pdo,
        $issueId,
        $workerId,
        'Resolved',
        'Streetlight repaired and tested'
    );

    verify_integration(
        'assigned worker resolves issue with a note',
        true,
        $resolved
    );

    if ($resolved) {
        notify_status_change($issueId, 'Resolved', $pdo);
    }

    // 7. Supervisor closes the issue.
    $closed = close_issue(
        $pdo,
        $issueId,
        $supervisorId,
        'Repair confirmed'
    );

    verify_integration(
        'supervisor closes resolved issue',
        true,
        $closed
    );

    if ($closed) {
        notify_status_change($issueId, 'Closed', $pdo);
    }

    // 8. Verify all queued notifications.
    $stmt = $pdo->prepare(
        'SELECT recipient, channel, message, status
         FROM notifications
         WHERE issue_id = ?
         ORDER BY id'
    );
    $stmt->execute([$issueId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    verify_integration(
        'four lifecycle notifications are queued',
        4,
        count($rows)
    );

    $statuses = [
        'Assigned',
        'In-progress',
        'Resolved',
        'Closed'
    ];

    foreach ($statuses as $index => $status) {
        $row = $rows[$index] ?? null;

        verify_integration(
            "$status notification uses correct recipient",
            $contact,
            $row['recipient'] ?? null
        );

        verify_integration(
            "$status notification uses email channel",
            'email',
            $row['channel'] ?? null
        );

        verify_integration(
            "$status notification is queued",
            'queued',
            $row['status'] ?? null
        );

        verify_integration(
            "$status notification contains correct message",
            build_status_message($trackingRef, $status),
            $row['message'] ?? null
        );
    }

    // 9. Verify the final issue status.
    $stmt = $pdo->prepare(
        'SELECT status FROM issues WHERE id = ?'
    );
    $stmt->execute([$issueId]);

    verify_integration(
        'issue lifecycle ends in Closed status',
        'Closed',
        $stmt->fetchColumn()
    );

} catch (Throwable $ex) {
    $failures++;
    echo "FAIL  Integration test error: "
       . $ex->getMessage() . "\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

if ($failures > 0) {
    echo "\n$failures integration check(s) failed.\n";
    exit(1);
}

echo "\nAll UC7 integration checks passed.\n";
