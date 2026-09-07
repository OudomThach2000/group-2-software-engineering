<?php
/**
 * UC6 Log In  (FR7).  Owner: Sethouday Prum.
 */
require_once __DIR__ . '/../includes/auth.php';
// TODO(Sethouday): on POST, look up user by email, password_verify(),
//   set $_SESSION['user'] = ['id','name','role'], then redirect by role.
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Staff log in</h1>
<form method="post" class="form">
  <label>Email <input type="email" name="email" required></label>
  <label>Password <input type="password" name="password" required></label>
  <button type="submit">Log in</button>
</form>
<p><a href="/register.php">Create an account</a></p>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
