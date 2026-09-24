<?php

declare(strict_types=1);

/**
 * public_html/admin/certifications.php
 *
 * Phase 4.4 — Certifications CRUD (list / create / edit / delete).
 * Same pattern as admin/education.php, admin/experience.php, and
 * admin/skills.php: PDO prepared statements, existing
 * requireAdmin()/CSRF/e(), server-side validation, PRG after every
 * state-changing action.
 */

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/helpers.php';
require __DIR__ . '/../../app/admin-auth.php';

requireAdmin();

$pdo = db();

const CERTIFICATION_FIELDS = ['name', 'issuer', 'issue_date', 'expire_date', 'credential_url', 'sort_order'];

const CERTIFICATION_MAX_LENGTHS = [
    'name'           => 191,
    'issuer'         => 191,
    'credential_url' => 255,
];

function loadCertificationRows(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, name, issuer, issue_date, expire_date, credential_url, sort_order
         FROM certifications
         ORDER BY sort_order ASC, id ASC'
    );

    return $stmt ? $stmt->fetchAll() : [];
}

function loadCertificationRow(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, issuer, issue_date, expire_date, credential_url, sort_order
         FROM certifications WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

$errors  = [];
$success = '';
$editId  = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

$formValues = array_fill_keys(CERTIFICATION_FIELDS, '');
$formValues['sort_order'] = '0';
$editingRow = null;

if ($editId > 0) {
    $editingRow = loadCertificationRow($pdo, $editId);
    if ($editingRow === null) {
        $errors[] = 'The record you tried to edit no longer exists.';
        $editId   = 0;
    } else {
        foreach (CERTIFICATION_FIELDS as $field) {
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
            $stmt = $pdo->prepare('DELETE FROM certifications WHERE id = :id');
            $stmt->execute(['id' => $deleteId]);

            header('Location: ' . adminUrl('certifications.php') . '?deleted=1');
            exit;
        }
    } elseif ($action === 'save') {
        $postId = (int) ($_POST['id'] ?? 0);

        foreach (CERTIFICATION_FIELDS as $field) {
            $formValues[$field] = trim((string) ($_POST[$field] ?? ''));
        }

        /* ---------- Server-side validation ---------- */
        if ($formValues['name'] === '') {
            $errors[] = 'Name is required.';
        }

        foreach (CERTIFICATION_MAX_LENGTHS as $field => $max) {
            if (mb_strlen($formValues[$field]) > $max) {
                $label    = ucfirst(str_replace('_', ' ', $field));
                $errors[] = "{$label} must be {$max} characters or fewer.";
            }
        }

        if (!isValidDateString($formValues['issue_date'])) {
            $errors[] = 'Issue date must be a valid date (YYYY-MM-DD).';
        }

        if (!isValidDateString($formValues['expire_date'])) {
            $errors[] = 'Expiry date must be a valid date (YYYY-MM-DD).';
        }

        if (
            $formValues['issue_date'] !== ''
            && $formValues['expire_date'] !== ''
            && isValidDateString($formValues['issue_date'])
            && isValidDateString($formValues['expire_date'])
            && $formValues['expire_date'] < $formValues['issue_date']
        ) {
            $errors[] = 'Expiry date cannot be before issue date.';
        }

        if (
            $formValues['credential_url'] !== ''
            && filter_var($formValues['credential_url'], FILTER_VALIDATE_URL) === false
        ) {
            $errors[] = 'Credential URL must be a valid URL.';
        }

        if ($formValues['sort_order'] === '' || !preg_match('/^-?\d+$/', $formValues['sort_order'])) {
            $errors[] = 'Sort order must be a whole number.';
        }

        if (empty($errors)) {
            $params = [
                'name'           => $formValues['name'],
                'issuer'         => $formValues['issuer'] !== '' ? $formValues['issuer'] : null,
                'issue_date'     => $formValues['issue_date'] !== '' ? $formValues['issue_date'] : null,
                'expire_date'    => $formValues['expire_date'] !== '' ? $formValues['expire_date'] : null,
                'credential_url' => $formValues['credential_url'] !== '' ? $formValues['credential_url'] : null,
                'sort_order'     => (int) $formValues['sort_order'],
            ];

            if ($postId > 0) {
                $exists = loadCertificationRow($pdo, $postId);
                if ($exists === null) {
                    $errors[] = 'The record you tried to save no longer exists.';
                } else {
                    $params['id'] = $postId;
                    $stmt = $pdo->prepare(
                        'UPDATE certifications
                         SET name           = :name,
                             issuer         = :issuer,
                             issue_date     = :issue_date,
                             expire_date    = :expire_date,
                             credential_url = :credential_url,
                             sort_order     = :sort_order,
                             updated_at     = CURRENT_TIMESTAMP
                         WHERE id = :id'
                    );
                    $stmt->execute($params);

                    header('Location: ' . adminUrl('certifications.php') . '?saved=1');
                    exit;
                }
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO certifications (name, issuer, issue_date, expire_date, credential_url, sort_order)
                     VALUES (:name, :issuer, :issue_date, :expire_date, :credential_url, :sort_order)'
                );
                $stmt->execute($params);

                header('Location: ' . adminUrl('certifications.php') . '?saved=1');
                exit;
            }
        }

        $editId = $postId;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['saved'])) {
        $success = 'Certification saved.';
    } elseif (isset($_GET['deleted'])) {
        $success = 'Certification deleted.';
    }
}

