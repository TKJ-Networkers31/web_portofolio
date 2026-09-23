<?php

declare(strict_types=1);

/**
 * public_html/admin/social.php
 *
 * Phase 4.6 — Social links CRUD (list / create / edit / delete).
 * Shares the existing `contacts` table with admin/contact.php (schema
 * unchanged); scoped to SOCIAL_TYPES so the two pages never read, edit,
 * or delete each other's rows.
 */

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/helpers.php';
require __DIR__ . '/../../app/admin-auth.php';

requireAdmin();

$pdo = db();

const SOCIAL_FIELDS = ['label', 'type', 'value', 'icon', 'is_visible', 'sort_order'];

const SOCIAL_TYPES = ['github', 'linkedin', 'instagram', 'twitter', 'facebook', 'youtube', 'tiktok', 'website', 'other'];

const SOCIAL_MAX_LENGTHS = [
    'label' => 191,
    'type'  => 64,
    'value' => 255,
    'icon'  => 64,
];

function socialTypePlaceholders(): string
{
    return implode(',', array_fill(0, count(SOCIAL_TYPES), '?'));
}

function loadSocialRows(PDO $pdo): array
{
    $sql = 'SELECT id, label, type, value, icon, is_visible, sort_order
            FROM contacts
            WHERE type IN (' . socialTypePlaceholders() . ')
            ORDER BY sort_order ASC, id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(SOCIAL_TYPES);

    return $stmt->fetchAll();
}

function loadSocialRow(PDO $pdo, int $id): ?array
{
    $sql = 'SELECT id, label, type, value, icon, is_visible, sort_order
            FROM contacts
            WHERE id = ? AND type IN (' . socialTypePlaceholders() . ')
            LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$id], SOCIAL_TYPES));
    $row = $stmt->fetch();

    return $row ?: null;
}

$errors  = [];
$success = '';
$editId  = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

$formValues = array_fill_keys(SOCIAL_FIELDS, '');
$formValues['type']       = SOCIAL_TYPES[0];
$formValues['sort_order'] = '0';
$formValues['is_visible'] = '1';
$editingRow = null;

if ($editId > 0) {
    $editingRow = loadSocialRow($pdo, $editId);
    if ($editingRow === null) {
        $errors[] = 'The record you tried to edit no longer exists.';
        $editId   = 0;
    } else {
        foreach (SOCIAL_FIELDS as $field) {
            if ($field === 'is_visible') {
                $formValues['is_visible'] = !empty($editingRow['is_visible']) ? '1' : '';
                continue;
            }
            $formValues[$field] = (string) ($editingRow[$field] ?? '');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please reload the page and try again.';
    } elseif ($action === 'delete') {
        $deleteId = (int) ($_POST['id'] ?? 0);

        if ($deleteId <= 0) {
            $errors[] = 'Invalid record.';
        } else {
            $existing = loadSocialRow($pdo, $deleteId);
            if ($existing === null) {
                $errors[] = 'That record does not belong to Social or no longer exists.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM contacts WHERE id = :id');
                $stmt->execute(['id' => $deleteId]);

                header('Location: /admin/social.php?deleted=1');
                exit;
            }
        }
    } elseif ($action === 'save') {
        $postId = (int) ($_POST['id'] ?? 0);

        foreach (SOCIAL_FIELDS as $field) {
            if ($field === 'is_visible') {
                $formValues['is_visible'] = !empty($_POST['is_visible']) ? '1' : '';
                continue;
            }
            $formValues[$field] = trim((string) ($_POST[$field] ?? ''));
        }

        /* ---------- Server-side validation ---------- */
        if ($formValues['label'] === '') {
            $errors[] = 'Label is required.';
        }

        if ($formValues['type'] === '' || !in_array($formValues['type'], SOCIAL_TYPES, true)) {
            $errors[] = 'Type must be one of: ' . implode(', ', SOCIAL_TYPES) . '.';
        }

        if ($formValues['value'] === '') {
            $errors[] = 'URL is required.';
        } elseif (filter_var($formValues['value'], FILTER_VALIDATE_URL) === false) {
            $errors[] = 'Value must be a valid URL (e.g. https://github.com/username).';
        }

        foreach (SOCIAL_MAX_LENGTHS as $field => $max) {
            if (mb_strlen($formValues[$field]) > $max) {
                $label    = ucfirst($field);
                $errors[] = "{$label} must be {$max} characters or fewer.";
            }
        }

        if ($formValues['sort_order'] === '' || !preg_match('/^-?\d+$/', $formValues['sort_order'])) {
            $errors[] = 'Sort order must be a whole number.';
        }

        if (empty($errors)) {
            $params = [
                'label'      => $formValues['label'],
                'type'       => $formValues['type'],
                'value'      => $formValues['value'],
                'icon'       => $formValues['icon'] !== '' ? $formValues['icon'] : null,
                'is_visible' => $formValues['is_visible'] === '1' ? 1 : 0,
                'sort_order' => (int) $formValues['sort_order'],
            ];

            if ($postId > 0) {
                $exists = loadSocialRow($pdo, $postId);
                if ($exists === null) {
                    $errors[] = 'The record you tried to save does not belong to Social or no longer exists.';
                } else {
                    $params['id'] = $postId;
                    $stmt = $pdo->prepare(
                        'UPDATE contacts
                         SET label = :label, type = :type, value = :value, icon = :icon,
                             is_visible = :is_visible, sort_order = :sort_order,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE id = :id'
                    );
                    $stmt->execute($params);

                    header('Location: /admin/social.php?saved=1');
                    exit;
                }
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO contacts (label, type, value, icon, is_visible, sort_order)
                     VALUES (:label, :type, :value, :icon, :is_visible, :sort_order)'
                );
                $stmt->execute($params);

                header('Location: /admin/social.php?saved=1');
                exit;
            }
        }

        $editId = $postId;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['saved'])) {
        $success = 'Social link saved.';
    } elseif (isset($_GET['deleted'])) {
        $success = 'Social link deleted.';
    }
}

