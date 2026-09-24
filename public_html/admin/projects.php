<?php

declare(strict_types=1);

/**
 * public_html/admin/projects.php
 *
 * Phase 4.5 — Projects CRUD (list / create / edit / delete).
 * Same pattern as admin/education.php, admin/skills.php, etc.: PDO
 * prepared statements, existing requireAdmin()/CSRF/e(), server-side
 * validation, PRG after every state-changing action.
 *
 * IMPORTANT: this is the CMS `projects` table only. Phase 3's public
 * site (work.php, project.php) still reads exclusively from
 * data/projects.php — nothing here is wired to the public pages yet.
 * That migration is explicitly deferred to Phase 4.9.
 */

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/helpers.php';
require __DIR__ . '/../../app/admin-auth.php';

requireAdmin();

$pdo = db();

const PROJECT_FIELDS = [
    'slug', 'title', 'category', 'status', 'year', 'featured',
    'summary', 'technologies', 'problem', 'approach', 'result',
    'result_highlight', 'lessons', 'sort_order',
];

const PROJECT_MAX_LENGTHS = [
    'slug'             => 191,
    'title'            => 191,
    'category'         => 191,
    'status'           => 64,
    'year'             => 16,
    'result_highlight' => 255,
];

const PROJECT_LONGTEXT_MAX_LENGTH = 5000;

/**
 * Same slug rule Phase 3 routing (.htaccess + project.php) already
 * enforces: lowercase letters, digits, hyphens only. Not changed here —
 * only reused, so a slug created in the CMS would be routable if it were
 * ever wired to the public site.
 */
const PROJECT_SLUG_PATTERN = '/^[a-z0-9-]+$/';

function loadProjectRows(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, slug, title, category, status, year, featured, sort_order
         FROM projects
         ORDER BY sort_order ASC, id ASC'
    );

    return $stmt ? $stmt->fetchAll() : [];
}

