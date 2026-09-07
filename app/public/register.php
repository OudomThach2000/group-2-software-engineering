<?php
/**
 * UC6 Register  (FR7).  Owner: Sethouday Prum.
 */
require_once __DIR__ . '/../includes/db.php';
// TODO(Sethouday): on POST, validate, hash with password_hash($pw, PASSWORD_DEFAULT),
//   INSERT into users (role defaults to 'resident'); a supervisor promotes staff later.
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Create an account</h1>
<form method="post" class="form">
  <label>Name <input type="text" name="name" required></label>
  <label>Email <input type="email" name="email" required></label>
  <label>Password <input type="password" name="password" required></label>
  <button type="submit">Register</button>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
