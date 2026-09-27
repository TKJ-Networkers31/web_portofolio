<?php

declare(strict_types=1);

/**
 * public_html/admin/media.php
 *
 * PHASE 5.3 — Universal Media Library.
 *
 * Was: metadata-only CRUD (Phase 4.7) — filename/path/mime/size all had
 * to be typed by hand, list was a plain table.
 *
 * Now: a real asset library.
 *   - "Upload" tab: real file picker (+ drag & drop), server-side
 *     validation, auto filename/MIME/size detection, unique generated
 *     filename, stored under public_html/assets/media/, `media` row
 *     created automatically. Nothing here is typed by the user.
 *   - "Add External URL" tab: the OLD manual-entry form, kept exactly as
 *     it was (filename/path/mime/size/alt fields) — not removed, just
 *     demoted to a secondary, clearly-labelled option for the one case
 *     upload can't cover: referencing a file that already lives
 *     somewhere else (a URL, or a path uploaded outside this tool).
 *   - Library: thumbnail grid (image preview, icon for non-image),
 *     search + type filter (same behaviour as before, logic now shared
 *     via app/media.php::mediaSearchRows() so the pickers on
 *     admin/profile.php and admin/project-media.php stay in sync with
 *     this page), inline path "Copy" for reuse elsewhere, Edit/Delete.
 *   - Export / Import: wired to the existing, unchanged
 *     exportMediaManifest()/importMediaManifest() in app/media.php —
 *     same format, same dedupe-by-path behaviour, no second system.
 *
 * Uses the existing `media` table and existing storage convention only
 * (schema unchanged). Deleting a row removes the DB record; if the
 * asset was uploaded through this tool (path under assets/media/) the
 * underlying file is also removed, since this tool now owns that
 * file's whole lifecycle — a manually-entered external URL is never
 * touched on delete.
 */

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/helpers.php';
require __DIR__ . '/../../app/admin-auth.php';
require __DIR__ . '/../../app/media.php';

requireAdmin();

$pdo = db();

const MEDIA_FIELDS = ['filename', 'path', 'mime_type', 'size', 'alt_text'];

const MEDIA_MAX_LENGTHS = [
    'filename'  => 191,
    'path'      => 255,
    'mime_type' => 127,
    'alt_text'  => 255,
];

/** Derived, UI-only categories over mime_type — schema has no `type` column. */
const MEDIA_TYPE_FILTERS = ['image', 'video', 'document', 'other'];

