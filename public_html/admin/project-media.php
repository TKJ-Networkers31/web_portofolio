<?php

declare(strict_types=1);

/**
 * public_html/admin/project-media.php
 *
 * Phase 4.5 — Project Media admin, scoped to a single project via
 * ?project_id=ID. Uses the existing `project_media` table only.
 *
 * PHASE 5.3 additions:
 *   - "Upload New Media" — a real file picker; uploads through the same
 *     storeUploadedMedia() the Media Library uses (auto filename/MIME/
 *     size), auto-creates a `media` library record, and immediately
 *     inserts the matching project_media row (type derived from the
 *     detected MIME, alt text + sort order from the small upload form).
 *   - "Add Existing Media" — opens a picker over everything already in
 *     the Media Library; picking an item fills the existing manual
 *     path/type/alt fields below instead of the user typing them, then
 *     the existing "Add Media" button submits it as before.
 * The original manual path/type/alt_text/sort_order form is kept as-is
 * (still the only way to reference an asset that isn't uploaded through
 * this tool, e.g. a bare external URL) — nothing existing was removed.
 * Edit/Delete for already-added rows are unchanged.
 */

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/helpers.php';
require __DIR__ . '/../../app/admin-auth.php';
require __DIR__ . '/../../app/media.php';

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

function nextSortOrder(PDO $pdo, int $projectId): int
{
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 AS next FROM project_media WHERE project_id = :project_id');
    $stmt->execute(['project_id' => $projectId]);
    $row = $stmt->fetch();

    return (int) ($row['next'] ?? 0);
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
$formValues['sort_order'] = (string) nextSortOrder($pdo, $projectId);
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

            header('Location: ' . adminUrl('project-media.php') . '?project_id=' . $projectId . '&deleted=1');
            exit;
        }
    } elseif ($action === 'upload') {
        $altText   = trim((string) ($_POST['alt_text'] ?? ''));
        $sortOrder = (string) ($_POST['sort_order'] ?? '');
        $result    = storeUploadedMedia($pdo, $_FILES['file'] ?? [], $altText);

        if (!$result['ok']) {
            $errors[] = $result['error'] ?? 'The upload failed.';
        } elseif ($sortOrder !== '' && !preg_match('/^-?\d+$/', $sortOrder)) {
            $errors[] = 'Sort order must be a whole number.';
        } else {
            $type = mediaCategoryToProjectMediaType(mediaTypeCategory($result['mime_type'] ?? ''));
            $stmt = $pdo->prepare(
                'INSERT INTO project_media (project_id, type, path, alt_text, sort_order)
                 VALUES (:project_id, :type, :path, :alt_text, :sort_order)'
            );
            $stmt->execute([
                'project_id' => $projectId,
                'type'       => $type,
                'path'       => $result['path'],
                'alt_text'   => $altText !== '' ? $altText : null,
                'sort_order' => $sortOrder !== '' ? (int) $sortOrder : nextSortOrder($pdo, $projectId),
            ]);

            header('Location: ' . adminUrl('project-media.php') . '?project_id=' . $projectId . '&saved=1');
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

                    header('Location: ' . adminUrl('project-media.php') . '?project_id=' . $projectId . '&saved=1');
                    exit;
                }
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO project_media (project_id, type, path, alt_text, sort_order)
                     VALUES (:project_id, :type, :path, :alt_text, :sort_order)'
                );
                $stmt->execute($params);

                header('Location: ' . adminUrl('project-media.php') . '?project_id=' . $projectId . '&saved=1');
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

/* Everything already in the Media Library, for "Add Existing Media". */
$libraryRows = mediaSearchRows($pdo, '', '');

$pageTitle = 'Project Media';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-crud">
  <div class="admin-dashboard__head">
    <div>
      <p class="meta">Project Media &middot; <?= e((string) $project['title']) ?> &middot; Documentation</p>
      <h1><?= $editId > 0 ? 'Edit Media Item' : 'Add Media' ?></h1>
    </div>
  </div>

  <p class="admin-dashboard__note">
    <a class="link-text" href="projects.php">&larr; Back to Projects</a> &middot;
    Supports any number of items — photos, screenshots, topology diagrams,
    PDFs, or other documentation. Nothing here is hardcoded to a fixed count.
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

<?php if ($editId === 0): ?>
  <div class="admin-tabs" data-admin-tabs>
    <div class="admin-tabs__nav" role="tablist">
      <button type="button" class="admin-tabs__tab is-active" role="tab" data-tab-target="pm-tab-upload">Upload New Media</button>
      <button type="button" class="admin-tabs__tab" role="tab" data-tab-target="pm-tab-existing">Add Existing Media</button>
    </div>

    <div class="admin-tabs__panel is-active" id="pm-tab-upload" role="tabpanel">
      <form method="post" action="project-media.php" enctype="multipart/form-data" class="admin-panel-form" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="upload">
        <input type="hidden" name="project_id" value="<?= (int) $projectId ?>">

        <label class="admin-dropzone" data-dropzone for="pm-upload-input">
          <input type="file" id="pm-upload-input" name="file" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf,video/mp4,text/plain" required data-dropzone-input>
          <span class="admin-dropzone__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 16V4M12 4 7 9M12 4l5 5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
          </span>
          <span class="admin-dropzone__text"><strong>Click to choose a file</strong> or drag and drop it here</span>
          <span class="meta">Image, PDF, video, or text &mdash; type is detected automatically</span>
          <span class="admin-dropzone__filename meta" data-dropzone-filename></span>
        </label>

        <label class="admin-field">
          <span>Alt text / caption</span>
          <input type="text" name="alt_text" maxlength="255" placeholder="Shown as the gallery caption on the project page">
        </label>

        <label class="admin-field">
          <span>Sort order</span>
          <input type="number" name="sort_order" value="<?= e($formValues['sort_order']) ?>" step="1">
        </label>

        <div class="admin-form-actions">
          <button class="btn btn--primary" type="submit">Upload &amp; Add to Project</button>
        </div>
      </form>
    </div>

    <div class="admin-tabs__panel" id="pm-tab-existing" role="tabpanel" hidden>
      <p class="admin-dashboard__note">Pick an asset already in the <a class="link-text" href="media.php">Media Library</a> — it fills the form below, then click "Add Media".</p>
      <button type="button" class="btn btn--secondary" data-open-picker="pm-picker-dialog" data-target-input="pm_path_input" data-target-type="pm_type_input" data-target-alt="pm_alt_input">
        Browse Library
      </button>
    </div>
  </div>

  <dialog id="pm-picker-dialog" class="admin-picker-dialog">
    <div class="admin-picker-dialog__head">
      <h2>Add existing media</h2>
      <button type="button" class="link-text" data-close-picker>Close</button>
    </div>
