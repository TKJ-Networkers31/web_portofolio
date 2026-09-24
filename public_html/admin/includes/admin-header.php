<?php

declare(strict_types=1);

/**
 * public_html/admin/includes/admin-header.php
 *
 * Minimal admin shell that reuses the public site's design tokens
 * (tokens.css, reset.css) so the admin area feels consistent, without
 * pulling in public-page-specific components or redesigning anything.
 *
 * FINAL QA item #5 (admin UI "too flat / boring"):
 * Audit found the actual root cause — this file linked tokens.css,
 * reset.css and admin.css only. It never linked components.css, which
 * is where .btn, .btn--primary/--secondary, .link-text, .status-badge,
 * .tag, etc. are defined. Every admin page uses those classes (Log Out,
 * Save Changes, Delete, Edit…) but they were rendering completely
 * unstyled — plain browser default buttons/links — because the
 * stylesheet that styles them was simply never loaded in admin. Adding
 * the missing <link> here, with no other markup change, is what fixes
 * the majority of the "flat" complaint; admin.css (polished separately)
 * layers admin-specific spacing/cards on top of it.
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
  <link rel="stylesheet" href="../assets/css/components.css">
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
          <li><a href="contact.php"<?= $adminScript === 'contact.php' ? ' aria-current="page"' : '' ?>>Contact</a></li>
          <li><a href="social.php"<?= $adminScript === 'social.php' ? ' aria-current="page"' : '' ?>>Social</a></li>
          <li><a href="messages.php"<?= $adminScript === 'messages.php' ? ' aria-current="page"' : '' ?>>Messages</a></li>
          <li><a href="cv.php"<?= $adminScript === 'cv.php' ? ' aria-current="page"' : '' ?>>CV</a></li>
          <li><a href="media.php"<?= $adminScript === 'media.php' ? ' aria-current="page"' : '' ?>>Media</a></li>
        </ul>
      </nav>
      <a class="link-text" href="../index.php">&larr; View site</a>
    </aside>
    <main class="admin-main">
<?php else: ?>
    <main class="admin-main admin-main--auth">
<?php endif; ?>
