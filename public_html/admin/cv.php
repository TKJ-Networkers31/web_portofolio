<?php

declare(strict_types=1);

/**
 * public_html/admin/cv.php
 *
 * Phase 4.6 — CV / document management (list / create / edit / delete /
 * set active). Uses the existing `documents` table, scoped to
 * type = 'cv' (schema unchanged). No upload engine: file_path stays a
 * plain text field, same convention as project_media.path.
 *
 * Only one CV may have is_current = 1 at a time, enforced in a
 * transaction on every save/set-active — not just at the UI level.
 */

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/helpers.php';
require __DIR__ . '/../../app/admin-auth.php';

requireAdmin();

$pdo = db();

const CV_TYPE = 'cv';

const CV_FIELDS = ['title', 'file_path', 'is_current'];

const CV_MAX_LENGTHS = [
    'title'     => 191,
    'file_path' => 255,
];

function loadCvRows(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        'SELECT id, title, file_path, is_current, uploaded_at
         FROM documents
         WHERE type = :type
         ORDER BY is_current DESC, uploaded_at DESC, id DESC'
    );
    $stmt->execute(['type' => CV_TYPE]);

    return $stmt->fetchAll();
}

function loadCvRow(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, title, file_path, is_current, uploaded_at
         FROM documents WHERE id = :id AND type = :type LIMIT 1'
    );
    $stmt->execute(['id' => $id, 'type' => CV_TYPE]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/** Makes $id the only is_current = 1 row of type 'cv'. All-or-nothing. */
function makeCvActive(PDO $pdo, int $id): void
{
    $pdo->beginTransaction();
    try {
        $clear = $pdo->prepare('UPDATE documents SET is_current = 0 WHERE type = :type');
        $clear->execute(['type' => CV_TYPE]);

        $set = $pdo->prepare('UPDATE documents SET is_current = 1 WHERE id = :id AND type = :type');
        $set->execute(['id' => $id, 'type' => CV_TYPE]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

$errors  = [];
$success = '';
$editId  = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

$formValues = array_fill_keys(CV_FIELDS, '');
$editingRow = null;

if ($editId > 0) {
    $editingRow = loadCvRow($pdo, $editId);
    if ($editingRow === null) {
        $errors[] = 'The document you tried to edit no longer exists.';
        $editId   = 0;
    } else {
        $formValues['title']      = (string) ($editingRow['title'] ?? '');
        $formValues['file_path']  = (string) ($editingRow['file_path'] ?? '');
        $formValues['is_current'] = !empty($editingRow['is_current']) ? '1' : '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please reload the page and try again.';
    } elseif ($action === 'delete') {
        $deleteId = (int) ($_POST['id'] ?? 0);

        if ($deleteId <= 0) {
            $errors[] = 'Invalid document.';
        } else {
            $existing = loadCvRow($pdo, $deleteId);
            if ($existing === null) {
                $errors[] = 'That document does not exist.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM documents WHERE id = :id AND type = :type');
                $stmt->execute(['id' => $deleteId, 'type' => CV_TYPE]);

                header('Location: ' . adminUrl('cv.php') . '?deleted=1');
                exit;
            }
        }
    } elseif ($action === 'set_active') {
        $activeId = (int) ($_POST['id'] ?? 0);
        $existing = $activeId > 0 ? loadCvRow($pdo, $activeId) : null;

        if ($existing === null) {
            $errors[] = 'That document does not exist.';
        } else {
            makeCvActive($pdo, $activeId);

            header('Location: ' . adminUrl('cv.php') . '?activated=1');
            exit;
        }
    } elseif ($action === 'save') {
        $postId = (int) ($_POST['id'] ?? 0);

        $formValues['title']      = trim((string) ($_POST['title'] ?? ''));
        $formValues['file_path']  = trim((string) ($_POST['file_path'] ?? ''));
        $formValues['is_current'] = !empty($_POST['is_current']) ? '1' : '';

        /* ---------- Server-side validation ---------- */
        if ($formValues['file_path'] === '') {
            $errors[] = 'File path / URL is required.';
        } elseif (str_contains($formValues['file_path'], "\0")) {
            $errors[] = 'File path is invalid.';
        } elseif (str_contains($formValues['file_path'], '..')) {
            $errors[] = 'File path may not contain ".." segments.';
        } elseif (
            preg_match('#^[a-z][a-z0-9+.-]*://#i', $formValues['file_path'])
            && filter_var($formValues['file_path'], FILTER_VALIDATE_URL) === false
        ) {
            $errors[] = 'File path must be a relative path or a valid URL.';
        }

        foreach (CV_MAX_LENGTHS as $field => $max) {
            if (mb_strlen($formValues[$field]) > $max) {
                $label    = ucfirst(str_replace('_', ' ', $field));
                $errors[] = "{$label} must be {$max} characters or fewer.";
            }
        }

        if (empty($errors)) {
            $wantsActive = $formValues['is_current'] === '1';

            if ($postId > 0) {
                $exists = loadCvRow($pdo, $postId);
                if ($exists === null) {
                    $errors[] = 'The document you tried to save no longer exists.';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE documents
                         SET title = :title, file_path = :file_path
                         WHERE id = :id AND type = :type'
                    );
                    $stmt->execute([
                        'title'     => $formValues['title'] !== '' ? $formValues['title'] : null,
                        'file_path' => $formValues['file_path'],
                        'id'        => $postId,
                        'type'      => CV_TYPE,
                    ]);

                    if ($wantsActive) {
                        makeCvActive($pdo, $postId);
                    }

                    header('Location: ' . adminUrl('cv.php')?saved=1');
                    exit;
                }
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO documents (type, title, file_path, is_current)
                     VALUES (:type, :title, :file_path, 0)'
                );
                $stmt->execute([
                    'type'      => CV_TYPE,
                    'title'     => $formValues['title'] !== '' ? $formValues['title'] : null,
                    'file_path' => $formValues['file_path'],
                ]);

                if ($wantsActive) {
                    $newId = (int) $pdo->lastInsertId();
                    makeCvActive($pdo, $newId);
                }

                header('Location: ' . adminUrl('cv.php')?saved=1');
                exit;
            }
        }

        $editId = $postId;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['saved'])) {
        $success = 'CV saved.';
    } elseif (isset($_GET['deleted'])) {
        $success = 'CV deleted.';
    } elseif (isset($_GET['activated'])) {
        $success = 'CV set as active.';
    }
}

$rows = loadCvRows($pdo);

$pageTitle = 'CV';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-crud">
  <div class="admin-dashboard__head">
    <div>
      <p class="meta">CV / Documents</p>
      <h1><?= $editId > 0 ? 'Edit CV' : 'Add CV' ?></h1>
    </div>
  </div>

  <p class="admin-dashboard__note">
    Manages the CMS <code>documents</code> table (type <code>cv</code>) only.
    File path is plain text — an existing URL or an already-uploaded
    file's relative path — same as Project Media. Only one CV can be
    active at a time.
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

  <form method="post" action="cv.php" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editId > 0 ? (int) $editId : '' ?>">

    <label class="admin-field">
      <span>Title (optional)</span>
      <input type="text" name="title" value="<?= e($formValues['title']) ?>" maxlength="191" placeholder="e.g. CV 2026 (EN)" autofocus>
    </label>

    <label class="admin-field">
      <span>File path or URL</span>
      <input type="text" name="file_path" value="<?= e($formValues['file_path']) ?>" maxlength="255" placeholder="assets/documents/cv.pdf or https://..." required>
    </label>

    <label class="admin-field admin-field--checkbox">
      <span>Set as active CV</span>
      <input type="checkbox" name="is_current" value="1"<?= $formValues['is_current'] === '1' ? ' checked' : '' ?>>
    </label>

    <div class="admin-form-actions">
      <button class="btn btn--primary" type="submit"><?= $editId > 0 ? 'Save Changes' : 'Add CV' ?></button>
<?php if ($editId > 0): ?>
      <a class="link-text" href="cv.php">Cancel</a>
<?php endif; ?>
    </div>
  </form>

  <h2 class="admin-crud__list-title">Existing CVs</h2>

<?php if (empty($rows)): ?>
  <p class="admin-dashboard__note">No CV documents yet. Add one using the form above.</p>
<?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Title</th>
          <th>File path</th>
          <th>Active</th>
          <th>Uploaded</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($rows as $row): ?>
        <tr>
          <td><?= e((string) ($row['title'] ?? '')) ?></td>
          <td><?= e((string) $row['file_path']) ?></td>
          <td class="meta"><?= !empty($row['is_current']) ? 'Yes' : 'No' ?></td>
          <td class="meta"><?= e((string) $row['uploaded_at']) ?></td>
          <td class="admin-table__actions">
            <a class="link-text" href="cv.php?edit=<?= (int) $row['id'] ?>">Edit</a>
<?php if (empty($row['is_current'])): ?>
            <form method="post" action="cv.php">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="set_active">
              <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
              <button class="link-text" type="submit">Set Active</button>
            </form>
<?php endif; ?>
            <form method="post" action="cv.php" onsubmit="return confirm('Delete this CV? This cannot be undone.');">
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