<?php

declare(strict_types=1);

/**
 * public_html/admin/media.php
 *
 * Phase 4.7 — Media Manager (list / create / edit / delete), metadata
 * only. Uses the existing `media` table exactly as-is (schema unchanged):
 * filename, path, mime_type, size, alt_text, uploaded_at.
 *
 * IMPORTANT: no upload engine, no filesystem writes/deletes, no image
 * processing. "path" is a plain text field — an existing URL or an
 * already-uploaded file's relative path — same convention as
 * project_media.path and documents.file_path. Deleting a row never
 * touches the filesystem, only the DB record.
 *
 * Search: filename / alt_text (LIKE), the only text fields the schema
 * offers for this. Filter: `media` has no discrete "type" column, so
 * type filtering is derived from the existing mime_type prefix
 * (image/*, video/*, application|text/* as "document", everything else
 * as "other") — a UI convenience only, not a schema addition.
 */

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/helpers.php';
require __DIR__ . '/../../app/admin-auth.php';

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

/**
 * Builds the WHERE clause + params for the list query from $q (search
 * over filename/alt_text) and $filter (derived mime_type category).
 * Kept separate from loadMediaRow() so a crafted search/filter can never
 * affect single-row lookups used by edit/delete/save.
 */
function buildMediaListWhere(string $q, string $filter): array
{
    $conditions = [];
    $params     = [];

    if ($q !== '') {
        $conditions[] = '(filename LIKE :q OR alt_text LIKE :q)';
        $params['q']  = '%' . $q . '%';
    }

    if (in_array($filter, MEDIA_TYPE_FILTERS, true)) {
        switch ($filter) {
            case 'image':
                $conditions[] = "mime_type LIKE 'image/%'";
                break;
            case 'video':
                $conditions[] = "mime_type LIKE 'video/%'";
                break;
            case 'document':
                $conditions[] = "(mime_type LIKE 'application/%' OR mime_type LIKE 'text/%')";
                break;
            case 'other':
                $conditions[] = "(mime_type IS NULL OR mime_type = '' OR (
                    mime_type NOT LIKE 'image/%'
                    AND mime_type NOT LIKE 'video/%'
                    AND mime_type NOT LIKE 'application/%'
                    AND mime_type NOT LIKE 'text/%'
                ))";
                break;
        }
    }

    $where = $conditions !== [] ? ('WHERE ' . implode(' AND ', $conditions)) : '';

    return [$where, $params];
}

