<?php

declare(strict_types=1);

/**
 * public_html/admin/experience.php
 *
 * Phase 4.3 — Experience CRUD (list / create / edit / delete).
 * Mirrors admin/education.php exactly in structure and validation
 * approach, adapted to the `experience` table's fields.
 */

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/helpers.php';
require __DIR__ . '/../../app/admin-auth.php';

requireAdmin();

$pdo = db();

const EXPERIENCE_FIELDS = ['company', 'role', 'start_date', 'end_date', 'description', 'sort_order'];

const EXPERIENCE_MAX_LENGTHS = [
    'company' => 191,
    'role'    => 191,
];

const EXPERIENCE_DESCRIPTION_MAX_LENGTH = 2000;

function loadExperienceRows(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, company, role, start_date, end_date, description, sort_order
         FROM experience
         ORDER BY sort_order ASC, id ASC'
    );

    return $stmt ? $stmt->fetchAll() : [];
}

function loadExperienceRow(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, company, role, start_date, end_date, description, sort_order
         FROM experience WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

$errors  = [];
$success = '';
$editId  = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

$formValues = array_fill_keys(EXPERIENCE_FIELDS, '');
$formValues['sort_order'] = '0';
$editingRow = null;

if ($editId > 0) {
    $editingRow = loadExperienceRow($pdo, $editId);
    if ($editingRow === null) {
        $errors[] = 'The record you tried to edit no longer exists.';
        $editId   = 0;
    } else {
        foreach (EXPERIENCE_FIELDS as $field) {
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
            $stmt = $pdo->prepare('DELETE FROM experience WHERE id = :id');
            $stmt->execute(['id' => $deleteId]);

            header('Location: ' . adminUrl('experience.php') . '?deleted=1');
            exit;
        }
    } elseif ($action === 'save') {
        $postId = (int) ($_POST['id'] ?? 0);

        foreach (EXPERIENCE_FIELDS as $field) {
            $formValues[$field] = trim((string) ($_POST[$field] ?? ''));
        }

        /* ---------- Server-side validation ---------- */
        if ($formValues['company'] === '') {
            $errors[] = 'Company is required.';
        }

        if ($formValues['role'] === '') {
            $errors[] = 'Role is required.';
        }

        foreach (EXPERIENCE_MAX_LENGTHS as $field => $max) {
            if (mb_strlen($formValues[$field]) > $max) {
                $label    = ucfirst($field);
                $errors[] = "{$label} must be {$max} characters or fewer.";
            }
        }

        if (mb_strlen($formValues['description']) > EXPERIENCE_DESCRIPTION_MAX_LENGTH) {
            $errors[] = 'Description must be ' . EXPERIENCE_DESCRIPTION_MAX_LENGTH . ' characters or fewer.';
        }

        if (!isValidDateString($formValues['start_date'])) {
            $errors[] = 'Start date must be a valid date (YYYY-MM-DD).';
        }

        if (!isValidDateString($formValues['end_date'])) {
            $errors[] = 'End date must be a valid date (YYYY-MM-DD).';
        }

        if (
            $formValues['start_date'] !== ''
            && $formValues['end_date'] !== ''
            && isValidDateString($formValues['start_date'])
            && isValidDateString($formValues['end_date'])
            && $formValues['end_date'] < $formValues['start_date']
        ) {
            $errors[] = 'End date cannot be before start date.';
        }

        if ($formValues['sort_order'] === '' || !preg_match('/^-?\d+$/', $formValues['sort_order'])) {
            $errors[] = 'Sort order must be a whole number.';
        }

        if (empty($errors)) {
            $params = [
                'company'     => $formValues['company'],
                'role'        => $formValues['role'],
                'start_date'  => $formValues['start_date'] !== '' ? $formValues['start_date'] : null,
                'end_date'    => $formValues['end_date'] !== '' ? $formValues['end_date'] : null,
                'description' => $formValues['description'] !== '' ? $formValues['description'] : null,
                'sort_order'  => (int) $formValues['sort_order'],
            ];

            if ($postId > 0) {
                $exists = loadExperienceRow($pdo, $postId);
                if ($exists === null) {
                    $errors[] = 'The record you tried to save no longer exists.';
                } else {
                    $params['id'] = $postId;
                    $stmt = $pdo->prepare(
                        'UPDATE experience
                         SET company     = :company,
                             role        = :role,
                             start_date  = :start_date,
                             end_date    = :end_date,
                             description = :description,
                             sort_order  = :sort_order,
                             updated_at  = CURRENT_TIMESTAMP
                         WHERE id = :id'
                    );
                    $stmt->execute($params);

                    header('Location: ' . adminUrl('experience.php') . '?saved=1');
                    exit;
                }
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO experience (company, role, start_date, end_date, description, sort_order)
                     VALUES (:company, :role, :start_date, :end_date, :description, :sort_order)'
                );
                $stmt->execute($params);

                header('Location: ' . adminUrl('experience.php') . '?saved=1');
                exit;
            }
        }

        $editId = $postId;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['saved'])) {
        $success = 'Experience entry saved.';
    } elseif (isset($_GET['deleted'])) {
        $success = 'Experience entry deleted.';
    }
}

