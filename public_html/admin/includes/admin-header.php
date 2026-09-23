<?php

declare(strict_types=1);

/**
 * public_html/admin/includes/admin-header.php
 *
 * Minimal admin shell that reuses the public site's design tokens
 * (tokens.css, reset.css) so the admin area feels consistent, without
 * pulling in public-page-specific components or redesigning anything.
 */

if (!defined('CMS_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

$pageTitle    = $pageTitle ?? 'Admin';
$loggedIn     = function_exists('isAdminLoggedIn') && isAdminLoggedIn();
$adminScript  = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> &middot; Admin &middot; Mohamad Lingga Syahputra</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="../assets/css/tokens.css">
  <link rel="stylesheet" href="../assets/css/reset.css">
  <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-body">
<?php if ($loggedIn): ?>
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <p class="admin-sidebar__brand">ML <span class="meta">Admin</span></p>
      <nav aria-label="Admin sections">
        <ul role="list">
          <li><a href="index.php"<?= $adminScript === 'index.php' ? ' aria-current="page"' : '' ?>>Dashboard</a></li>
          <li><a href="profile.php"<?= $adminScript === 'profile.php' ? ' aria-current="page"' : '' ?>>Profile</a></li>
          <li><a href="education.php"<?= $adminScript === 'education.php' ? ' aria-current="page"' : '' ?>>Education</a></li>
          <li><a href="experience.php"<?= $adminScript === 'experience.php' ? ' aria-current="page"' : '' ?>>Experience</a></li>
          <li><a href="skills.php"<?= $adminScript === 'skills.php' ? ' aria-current="page"' : '' ?>>Skills</a></li>
          <li><a href="certifications.php"<?= $adminScript === 'certifications.php' ? ' aria-current="page"' : '' ?>>Certifications</a></li>
          <li><a href="projects.php"<?= ($adminScript === 'projects.php' || $adminScript === 'project-media.php') ? ' aria-current="page"' : '' ?>>Projects</a></li>
          <li><span class="admin-nav__disabled" aria-disabled="true">Contact</span></li>
          <li><span class="admin-nav__disabled" aria-disabled="true">CV</span></li>
          <li><span class="admin-nav__disabled" aria-disabled="true">Media</span></li>
        </ul>
      </nav>
      <a class="link-text" href="../index.php">&larr; View site</a>
    </aside>
    <main class="admin-main">
<?php else: ?>
    <main class="admin-main admin-main--auth">
<?php endif; ?>