function loadProjectRow(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, slug, title, category, status, year, featured, summary,
                technologies, problem, approach, result, result_highlight,
                lessons, sort_order
         FROM projects WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function slugExists(PDO $pdo, string $slug, int $excludeId = 0): bool
{
    if ($excludeId > 0) {
        $stmt = $pdo->prepare('SELECT id FROM projects WHERE slug = :slug AND id != :id LIMIT 1');
        $stmt->execute(['slug' => $slug, 'id' => $excludeId]);
    } else {
        $stmt = $pdo->prepare('SELECT id FROM projects WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
    }

    return (bool) $stmt->fetch();
}

/** "MikroTik, VRRP, VLAN" <-> '["MikroTik","VRRP","VLAN"]' (technologies column, per schema comment). */
function technologiesToJson(string $commaList): ?string
{
    $items = array_values(array_filter(array_map('trim', explode(',', $commaList)), static function (string $v): bool {
        return $v !== '';
    }));

    return $items !== [] ? json_encode($items, JSON_UNESCAPED_UNICODE) : null;
}

function technologiesToCommaList(?string $json): string
{
    if ($json === null || $json === '') {
        return '';
    }

    $decoded = json_decode($json, true);

    return is_array($decoded) ? implode(', ', $decoded) : '';
}

$errors  = [];
$success = '';
$editId  = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

$formValues = array_fill_keys(PROJECT_FIELDS, '');
$formValues['sort_order'] = '0';
$formValues['featured']   = '';
$editingRow = null;

if ($editId > 0) {
    $editingRow = loadProjectRow($pdo, $editId);
    if ($editingRow === null) {
        $errors[] = 'The project you tried to edit no longer exists.';
        $editId   = 0;
    } else {
        foreach (PROJECT_FIELDS as $field) {
            if ($field === 'technologies') {
                $formValues['technologies'] = technologiesToCommaList($editingRow['technologies'] ?? null);
                continue;
            }
            if ($field === 'featured') {
                $formValues['featured'] = !empty($editingRow['featured']) ? '1' : '';
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
            $errors[] = 'Invalid project.';
        } else {
            // project_media rows reference project_id with no DB-level FK
            // guarantee in this schema (unlike SQLite's ON DELETE CASCADE,
            // MySQL's schema here defines no FK at all) — clean up media
            // for this project explicitly so nothing is left orphaned.
            $pdo->beginTransaction();
            try {
                $delMedia = $pdo->prepare('DELETE FROM project_media WHERE project_id = :id');
                $delMedia->execute(['id' => $deleteId]);

                $delProject = $pdo->prepare('DELETE FROM projects WHERE id = :id');
                $delProject->execute(['id' => $deleteId]);

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            header('Location: ' . adminUrl('projects.php') . '?deleted=1');
            exit;
        }
    } elseif ($action === 'save') {
        $postId = (int) ($_POST['id'] ?? 0);

        foreach (PROJECT_FIELDS as $field) {
            if ($field === 'featured') {
                $formValues['featured'] = !empty($_POST['featured']) ? '1' : '';
                continue;
            }
            $formValues[$field] = trim((string) ($_POST[$field] ?? ''));
        }

        /* ---------- Server-side validation ---------- */
        if ($formValues['title'] === '') {
            $errors[] = 'Title is required.';
        }

        if ($formValues['slug'] === '') {
            $errors[] = 'Slug is required.';
        } elseif (!preg_match(PROJECT_SLUG_PATTERN, $formValues['slug'])) {
            $errors[] = 'Slug may only contain lowercase letters, numbers, and hyphens (matches Phase 3 routing rules).';
        } elseif (slugExists($pdo, $formValues['slug'], $postId)) {
            $errors[] = 'This slug is already used by another project. Slugs must be unique.';
        }

        foreach (PROJECT_MAX_LENGTHS as $field => $max) {
            if (mb_strlen($formValues[$field]) > $max) {
                $label    = ucfirst(str_replace('_', ' ', $field));
                $errors[] = "{$label} must be {$max} characters or fewer.";
            }
        }

        foreach (['summary', 'problem', 'approach', 'result', 'lessons'] as $longField) {
            if (mb_strlen($formValues[$longField]) > PROJECT_LONGTEXT_MAX_LENGTH) {
                $label    = ucfirst($longField);
                $errors[] = "{$label} must be " . PROJECT_LONGTEXT_MAX_LENGTH . ' characters or fewer.';
            }
        }

        if ($formValues['sort_order'] === '' || !preg_match('/^-?\d+$/', $formValues['sort_order'])) {
            $errors[] = 'Sort order must be a whole number.';
        }

        if (empty($errors)) {
            $params = [
                'slug'             => $formValues['slug'],
                'title'            => $formValues['title'],
                'category'         => $formValues['category'] !== '' ? $formValues['category'] : null,
                'status'           => $formValues['status'] !== '' ? $formValues['status'] : null,
                'year'             => $formValues['year'] !== '' ? $formValues['year'] : null,
                'featured'         => $formValues['featured'] === '1' ? 1 : 0,
                'summary'          => $formValues['summary'] !== '' ? $formValues['summary'] : null,
                'technologies'     => technologiesToJson($formValues['technologies']),
                'problem'          => $formValues['problem'] !== '' ? $formValues['problem'] : null,
                'approach'         => $formValues['approach'] !== '' ? $formValues['approach'] : null,
                'result'           => $formValues['result'] !== '' ? $formValues['result'] : null,
                'result_highlight' => $formValues['result_highlight'] !== '' ? $formValues['result_highlight'] : null,
                'lessons'          => $formValues['lessons'] !== '' ? $formValues['lessons'] : null,
                'sort_order'       => (int) $formValues['sort_order'],
            ];

            if ($postId > 0) {
                // Only update if the id actually exists — avoids a no-op
                // UPDATE silently "succeeding" against a deleted record.
                $exists = loadProjectRow($pdo, $postId);
                if ($exists === null) {
                    $errors[] = 'The project you tried to save no longer exists.';
                } else {
                    $params['id'] = $postId;
                    $stmt = $pdo->prepare(
                        'UPDATE projects
                         SET slug = :slug, title = :title, category = :category,
                             status = :status, year = :year, featured = :featured,
                             summary = :summary, technologies = :technologies,
                             problem = :problem, approach = :approach, result = :result,
                             result_highlight = :result_highlight, lessons = :lessons,
                             sort_order = :sort_order, updated_at = CURRENT_TIMESTAMP
                         WHERE id = :id'
                    );
                    $stmt->execute($params);

                    header('Location: ' . adminUrl('projects.php') . '?saved=1');
                    exit;
                }
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO projects
                        (slug, title, category, status, year, featured, summary,
                         technologies, problem, approach, result, result_highlight,
                         lessons, sort_order)
                     VALUES
                        (:slug, :title, :category, :status, :year, :featured, :summary,
                         :technologies, :problem, :approach, :result, :result_highlight,
                         :lessons, :sort_order)'
                );
                $stmt->execute($params);

                header('Location: ' . adminUrl('projects.php') . '?saved=1');
                exit;
            }
        }

        // Validation failed: stay in the same create/edit state the user was in.
        $editId = $postId;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['saved'])) {
        $success = 'Project saved.';
    } elseif (isset($_GET['deleted'])) {
        $success = 'Project deleted.';
    }
}

$rows = loadProjectRows($pdo);

$pageTitle = 'Projects';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-crud">
  <div class="admin-dashboard__head">
    <div>
      <p class="meta">Projects (CMS)</p>
      <h1><?= $editId > 0 ? 'Edit Project' : 'Add Project' ?></h1>
    </div>
  </div>

  <p class="admin-dashboard__note">
    This manages the CMS <code>projects</code> table only. The public
    <code>/work</code> and <code>/project/{slug}</code> pages still read
    from <code>data/projects.php</code> until that migration happens in
    a later phase — nothing saved here appears on the live site yet.
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

  <form method="post" action="projects.php" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editId > 0 ? (int) $editId : '' ?>">

    <label class="admin-field">
      <span>Title</span>
      <input type="text" name="title" value="<?= e($formValues['title']) ?>" maxlength="191" required autofocus>
    </label>

    <label class="admin-field">
      <span>Slug (lowercase letters, numbers, hyphens only)</span>
      <input type="text" name="slug" value="<?= e($formValues['slug']) ?>" maxlength="191" pattern="[a-z0-9-]+" required>
    </label>

    <div class="admin-field-row">
      <label class="admin-field">
        <span>Category</span>
        <input type="text" name="category" value="<?= e($formValues['category']) ?>" maxlength="191">
      </label>

      <label class="admin-field">
        <span>Status</span>
        <input type="text" name="status" value="<?= e($formValues['status']) ?>" maxlength="64" placeholder="Completed, Active, Experimental...">
      </label>
    </div>

    <div class="admin-field-row">
      <label class="admin-field">
        <span>Year</span>
        <input type="text" name="year" value="<?= e($formValues['year']) ?>" maxlength="16">
      </label>

      <label class="admin-field admin-field--checkbox">
        <span>Featured (shows on Home)</span>
        <input type="checkbox" name="featured" value="1"<?= $formValues['featured'] === '1' ? ' checked' : '' ?>>
      </label>
    </div>

    <label class="admin-field">
      <span>Summary</span>
      <textarea name="summary" rows="2" maxlength="<?= PROJECT_LONGTEXT_MAX_LENGTH ?>"><?= e($formValues['summary']) ?></textarea>
    </label>

    <label class="admin-field">
      <span>Technologies (comma-separated)</span>
      <input type="text" name="technologies" value="<?= e($formValues['technologies']) ?>" placeholder="MikroTik, VRRP, VLAN">
    </label>

    <label class="admin-field">
      <span>Problem</span>
      <textarea name="problem" rows="3" maxlength="<?= PROJECT_LONGTEXT_MAX_LENGTH ?>"><?= e($formValues['problem']) ?></textarea>
    </label>

    <label class="admin-field">
      <span>Approach</span>
      <textarea name="approach" rows="3" maxlength="<?= PROJECT_LONGTEXT_MAX_LENGTH ?>"><?= e($formValues['approach']) ?></textarea>
    </label>

    <label class="admin-field">
      <span>Result</span>
      <textarea name="result" rows="3" maxlength="<?= PROJECT_LONGTEXT_MAX_LENGTH ?>"><?= e($formValues['result']) ?></textarea>
    </label>

    <label class="admin-field">
      <span>Result highlight (optional callout)</span>
      <input type="text" name="result_highlight" value="<?= e($formValues['result_highlight']) ?>" maxlength="255">
    </label>

    <label class="admin-field">
      <span>Lessons learned</span>
      <textarea name="lessons" rows="3" maxlength="<?= PROJECT_LONGTEXT_MAX_LENGTH ?>"><?= e($formValues['lessons']) ?></textarea>
    </label>

    <label class="admin-field">
      <span>Sort order</span>
      <input type="number" name="sort_order" value="<?= e($formValues['sort_order']) ?>" step="1">
    </label>

    <div class="admin-form-actions">
      <button class="btn btn--primary" type="submit"><?= $editId > 0 ? 'Save Changes' : 'Add Project' ?></button>
<?php if ($editId > 0): ?>
      <a class="link-text" href="projects.php">Cancel</a>
<?php endif; ?>
    </div>
  </form>

  <h2 class="admin-crud__list-title">Existing Projects (CMS)</h2>

<?php if (empty($rows)): ?>
  <p class="admin-dashboard__note">No projects in the CMS table yet. Add one using the form above.</p>
<?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Title</th>
          <th>Slug</th>
          <th>Status</th>
          <th>Year</th>
          <th>Featured</th>
          <th>Order</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($rows as $row): ?>
        <tr>
          <td><?= e((string) $row['title']) ?></td>
          <td class="meta"><?= e((string) $row['slug']) ?></td>
          <td class="meta"><?= e((string) ($row['status'] ?? '')) ?></td>
          <td class="meta"><?= e((string) ($row['year'] ?? '')) ?></td>
          <td class="meta"><?= !empty($row['featured']) ? 'Yes' : 'No' ?></td>
          <td class="meta"><?= (int) $row['sort_order'] ?></td>
          <td class="admin-table__actions">
            <a class="link-text" href="projects.php?edit=<?= (int) $row['id'] ?>">Edit</a>
            <a class="link-text" href="project-media.php?project_id=<?= (int) $row['id'] ?>">Media</a>
            <form method="post" action="projects.php" onsubmit="return confirm('Delete this project and all its media? This cannot be undone.');">
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