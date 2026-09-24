<?php

declare(strict_types=1);

/**
 * public_html/admin/messages.php
 *
 * FINAL QA item #4 — "Send Message" audit result.
 *
 * BEFORE this fix: the public "Send Message" button was a disabled dummy
 * link (`<a href="#" data-dummy aria-disabled="true">`) with NO form and
 * NO handler anywhere in the codebase — nothing was ever processed or
 * stored. See public_html/index.php for the new real form + handler
 * (submitContactMessage() in includes/functions.php), which inserts into
 * the new, additive `messages` table (database/schema-*.sql).
 *
 * This page is the admin-side counterpart: list / mark read / delete,
 * following the exact same pattern as admin/media.php (PDO prepared
 * statements, requireAdmin()/CSRF/e(), PRG after state changes).
 */

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/helpers.php';
require __DIR__ . '/../../app/admin-auth.php';

requireAdmin();

$pdo = db();

function loadMessageRows(PDO $pdo): array
{
    try {
        $stmt = $pdo->query(
            'SELECT id, name, email, message, is_read, created_at
             FROM messages
             ORDER BY created_at DESC, id DESC'
        );

        return $stmt ? $stmt->fetchAll() : [];
    } catch (Throwable $e) {
        // Table may not exist yet if database/migrate.php (or setup.php)
        // hasn't been re-run since this fix was applied — degrade to an
        // empty list with a clear notice below, never a fatal error.
        return [];
    }
}

function messagesTableExists(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM messages LIMIT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

$errors  = [];
$success = '';

$tableExists = messagesTableExists($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please reload the page and try again.';
    } elseif ($id <= 0) {
        $errors[] = 'Invalid message.';
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM messages WHERE id = :id');
        $stmt->execute(['id' => $id]);

        header('Location: /admin/messages.php?deleted=1');
        exit;
    } elseif ($action === 'mark_read') {
        $stmt = $pdo->prepare('UPDATE messages SET is_read = 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);

        header('Location: /admin/messages.php?read=1');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['deleted'])) {
        $success = 'Message deleted.';
    } elseif (isset($_GET['read'])) {
        $success = 'Message marked as read.';
    }
}

$rows = $tableExists ? loadMessageRows($pdo) : [];

$pageTitle = 'Messages';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-crud">
  <div class="admin-dashboard__head">
    <div>
      <p class="meta">Messages</p>
      <h1>Contact Messages</h1>
    </div>
  </div>

  <p class="admin-dashboard__note">
    Messages submitted through the public <code>Send Message</code> form
    (Home &middot; Contact section). Read-only content — mark as read or
    delete.
  </p>

<?php if (!$tableExists): ?>
  <p class="admin-alert" role="alert">
    The <code>messages</code> table does not exist yet on this database.
    Run <code>php database/migrate.php</code> (or the browser setup page)
    again to create it — it's an additive change and will not touch any
    existing data.
  </p>
<?php endif; ?>

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

<?php if ($tableExists && empty($rows)): ?>
  <p class="admin-dashboard__note">No messages yet.</p>
<?php elseif ($tableExists): ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>From</th>
          <th>Message</th>
          <th>Received</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($rows as $row): ?>
        <tr>
          <td>
            <?= e((string) $row['name']) ?><br>
            <span class="meta"><?= e((string) $row['email']) ?></span>
          </td>
          <td style="white-space: normal; max-width: 360px;"><?= nl2br(e((string) $row['message'])) ?></td>
          <td class="meta"><?= e((string) $row['created_at']) ?></td>
          <td class="meta"><?= !empty($row['is_read']) ? 'Read' : 'Unread' ?></td>
          <td class="admin-table__actions">
<?php if (empty($row['is_read'])): ?>
            <form method="post" action="messages.php">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="mark_read">
              <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
              <button class="link-text" type="submit">Mark read</button>
            </form>
<?php endif; ?>
            <form method="post" action="messages.php" onsubmit="return confirm('Delete this message? This cannot be undone.');">
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
