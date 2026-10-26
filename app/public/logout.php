<?php
/**
 * UC6 Log out.  Owner: Sethouday Prum.
 * Logs out only on a POST that carries the session's token (#11), so a link or an image on
 * another page can no longer log a user out. A plain visit just goes to the home page.
 */
require_once __DIR__ . '/../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    reject_forged_post();
    logout_user();
}
header('Location: /index.php');
exit;
