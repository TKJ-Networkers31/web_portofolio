<?php

declare(strict_types=1);

/**
 * public_html/admin/logout.php
 *
 * Destroys the admin session. Only accepts POST + a valid CSRF token, so
 * an attacker cannot force a logout (or worse) via a bare link/image tag.
 */

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/helpers.php';
require __DIR__ . '/../../app/admin-auth.php';

if (isAdminLoggedIn()
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && verifyCsrf($_POST['csrf_token'] ?? null)
) {
    logoutAdmin();
}

header('Location: ' . adminUrl('login.php'));
exit;
