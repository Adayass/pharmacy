<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['userName'])) {
    header("Location: index.php");
    exit();
}

/**
 * Role helpers
 * ---------------------------------------------------------------
 * $_SESSION['role'] is set at login (see login.php) and holds one
 * of: 'admin', 'pharmacist', 'staff'.
 */

function current_role(): string
{
    return $_SESSION['role'] ?? '';
}

function is_admin(): bool
{
    return current_role() === 'admin';
}

function require_role(array $allowedRoles): void
{
    if (!in_array(current_role(), $allowedRoles, true)) {
        http_response_code(404);
        echo "You dont have the autorization to acces this page";
        exit();
    }
}
