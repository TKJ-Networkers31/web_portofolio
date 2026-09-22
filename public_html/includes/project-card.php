<?php

declare(strict_types=1);

/**
 * includes/project-card.php
 *
 * Markup SATU kartu project. Dipakai lewat `include` di dalam loop, dari:
 *   - index.php   (Selected Work, featured saja)
 *   - work.php    (semua project)
 *   - project.php (Related Projects)
 *
 * Variabel yang harus sudah di-set oleh caller sebelum include ini:
 *   $project     (array)  — satu item dari data/projects.php
 *   $headingTag  (string) — 'h2' atau 'h3', supaya hierarki heading per
 *                            halaman tetap benar (lihat pemanggil masing2)
 *
 * Membutuhkan e(), projectUrl(), projectStatusMeta() dari
 * includes/functions.php, sudah di-require sebelum include ini.
 */

if (!defined('SITE_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

/** @var array<string,mixed> $project */
/** @var string $headingTag */

$headingTag = $headingTag ?? 'h3';
$statusMeta = projectStatusMeta($project['status']);
$url        = projectUrl($project['slug']);
?>
<article class="card">
  <p class="project-card__category"><?= e($project['category']) ?></p>

  <<?= $headingTag ?> class="project-card__title">
    <a class="stretched-link" href="<?= e($url) ?>"><?= e($project['title']) ?></a>
  </<?= $headingTag ?>>

  <p class="project-card__desc"><?= e($project['summary']) ?></p>

  <ul class="tag-list" role="list" aria-label="Technologies used">
<?php foreach ($project['technologies'] as $tech): ?>
    <li class="tag"><?= e($tech) ?></li>
<?php endforeach; ?>
  </ul>

  <div class="project-card__meta">
    <span class="status-badge status-badge--<?= e($statusMeta['tone']) ?>">
      <span class="status-badge__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><?= $statusMeta['icon'] ?></svg></span>
      <span><?= e($statusMeta['label']) ?></span>
    </span>
    <span class="meta"><?= e($project['year']) ?></span>
  </div>
</article>