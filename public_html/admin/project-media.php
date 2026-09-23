<?php

declare(strict_types=1);

/**
 * public_html/admin/project-media.php
 *
 * Phase 4.5 — Project Media admin (list / create / edit / delete),
 * scoped to a single project via ?project_id=ID.
 *
 * Uses the existing `project_media` table only (project_id, type, path,
 * alt_text, sort_order). No new upload engine: "path" is a plain text
 * field (an existing URL or an already-uploaded file's relative path),
 * matching what the schema already supports. Deleting a media row never
 * touches the filesystem — only the DB record, since there is no
 * established storage contract yet to safely do otherwise.
 */

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/helpers.php';
require __DIR__ . '/../../app/admin-auth.php';

requireAdmin();

$pdo = db();

const MEDIA_FIELDS = ['type', 'path', 'alt_text', 'sort_order'];

const MEDIA_ALLOWED_TYPES = ['image', 'video', 'document'];

const MEDIA_MAX_LENGTHS = [
    'type'     => 32,
    'path'     => 255,
    'alt_text' => 255,
];

function loadProjectById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, title, slug FROM projects WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function loadMediaRows(PDO $pdo, int $projectId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, project_id, type, path, alt_text, sort_order
         FROM project_media
         WHERE project_id = :project_id
         ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute(['project_id' => $projectId]);

    return $stmt->fetchAll();
}

/** Loaded scoped to a project so one project's media can never be edited/deleted via another project's page. */
function loadMediaRow(PDO $pdo, int $id, int $projectId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, project_id, type, path, alt_text, sort_order
         FROM project_media WHERE id = :id AND project_id = :project_id LIMIT 1'
    );
    $stmt->execute(['id' => $id, 'project_id' => $projectId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

$projectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0;
if ($projectId <= 0) {
    $projectId = isset($_POST['project_id']) ? (int) $_POST['project_id'] : 0;
}

$project = $projectId > 0 ? loadProjectById($pdo, $projectId) : null;

if ($project === null) {
    $pageTitle = 'Project Media';
    require __DIR__ . '/includes/admin-header.php';
    ?>
    <div class="admin-crud">
      <div class="admin-dashboard__head">
        <div>
          <p class="meta">Project Media</p>
          <h1>Project Not Found</h1>
        </div>
      </div>
      <p class="admin-alert" role="alert">
        No project matches the given ID. <a class="link-text" href="projects.php">Back to Projects</a>.
      </p>
    </div>
    <?php
    require __DIR__ . '/includes/admin-footer.php';
    exit;
}

$errors  = [];
$success = '';
$editId  = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

$formValues = array_fill_keys(MEDIA_FIELDS, '');
$formValues['type']       = 'image';
$formValues['sort_order'] = '0';
$editingRow = null;

if ($editId > 0) {
    $editingRow = loadMediaRow($pdo, $editId, $projectId);
    if ($editingRow === null) {
        $errors[] = 'The media item you tried to edit does not belong to this project or no longer exists.';
        $editId   = 0;
    } else {
        foreach (MEDIA_FIELDS as $field) {
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
            $errors[] = 'Invalid media item.';
        } else {
            // Scoped to project_id so a crafted id belonging to another
            // project can never be deleted from this page.
            $stmt = $pdo->prepare('DELETE FROM project_media WHERE id = :id AND project_id = :project_id');
            $stmt->execute(['id' => $deleteId, 'project_id' => $projectId]);

            header('Location: /admin/project-media.php?project_id=' . $projectId . '&deleted=1');
            exit;
        }
    } elseif ($action === 'save') {
        $postId = (int) ($_POST['id'] ?? 0);

        foreach (MEDIA_FIELDS as $field) {
            $formValues[$field] = trim((string) ($_POST[$field] ?? ''));
        }

        /* ---------- Server-side validation ---------- */
        if ($formValues['path'] === '') {
            $errors[] = 'Path / URL is required.';
        }

        if ($formValues['type'] === '') {
            $formValues['type'] = 'image';
        } elseif (!in_array($formValues['type'], MEDIA_ALLOWED_TYPES, true)) {
            $errors[] = 'Type must be one of: ' . implode(', ', MEDIA_ALLOWED_TYPES) . '.';
        }

        foreach (MEDIA_MAX_LENGTHS as $field => $max) {
            if (mb_strlen($formValues[$field]) > $max) {
                $label    = ucfirst(str_replace('_', ' ', $field));
                $errors[] = "{$label} must be {$max} characters or fewer.";
            }
        }

        // path is a relative site path ("assets/media/x.jpg") or a full
        // http(s) URL — reject anything else (no arbitrary local paths,
        // no path traversal sequences, no other schemes).
        if (
            $formValues['path'] !== ''
            && str_contains($formValues['path'], "\0")
        ) {
            $errors[] = 'Path is invalid.';
        } elseif ($formValues['path'] !== '' && str_contains($formValues['path'], '..')) {
            $errors[] = 'Path may not contain ".." segments.';
        } elseif (
            $formValues['path'] !== ''
            && preg_match('#^[a-z][a-z0-9+.-]*://#i', $formValues['path'])
            && filter_var($formValues['path'], FILTER_VALIDATE_URL) === false
        ) {
            $errors[] = 'Path must be a relative path or a valid URL.';
        }

        if ($formValues['sort_order'] === '' || !preg_match('/^-?\d+$/', $formValues['sort_order'])) {
            $errors[] = 'Sort order must be a whole number.';
        }

        if (empty($errors)) {
            $params = [
                'project_id' => $projectId,
                'type'       => $formValues['type'],
                'path'       => $formValues['path'],
                'alt_text'   => $formValues['alt_text'] !== '' ? $formValues['alt_text'] : null,
                'sort_order' => (int) $formValues['sort_order'],
            ];

            if ($postId > 0) {
                // Only update if this media row actually belongs to this project.
                $exists = loadMediaRow($pdo, $postId, $projectId);
                if ($exists === null) {
                    $errors[] = 'The media item you tried to save does not belong to this project or no longer exists.';
                } else {
                    $params['id'] = $postId;
                    $stmt = $pdo->prepare(
                        'UPDATE project_media
                         SET type = :type, path = :path, alt_text = :alt_text, sort_order = :sort_order
                         WHERE id = :id AND project_id = :project_id'
                    );
                    $stmt->execute($params);

                    header('Location: /admin/project-media.php?project_id=' . $projectId . '&saved=1');
                    exit;
                }
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO project_media (project_id, type, path, alt_text, sort_order)
                     VALUES (:project_id, :type, :path, :alt_text, :sort_order)'
                );
                $stmt->execute($params);

                header('Location: /admin/project-media.php?project_id=' . $projectId . '&saved=1');
                exit;
            }
        }

        $editId = $postId;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['saved'])) {
        $success = 'Media item saved.';
    } elseif (isset($_GET['deleted'])) {
        $success = 'Media item deleted.';
    }
}

