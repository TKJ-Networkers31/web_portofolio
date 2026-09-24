<?php

declare(strict_types=1);

/**
 * public_html/admin/education.php
 *
 * Phase 4.3 — Education CRUD (list / create / edit / delete).
 * Same pattern as admin/profile.php: PDO prepared statements, existing
 * requireAdmin()/CSRF/e(), server-side validation, PRG after every
 * state-changing action.
 */

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/helpers.php';
require __DIR__ . '/../../app/admin-auth.php';

requireAdmin();

$pdo = db();

const EDUCATION_FIELDS = ['institution', 'degree', 'field', 'start_date', 'end_date', 'description', 'sort_order'];

const EDUCATION_MAX_LENGTHS = [
    'institution' => 191,
    'degree'      => 191,
    'field'       => 191,
];

const EDUCATION_DESCRIPTION_MAX_LENGTH = 2000;

function loadEducationRows(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, institution, degree, field, start_date, end_date, description, sort_order
         FROM education
         ORDER BY sort_order ASC, id ASC'
    );

    return $stmt ? $stmt->fetchAll() : [];
}

function loadEducationRow(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, institution, degree, field, start_date, end_date, description, sort_order
         FROM education WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

$errors  = [];
$success = '';
$editId  = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

$formValues = array_fill_keys(EDUCATION_FIELDS, '');
$formValues['sort_order'] = '0';
$editingRow = null;

if ($editId > 0) {
    $editingRow = loadEducationRow($pdo, $editId);
    if ($editingRow === null) {
        $errors[] = 'The record you tried to edit no longer exists.';
        $editId   = 0;
    } else {
        foreach (EDUCATION_FIELDS as $field) {
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
            $stmt = $pdo->prepare('DELETE FROM education WHERE id = :id');
            $stmt->execute(['id' => $deleteId]);

            header('Location: ' . adminUrl('education.php') . '?deleted=1');
            exit;
        }
    } elseif ($action === 'save') {
        $postId = (int) ($_POST['id'] ?? 0);

        foreach (EDUCATION_FIELDS as $field) {
            $formValues[$field] = trim((string) ($_POST[$field] ?? ''));
        }

        /* ---------- Server-side validation ---------- */
        if ($formValues['institution'] === '') {
            $errors[] = 'Institution is required.';
        }

        foreach (EDUCATION_MAX_LENGTHS as $field => $max) {
            if (mb_strlen($formValues[$field]) > $max) {
                $label    = ucfirst($field);
                $errors[] = "{$label} must be {$max} characters or fewer.";
            }
        }

        if (mb_strlen($formValues['description']) > EDUCATION_DESCRIPTION_MAX_LENGTH) {
            $errors[] = 'Description must be ' . EDUCATION_DESCRIPTION_MAX_LENGTH . ' characters or fewer.';
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
                'institution' => $formValues['institution'],
                'degree'      => $formValues['degree'] !== '' ? $formValues['degree'] : null,
                'field'       => $formValues['field'] !== '' ? $formValues['field'] : null,
                'start_date'  => $formValues['start_date'] !== '' ? $formValues['start_date'] : null,
                'end_date'    => $formValues['end_date'] !== '' ? $formValues['end_date'] : null,
                'description' => $formValues['description'] !== '' ? $formValues['description'] : null,
                'sort_order'  => (int) $formValues['sort_order'],
            ];

            if ($postId > 0) {
                // Only update if the id actually exists — avoids a no-op
                // UPDATE silently "succeeding" against a deleted record.
                $exists = loadEducationRow($pdo, $postId);
                if ($exists === null) {
                    $errors[] = 'The record you tried to save no longer exists.';
                } else {
                    $params['id'] = $postId;
                    $stmt = $pdo->prepare(
                        'UPDATE education
                         SET institution = :institution,
                             degree      = :degree,
                             field       = :field,
                             start_date  = :start_date,
                             end_date    = :end_date,
                             description = :description,
                             sort_order  = :sort_order,
                             updated_at  = CURRENT_TIMESTAMP
                         WHERE id = :id'
                    );
                    $stmt->execute($params);

                    header('Location: ' . adminUrl('education.php') . '?saved=1');
                    exit;
                }
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO education (institution, degree, field, start_date, end_date, description, sort_order)
                     VALUES (:institution, :degree, :field, :start_date, :end_date, :description, :sort_order)'
                );
                $stmt->execute($params);

                header('Location: ' . adminUrl('education.php') . '?saved=1');
                exit;
            }
        }

        // Validation failed: stay in the same create/edit state the user was in.
        $editId = $postId;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['saved'])) {
        $success = 'Education entry saved.';
    } elseif (isset($_GET['deleted'])) {
        $success = 'Education entry deleted.';
    }
}

$rows = loadEducationRows($pdo);

$pageTitle = 'Education';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-crud">
  <div class="admin-dashboard__head">
    <div>
      <p class="meta">Education</p>
      <h1><?= $editId > 0 ? 'Edit Entry' : 'Add Education' ?></h1>
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

  <form method="post" action="education.php" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editId > 0 ? (int) $editId : '' ?>">

    <label class="admin-field">
      <span>Institution</span>
      <input type="text" name="institution" value="<?= e($formValues['institution']) ?>" maxlength="191" required autofocus>
    </label>

    <label class="admin-field">
      <span>Degree</span>
      <input type="text" name="degree" value="<?= e($formValues['degree']) ?>" maxlength="191">
    </label>

    <label class="admin-field">
      <span>Field of study</span>
      <input type="text" name="field" value="<?= e($formValues['field']) ?>" maxlength="191">
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
      <textarea name="description" rows="4" maxlength="<?= EDUCATION_DESCRIPTION_MAX_LENGTH ?>"><?= e($formValues['description']) ?></textarea>
    </label>

    <label class="admin-field">
      <span>Sort order</span>
      <input type="number" name="sort_order" value="<?= e($formValues['sort_order']) ?>" step="1">
    </label>

    <div class="admin-form-actions">
      <button class="btn btn--primary" type="submit"><?= $editId > 0 ? 'Save Changes' : 'Add Entry' ?></button>
<?php if ($editId > 0): ?>
      <a class="link-text" href="education.php">Cancel</a>
<?php endif; ?>
    </div>
  </form>

  <h2 class="admin-crud__list-title">Existing Entries</h2>

<?php if (empty($rows)): ?>
  <p class="admin-dashboard__note">No education entries yet. Add one using the form above.</p>
<?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Institution</th>
          <th>Degree / Field</th>
          <th>Dates</th>
          <th>Order</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($rows as $row): ?>
        <tr>
          <td><?= e((string) $row['institution']) ?></td>
          <td><?= e(trim(($row['degree'] ?? '') . (($row['degree'] ?? '') !== '' && ($row['field'] ?? '') !== '' ? ' &middot; ' : '') . ($row['field'] ?? ''))) ?></td>
          <td class="meta"><?= e((string) ($row['start_date'] ?? '')) ?> &ndash; <?= e((string) ($row['end_date'] ?? '')) ?></td>
          <td class="meta"><?= (int) $row['sort_order'] ?></td>
          <td class="admin-table__actions">
            <a class="link-text" href="education.php?edit=<?= (int) $row['id'] ?>">Edit</a>
            <form method="post" action="education.php" onsubmit="return confirm('Delete this education entry? This cannot be undone.');">
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