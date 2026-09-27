<?php

declare(strict_types=1);

/**
 * project.php — Project detail (Phase 3.2 + 3.3 pretty URL + Phase 4.9 DB)
 * portofolio.mohamadlingga.my.id/project/{slug}
 *
 * Slug diambil dari ?slug= — baik lewat akses langsung (legacy) maupun
 * lewat rewrite .htaccess dari /project/{slug} (internal, transparan).
 *
 * Slug tidak dikenal (atau kosong / format tidak valid) menampilkan 404
 * sederhana pada URL yang sama, tetap di dalam layout situs — tidak ada
 * redirect untuk kasus ini.
 *
 * Akses legacy langsung ke project.php?slug=... (bukan lewat pretty URL)
 * di-301-redirect ke URL kanonik /project/{slug} agar tidak ada dua URL
 * berbeda untuk konten yang sama (SEO), tanpa redirect loop — dideteksi
 * lewat REQUEST_URI: rewrite internal .htaccess tidak mengubah REQUEST_URI
 * menjadi project.php, hanya akses langsung yang mengandungnya.
 *
 * Phase 4.9: sumber data project sekarang lewat getProjectBySlug(), yang
 * membaca MySQL (tabel `projects`) dengan fallback otomatis ke
 * data/projects.php bila DB kosong/gagal — lihat includes/functions.php.
 * Bentuk array $project identik apa pun sumbernya, jadi seluruh logika
 * di bawah (404, redirect, SEO, breadcrumb, related) tidak berubah.
 *
 * PHASE 5.3: Gallery section rewritten to a responsive grid with a
 * simple lightbox (assets/js/lightbox.js + style.css additions) and
 * unlimited items — no fixed count anywhere. Non-image media (PDF,
 * other documents) is split into its own "Documentation" attachment
 * list instead of being mixed into the image grid, since those still
 * need to open as a normal attachment rather than a lightbox. Routing,
 * SEO fields (canonical/OG/robots), and every other section below are
 * untouched.
 */

define('SITE_BOOT', true);
require __DIR__ . '/includes/functions.php';

$slug        = isset($_GET['slug']) ? (string) $_GET['slug'] : '';
$isSlugValid = $slug !== '' && preg_match('/^[a-z0-9-]+$/', $slug) === 1;
$project     = $isSlugValid ? getProjectBySlug($slug) : null;