$rows = loadCertificationRows($pdo);

$pageTitle = 'Certifications';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-crud">
  <div class="admin-dashboard__head">
    <div>
      <p class="meta">Certifications</p>
      <h1><?= $editId > 0 ? 'Edit Certification' : 'Add Certification' ?></h1>
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

  <form method="post" action="certifications.php" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editId > 0 ? (int) $editId : '' ?>">

    <label class="admin-field">
      <span>Name</span>
      <input type="text" name="name" value="<?= e($formValues['name']) ?>" maxlength="191" required autofocus>
    </label>

    <label class="admin-field">
      <span>Issuer</span>
      <input type="text" name="issuer" value="<?= e($formValues['issuer']) ?>" maxlength="191">
    </label>

    <div class="admin-field-row">
      <label class="admin-field">
        <span>Issue date</span>
        <input type="date" name="issue_date" value="<?= e($formValues['issue_date']) ?>">
      </label>

      <label class="admin-field">
        <span>Expiry date</span>
        <input type="date" name="expire_date" value="<?= e($formValues['expire_date']) ?>">
      </label>
    </div>

    <label class="admin-field">
      <span>Credential URL</span>
      <input type="url" name="credential_url" value="<?= e($formValues['credential_url']) ?>" maxlength="255" placeholder="https://...">
    </label>

    <label class="admin-field">
      <span>Sort order</span>
      <input type="number" name="sort_order" value="<?= e($formValues['sort_order']) ?>" step="1">
    </label>

    <div class="admin-form-actions">
      <button class="btn btn--primary" type="submit"><?= $editId > 0 ? 'Save Changes' : 'Add Certification' ?></button>
<?php if ($editId > 0): ?>
      <a class="link-text" href="certifications.php">Cancel</a>
<?php endif; ?>
    </div>
  </form>

  <h2 class="admin-crud__list-title">Existing Certifications</h2>

<?php if (empty($rows)): ?>
  <p class="admin-dashboard__note">No certifications yet. Add one using the form above.</p>
<?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Issuer</th>
          <th>Dates</th>
          <th>Order</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($rows as $row): ?>
        <tr>
          <td>
            <?= e((string) $row['name']) ?>
<?php if (!empty($row['credential_url'])): ?>
            <br><a class="link-text" href="<?= e((string) $row['credential_url']) ?>" target="_blank" rel="noopener noreferrer">View credential</a>
<?php endif; ?>
          </td>
          <td class="meta"><?= e((string) ($row['issuer'] ?? '')) ?></td>
          <td class="meta"><?= e((string) ($row['issue_date'] ?? '')) ?> &ndash; <?= e((string) ($row['expire_date'] ?? '')) ?></td>
          <td class="meta"><?= (int) $row['sort_order'] ?></td>
          <td class="admin-table__actions">
            <a class="link-text" href="certifications.php?edit=<?= (int) $row['id'] ?>">Edit</a>
            <form method="post" action="certifications.php" onsubmit="return confirm('Delete this certification? This cannot be undone.');">
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