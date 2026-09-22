<?php

declare(strict_types=1);

/**
 * includes/project-card.php
 *
 * Kartu project yang dapat dipakai ulang: Home (Selected Work), Work
 * (index penuh), dan project.php (Related Projects). Seluruh kartu dapat
 * diklik lewat pola "stretched link" — satu <a> di judul, diperluas
 * secara visual ke seluruh kartu lewat CSS (.stretched-link), supaya
 * tetap satu link per kartu (bukan link bersarang).
 *
 * Variabel yang harus di-set SEBELUM include ini:
 *   $project     (array)  wajib — satu baris data dari data/projects.php
 *   $headingTag  (string) opsional — 'h2' atau 'h3', default 'h3'
 *
 * Membutuhkan e() dan asset() dari includes/header.php, serta
 * projectStatusMeta() dari includes/functions.php — pastikan keduanya
 * sudah di-require di halaman pemanggil sebelum include ini.
 */

if (!defined('SITE_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

/** @var array<string,mixed> $project */
/** @var string $headingTag */

$headingTag = $headingTag ?? 'h3';
$statusMeta = projectStatusMeta($project['status']);

$headingTag = $headingTag ?? 'h3';
$statusMeta = projectStatusMeta($project['status']);
$detailUrl  = 'project.php?slug=' . rawurlencode($project['slug']);
?>
<article class="card project-card">
  <p class="project-card__category"><?= e($project['category']) ?></p>

  <<?= $headingTag ?> class="project-card__title"><a class="stretched-link" href="<?= e($detailUrl) ?>"><?= e($project['title']) ?></a></<?= $headingTag ?>>

  <p class="project-card__desc"><?= e($project['summary']) ?></p>

  <ul class="tag-list" role="list" aria-label="Technologies">
<?php foreach ($project['technologies'] as $tech): ?>
    <li class="tag"><?= e($tech) ?></li>
<?php endforeach; ?>
  </ul>

  <p class="project-card__meta">
    <span class="status-badge status-badge--<?= e($statusMeta['tone']) ?>">
      <span class="status-badge__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><?= $statusMeta['icon'] ?></svg></span>
      <span><?= e($statusMeta['label']) ?></span>
    </span>
    <span class="meta"><?= e($project['year']) ?></span>
  </p>

  <span class="project-card__link" aria-hidden="true">View Case Study &rarr;</span>
</article>