$rows = loadExperienceRows($pdo);

$pageTitle = 'Experience';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-crud">
  <div class="admin-dashboard__head">
    <div>
      <p class="meta">Experience</p>
      <h1><?= $editId > 0 ? 'Edit Entry' : 'Add Experience' ?></h1>
    </div>
  </div>

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

  <form method="post" action="experience.php" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editId > 0 ? (int) $editId : '' ?>">

    <label class="admin-field">
      <span>Company</span>
      <input type="text" name="company" value="<?= e($formValues['company']) ?>" maxlength="191" required autofocus>
    </label>

    <label class="admin-field">
      <span>Role</span>
      <input type="text" name="role" value="<?= e($formValues['role']) ?>" maxlength="191" required>
    </label>

    <div class="admin-field-row">
      <label class="admin-field">
        <span>Start date</span>
        <input type="date" name="start_date" value="<?= e($formValues['start_date']) ?>">
      </label>

      <label class="admin-field">
        <span>End date</span>
        <input type="date" name="end_date" value="<?= e($formValues['end_date']) ?>">
      </label>
    </div>

    <label class="admin-field">
      <span>Description</span>
      <textarea name="description" rows="4" maxlength="<?= EXPERIENCE_DESCRIPTION_MAX_LENGTH ?>"><?= e($formValues['description']) ?></textarea>
    </label>

    <label class="admin-field">
      <span>Sort order</span>
      <input type="number" name="sort_order" value="<?= e($formValues['sort_order']) ?>" step="1">
    </label>

    <div class="admin-form-actions">
      <button class="btn btn--primary" type="submit"><?= $editId > 0 ? 'Save Changes' : 'Add Entry' ?></button>
<?php if ($editId > 0): ?>
      <a class="link-text" href="experience.php">Cancel</a>
<?php endif; ?>
    </div>
  </form>

  <h2 class="admin-crud__list-title">Existing Entries</h2>

<?php if (empty($rows)): ?>
  <p class="admin-dashboard__note">No experience entries yet. Add one using the form above.</p>
<?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Company</th>
          <th>Role</th>
          <th>Dates</th>
          <th>Order</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($rows as $row): ?>
        <tr>
          <td><?= e((string) $row['company']) ?></td>
          <td><?= e((string) $row['role']) ?></td>
          <td class="meta"><?= e((string) ($row['start_date'] ?? '')) ?> &ndash; <?= e((string) ($row['end_date'] ?? '')) ?></td>
          <td class="meta"><?= (int) $row['sort_order'] ?></td>
          <td class="admin-table__actions">
            <a class="link-text" href="experience.php?edit=<?= (int) $row['id'] ?>">Edit</a>
            <form method="post" action="experience.php" onsubmit="return confirm('Delete this experience entry? This cannot be undone.');">
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