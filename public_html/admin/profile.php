<?php

declare(strict_types=1);

/**
 * public_html/admin/profile.php
 *
 * Phase 4.2 — Profile Management.
 * View / create / edit the single row in `profile` (id = 1) via PDO
 * prepared statements. Reuses existing admin auth, CSRF, and layout.
 * No new framework, no new dependency.
 */

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/helpers.php';
require __DIR__ . '/../../app/admin-auth.php';

requireAdmin();

$pdo = db();

/** Only fields that already exist on the `profile` table. */
const PROFILE_FIELDS = ['full_name', 'headline', 'bio', 'location', 'photo_path'];

const PROFILE_MAX_LENGTHS = [
    'full_name'  => 191,
    'headline'   => 191,
    'location'   => 191,
    'photo_path' => 255,
];

const PROFILE_BIO_MAX_LENGTH = 5000;

function loadProfileRow(PDO $pdo): ?array
{
    $stmt = $pdo->query(
        'SELECT full_name, headline, bio, location, photo_path, updated_at
         FROM profile WHERE id = 1 LIMIT 1'
    );
    $row = $stmt ? $stmt->fetch() : null;

    return $row ?: null;
}

$errors  = [];
$success = false;

$profile = loadProfileRow($pdo);

/* Values shown in the form: current row by default, empty if none exists. */
$formValues = array_fill_keys(PROFILE_FIELDS, '');
if ($profile !== null) {
    foreach (PROFILE_FIELDS as $field) {
        $formValues[$field] = (string) ($profile[$field] ?? '');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please reload the page and try again.';
    } else {
        foreach (PROFILE_FIELDS as $field) {
            $formValues[$field] = trim((string) ($_POST[$field] ?? ''));
        }

        /* ---------- Server-side validation ---------- */
        if ($formValues['full_name'] === '') {
            $errors[] = 'Full name is required.';
        }

        foreach (PROFILE_MAX_LENGTHS as $field => $max) {
            if (mb_strlen($formValues[$field]) > $max) {
                $label    = ucfirst(str_replace('_', ' ', $field));
                $errors[] = "{$label} must be {$max} characters or fewer.";
            }
        }

        if (mb_strlen($formValues['bio']) > PROFILE_BIO_MAX_LENGTH) {
            $errors[] = 'Bio must be ' . PROFILE_BIO_MAX_LENGTH . ' characters or fewer.';
        }

        // photo_path is a plain path/URL string here (no upload handling yet —
        // that belongs to a future Media phase). Reject stray null bytes only.
        if (str_contains($formValues['photo_path'], "\0")) {
            $errors[] = 'Photo path is invalid.';
        }

        if (empty($errors)) {
            $params = [
                'full_name'  => $formValues['full_name'] !== '' ? $formValues['full_name'] : null,
                'headline'   => $formValues['headline'] !== '' ? $formValues['headline'] : null,
                'bio'        => $formValues['bio'] !== '' ? $formValues['bio'] : null,
                'location'   => $formValues['location'] !== '' ? $formValues['location'] : null,
                'photo_path' => $formValues['photo_path'] !== '' ? $formValues['photo_path'] : null,
            ];

            $exists = $pdo->query('SELECT id FROM profile WHERE id = 1')->fetch();

            if ($exists) {
                $stmt = $pdo->prepare(
                    'UPDATE profile
                     SET full_name = :full_name,
                         headline  = :headline,
                         bio       = :bio,
                         location  = :location,
                         photo_path = :photo_path,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = 1'
                );
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO profile (id, full_name, headline, bio, location, photo_path, updated_at)
                     VALUES (1, :full_name, :headline, :bio, :location, :photo_path, CURRENT_TIMESTAMP)'
                );
            }

            $stmt->execute($params);

            // PRG: redirect after a successful write so refresh never resubmits.
            header('Location: /admin/profile.php?saved=1');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['saved'])) {
    $success = true;
    $profile = loadProfileRow($pdo);
    if ($profile !== null) {
        foreach (PROFILE_FIELDS as $field) {
            $formValues[$field] = (string) ($profile[$field] ?? '');
        }
    }
}

$pageTitle = 'Profile';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-profile">
  <div class="admin-dashboard__head">
    <div>
      <p class="meta">Profile</p>
      <h1><?= $profile === null && !$success ? 'Create Profile' : 'Edit Profile' ?></h1>
    </div>
  </div>

<?php if ($success): ?>
  <p class="admin-alert" role="status" style="border-color: var(--success); color: var(--success);">
    Profile saved.
  </p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <p class="admin-alert" role="alert">
<?php foreach ($errors as $error): ?>
    <?= e($error) ?><br>
<?php endforeach; ?>
  </p>
<?php endif; ?>

<?php if ($profile === null && !$success): ?>
  <p class="admin-dashboard__note">No profile row exists yet. Fill in the form below to create one.</p>
<?php endif; ?>

  <form method="post" action="profile.php" novalidate>
    <?= csrfField() ?>

    <label class="admin-field">
      <span>Full name</span>
      <input type="text" name="full_name" value="<?= e($formValues['full_name']) ?>" maxlength="191" required autofocus>
    </label>

    <label class="admin-field">
      <span>Headline</span>
      <input type="text" name="headline" value="<?= e($formValues['headline']) ?>" maxlength="191">
    </label>

    <label class="admin-field">
      <span>Bio</span>
      <textarea name="bio" rows="6" maxlength="<?= PROFILE_BIO_MAX_LENGTH ?>"><?= e($formValues['bio']) ?></textarea>
    </label>

    <label class="admin-field">
      <span>Location</span>
      <input type="text" name="location" value="<?= e($formValues['location']) ?>" maxlength="191">
    </label>

    <label class="admin-field">
      <span>Photo path (optional — upload handling ships in a later Media phase)</span>
      <input type="text" name="photo_path" value="<?= e($formValues['photo_path']) ?>" maxlength="255">
    </label>

    <button class="btn btn--primary admin-auth__submit" type="submit">Save Profile</button>
  </form>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>