function loadMediaRows(PDO $pdo, string $q, string $filter): array
{
    [$where, $params] = buildMediaListWhere($q, $filter);

    $stmt = $pdo->prepare(
        "SELECT id, filename, path, mime_type, size, alt_text, uploaded_at
         FROM media
         {$where}
         ORDER BY uploaded_at DESC, id DESC"
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function formatBytes(?int $bytes): string
{
    if ($bytes === null) {
        return '';
    }

    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    $units = ['KB', 'MB', 'GB', 'TB'];
    $value = $bytes / 1024;
    $unit  = 0;

    while ($value >= 1024 && $unit < count($units) - 1) {
        $value /= 1024;
        $unit++;
    }

    return round($value, 1) . ' ' . $units[$unit];
}

$errors  = [];
$success = '';
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
    } elseif ($action === 'delete') {
        $deleteId = (int) ($_POST['id'] ?? 0);

        if ($deleteId <= 0) {
            $errors[] = 'Invalid media item.';
        } else {
            $existing = loadMediaRow($pdo, $deleteId);
            if ($existing === null) {
                $errors[] = 'That media item no longer exists.';
            } else {
                // Metadata only — the underlying file on disk (if any) is
                // never touched. No upload/storage engine exists yet.
                $stmt = $pdo->prepare('DELETE FROM media WHERE id = :id');
                $stmt->execute(['id' => $deleteId]);

                header('Location: /admin/media.php?deleted=1');
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

                    header('Location: /admin/media.php?saved=1');
                    exit;
                }
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO media (filename, path, mime_type, size, alt_text)
                     VALUES (:filename, :path, :mime_type, :size, :alt_text)'
                );
                $stmt->execute($params);

                header('Location: /admin/media.php?saved=1');
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

$rows = loadMediaRows($pdo, $q, $filter);

$pageTitle = 'Media';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-crud">
  <div class="admin-dashboard__head">
    <div>
      <p class="meta">Media</p>
      <h1><?= $editId > 0 ? 'Edit Media Item' : 'Add Media Item' ?></h1>
    </div>
  </div>

  <p class="admin-dashboard__note">
    This manages metadata in the <code>media</code> table only — filename,
    path, MIME type, size, and alt text. There is no upload engine yet:
    <code>path</code> is a plain relative path or URL you enter yourself,
    the same convention as Project Media and CV. Deleting an entry here
    removes the database record only; it never touches a file on disk.
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

  <form method="post" action="media.php" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editId > 0 ? (int) $editId : '' ?>">

    <label class="admin-field">
      <span>Filename</span>
      <input type="text" name="filename" value="<?= e($formValues['filename']) ?>" maxlength="191" required autofocus placeholder="e.g. topology-diagram.png">
    </label>

    <label class="admin-field">
      <span>Path or URL</span>
      <input type="text" name="path" value="<?= e($formValues['path']) ?>" maxlength="255" placeholder="assets/media/example.jpg or https://..." required>
    </label>

    <div class="admin-field-row">
      <label class="admin-field">
        <span>MIME type</span>
        <input type="text" name="mime_type" value="<?= e($formValues['mime_type']) ?>" maxlength="127" placeholder="e.g. image/png">
      </label>

      <label class="admin-field">
        <span>Size (bytes)</span>
        <input type="number" name="size" value="<?= e($formValues['size']) ?>" min="0" step="1" placeholder="e.g. 204800">
      </label>
    </div>

    <label class="admin-field">
      <span>Alt text</span>
      <input type="text" name="alt_text" value="<?= e($formValues['alt_text']) ?>" maxlength="255">
    </label>

    <div class="admin-form-actions">
      <button class="btn btn--primary" type="submit"><?= $editId > 0 ? 'Save Changes' : 'Add Media' ?></button>
<?php if ($editId > 0): ?>
      <a class="link-text" href="media.php">Cancel</a>
<?php endif; ?>
    </div>
  </form>

  <h2 class="admin-crud__list-title">Existing Media</h2>

  <form method="get" action="media.php" class="admin-field-row" style="margin-bottom: var(--space-5); align-items: end;">
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
  <p class="admin-dashboard__note">No media items yet. Add one using the form above.</p>
<?php elseif (empty($rows)): ?>
  <p class="admin-dashboard__note">No media items match your search/filter. <a class="link-text" href="media.php">Clear search &amp; filter</a>.</p>
<?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Filename</th>
          <th>Path</th>
          <th>MIME type</th>
          <th>Size</th>
          <th>Alt text</th>
          <th>Uploaded</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($rows as $row): ?>
        <tr>
          <td><?= e((string) $row['filename']) ?></td>
          <td><?= e((string) $row['path']) ?></td>
          <td class="meta"><?= e((string) ($row['mime_type'] ?? '')) ?></td>
          <td class="meta"><?= e(formatBytes($row['size'] !== null ? (int) $row['size'] : null)) ?></td>
          <td class="meta"><?= e((string) ($row['alt_text'] ?? '')) ?></td>
          <td class="meta"><?= e((string) $row['uploaded_at']) ?></td>
          <td class="admin-table__actions">
            <a class="link-text" href="media.php?edit=<?= (int) $row['id'] ?>">Edit</a>
            <form method="post" action="media.php" onsubmit="return confirm('Delete this media record? This only removes the database entry, not any file on disk. This cannot be undone.');">
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