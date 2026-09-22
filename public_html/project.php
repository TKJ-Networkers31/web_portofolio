<?php

declare(strict_types=1);

/**
 * project.php — Project detail (Phase 3.2)
 * portofolio.mohamadlingga.my.id/project.php?slug=...
 *
 * Mengambil satu project berdasarkan ?slug=. Slug tidak dikenal (atau
 * kosong / format tidak valid) menampilkan 404 sederhana pada URL yang
 * sama, tetap di dalam layout situs — tidak ada redirect.
 */

define('SITE_BOOT', true);
require __DIR__ . '/includes/functions.php';

$slug        = isset($_GET['slug']) ? (string) $_GET['slug'] : '';
$isSlugValid = $slug !== '' && preg_match('/^[a-z0-9-]+$/', $slug) === 1;
$project     = $isSlugValid ? findProjectBySlug($slug) : null;

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

/* ===================== PROJECT DITEMUKAN ===================== */
$related = getRelatedProjects($project['slug'], 2);

$pageTitle       = $project['title'] . ' · Mohamad Lingga Syahputra';
$pageDescription = $project['summary'];
$canonicalUrl    = 'https://portofolio.mohamadlingga.my.id/project.php?slug=' . rawurlencode($project['slug']);
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