function loadMediaRow(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, filename, path, mime_type, size, alt_text, uploaded_at
         FROM media WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/* ---------- Export: plain JSON download, before any HTML is sent ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['export'])) {
    $manifest = exportMediaManifest($pdo);
    $json     = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="media-export-' . date('Y-m-d') . '.json"');
    echo $json;
    exit;
}

$errors  = [];
$success = '';
$importResult = null;
$editId  = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

$q      = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$filter = isset($_GET['filter']) ? (string) $_GET['filter'] : '';
if (!in_array($filter, MEDIA_TYPE_FILTERS, true)) {
    $filter = '';
}

$formValues = array_fill_keys(MEDIA_FIELDS, '');
$editingRow = null;

if ($editId > 0) {
    $editingRow = loadMediaRow($pdo, $editId);
    if ($editingRow === null) {
        $errors[] = 'The media item you tried to edit no longer exists.';
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
    } elseif ($action === 'upload') {
        $altText = trim((string) ($_POST['alt_text'] ?? ''));
        $result  = storeUploadedMedia($pdo, $_FILES['file'] ?? [], $altText);

        if (!$result['ok']) {
            $errors[] = $result['error'] ?? 'The upload failed.';
        } else {
            header('Location: ' . adminUrl('media.php') . '?saved=1');
            exit;
        }
    } elseif ($action === 'import') {
        $importJson = '';

        if (isset($_FILES['import_file']) && (int) ($_FILES['import_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $importJson = (string) file_get_contents($_FILES['import_file']['tmp_name']);
        } else {
            $importJson = trim((string) ($_POST['import_json'] ?? ''));
        }

        if ($importJson === '') {
            $errors[] = 'Choose a JSON file or paste an export to import.';
        } else {
            $importResult = importMediaManifest($pdo, $importJson);
            if (!empty($importResult['errors'])) {
                $errors = array_merge($errors, $importResult['errors']);
            } else {
                $success = "Import complete — {$importResult['imported']} added, {$importResult['skipped']} already existed"
                    . (!empty($importResult['missing']) ? ', ' . count($importResult['missing']) . ' reference a file not found on disk' : '')
                    . '.';
            }
        }
    } elseif ($action === 'delete') {
        $deleteId = (int) ($_POST['id'] ?? 0);

        if ($deleteId <= 0) {
            $errors[] = 'Invalid media item.';
        } else {
            $existing = loadMediaRow($pdo, $deleteId);
            if ($existing === null) {
                $errors[] = 'That media item no longer exists.';
            } else {
                $stmt = $pdo->prepare('DELETE FROM media WHERE id = :id');
                $stmt->execute(['id' => $deleteId]);

                // Only remove the physical file for assets this tool
                // uploaded itself (path under assets/media/) — a
                // manually-entered external URL / arbitrary path is
                // metadata-only and is never touched on disk.
                $path = (string) $existing['path'];
                if (str_starts_with($path, 'assets/media/')) {
                    $absolute = mediaAbsolutePath($path);
                    if ($absolute !== null && is_file($absolute)) {
                        @unlink($absolute);
                    }
                }

                header('Location: ' . adminUrl('media.php') . '?deleted=1');
                exit;
            }
        }
    } elseif ($action === 'save') {
        $postId = (int) ($_POST['id'] ?? 0);

        foreach (MEDIA_FIELDS as $field) {
            $formValues[$field] = trim((string) ($_POST[$field] ?? ''));
        }

        /* ---------- Server-side validation ---------- */
        if ($formValues['filename'] === '') {
            $errors[] = 'Filename is required.';
        }

        if ($formValues['path'] === '') {
            $errors[] = 'Path / URL is required.';
        } elseif (str_contains($formValues['path'], "\0")) {
            $errors[] = 'Path is invalid.';
        } elseif (str_contains($formValues['path'], '..')) {
            $errors[] = 'Path may not contain ".." segments.';
        } elseif (
            preg_match('#^[a-z][a-z0-9+.-]*://#i', $formValues['path'])
            && filter_var($formValues['path'], FILTER_VALIDATE_URL) === false
        ) {
            $errors[] = 'Path must be a relative path or a valid URL.';
        }

        if ($formValues['size'] !== '' && !preg_match('/^\d+$/', $formValues['size'])) {
            $errors[] = 'Size must be a non-negative whole number of bytes.';
        }

        foreach (MEDIA_MAX_LENGTHS as $field => $max) {
            if (mb_strlen($formValues[$field]) > $max) {
                $label    = ucfirst(str_replace('_', ' ', $field));
                $errors[] = "{$label} must be {$max} characters or fewer.";
            }
        }

        if (empty($errors)) {
            $params = [
                'filename'  => $formValues['filename'],
                'path'      => $formValues['path'],
                'mime_type' => $formValues['mime_type'] !== '' ? $formValues['mime_type'] : null,
                'size'      => $formValues['size'] !== '' ? (int) $formValues['size'] : null,
                'alt_text'  => $formValues['alt_text'] !== '' ? $formValues['alt_text'] : null,
            ];

            if ($postId > 0) {
                $exists = loadMediaRow($pdo, $postId);
                if ($exists === null) {
                    $errors[] = 'The media item you tried to save no longer exists.';
                } else {
                    $params['id'] = $postId;
                    $stmt = $pdo->prepare(
                        'UPDATE media
                         SET filename = :filename, path = :path, mime_type = :mime_type,
                             size = :size, alt_text = :alt_text
                         WHERE id = :id'
                    );
                    $stmt->execute($params);

                    header('Location: ' . adminUrl('media.php') . '?saved=1');
                    exit;
                }
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO media (filename, path, mime_type, size, alt_text)
                     VALUES (:filename, :path, :mime_type, :size, :alt_text)'
                );
                $stmt->execute($params);

                header('Location: ' . adminUrl('media.php') . '?saved=1');
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

$rows = mediaSearchRows($pdo, $q, $filter);

$pageTitle = 'Media';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-crud admin-media">
  <div class="admin-dashboard__head">
    <div>
      <p class="meta">Media Library</p>
      <h1><?= $editId > 0 ? 'Edit Media Item' : 'Media Library' ?></h1>
    </div>
  </div>

  <p class="admin-dashboard__note">
    Upload a file and its filename, MIME type, and size are detected
    automatically — nothing to type. Use "Add External URL" only for an
    asset that already lives somewhere else (an existing URL, or a file
    uploaded outside this tool).
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

<?php if ($editId > 0 && $editingRow !== null): ?>
  <!-- ---- Edit existing record (metadata only — re-upload isn't offered here; delete + upload again to replace the file itself) ---- -->
  <form method="post" action="media.php" class="admin-panel-form" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) $editId ?>">

    <label class="admin-field">
      <span>Filename</span>
      <input type="text" name="filename" value="<?= e($formValues['filename']) ?>" maxlength="191" required autofocus>
    </label>

    <label class="admin-field">
      <span>Path or URL</span>
      <input type="text" name="path" value="<?= e($formValues['path']) ?>" maxlength="255" required>
    </label>

    <div class="admin-field-row">
      <label class="admin-field">
        <span>MIME type</span>
        <input type="text" name="mime_type" value="<?= e($formValues['mime_type']) ?>" maxlength="127">
      </label>

      <label class="admin-field">
        <span>Size (bytes)</span>
        <input type="number" name="size" value="<?= e($formValues['size']) ?>" min="0" step="1">
      </label>
    </div>

    <label class="admin-field">
      <span>Alt text</span>
      <input type="text" name="alt_text" value="<?= e($formValues['alt_text']) ?>" maxlength="255">
    </label>

    <div class="admin-form-actions">
      <button class="btn btn--primary" type="submit">Save Changes</button>
      <a class="link-text" href="media.php">Cancel</a>
    </div>
  </form>
<?php else: ?>

  <!-- ---- Upload / Add External URL tabs ---- -->
  <div class="admin-tabs" data-admin-tabs>
    <div class="admin-tabs__nav" role="tablist">
      <button type="button" class="admin-tabs__tab is-active" role="tab" data-tab-target="tab-upload">Upload</button>
      <button type="button" class="admin-tabs__tab" role="tab" data-tab-target="tab-url">Add External URL</button>
      <button type="button" class="admin-tabs__tab" role="tab" data-tab-target="tab-import-export">Import / Export</button>
    </div>

    <div class="admin-tabs__panel is-active" id="tab-upload" role="tabpanel">
      <form method="post" action="media.php" enctype="multipart/form-data" class="admin-panel-form" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="upload">

        <label class="admin-dropzone" data-dropzone for="media-upload-input">
          <input type="file" id="media-upload-input" name="file" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf,video/mp4,text/plain" required data-dropzone-input>
          <span class="admin-dropzone__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 16V4M12 4 7 9M12 4l5 5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
          </span>
          <span class="admin-dropzone__text">
            <strong>Click to choose a file</strong> or drag and drop it here
          </span>
          <span class="meta">JPEG, PNG, GIF, WebP, PDF, MP4, or TXT — up to <?= e(formatBytes(mediaMaxBytes())) ?></span>
          <span class="admin-dropzone__filename meta" data-dropzone-filename></span>
        </label>

        <label class="admin-field">
          <span>Alt text (optional)</span>
          <input type="text" name="alt_text" maxlength="255" placeholder="Describe the file for accessibility / captions">
        </label>

        <div class="admin-form-actions">
          <button class="btn btn--primary" type="submit">Upload &amp; Add to Library</button>
        </div>
      </form>
    </div>

    <div class="admin-tabs__panel" id="tab-url" role="tabpanel" hidden>
      <p class="admin-dashboard__note">
        For an asset that already exists elsewhere (an external URL, or a
        file placed on the server outside this tool). Filename, path, MIME
        type, and size are <strong>not</strong> auto-detected here — enter
        them yourself.
      </p>
      <form method="post" action="media.php" class="admin-panel-form" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="">

        <label class="admin-field">
          <span>Filename</span>
          <input type="text" name="filename" maxlength="191" placeholder="e.g. topology-diagram.png" required>
        </label>

        <label class="admin-field">
          <span>Path or URL</span>
          <input type="text" name="path" maxlength="255" placeholder="assets/media/example.jpg or https://..." required>
        </label>

        <div class="admin-field-row">
          <label class="admin-field">
            <span>MIME type</span>
            <input type="text" name="mime_type" maxlength="127" placeholder="e.g. image/png">
          </label>

          <label class="admin-field">
            <span>Size (bytes)</span>
            <input type="number" name="size" min="0" step="1" placeholder="e.g. 204800">
          </label>
        </div>

        <label class="admin-field">
          <span>Alt text</span>
          <input type="text" name="alt_text" maxlength="255">
        </label>

        <div class="admin-form-actions">
          <button class="btn btn--secondary" type="submit">Add to Library</button>
        </div>
      </form>
    </div>

    <div class="admin-tabs__panel" id="tab-import-export" role="tabpanel" hidden>
      <div class="admin-import-export">
        <div class="admin-import-export__col">
          <h3>Export</h3>
          <p class="admin-dashboard__note">
            Downloads every asset's metadata (filename, path, MIME type,
            size, alt text) as JSON. No credentials or secrets are
            included — only what's already in the <code>media</code>
            table.
          </p>
          <a class="btn btn--secondary" href="media.php?export=1">Download media-export.json</a>
        </div>

        <div class="admin-import-export__col">
          <h3>Import</h3>
          <p class="admin-dashboard__note">
            Restores metadata from a previous export. An asset whose
            <strong>path</strong> already exists in the library is
            skipped, never overwritten. A file referenced by the import
            that isn't actually present in storage is still recorded,
            and flagged below, rather than silently dropped.
          </p>
          <form method="post" action="media.php" enctype="multipart/form-data" class="admin-panel-form" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="import">

            <label class="admin-field">
              <span>Export JSON file</span>
              <input type="file" name="import_file" accept="application/json,.json">
            </label>

            <label class="admin-field">
              <span>...or paste JSON</span>
              <textarea name="import_json" rows="4" placeholder='{"format":"portfolio-media-v1", ...}'></textarea>
            </label>

            <div class="admin-form-actions">
              <button class="btn btn--secondary" type="submit">Import</button>
            </div>
          </form>

<?php if ($importResult !== null && empty($importResult['errors'])): ?>
          <ul class="admin-import-summary" role="list">
            <li><strong><?= (int) $importResult['imported'] ?></strong> imported</li>
            <li><strong><?= (int) $importResult['skipped'] ?></strong> already existed (skipped)</li>
<?php if (!empty($importResult['missing'])): ?>
            <li class="admin-import-summary__warning">
              <strong><?= count($importResult['missing']) ?></strong> imported but file not found on disk:
              <code><?= e(implode(', ', $importResult['missing'])) ?></code>
            </li>
<?php endif; ?>
          </ul>
<?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

  <h2 class="admin-crud__list-title">Library</h2>

  <form method="get" action="media.php" class="admin-field-row admin-media__search" style="align-items: end;">
    <label class="admin-field">
      <span>Search (filename / alt text)</span>
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search...">
    </label>

    <label class="admin-field">
      <span>Type</span>
      <select name="filter">
        <option value="">All types</option>
<?php foreach (MEDIA_TYPE_FILTERS as $typeOption): ?>
        <option value="<?= e($typeOption) ?>"<?= $filter === $typeOption ? ' selected' : '' ?>><?= e(ucfirst($typeOption)) ?></option>
<?php endforeach; ?>
      </select>
    </label>

    <div class="admin-form-actions">
      <button class="btn btn--secondary" type="submit">Apply</button>
<?php if ($q !== '' || $filter !== ''): ?>
      <a class="link-text" href="media.php">Clear</a>
<?php endif; ?>
    </div>
  </form>

<?php if (empty($rows) && $q === '' && $filter === ''): ?>
  <div class="admin-empty-state">
    <span class="admin-empty-state__icon" aria-hidden="true">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg>
    </span>
    <p>No media yet. Upload your first file above.</p>
  </div>
<?php elseif (empty($rows)): ?>
  <div class="admin-empty-state">
    <p>No media matches your search/filter. <a class="link-text" href="media.php">Clear search &amp; filter</a>.</p>
  </div>
<?php else: ?>
  <div class="admin-media-grid">
<?php foreach ($rows as $row): ?>
<?php
    $category = mediaTypeCategory($row['mime_type']);
    $url      = publicMediaUrl((string) $row['path']);
?>
    <article class="admin-media-card">
      <div class="admin-media-card__thumb">
<?php if ($category === 'image'): ?>
        <img src="<?= e($url) ?>" alt="<?= e((string) ($row['alt_text'] ?? '')) ?>" loading="lazy">
<?php else: ?>
        <span class="admin-media-card__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><?= mediaTypeIconSvg($category) ?></svg></span>
<?php endif; ?>
      </div>
      <div class="admin-media-card__body">
        <p class="admin-media-card__name" title="<?= e((string) $row['filename']) ?>"><?= e((string) $row['filename']) ?></p>
        <p class="meta">
          <?= e((string) ($row['mime_type'] ?? 'unknown')) ?>
<?php if ($row['size'] !== null): ?>
           &middot; <?= e(formatBytes((int) $row['size'])) ?>
<?php endif; ?>
        </p>
<?php if (!empty($row['alt_text'])): ?>
        <p class="admin-media-card__alt meta"><?= e((string) $row['alt_text']) ?></p>
<?php endif; ?>
      </div>
      <div class="admin-media-card__actions">
        <button type="button" class="link-text" data-copy-path="<?= e($url) ?>">Copy path</button>
        <a class="link-text" href="media.php?edit=<?= (int) $row['id'] ?>">Edit</a>
        <form method="post" action="media.php" onsubmit="return confirm('Delete this media item? If it was uploaded through this tool, the file is removed too. This cannot be undone.');">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="delete">
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
