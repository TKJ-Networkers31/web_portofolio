<?php

declare(strict_types=1);

/**
 * public_html/admin/index.php
 *
 * Dashboard skeleton for Phase 4.1. Shows who is logged in and a
 * read-only record count per content area — no editing yet, that lands
 * in later phases module by module.
 */

require __DIR__ . '/../../config/config.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../app/helpers.php';
require __DIR__ . '/../../app/admin-auth.php';

requireAdmin();

$admin = currentAdmin();
$pdo   = db();

/** Defensive count: never fatal even if a table is empty or briefly locked. */
function safeCount(PDO $pdo, string $table): int
{
    try {
        $stmt = $pdo->query('SELECT COUNT(*) AS c FROM ' . $table);
        $row  = $stmt ? $stmt->fetch() : null;
        return (int) ($row['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

$sections = [
    ['label' => 'Profile',                'table' => 'profile'],
    ['label' => 'Education',              'table' => 'education'],
    ['label' => 'Experience',             'table' => 'experience'],
    ['label' => 'Skills',                 'table' => 'skills'],
    ['label' => 'Certifications',         'table' => 'certifications'],
    ['label' => 'Projects',               'table' => 'projects'],
    ['label' => 'Contact / Social Links', 'table' => 'contacts'],
    ['label' => 'CV / Documents',         'table' => 'documents'],
    ['label' => 'Messages',               'table' => 'contact_messages'],
    ['label' => 'Media',                  'table' => 'media'],
];

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-dashboard">
  <div class="admin-dashboard__head">
    <div>
      <p class="meta">Signed in as</p>
      <h1><?= e($admin['username'] ?? '') ?></h1>
    </div>
    <form method="post" action="logout.php">
      <?= csrfField() ?>
      <button class="btn btn--secondary" type="submit">Log Out</button>
    </form>
  </div>

  <p class="admin-dashboard__note">
    Phase 4.1 — CMS foundation. The database, authentication, and this
    dashboard are live. Editing screens for each section below ship in
    later phases, one module at a time.
  </p>

  <div class="admin-grid">
<?php foreach ($sections as $section): ?>
    <article class="admin-card">
      <p class="admin-card__label"><?= e($section['label']) ?></p>
      <p class="admin-card__count"><?= safeCount($pdo, $section['table']) ?> <span class="meta">records</span></p>
      <p class="admin-card__status meta">Editing coming in a later phase</p>
    </article>
<?php endforeach; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/admin-footer.php'; ?>
