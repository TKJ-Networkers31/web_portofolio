<?php

declare(strict_types=1);

/**
 * public_html/admin/skills.php
 *
 * Phase 4.4 — Skills CRUD (list / create / edit / delete).
 * Same pattern as admin/education.php and admin/experience.php: PDO
 * prepared statements, existing requireAdmin()/CSRF/e(), server-side
 * validation, PRG after every state-changing action.
 */

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/helpers.php';
require __DIR__ . '/../../app/admin-auth.php';

requireAdmin();

$pdo = db();

const SKILL_FIELDS = ['name', 'category', 'level', 'sort_order'];

const SKILL_MAX_LENGTHS = [
    'name'     => 191,
    'category' => 191,
    'level'    => 64,
];

function loadSkillRows(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, name, category, level, sort_order
         FROM skills
         ORDER BY sort_order ASC, id ASC'
    );

    return $stmt ? $stmt->fetchAll() : [];
}

function loadSkillRow(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, category, level, sort_order
         FROM skills WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

$errors  = [];
$success = '';
$editId  = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

$formValues = array_fill_keys(SKILL_FIELDS, '');
$formValues['sort_order'] = '0';
$editingRow = null;

if ($editId > 0) {
    $editingRow = loadSkillRow($pdo, $editId);
    if ($editingRow === null) {
        $errors[] = 'The record you tried to edit no longer exists.';
        $editId   = 0;
    } else {
        foreach (SKILL_FIELDS as $field) {
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
            $stmt = $pdo->prepare('DELETE FROM skills WHERE id = :id');
            $stmt->execute(['id' => $deleteId]);

            header('Location: /admin/skills.php?deleted=1');
            exit;
        }
    } elseif ($action === 'save') {
        $postId = (int) ($_POST['id'] ?? 0);

        foreach (SKILL_FIELDS as $field) {
            $formValues[$field] = trim((string) ($_POST[$field] ?? ''));
        }

        /* ---------- Server-side validation ---------- */
        if ($formValues['name'] === '') {
            $errors[] = 'Name is required.';
        }

        foreach (SKILL_MAX_LENGTHS as $field => $max) {
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
                'name'       => $formValues['name'],
                'category'   => $formValues['category'] !== '' ? $formValues['category'] : null,
                'level'      => $formValues['level'] !== '' ? $formValues['level'] : null,
                'sort_order' => (int) $formValues['sort_order'],
            ];

            if ($postId > 0) {
                // Only update if the id actually exists — avoids a no-op
                // UPDATE silently "succeeding" against a deleted record.
                $exists = loadSkillRow($pdo, $postId);
                if ($exists === null) {
                    $errors[] = 'The record you tried to save no longer exists.';
                } else {
                    $params['id'] = $postId;
                    $stmt = $pdo->prepare(
                        'UPDATE skills
                         SET name       = :name,
                             category   = :category,
                             level      = :level,
                             sort_order = :sort_order,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE id = :id'
                    );
                    $stmt->execute($params);

                    header('Location: /admin/skills.php?saved=1');
                    exit;
                }
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO skills (name, category, level, sort_order)
                     VALUES (:name, :category, :level, :sort_order)'
                );
                $stmt->execute($params);

                header('Location: /admin/skills.php?saved=1');
                exit;
            }
        }

        // Validation failed: stay in the same create/edit state the user was in.
        $editId = $postId;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['saved'])) {
        $success = 'Skill saved.';
    } elseif (isset($_GET['deleted'])) {
        $success = 'Skill deleted.';
    }
}

$rows = loadSkillRows($pdo);

$pageTitle = 'Skills';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-crud">
  <div class="admin-dashboard__head">
    <div>
      <p class="meta">Skills</p>
      <h1><?= $editId > 0 ? 'Edit Skill' : 'Add Skill' ?></h1>
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

  <form method="post" action="skills.php" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editId > 0 ? (int) $editId : '' ?>">

    <label class="admin-field">
      <span>Name</span>
      <input type="text" name="name" value="<?= e($formValues['name']) ?>" maxlength="191" required autofocus>
    </label>

    <label class="admin-field">
      <span>Category</span>
      <input type="text" name="category" value="<?= e($formValues['category']) ?>" maxlength="191" placeholder="e.g. Networking, Programming">
    </label>

    <label class="admin-field">
      <span>Level</span>
      <input type="text" name="level" value="<?= e($formValues['level']) ?>" maxlength="64" placeholder="e.g. Beginner, Intermediate, Advanced">
    </label>

    <label class="admin-field">
      <span>Sort order</span>
      <input type="number" name="sort_order" value="<?= e($formValues['sort_order']) ?>" step="1">
    </label>

    <div class="admin-form-actions">
      <button class="btn btn--primary" type="submit"><?= $editId > 0 ? 'Save Changes' : 'Add Skill' ?></button>
<?php if ($editId > 0): ?>
      <a class="link-text" href="skills.php">Cancel</a>
<?php endif; ?>
    </div>
  </form>

  <h2 class="admin-crud__list-title">Existing Skills</h2>

<?php if (empty($rows)): ?>
  <p class="admin-dashboard__note">No skills yet. Add one using the form above.</p>
<?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Category</th>
          <th>Level</th>
          <th>Order</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($rows as $row): ?>
        <tr>
          <td><?= e((string) $row['name']) ?></td>
          <td class="meta"><?= e((string) ($row['category'] ?? '')) ?></td>
          <td class="meta"><?= e((string) ($row['level'] ?? '')) ?></td>
          <td class="meta"><?= (int) $row['sort_order'] ?></td>
          <td class="admin-table__actions">
            <a class="link-text" href="skills.php?edit=<?= (int) $row['id'] ?>">Edit</a>
            <form method="post" action="skills.php" onsubmit="return confirm('Delete this skill? This cannot be undone.');">
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