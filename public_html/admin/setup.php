<?php

declare(strict_types=1);

/**
 * public_html/admin/setup.php
 *
 * ONE-TIME browser-based bootstrap for hosts with no SSH/terminal access.
 * Runs the database migration and creates the first admin account —
 * everything database/migrate.php + database/create_admin.php would do
 * from a terminal, done here through a form instead.
 *
 * SECURITY
 *  - Does nothing at all unless ?token=... matches SETUP_TOKEN in .env.
 *    Set SETUP_TOKEN to a long random string yourself before using this.
 *  - Once an admin account exists, this page writes a marker file and
 *    permanently refuses to run again — even with the correct token —
 *    so it can't be used to add a second, unwanted admin later.
 *  - DELETE THIS FILE once setup is done. It is not linked from anywhere
 *    in the admin UI on purpose.
 */

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/helpers.php';
require __DIR__ . '/../../app/admin-auth.php';
require __DIR__ . '/../../app/migration-runner.php';

$markerFile = __DIR__ . '/../../storage/.setup_complete';

if (is_file($markerFile)) {
    http_response_code(403);
    exit('Setup already completed. Delete public_html/admin/setup.php.');
}

$setupToken = env('SETUP_TOKEN', '');
$givenToken = (string) ($_GET['token'] ?? ($_POST['token'] ?? ''));

if ($setupToken === '') {
    http_response_code(500);
    exit('SETUP_TOKEN is not set in .env. Set it to a long random value, then reload this page with ?token=that-value.');
}

if ($givenToken === '' || !hash_equals($setupToken, $givenToken)) {
    http_response_code(403);
    exit('Forbidden. Open this page as setup.php?token=YOUR_SETUP_TOKEN.');
}

/* ---------- Step 1: migration (safe to re-run, all statements are idempotent) ---------- */

$driver         = env('DB_DRIVER', 'sqlite');
$pdo            = db();
$migrationError = null;
$migrationCount = null;

try {
    $migrationCount = runMigrations($pdo, $driver, __DIR__ . '/../../database');
} catch (Throwable $e) {
    $migrationError = $e->getMessage();
}

$adminCount = 0;
if ($migrationError === null) {
    try {
        $row        = $pdo->query('SELECT COUNT(*) AS c FROM admins')->fetch();
        $adminCount = (int) ($row['c'] ?? 0);
    } catch (Throwable $e) {
        $migrationError = $e->getMessage();
    }
}

/* ---------- Step 2: create the first admin ---------- */

$createError   = '';
$createSuccess = false;

if ($migrationError === null
    && $adminCount === 0
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['create_admin'])
) {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $createError = 'Form expired — reload the page (with the same ?token=...) and try again.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $email    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || strlen($password) < 10) {
            $createError = 'Username is required and password must be at least 10 characters.';
        } else {
            $hash   = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare(
                'INSERT INTO admins (username, email, password_hash) VALUES (:u, :e, :h)'
            );
            $insert->execute([
                'u' => $username,
                'e' => $email !== '' ? $email : null,
                'h' => $hash,
            ]);

            file_put_contents(
                $markerFile,
                date('c') . " setup.php created admin '{$username}'\n"
            );

            $createSuccess = true;
            $adminCount    = 1;
        }
    }
}

$pageTitle = 'One-time Setup';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-auth">
  <div class="admin-auth__card" style="max-width: 460px;">
    <h1>One-time Setup</h1>
    <p class="meta">Database migration + first admin account</p>

<?php if ($migrationError !== null): ?>
    <p class="admin-alert" role="alert">Migration failed: <?= e($migrationError) ?></p>
<?php else: ?>
    <p class="admin-alert" role="status" style="border-color: var(--success); color: var(--success);">
      Database ready (<?= e($driver) ?>) &mdash; <?= (int) $migrationCount ?> statements applied.
    </p>
<?php endif; ?>

<?php if ($createSuccess): ?>
    <p class="admin-alert" role="status" style="border-color: var(--success); color: var(--success);">
      Admin account created. <strong>Delete public_html/admin/setup.php now</strong>, then
      <a class="link-text" href="login.php">go to login</a>.
    </p>
<?php elseif ($adminCount > 0): ?>
    <p class="admin-alert" role="status" style="border-color: var(--warning); color: var(--warning);">
      An admin account already exists — this page will not create another.
      <strong>Delete public_html/admin/setup.php now</strong> and go to
      <a class="link-text" href="login.php">login</a>.
    </p>
<?php elseif ($migrationError === null): ?>
    <form method="post" action="setup.php?token=<?= e($givenToken) ?>" novalidate>
      <?= csrfField() ?>
      <input type="hidden" name="create_admin" value="1">

<?php if ($createError !== ''): ?>
      <p class="admin-alert" role="alert"><?= e($createError) ?></p>
<?php endif; ?>

      <label class="admin-field">
        <span>Username</span>
        <input type="text" name="username" required autofocus>
      </label>

      <label class="admin-field">
        <span>Email (optional)</span>
        <input type="email" name="email">
      </label>

      <label class="admin-field">
        <span>Password (min. 10 characters)</span>
        <input type="password" name="password" minlength="10" required>
      </label>

      <button class="btn btn--primary admin-auth__submit" type="submit">Create Admin Account</button>
    </form>
<?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
