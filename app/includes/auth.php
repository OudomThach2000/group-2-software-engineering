<?php
/**
 * Authentication & session helpers  (FR7, UC6).  Owner: Sethouday Prum.
 */
require_once __DIR__ . '/db.php';

function current_user(): ?array
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION['user'] ?? null;
}

/** Redirect to login unless the current user has one of the given roles. */
function require_role(string ...$roles): void
{
    $u = current_user();
    if (!$u || !in_array($u['role'], $roles, true)) {
        header('Location: /login.php');
        exit;
    }
}

// TODO(Sethouday): implement login($email,$password) using password_verify()
//   (sets $_SESSION['user'] = ['id','name','role']) and logout().
