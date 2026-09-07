<?php require_once __DIR__ . '/auth.php'; $u = current_user(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Community Issue Reporting System</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="site-header">
  <a class="brand" href="/index.php">Community Issue Reporting</a>
  <nav>
    <a href="/submit.php">Report an issue</a>
    <a href="/track.php">Track</a>
    <?php if ($u): ?>
      <?php if ($u['role'] === 'supervisor'): ?>
        <a href="/supervisor/triage.php">Triage</a>
        <a href="/supervisor/dashboard.php">Dashboard</a>
      <?php endif; ?>
      <?php if (in_array($u['role'], ['staff','supervisor'], true)): ?>
        <a href="/staff/issues.php">My issues</a>
      <?php endif; ?>
      <span class="who"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['role']) ?>)</span>
      <a href="/logout.php">Log out</a>
    <?php else: ?>
      <a href="/login.php">Staff log in</a>
    <?php endif; ?>
  </nav>
</header>
<main class="container">