<?php if (empty($libraryRows)): ?>
    <p class="admin-dashboard__note">The Media Library is empty. Use "Upload New Media" instead, or add assets via <a class="link-text" href="media.php">Media</a>.</p>
<?php else: ?>
    <div class="admin-media-grid admin-media-grid--picker">
<?php foreach ($libraryRows as $row): ?>
<?php
    $pickerCategory = mediaTypeCategory($row['mime_type']);
    $pickerUrl      = publicMediaUrl((string) $row['path']);
    $pickerType     = mediaCategoryToProjectMediaType($pickerCategory);
?>
      <button type="button" class="admin-media-card admin-media-card--pick" data-media-pick data-path="<?= e($pickerUrl) ?>" data-type="<?= e($pickerType) ?>" data-alt="<?= e((string) ($row['alt_text'] ?? '')) ?>">
        <span class="admin-media-card__thumb">
<?php if ($pickerCategory === 'image'): ?>
          <img src="<?= e($pickerUrl) ?>" alt="" loading="lazy">
<?php else: ?>
          <span class="admin-media-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><?= mediaTypeIconSvg($pickerCategory) ?></svg></span>
<?php endif; ?>
        </span>
        <span class="admin-media-card__name"><?= e((string) $row['filename']) ?></span>
      </button>
<?php endforeach; ?>
    </div>
<?php endif; ?>
  </dialog>
<?php endif; ?>

  <form method="post" action="project-media.php" class="admin-panel-form" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="project_id" value="<?= (int) $projectId ?>">
    <input type="hidden" name="id" value="<?= $editId > 0 ? (int) $editId : '' ?>">

    <label class="admin-field">
      <span>Type</span>
      <select name="type" id="pm_type_input">
<?php foreach (MEDIA_ALLOWED_TYPES as $type): ?>
        <option value="<?= e($type) ?>"<?= $formValues['type'] === $type ? ' selected' : '' ?>><?= e(ucfirst($type)) ?></option>
<?php endforeach; ?>
      </select>
    </label>

    <label class="admin-field">
      <span>Path or URL</span>
      <input type="text" name="path" id="pm_path_input" value="<?= e($formValues['path']) ?>" maxlength="255" placeholder="Filled automatically by Upload / Add Existing Media above, or paste a URL" required>
    </label>

    <label class="admin-field">
      <span>Alt text / caption</span>
      <input type="text" name="alt_text" id="pm_alt_input" value="<?= e($formValues['alt_text']) ?>" maxlength="255">
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
  <div class="admin-empty-state">
    <p>No media items for this project yet. Upload or add one above.</p>
  </div>
<?php else: ?>
  <div class="admin-media-grid">
<?php foreach ($rows as $row): ?>
<?php
    $rowCategory = $row['type'] === 'image' ? 'image' : $row['type'];
    $rowUrl      = publicMediaUrl((string) $row['path']);
?>
    <article class="admin-media-card">
      <div class="admin-media-card__thumb">
<?php if ($rowCategory === 'image'): ?>
        <img src="<?= e($rowUrl) ?>" alt="<?= e((string) ($row['alt_text'] ?? '')) ?>" loading="lazy">
<?php else: ?>
        <span class="admin-media-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><?= mediaTypeIconSvg($rowCategory === 'video' ? 'video' : 'document') ?></svg></span>
<?php endif; ?>
      </div>
      <div class="admin-media-card__body">
        <p class="meta"><?= e(ucfirst((string) $row['type'])) ?> &middot; order <?= (int) $row['sort_order'] ?></p>
<?php if (!empty($row['alt_text'])): ?>
        <p class="admin-media-card__alt meta"><?= e((string) $row['alt_text']) ?></p>
<?php endif; ?>
      </div>
      <div class="admin-media-card__actions">
        <a class="link-text" href="project-media.php?project_id=<?= (int) $projectId ?>&edit=<?= (int) $row['id'] ?>">Edit</a>
        <form method="post" action="project-media.php" onsubmit="return confirm('Delete this media item? This cannot be undone.');">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="project_id" value="<?= (int) $projectId ?>">
          <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
          <button class="link-text admin-table__delete" type="submit">Delete</button>
        </form>
      </div>
    </article>
<?php endforeach; ?>
  </div>
<?php endif; ?>
</div>

<script src="assets/admin-media.js" defer></script>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
