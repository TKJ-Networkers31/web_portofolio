<?php

declare(strict_types=1);

/**
 * includes/project-header.php
 *
 * Hero halaman detail project: breadcrumb, tautan kembali ke Work,
 * kategori, judul (H1), status · tahun, dan ringkasan.
 *
 * Membutuhkan $project (array) sudah di-set oleh project.php, dan
 * projectStatusMeta() dari includes/functions.php, sebelum include ini.
 */

if (!defined('SITE_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

/** @var array<string,mixed> $project */

$statusMeta = projectStatusMeta($project['status']);

$statusMeta = projectStatusMeta($project['status']);
?>
<section class="project-hero">
  <div class="container">
    <div class="project-hero__top">
      <nav class="breadcrumb" aria-label="Breadcrumb">
        <ol role="list">
          <li><a href="index.php">Home</a></li>
          <li><a href="work.php">Work</a></li>
          <li aria-current="page"><?= e($project['title']) ?></li>
        </ol>
      </nav>

      <a class="link-text" href="work.php">&larr; Back to Work</a>
    </div>

    <p class="project-hero__category"><?= e($project['category']) ?></p>
    <h1 class="page-title"><?= e($project['title']) ?></h1>

    <p class="project-hero__meta">
      <span class="status-badge status-badge--<?= e($statusMeta['tone']) ?>">
        <span class="status-badge__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><?= $statusMeta['icon'] ?></svg></span>
        <span><?= e($statusMeta['label']) ?></span>
      </span>
      <span aria-hidden="true">&bull;</span>
      <span><?= e($project['year']) ?></span>
    </p>

    <p class="project-hero__summary"><?= e($project['summary']) ?></p>
  </div>
</section>