/* ===================== 404: PROJECT TIDAK DITEMUKAN ===================== */
if ($project === null) {
    http_response_code(404);

    $pageTitle       = 'Project Not Found · Mohamad Lingga Syahputra';
    $pageDescription = 'The project you are looking for could not be found.';
    $canonicalUrl    = 'https://portofolio.mohamadlingga.my.id/work.php';
    $pageRobots      = 'noindex, nofollow';
    $currentPage     = 'work';

    require __DIR__ . '/includes/header.php';
    ?>

    <section class="section">
      <div class="container notfound">
        <p class="meta">Error 404</p>
        <h1 class="page-title">Project Not Found</h1>
        <p>The project you&rsquo;re looking for doesn&rsquo;t exist, or may have been moved.</p>
        <a class="btn btn--secondary" href="work.php">Back to Work</a>
      </div>
    </section>

    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* ===================== BACKWARD COMPAT: LEGACY URL → PRETTY URL ===================== */
/*
 * Jika project.php diakses langsung (project.php?slug=...) — bukan lewat
 * rewrite internal /project/{slug} — redirect 301 ke URL kanonik.
 * REQUEST_URI hanya mengandung "project.php" pada akses langsung; request
 * lewat .htaccess rewrite tetap menampilkan /project/{slug} di REQUEST_URI,
 * jadi tidak akan masuk blok ini (aman dari redirect loop).
 */
$requestUri     = $_SERVER['REQUEST_URI'] ?? '';
$isLegacyAccess = str_contains($requestUri, 'project.php');

if ($isLegacyAccess) {
    header('Location: ' . projectUrl($project['slug']), true, 301);
    exit;
}

/* ===================== PROJECT DITEMUKAN ===================== */
$related = getRelatedProjects($project['slug'], 2);

/*
 * Phase 4.9: gallery hanya diambil jika project ini berasal dari MySQL
 * ('_source' === 'db') — lihat catatan di getProjectMedia() pada
 * includes/functions.php. Project hasil fallback data/projects.php
 * memakai id statis 1/2/3 yang tidak boleh diasumsikan berkorespondensi
 * dengan project_media.project_id milik baris DB yang mungkin masih ada.
 */
$media = ($project['_source'] ?? null) === 'db' && !empty($project['id'])
    ? getProjectMedia((int) $project['id'])
    : [];

/*
 * PHASE 5.3: split into image items (rendered as the lightbox gallery)
 * vs everything else (rendered as plain "Documentation" attachment
 * links, same as before — PDFs/other files still open as a normal
 * link, never forced into the image grid). No count is assumed either
 * way; both lists can be empty, one item, or many.
 */
$galleryImages = array_values(array_filter($media, static function (array $item): bool {
    return ($item['type'] ?? 'image') === 'image';
}));
$attachments = array_values(array_filter($media, static function (array $item): bool {
    return ($item['type'] ?? 'image') !== 'image';
}));

$pageTitle       = $project['title'] . ' · Mohamad Lingga Syahputra';
$pageDescription = $project['summary'];
$canonicalUrl    = 'https://portofolio.mohamadlingga.my.id' . projectUrl($project['slug']);
$ogType          = 'article';
$currentPage     = 'project';

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/project-header.php';
?>

    <!-- ===================== CASE STUDY BODY ===================== -->
    <section class="section">
      <div class="container detail">

        <!-- ---- Overview ---- -->
        <div class="detail-block reveal">
          <h2>Overview</h2>
          <dl class="overview-grid">
            <div>
              <dt class="meta">Category</dt>
              <dd><?= e($project['category']) ?></dd>
            </div>
            <div>
              <dt class="meta">Status</dt>
              <dd><?= e($project['status']) ?></dd>
            </div>
            <div>
              <dt class="meta">Year</dt>
              <dd><?= e($project['year']) ?></dd>
            </div>
            <div>
              <dt class="meta">Technologies</dt>
              <dd><?= e(implode(', ', $project['technologies'])) ?></dd>
            </div>
          </dl>
        </div>

        <!-- ---- Problem ---- -->
        <div class="detail-block reveal">
          <h2>The Problem</h2>
          <p><?= e($project['problem']) ?></p>
        </div>

        <!-- ---- Approach ---- -->
        <div class="detail-block reveal">
          <h2>Architecture &amp; Approach</h2>
          <p><?= e($project['approach']) ?></p>
          <?= projectTopologySvg($project['slug']) ?>
        </div>

<?php if (!empty($galleryImages)): ?>
        <!-- ---- Gallery (project_media, PHASE 5.3: responsive grid + lightbox) ----
             Unlimited items, never a hardcoded count. The lightbox is
             plain vanilla JS (assets/js/lightbox.js); without JS every
             thumbnail is still a normal <a href> straight to the
             full-size image, so nothing breaks for a no-JS visitor. -->
        <div class="detail-block reveal">
          <h2>Gallery</h2>
          <ul class="gallery-grid" role="list">
<?php foreach ($galleryImages as $item): ?>
<?php $itemAlt = (string) ($item['alt_text'] ?? ''); ?>
            <li class="gallery-item">
              <a class="gallery-item__link" href="<?= e((string) $item['path']) ?>" data-lightbox<?= $itemAlt !== '' ? ' data-caption="' . e($itemAlt) . '"' : '' ?>>
                <img src="<?= e((string) $item['path']) ?>" alt="<?= e($itemAlt) ?>" loading="lazy">
              </a>
<?php if ($itemAlt !== ''): ?>
              <p class="gallery-item__caption meta"><?= e($itemAlt) ?></p>
<?php endif; ?>
            </li>
<?php endforeach; ?>
          </ul>
        </div>
<?php endif; ?>

<?php if (!empty($attachments)): ?>
        <!-- ---- Documentation (non-image project_media: PDF, other files) ---- -->
        <div class="detail-block reveal">
          <h2>Documentation</h2>
          <ul class="attachment-list" role="list">
<?php foreach ($attachments as $item): ?>
<?php
    $itemAlt  = (string) ($item['alt_text'] ?? '');
    $itemPath = (string) $item['path'];
    $itemType = (string) ($item['type'] ?? 'file');
?>
            <li class="attachment-list__item">
              <a class="link-text" href="<?= e($itemPath) ?>" target="_blank" rel="noopener noreferrer"><?= e($itemAlt !== '' ? $itemAlt : $itemPath) ?></a>
              <span class="meta attachment-list__type"><?= e(strtoupper($itemType)) ?></span>
            </li>
<?php endforeach; ?>
          </ul>
        </div>
<?php endif; ?>

        <!-- ---- Implementation ---- -->
        <div class="detail-block reveal">
          <h2>Implementation</h2>
          <ul class="tag-list" role="list" aria-label="Technologies used">
<?php foreach ($project['technologies'] as $tech): ?>
            <li class="tag"><?= e($tech) ?></li>
<?php endforeach; ?>
          </ul>
        </div>

        <!-- ---- Result ---- -->
        <div class="detail-block reveal">
          <h2>Result</h2>
          <p><?= e($project['result']) ?></p>
<?php if (!empty($project['result_highlight'])): ?>
          <div class="callout">
            <span class="callout__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg></span>
            <span><?= e($project['result_highlight']) ?></span>
          </div>
<?php endif; ?>
        </div>

        <!-- ---- Lessons Learned ---- -->
        <div class="detail-block reveal">
          <h2>Lessons Learned</h2>
          <div class="panel">
            <p><?= e($project['lessons']) ?></p>
          </div>
        </div>

      </div>
    </section>

<?php if (!empty($related)): ?>
    <!-- ===================== RELATED PROJECTS ===================== -->
    <section class="section section--alt">
      <div class="container">
        <div class="section-head reveal">
          <h2>Related Projects</h2>
        </div>
        <div class="project-grid project-grid--related reveal">
<?php foreach ($related as $relatedProject): ?>
<?php $project = $relatedProject; $headingTag = 'h3'; include __DIR__ . '/includes/project-card.php'; ?>
<?php endforeach; ?>
        </div>
      </div>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