$rows = loadSocialRows($pdo);

$pageTitle = 'Social';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-crud">
  <div class="admin-dashboard__head">
    <div>
      <p class="meta">Social</p>
      <h1><?= $editId > 0 ? 'Edit Social Link' : 'Add Social Link' ?></h1>
    </div>
  </div>

  <p class="admin-dashboard__note">
    Social profile links (GitHub, LinkedIn, Instagram, etc.). Shares the
    <code>contacts</code> table with <a class="link-text" href="contact.php">Contact</a>,
    scoped by type so the two lists never overlap.
  </p>

<?php if ($success !== ''): ?>
  <p class="admin-alert" role="status" style="border-color: var(--success); color: var(--success);">
    <?= e($success) ?>
  </p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <p class="admin-alert" role="alert">
<?php foreach ($errors as $error): ?>
    <?= e($error) ?><br>
<?php endforeach; ?>
  </p>
<?php endif; ?>

  <form method="post" action="social.php" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editId > 0 ? (int) $editId : '' ?>">

    <label class="admin-field">
      <span>Label</span>
      <input type="text" name="label" value="<?= e($formValues['label']) ?>" maxlength="191" required autofocus placeholder="e.g. GitHub">
    </label>

    <div class="admin-field-row">
      <label class="admin-field">
        <span>Type</span>
        <select name="type">
<?php foreach (SOCIAL_TYPES as $type): ?>
          <option value="<?= e($type) ?>"<?= $formValues['type'] === $type ? ' selected' : '' ?>><?= e(ucfirst($type)) ?></option>
<?php endforeach; ?>
        </select>
      </label>

      <label class="admin-field admin-field--checkbox">
        <span>Visible on public site</span>
        <input type="checkbox" name="is_visible" value="1"<?= $formValues['is_visible'] === '1' ? ' checked' : '' ?>>
      </label>
    </div>

    <label class="admin-field">
      <span>URL</span>
      <input type="url" name="value" value="<?= e($formValues['value']) ?>" maxlength="255" required placeholder="https://github.com/username">
    </label>

    <label class="admin-field">
      <span>Icon (optional key, e.g. github)</span>
      <input type="text" name="icon" value="<?= e($formValues['icon']) ?>" maxlength="64">
    </label>

    <label class="admin-field">
      <span>Sort order</span>
      <input type="number" name="sort_order" value="<?= e($formValues['sort_order']) ?>" step="1">
    </label>

    <div class="admin-form-actions">
      <button class="btn btn--primary" type="submit"><?= $editId > 0 ? 'Save Changes' : 'Add Social Link' ?></button>
<?php if ($editId > 0): ?>
      <a class="link-text" href="social.php">Cancel</a>
<?php endif; ?>
    </div>
  </form>

  <h2 class="admin-crud__list-title">Existing Social Links</h2>

<?php if (empty($rows)): ?>
  <p class="admin-dashboard__note">No social links yet. Add one using the form above.</p>
<?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Label</th>
          <th>Type</th>
          <th>URL</th>
          <th>Visible</th>
          <th>Order</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($rows as $row): ?>
        <tr>
          <td><?= e((string) $row['label']) ?></td>
          <td class="meta"><?= e((string) $row['type']) ?></td>
          <td><a class="link-text" href="<?= e((string) $row['value']) ?>" target="_blank" rel="noopener noreferrer"><?= e((string) $row['value']) ?></a></td>
          <td class="meta"><?= !empty($row['is_visible']) ? 'Yes' : 'No' ?></td>
          <td class="meta"><?= (int) $row['sort_order'] ?></td>
          <td class="admin-table__actions">
            <a class="link-text" href="social.php?edit=<?= (int) $row['id'] ?>">Edit</a>
            <form method="post" action="social.php" onsubmit="return confirm('Delete this social link? This cannot be undone.');">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
              <button class="link-text admin-table__delete" type="submit">Delete</button>
            </form>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>