$rows = loadMediaRows($pdo, $projectId);

$pageTitle = 'Project Media';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-crud">
  <div class="admin-dashboard__head">
    <div>
      <p class="meta">Project Media &middot; <?= e((string) $project['title']) ?></p>
      <h1><?= $editId > 0 ? 'Edit Media Item' : 'Add Media Item' ?></h1>
    </div>
  </div>

  <p class="admin-dashboard__note">
    <a class="link-text" href="projects.php">&larr; Back to Projects</a>
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

  <form method="post" action="project-media.php" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="project_id" value="<?= (int) $projectId ?>">
    <input type="hidden" name="id" value="<?= $editId > 0 ? (int) $editId : '' ?>">

    <label class="admin-field">
      <span>Type</span>
      <select name="type">
<?php foreach (MEDIA_ALLOWED_TYPES as $type): ?>
        <option value="<?= e($type) ?>"<?= $formValues['type'] === $type ? ' selected' : '' ?>><?= e(ucfirst($type)) ?></option>
<?php endforeach; ?>
      </select>
    </label>

    <label class="admin-field">
      <span>Path or URL</span>
      <input type="text" name="path" value="<?= e($formValues['path']) ?>" maxlength="255" placeholder="assets/media/example.jpg or https://..." required>
    </label>

    <label class="admin-field">
      <span>Alt text</span>
      <input type="text" name="alt_text" value="<?= e($formValues['alt_text']) ?>" maxlength="255">
    </label>

    <label class="admin-field">
      <span>Sort order</span>
      <input type="number" name="sort_order" value="<?= e($formValues['sort_order']) ?>" step="1">
    </label>

    <div class="admin-form-actions">
      <button class="btn btn--primary" type="submit"><?= $editId > 0 ? 'Save Changes' : 'Add Media' ?></button>
<?php if ($editId > 0): ?>
      <a class="link-text" href="project-media.php?project_id=<?= (int) $projectId ?>">Cancel</a>
<?php endif; ?>
    </div>
  </form>

  <h2 class="admin-crud__list-title">Existing Media</h2>

<?php if (empty($rows)): ?>
  <p class="admin-dashboard__note">No media items for this project yet. Add one using the form above.</p>
<?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Type</th>
          <th>Path</th>
          <th>Alt text</th>
          <th>Order</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($rows as $row): ?>
        <tr>
          <td class="meta"><?= e((string) $row['type']) ?></td>
          <td><?= e((string) $row['path']) ?></td>
          <td class="meta"><?= e((string) ($row['alt_text'] ?? '')) ?></td>
          <td class="meta"><?= (int) $row['sort_order'] ?></td>
          <td class="admin-table__actions">
            <a class="link-text" href="project-media.php?project_id=<?= (int) $projectId ?>&edit=<?= (int) $row['id'] ?>">Edit</a>
            <form method="post" action="project-media.php" onsubmit="return confirm('Delete this media item? This cannot be undone.');">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="project_id" value="<?= (int) $projectId ?>">
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