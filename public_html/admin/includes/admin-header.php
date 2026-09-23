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

$pageTitle = $pageTitle ?? 'Admin';
$loggedIn  = function_exists('isAdminLoggedIn') && isAdminLoggedIn();
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
          <li><a href="index.php" aria-current="page">Dashboard</a></li>
          <li><span class="admin-nav__disabled" aria-disabled="true">Profile</span></li>
          <li><span class="admin-nav__disabled" aria-disabled="true">Education</span></li>
          <li><span class="admin-nav__disabled" aria-disabled="true">Experience</span></li>
          <li><span class="admin-nav__disabled" aria-disabled="true">Skills</span></li>
          <li><span class="admin-nav__disabled" aria-disabled="true">Certifications</span></li>
          <li><span class="admin-nav__disabled" aria-disabled="true">Projects</span></li>
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
