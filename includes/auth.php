<?php
/**
 * PIMS - Auth Guard
 * ---------------------------------------------------------------
 * Include this as the FIRST line of every protected page, before
 * any HTML, any <link> tag, or any other output:
 *
 *      <?php require_once 'includes/auth.php'; ?>
 * ---------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['userName'])) {
    header("Location: login.php");
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

/**
 * Call at the top of any page (after auth.php) that must be
 * restricted to specific roles. Anyone else is stopped with a
 * 403 page before any other output/DB queries run.
 *
 *      require_once 'includes/auth.php';
 *      require_role(['admin']);
 */
function require_role(array $allowedRoles): void
{
    if (!in_array(current_role(), $allowedRoles, true)) {
        http_response_code(403);
        require __DIR__ . '/access_denied.php';
        exit();
    }
}