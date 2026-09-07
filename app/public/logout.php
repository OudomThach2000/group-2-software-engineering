<?php
/** UC6 Log out.  Owner: Sethouday Prum. */
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION = [];
session_destroy();
header('Location: /index.php');
