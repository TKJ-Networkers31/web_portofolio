<?php

declare(strict_types=1);

/**
 * public_html/admin/contact.php
 *
 * Phase 4.6 — Contact CRUD (list / create / edit / delete).
 * Uses the existing `contacts` table (schema unchanged). This module
 * manages non-social contact channels only (email, phone, whatsapp,
 * address, other) — social links share the same table but are managed
 * separately by admin/social.php, scoped by `type`.
 */

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/helpers.php';
require __DIR__ . '/../../app/admin-auth.php';

requireAdmin();

$pdo = db();

const CONTACT_FIELDS = ['label', 'type', 'value', 'icon', 'is_visible', 'sort_order'];

const CONTACT_TYPES = ['email', 'phone', 'whatsapp', 'address', 'other'];

const CONTACT_MAX_LENGTHS = [
    'label' => 191,
    'type'  => 64,
    'value' => 255,
    'icon'  => 64,
];

function contactTypePlaceholders(): string
{
    return implode(',', array_fill(0, count(CONTACT_TYPES), '?'));
}

/** Scoped to CONTACT_TYPES so this page can never see/edit a Social row. */
function loadContactRows(PDO $pdo): array
{
    $sql = 'SELECT id, label, type, value, icon, is_visible, sort_order
            FROM contacts
            WHERE type IN (' . contactTypePlaceholders() . ')
            ORDER BY sort_order ASC, id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(CONTACT_TYPES);

    return $stmt->fetchAll();
}

function loadContactRow(PDO $pdo, int $id): ?array
{
    $sql = 'SELECT id, label, type, value, icon, is_visible, sort_order
            FROM contacts
            WHERE id = ? AND type IN (' . contactTypePlaceholders() . ')
            LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$id], CONTACT_TYPES));
    $row = $stmt->fetch();

    return $row ?: null;
}

$errors  = [];
$success = '';
$editId  = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

$formValues = array_fill_keys(CONTACT_FIELDS, '');
$formValues['type']       = CONTACT_TYPES[0];
$formValues['sort_order'] = '0';
$formValues['is_visible'] = '1';
$editingRow = null;

if ($editId > 0) {
    $editingRow = loadContactRow($pdo, $editId);
    if ($editingRow === null) {
        $errors[] = 'The record you tried to edit no longer exists.';
        $editId   = 0;
    } else {
        foreach (CONTACT_FIELDS as $field) {
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
            // Scoped lookup: a Social row sharing this id/table can never
            // be deleted from the Contact page.
            $existing = loadContactRow($pdo, $deleteId);
            if ($existing === null) {
                $errors[] = 'That record does not belong to Contact or no longer exists.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM contacts WHERE id = :id');
                $stmt->execute(['id' => $deleteId]);

                header('Location: ' . adminUrl('contact.php') . '?deleted=1');
                exit;
            }
        }
    } elseif ($action === 'save') {
        $postId = (int) ($_POST['id'] ?? 0);

        foreach (CONTACT_FIELDS as $field) {
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

        if ($formValues['type'] === '' || !in_array($formValues['type'], CONTACT_TYPES, true)) {
            $errors[] = 'Type must be one of: ' . implode(', ', CONTACT_TYPES) . '.';
        }

        if ($formValues['value'] === '') {
            $errors[] = 'Value is required.';
        }

        if (
            $formValues['type'] === 'email'
            && $formValues['value'] !== ''
            && filter_var($formValues['value'], FILTER_VALIDATE_EMAIL) === false
        ) {
            $errors[] = 'Value must be a valid email address for type "email".';
        }

        foreach (CONTACT_MAX_LENGTHS as $field => $max) {
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
                $exists = loadContactRow($pdo, $postId);
                if ($exists === null) {
                    $errors[] = 'The record you tried to save does not belong to Contact or no longer exists.';
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

                    header('Location: ' . adminUrl('contact.php') . '?saved=1');
                    exit;
                }
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO contacts (label, type, value, icon, is_visible, sort_order)
                     VALUES (:label, :type, :value, :icon, :is_visible, :sort_order)'
                );
                $stmt->execute($params);

                header('Location: ' . adminUrl('contact.php') . '?saved=1');
                exit;
            }
        }

        $editId = $postId;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['saved'])) {
        $success = 'Contact saved.';
    } elseif (isset($_GET['deleted'])) {
        $success = 'Contact deleted.';
    }
}

$rows = loadContactRows($pdo);

$pageTitle = 'Contact';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-crud">
  <div class="admin-dashboard__head">
    <div>
      <p class="meta">Contact</p>
      <h1><?= $editId > 0 ? 'Edit Contact' : 'Add Contact' ?></h1>
    </div>
  </div>

  <p class="admin-dashboard__note">
    Non-social contact channels (email, phone, WhatsApp, address). Social
    links share the same <code>contacts</code> table but are managed
    separately on the <a class="link-text" href="social.php">Social</a> page.
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

  <form method="post" action="contact.php" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editId > 0 ? (int) $editId : '' ?>">

    <label class="admin-field">
      <span>Label</span>
      <input type="text" name="label" value="<?= e($formValues['label']) ?>" maxlength="191" required autofocus placeholder="e.g. Email, Phone, Office Address">
    </label>

    <div class="admin-field-row">
      <label class="admin-field">
        <span>Type</span>
        <select name="type">
<?php foreach (CONTACT_TYPES as $type): ?>
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
      <span>Value</span>
      <input type="text" name="value" value="<?= e($formValues['value']) ?>" maxlength="255" required placeholder="e.g. hello@example.com, +62 812..., Jl. Example No. 1">
    </label>

    <label class="admin-field">
      <span>Icon (optional key, e.g. mail)</span>
      <input type="text" name="icon" value="<?= e($formValues['icon']) ?>" maxlength="64">
    </label>

    <label class="admin-field">
      <span>Sort order</span>
      <input type="number" name="sort_order" value="<?= e($formValues['sort_order']) ?>" step="1">
    </label>

    <div class="admin-form-actions">
      <button class="btn btn--primary" type="submit"><?= $editId > 0 ? 'Save Changes' : 'Add Contact' ?></button>
<?php if ($editId > 0): ?>
      <a class="link-text" href="contact.php">Cancel</a>
<?php endif; ?>
    </div>
  </form>

  <h2 class="admin-crud__list-title">Existing Contacts</h2>

<?php if (empty($rows)): ?>
  <p class="admin-dashboard__note">No contact entries yet. Add one using the form above.</p>
<?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Label</th>
          <th>Type</th>
          <th>Value</th>
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
          <td><?= e((string) $row['value']) ?></td>
          <td class="meta"><?= !empty($row['is_visible']) ? 'Yes' : 'No' ?></td>
          <td class="meta"><?= (int) $row['sort_order'] ?></td>
          <td class="admin-table__actions">
            <a class="link-text" href="contact.php?edit=<?= (int) $row['id'] ?>">Edit</a>
            <form method="post" action="contact.php" onsubmit="return confirm('Delete this contact? This cannot be undone.');">
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