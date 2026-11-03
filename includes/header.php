<?php
/**
 * PIMS - Reusable Top Header Include
 * ---------------------------------------------------------------
 * HOW TO USE:
 * 1. Session values are set in login.php on successful login:
 *      $_SESSION['userName'] = ...;
 *      $_SESSION['fullName'] = ...;
 *      $_SESSION['role']     = ...;
 *
 * 2. On every page, include this AFTER sidebar.php, inside <main>:
 *      <main class="pims-main">
 *          <?php include 'includes/header.php'; ?>
 *          ... rest of your page content ...
 *      </main>
 * ---------------------------------------------------------------
 */

$pageTitle  = $pageTitle ?? ucfirst(str_replace(['_', '.php'], [' ', ''], basename($_SERVER['SCRIPT_NAME'])));
$userName   = $_SESSION['fullName'] ?? $_SESSION['userName'] ?? 'Guest';
$userRole   = $_SESSION['role'] ?? 'User';
$userAvatar = $_SESSION['userAvatar'] ?? '';
$initials   = strtoupper(substr($userName, 0, 1) . (strpos($userName, ' ') ? substr($userName, strpos($userName, ' ') + 1, 1) : ''));
?>
<link rel="stylesheet" href="includes/header.css">


<header class="pims-header">
    <h1 class="pims-page-title"><?= htmlspecialchars($pageTitle) ?></h1>

    <div class="pims-user">
        <div class="pims-user-text">
            <p class="pims-user-name"><?= htmlspecialchars($userName) ?></p>
            <p class="pims-user-role">Role: <?= htmlspecialchars($userRole) ?></p>
        </div>

        <?php if ($userAvatar): ?>
            <img src="<?= htmlspecialchars($userAvatar) ?>" alt="<?= htmlspecialchars($userName) ?>" class="pims-avatar-img">
        <?php else: ?>
            <div class="pims-avatar-fallback"><?= htmlspecialchars($initials) ?></div>
        <?php endif; ?>
    </div>
</header>