<?php

declare(strict_types=1);

/**
 * public_html/admin/profile.php
 *
 * Phase 4.2 — Profile Management. View / create / edit the single row
 * in `profile` (id = 1) via PDO prepared statements.
 *
 * PHASE 5.3 — photo_path is no longer a plain text field. It now has:
 *   - Preview of the current photo
 *   - "Upload Photo" — a real file picker; uploads through the same
 *     storeUploadedMedia() the Media Library uses (auto filename/MIME/
 *     size, validated, stored under assets/media/, a `media` row is
 *     created), then immediately saves it as profile.photo_path.
 *   - "Choose Existing" — opens a picker <dialog> listing image assets
 *     already in the Media Library; picking one updates the preview
 *     instantly and fills the hidden photo_path field, persisted when
 *     "Save Profile" is clicked (same as any other field on this form).
 *   - "Remove Photo" — clears profile.photo_path (does not delete the
 *     underlying media asset, since it may be reused elsewhere).
 * Every other profile field and the save/validation logic is unchanged.
 * The public site keeps reading profile.photo_path exactly as before —
 * only how the admin sets that value changed.
 */

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/helpers.php';
require __DIR__ . '/../../app/admin-auth.php';
require __DIR__ . '/../../app/media.php';

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

/** Upsert helper — same statement the original 'save' branch used, reused by upload_photo/remove_photo so photo changes persist immediately without requiring a separate "Save Profile" click. */
function saveProfileRow(PDO $pdo, array $values): void
{
    $params = [
        'full_name'  => $values['full_name'] !== '' ? $values['full_name'] : null,
        'headline'   => $values['headline'] !== '' ? $values['headline'] : null,
        'bio'        => $values['bio'] !== '' ? $values['bio'] : null,
        'location'   => $values['location'] !== '' ? $values['location'] : null,
        'photo_path' => $values['photo_path'] !== '' ? $values['photo_path'] : null,
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
    $action = (string) ($_POST['action'] ?? 'save');

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please reload the page and try again.';
    } elseif ($action === 'upload_photo') {
        $result = storeUploadedMedia($pdo, $_FILES['photo_file'] ?? []);

        if (!$result['ok']) {
            $errors[] = $result['error'] ?? 'The photo could not be uploaded.';
        } elseif (mediaTypeCategory($result['mime_type'] ?? '') !== 'image') {
            $errors[] = 'Please upload an image file for the profile photo.';
        } else {
            $formValues['photo_path'] = $result['path'];
            saveProfileRow($pdo, $formValues);

            header('Location: ' . adminUrl('profile.php') . '?saved=1');
            exit;
        }
    } elseif ($action === 'remove_photo') {
        $formValues['photo_path'] = '';
        saveProfileRow($pdo, $formValues);

        header('Location: ' . adminUrl('profile.php') . '?saved=1');
        exit;
    } elseif ($action === 'save') {
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

        // photo_path now normally comes from Upload/Choose Existing (a
        // known-safe assets/media/... path or a picked library path),
        // but the hidden field is still defensively checked here in
        // case of a tampered request.
        if (str_contains($formValues['photo_path'], "\0")) {
            $errors[] = 'Photo path is invalid.';
        }

        if (empty($errors)) {
            saveProfileRow($pdo, $formValues);

            // PRG: redirect after a successful write so refresh never resubmits.
            header('Location: ' . adminUrl('profile.php') . '?saved=1');
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

/* Image assets available for the "Choose Existing" picker. */
$photoPickerRows = mediaSearchRows($pdo, '', 'image');

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

  <!-- ---- Profile Photo ---- -->
  <div class="admin-photo-field">
    <div class="admin-photo-field__preview">
<?php if ($formValues['photo_path'] !== ''): ?>
      <img id="profile-photo-preview" src="<?= e(publicMediaUrl($formValues['photo_path'])) ?>" alt="Profile photo preview">
<?php else: ?>
      <img id="profile-photo-preview" src="" alt="Profile photo preview" hidden>
      <span class="admin-photo-field__placeholder" id="profile-photo-placeholder">No photo set</span>
<?php endif; ?>
    </div>

    <div class="admin-photo-field__actions">
      <form method="post" action="profile.php" enctype="multipart/form-data" class="admin-photo-field__upload">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="upload_photo">
        <label class="btn btn--secondary admin-photo-field__upload-btn">
          Upload Photo
          <input type="file" name="photo_file" accept="image/jpeg,image/png,image/gif,image/webp" onchange="this.form.submit()" hidden>
        </label>
      </form>

      <button type="button" class="btn btn--secondary" data-open-picker="photo-picker-dialog" data-target-input="profile_photo_path_input" data-target-preview="profile-photo-preview">
        Choose Existing
      </button>

<?php if ($formValues['photo_path'] !== ''): ?>
      <form method="post" action="profile.php" onsubmit="return confirm('Remove the profile photo?');">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="remove_photo">
        <button type="submit" class="link-text admin-table__delete">Remove Photo</button>
      </form>
<?php endif; ?>
    </div>

    <p class="meta">JPEG, PNG, GIF, or WebP, up to <?= e(formatBytes(mediaMaxBytes())) ?>. Uploading or choosing a photo saves it immediately.</p>
  </div>

  <!-- ---- Asset picker dialog (image assets from the Media Library) ---- -->
  <dialog id="photo-picker-dialog" class="admin-picker-dialog">
    <div class="admin-picker-dialog__head">
      <h2>Choose a photo</h2>
      <button type="button" class="link-text" data-close-picker>Close</button>
    </div>
<?php if (empty($photoPickerRows)): ?>
    <p class="admin-dashboard__note">No images in the Media Library yet. Use "Upload Photo" instead, or add images via <a class="link-text" href="media.php">Media</a>.</p>
<?php else: ?>
    <div class="admin-media-grid admin-media-grid--picker">
<?php foreach ($photoPickerRows as $row): ?>
<?php $pickerUrl = publicMediaUrl((string) $row['path']); ?>
      <button type="button" class="admin-media-card admin-media-card--pick" data-media-pick data-path="<?= e($pickerUrl) ?>" data-preview="<?= e($pickerUrl) ?>">
        <span class="admin-media-card__thumb">
          <img src="<?= e($pickerUrl) ?>" alt="<?= e((string) ($row['alt_text'] ?? $row['filename'])) ?>" loading="lazy">
        </span>
        <span class="admin-media-card__name"><?= e((string) $row['filename']) ?></span>
      </button>
<?php endforeach; ?>
    </div>
<?php endif; ?>
  </dialog>

  <form method="post" action="profile.php" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="photo_path" id="profile_photo_path_input" value="<?= e($formValues['photo_path']) ?>">

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

    <button class="btn btn--primary admin-auth__submit" type="submit">Save Profile</button>
  </form>
</div>

<script src="assets/admin-media.js" defer></script>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
