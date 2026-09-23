<?php

declare(strict_types=1);

/**
 * work.php — Work index (Phase 3.2)
 * portofolio.mohamadlingga.my.id/work
 *
 * Menampilkan seluruh project dari data/projects.php lewat
 * includes/project-card.php. Tidak ada project yang di-hardcode di sini.
 */

define('SITE_BOOT', true);
require __DIR__ . '/includes/functions.php';

$projects = getProjects();

$pageTitle       = 'Selected Work · Mohamad Lingga Syahputra';
$pageDescription = 'A collection of networking, infrastructure, automation, and AI projects.';
$canonicalUrl    = 'https://portofolio.mohamadlingga.my.id/work.php';
$currentPage     = 'work';

require __DIR__ . '/includes/header.php';
?>

    <!-- ===================== PAGE HERO ===================== -->
    <section class="page-hero">
      <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <ol role="list">
            <li><a href="index.php">Home</a></li>
            <li aria-current="page">Work</li>
          </ol>
        </nav>

        <h1 class="page-title reveal">Selected Work</h1>
        <p class="page-hero__subtitle reveal delay-1">A collection of networking, infrastructure, automation, and AI projects.</p>
      </div>
    </section>

    <!-- ===================== PROJECT GRID ===================== -->
    <section class="section">
      <div class="container">
        <div class="project-grid reveal">
<?php foreach ($projects as $project): ?>
<?php $headingTag = 'h2'; include __DIR__ . '/includes/project-card.php'; ?>
<?php endforeach; ?>
        </div>
      </div>
    </section>

<?php require __DIR__ . '/includes/footer.php'; ?>