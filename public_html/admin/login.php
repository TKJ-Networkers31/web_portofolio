<?php

declare(strict_types=1);

/**
 * public_html/admin/login.php
 *
 * The only admin route reachable while logged out. Everything else under
 * /admin requires a valid session (see requireAdmin()).
 */

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/helpers.php';
require __DIR__ . '/../../app/admin-auth.php';

if (isAdminLoggedIn()) {
    header('Location: ' . adminUrl('index.php'));
    exit;
}

// Only allow redirects back into /admin — never to an external URL.
$adminPrefix = adminUrl('') . '/';
$redirect    = isset($_GET['redirect']) ? (string) $_GET['redirect'] : adminUrl('index.php');
if (!str_starts_with($redirect, $adminPrefix)) {
    $redirect = adminUrl('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (loginIsLockedOut()) {
        $error = 'Too many failed attempts. Please wait a minute and try again.';
    } elseif (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (attemptLogin($username, $password)) {
            header('Location: ' . $redirect);
            exit;
        }

        registerFailedLogin();
        $error = 'Invalid username or password.';
    }
}

$pageTitle = 'Admin Login';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-auth">
  <form class="admin-auth__card" method="post" action="login.php?redirect=<?= e($redirect) ?>" novalidate>
    <h1>Admin Login</h1>
    <p class="meta">Mohamad Lingga Syahputra &middot; Portfolio CMS</p>

<?php if ($error !== ''): ?>
    <p class="admin-alert" role="alert"><?= e($error) ?></p>
<?php endif; ?>

    <?= csrfField() ?>

    <label class="admin-field">
      <span>Username</span>
      <input type="text" name="username" autocomplete="username" required autofocus>
    </label>

    <label class="admin-field">
      <span>Password</span>
      <input type="password" name="password" autocomplete="current-password" required>
    </label>

    <button class="btn btn--primary admin-auth__submit" type="submit">Sign In</button>
  </form>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
