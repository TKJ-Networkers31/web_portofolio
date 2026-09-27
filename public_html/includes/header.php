<?php
/**
 * includes/header.php
 *
 * Membuka dokumen HTML: <head>, skip link, navbar, dan tag <main>.
 * Ditutup oleh includes/footer.php.
 *
 * PHASE 6: nav-env (ecosystem switcher) sebelumnya berisi 2 tautan dummy
 * ("Business", "Lab" — <a href="#" data-dummy aria-disabled="true">).
 * Sekarang membaca URL nyata dari getEcosystemLinks() (includes/
 * functions.php), dan "Business" menjadi label resmi "LS Technology".
 * Jika sebuah URL belum diatur (.env kosong), entri itu tetap tampil
 * sebagai dummy/disabled persis seperti sebelumnya — tidak ada link mati.
 */

if (!defined('SITE_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

if (!function_exists('e')) {
    /** Escape output untuk konteks HTML. */
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('asset')) {
    /**
     * Path aset root-absolute dengan versi (filemtime) untuk cache busting.
     * Root-absolute (diawali "/") supaya tetap benar dari URL manapun,
     * termasuk pretty URL /project/{slug} — lihat catatan Phase 3.2 QA
     * di atas.
     */
    function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = dirname(__DIR__) . '/' . $path;
        $version = is_file($file) ? (string) filemtime($file) : '1';

        return '/' . $path . '?v=' . $version;
    }
}

$pageTitle       = $pageTitle ?? 'Mohamad Lingga Syahputra';
$pageDescription = $pageDescription ?? '';
$canonicalUrl    = $canonicalUrl ?? 'https://portofolio.mohamadlingga.my.id/';
$pageRobots      = $pageRobots ?? 'index, follow';
$ogType          = $ogType ?? 'website';

$currentPage = $currentPage ?? 'home';

/*
 * Root-absolute: sebelumnya "index.php#about" / "#about" campur relatif,
 * yang salah resolve di bawah /project/{slug}. Sekarang selalu diawali
 * "/" untuk tautan ke halaman lain, dan hash-only untuk anchor di
 * halaman yang sama.
 */
$brandHref   = $currentPage === 'home' ? '#home' : '/index.php';
$aboutHref   = $currentPage === 'home' ? '#about' : '/index.php#about';
$contactHref = $currentPage === 'home' ? '#contact' : '/index.php#contact';

$workAriaCurrent = match ($currentPage) {
    'work'    => 'page', // halaman Work itu sendiri
    'project' => 'true', // halaman turunan Work (Phase 2 IA §5.3)
    default   => null,
};

// PHASE 6: ecosystem URLs — null if not configured in .env.
$ecoLinks = getEcosystemLinks();
?>

<!doctype html>
<html lang="en" class="no-js">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($pageTitle) ?></title>
<?php if ($pageDescription !== ''): ?>
  <meta name="description" content="<?= e($pageDescription) ?>">
<?php endif; ?>
  <meta name="color-scheme" content="dark">
  <meta name="theme-color" content="#060709">
  <meta name="robots" content="<?= e($pageRobots) ?>">
  <link rel="canonical" href="<?= e($canonicalUrl) ?>">

  <meta property="og:type" content="<?= e($ogType) ?>">
  <meta property="og:site_name" content="Mohamad Lingga Syahputra">
  <meta property="og:title" content="<?= e($pageTitle) ?>">
<?php if ($pageDescription !== ''): ?>
  <meta property="og:description" content="<?= e($pageDescription) ?>">
<?php endif; ?>
  <meta property="og:url" content="<?= e($canonicalUrl) ?>">

  <!-- Favicon inline (tanpa file tambahan). Ganti dengan aset final dari Phase 1. -->
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%23060709'/%3E%3Crect x='.5' y='.5' width='31' height='31' rx='5.5' fill='none' stroke='%232e3742'/%3E%3Ccircle cx='16' cy='16' r='5' fill='%233db8f5'/%3E%3C/svg%3E">

  <!--
    Mode JavaScript: kelas "js" mengaktifkan keadaan awal reveal dan menu mobile.
    Pengaman: jika main.js tidak sempat berjalan dalam 4 detik (mis. gagal
    dimuat), kelas dikembalikan ke "no-js" sehingga semua konten tetap terlihat.
  -->
  <script>
    (function (doc) {
      var root = doc.documentElement;
      root.className = root.className.replace('no-js', 'js');
      window.setTimeout(function () {
        if (!root.classList.contains('is-ready')) {
          root.className = root.className.replace('js', 'no-js');
        }
      }, 4000);
    })(document);
  </script>

  <link rel="stylesheet" href="<?= e(asset('assets/css/tokens.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('assets/css/reset.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('assets/css/layout.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('assets/css/components.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('assets/css/animations.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>

  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="<?= e($brandHref) ?>">
        <span class="brand__mark" aria-hidden="true">ML</span>
        <span class="brand__name">Mohamad Lingga</span>
      </a>

      <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-menu" aria-label="Open menu" data-nav-toggle>
        <span class="nav-toggle__bar" aria-hidden="true"></span>
        <span class="nav-toggle__bar" aria-hidden="true"></span>
        <span class="nav-toggle__bar" aria-hidden="true"></span>
      </button>

      <div class="nav-panel" id="site-menu">
        <nav class="nav-local" aria-label="Primary">
          <ul role="list">
            <li><a href="/work.php"<?= $workAriaCurrent !== null ? ' aria-current="' . e($workAriaCurrent) . '"' : '' ?>>Work</a></li>
            <li><a href="<?= e($aboutHref) ?>">About</a></li>
            <li><a href="<?= e($contactHref) ?>">Contact</a></li>
          </ul>
        </nav>

        <!--
          PHASE 6: ecosystem switcher — real links from .env (ROOT_URL,
          LAB_URL, BUSINESS_URL). Any URL left empty renders exactly like
          the old dummy state (data-dummy, aria-disabled) so there is
          never a dead link. "Business" is now labelled "LS Technology".
        -->
        <nav class="nav-env" aria-label="Ecosystem">
          <ul role="list">
            <li><a href="/" aria-current="true">Portfolio</a></li>

<?php if (!empty($ecoLinks['lab'])): ?>
            <li><a href="<?= e($ecoLinks['lab']) ?>" rel="noopener noreferrer">Lab</a></li>
<?php else: ?>
            <li><a href="#" data-dummy aria-disabled="true">Lab</a></li>
<?php endif; ?>

<?php if (!empty($ecoLinks['business'])): ?>
            <li><a href="<?= e($ecoLinks['business']) ?>" rel="noopener noreferrer">LS Technology</a></li>
<?php else: ?>
            <li><a href="#" data-dummy aria-disabled="true">LS Technology</a></li>
<?php endif; ?>

<?php if (!empty($ecoLinks['root'])): ?>
            <li><a href="<?= e($ecoLinks['root']) ?>" rel="noopener noreferrer">Root</a></li>
<?php else: ?>
            <li><a href="#" data-dummy aria-disabled="true">Root</a></li>
<?php endif; ?>
          </ul>
        </nav>
      </div>
    </div>
  </header>

  <main id="main">
