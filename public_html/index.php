<?php
declare(strict_types=1);

/**
 * index.php — Home Portfolio (Phase 3.1)
 * portofolio.mohamadlingga.my.id
 *
 * Halaman tunggal dengan anchor: #home, #work, #about, #capabilities,
 * #ecosystem, #contact.
 *
 * CATATAN KONTEN: seluruh isi di bawah adalah konten sementara sesuai brief
 * Phase 3.1. Penanda [CONTENT REQUIRED] wajib diganti dengan data owner dan
 * tidak boleh dipublikasikan.
 */

// SESUDAH
 /**
 * CATATAN KONTEN: seluruh isi di bawah adalah konten sementara sesuai brief
 * Phase 3.1. Penanda [CONTENT REQUIRED] wajib diganti dengan data owner dan
 * tidak boleh dipublikasikan.
 *
 * PHASE 3.2: section Selected Work sekarang membaca data/projects.php
 * (lewat getFeaturedProjects()) dan me-render tiap kartu lewat
 * includes/project-card.php, alih-alih array dummy terpisah — supaya Home
 * dan Work selalu menampilkan project yang sama persis dari satu sumber.
 *
 * PHASE 4.2: hero statement, About lead, dan Location sekarang membaca
 * dari tabel `profile` lewat getPublicProfile() jika baris profile sudah
 * diisi lewat /admin/profile.php. Jika belum ada / gagal dibaca, teks
 * placeholder Phase 3.1 tetap tampil persis seperti sebelumnya.
 *
 * PHASE 4.3: Education di section About sekarang membaca dari tabel
 * `education` lewat getEducationList() jika sudah ada entri lewat
 * /admin/education.php. Experience TIDAK diintegrasikan ke publik pada
 * fase ini karena belum ada section Experience di Phase 3 — CRUD-nya
 * sudah aktif di admin, tapi menunggu section publik dibuat di fase lain
 * supaya tidak melakukan redesign besar di luar scope.
 */

define('SITE_BOOT', true);
require __DIR__ . '/includes/functions.php';

$pageTitle       = 'Mohamad Lingga Syahputra | Network Engineering, Infrastructure, AI Systems';
$pageDescription = 'Building reliable network infrastructure, automation, and intelligent systems.';
$canonicalUrl    = 'https://portofolio.mohamadlingga.my.id/';

/* ---------- Data ---------- */

$focusAreas = ['Network Engineering', 'Infrastructure', 'AI Systems'];

/* Project featured saja yang tampil di Selected Work (data/projects.php). */
$featuredProjects = getFeaturedProjects();

/* Phase 4.2: profile dari tabel `profile`, dengan fallback ke teks Phase 3.1. */
$publicProfile = getPublicProfile();

$heroStatement = ($publicProfile['headline'] ?? '') !== ''
    ? $publicProfile['headline']
    : 'Building reliable network infrastructure, automation, and intelligent systems.';

$aboutLead = ($publicProfile['bio'] ?? '') !== ''
    ? $publicProfile['bio']
    : "I'm a vocational student focusing on network engineering, infrastructure, automation, and AI systems.";

$profileLocation = ($publicProfile['location'] ?? '') !== '' ? $publicProfile['location'] : null;

/* Phase 4.3: education dari tabel `education`, dengan fallback ke placeholder Phase 3.1. */
$educationEntries = getEducationList();

/* Phase 4.8: capabilities dari tabel `skills` lewat getSkillsList(),
 * fallback ke daftar statis Phase 3.1 jika tabel kosong atau DB gagal. */
$dbSkills = getSkillsList();

$capabilities = !empty($dbSkills)
    ? array_map(static function (array $row): string {
        return (string) ($row['name'] ?? '');
    }, $dbSkills)
    : [
        'Network Engineering',
        'MikroTik',
        'Cisco',
        'Linux',
        'Automation',
        'AI Systems',
    ];

$ecosystem = [
    [
        'name'    => 'Portfolio',
        'items'   => ['Identity', 'Projects', 'Achievements'],
        'current' => true,
        'cta'     => '',
    ],
    [
        'name'    => 'Business',
        'items'   => ['Services', 'Solutions', 'Consultation'],
        'current' => false,
        'cta'     => 'Open Business',
    ],
    [
        'name'    => 'Lab',
        'items'   => ['Experiments', 'Research', 'Prototypes'],
        'current' => false,
        'cta'     => 'Open Lab',
    ],
];

/* Ikon SVG sederhana (stroke), markup statis tepercaya. */
$icons = [
    'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
    'github'   => '<circle cx="6" cy="6" r="2.5"/><circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="8" r="2.5"/><path d="M6 8.5v7M18 10.5a6 6 0 0 1-6 6H8.5"/>',
    'linkedin' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 11v5M8 8v.01M12 16v-5M12 13.5c0-1.5 1-2.5 2.5-2.5s2.5 1 2.5 2.5V16"/>',
];

/* Phase 4.6: contacts dari tabel `contacts` lewat getPublicContacts(),
 * fallback ke placeholder Phase 3.1 jika belum ada baris visible. */
$dbContacts = getPublicContacts();

if (!empty($dbContacts)) {
    $contacts = array_map(static function (array $row): array {
        $type  = (string) ($row['type'] ?? '');
        $value = (string) ($row['value'] ?? '');

        $href = match (true) {
            $type === 'email'                            => 'mailto:' . $value,
            in_array($type, ['phone', 'whatsapp'], true)  => 'tel:' . preg_replace('/\s+/', '', $value),
            default                                       => $value,
        };

        return [
            'label'    => (string) ($row['label'] ?? ''),
            'icon'     => (string) ($row['icon'] ?? '') !== '' ? (string) $row['icon'] : 'mail',
            'value'    => $value,
            'href'     => $href,
            'external' => !in_array($type, ['email', 'phone', 'whatsapp', 'address'], true),
            'dummy'    => false,
        ];
    }, $dbContacts);
} else {
    $contacts = [
        ['label' => 'Email',    'icon' => 'mail',     'dummy' => true],
        ['label' => 'GitHub',   'icon' => 'github',   'dummy' => true],
        ['label' => 'LinkedIn', 'icon' => 'linkedin', 'dummy' => true],
    ];
}

require __DIR__ . '/includes/header.php';
?>

    <!-- ===================== HERO ===================== -->
    <section class="hero" id="home" aria-labelledby="hero-title">
      <div class="container hero__inner">

        <div class="hero__content">
          <p class="status reveal">
            <span class="status__dot" aria-hidden="true"></span>
            <span>ONLINE</span>
          </p>

          <h1 class="hero__title reveal delay-1" id="hero-title">
            <span>Mohamad</span>
            <span>Lingga</span>
            <span>Syahputra</span>
          </h1>

          <ul class="signal-list reveal delay-2" role="list" aria-label="Focus areas">
<?php foreach ($focusAreas as $area): ?>
            <li><?= e($area) ?></li>
<?php endforeach; ?>
          </ul>

          <!-- Phase 4.2: dari profile.headline jika sudah diisi, jika tidak fallback Phase 3.1. -->
          <p class="hero__statement reveal delay-2"><?= e($heroStatement) ?></p>

          <div class="hero__actions reveal delay-3">
            <a class="btn btn--primary" href="work.php">View Work</a>
            <a class="btn btn--secondary" href="#about">About Me</a>
          </div>
        </div>

        <aside class="panel ambient reveal delay-2" aria-label="System status">
          <div class="panel__row">
            <p class="meta">System status</p>
            <p class="status">
              <span class="status__dot" aria-hidden="true"></span>
              <span>ONLINE</span>
            </p>
          </div>

          <div class="panel__block">
            <p class="meta">Current focus</p>
            <p class="ambient__focus"><span class="placeholder">[CONTENT REQUIRED]</span></p>
          </div>

          <div class="panel__block">
            <p class="meta" aria-hidden="true">Node graph</p>
            <svg class="node-graph" viewBox="0 0 400 240" role="img" aria-label="Node graph: abstract network topology" focusable="false">
              <!-- Jalur sekunder -->
              <path class="node-graph__edge" d="M36 170 L140 208"/>
              <path class="node-graph__edge" d="M108 96 L140 208"/>
              <path class="node-graph__edge" d="M196 134 L140 208"/>
              <path class="node-graph__edge" d="M196 134 L290 196"/>
              <path class="node-graph__edge" d="M140 208 L290 196"/>
              <path class="node-graph__edge" d="M290 196 L364 120"/>
              <!-- Jalur utama (accent) -->
              <path class="node-graph__edge node-graph__edge--main" d="M36 170 L108 96 L196 134 L284 70 L364 120" stroke-linejoin="round" stroke-linecap="round"/>
              <!-- Node sekunder -->
              <circle class="node-graph__node" cx="140" cy="208" r="5"/>
              <circle class="node-graph__node" cx="290" cy="196" r="5"/>
              <!-- Node jalur utama -->
              <circle class="node-graph__node node-graph__node--main" cx="36" cy="170" r="6"/>
              <circle class="node-graph__node node-graph__node--main" cx="108" cy="96" r="6"/>
              <circle class="node-graph__node node-graph__node--main" cx="284" cy="70" r="6"/>
              <circle class="node-graph__node node-graph__node--main" cx="364" cy="120" r="6"/>
              <!-- Node pusat -->
              <circle class="node-graph__halo" cx="196" cy="134" r="14"/>
              <circle class="node-graph__node node-graph__node--main" cx="196" cy="134" r="6"/>
              <circle class="node-graph__core" cx="196" cy="134" r="2.5"/>
            </svg>
          </div>
        </aside>

      </div>
    </section>

    <!-- ===================== SELECTED WORK ===================== -->
    <section class="section" id="work" aria-labelledby="work-title">
      <div class="container">
       <div class="section-head reveal">
          <h2 id="work-title">Selected Work</h2>
          <p class="section-sub">A selection of infrastructure, networking, and automation projects.</p>
        </div>

        <div class="project-grid reveal">
<?php foreach ($featuredProjects as $project): ?>
<?php $headingTag = 'h3'; include __DIR__ . '/includes/project-card.php'; ?>
<?php endforeach; ?>
        </div>

        <p class="section-cta reveal">
          <a class="link-text" href="work.php">View all work &rarr;</a>
        </p>
      </div>
    </section>

    <!-- ===================== ABOUT ===================== -->
    <section class="section" id="about" aria-labelledby="about-title">
      <div class="container about-grid">
        <div class="photo-placeholder reveal" role="img" aria-label="Profile photo placeholder">
          <span class="placeholder">[CONTENT REQUIRED]</span>
          <span class="meta">Profile photo, 4:5</span>
        </div>

        <div class="about-body reveal delay-1">
          <h2 id="about-title">About</h2>
          <!-- Phase 4.2: dari profile.bio jika sudah diisi, jika tidak fallback Phase 3.1. -->
          <p class="about-body__lead"><?= e($aboutLead) ?></p>

          <dl class="meta-list">
            <div>
              <dt class="meta">Location</dt>
              <dd><?= $profileLocation !== null ? e($profileLocation) : '<span class="placeholder">[CONTENT REQUIRED]</span>' ?></dd>
            </div>
            <div>
              <dt class="meta">Education</dt>
              <dd>
<?php if (empty($educationEntries)): ?>
                <span class="placeholder">[CONTENT REQUIRED]</span>
<?php else: ?>
<?php foreach ($educationEntries as $index => $edu): ?>
<?php
    $degreeField = trim(
        (string) ($edu['degree'] ?? '')
        . (($edu['degree'] ?? '') !== '' && ($edu['field'] ?? '') !== '' ? ' in ' : '')
        . (string) ($edu['field'] ?? '')
    );
    $line = $degreeField !== ''
        ? $degreeField . ' &mdash; ' . e((string) $edu['institution'])
        : e((string) $edu['institution']);
?>
<?= $degreeField !== '' ? e($degreeField) . ' &mdash; ' . e((string) $edu['institution']) : e((string) $edu['institution']) ?><?= $index < count($educationEntries) - 1 ? '<br>' : '' ?>
<?php endforeach; ?>
<?php endif; ?>
              </dd>
            </div>
          </dl>
        </div>
      </div>
    </section>

    <!-- ===================== CAPABILITIES ===================== -->
    <section class="section" id="capabilities" aria-labelledby="capabilities-title">
      <div class="container">
        <div class="section-head reveal">
          <h2 id="capabilities-title">Capabilities</h2>
        </div>

        <!-- Tanpa progress bar dan tanpa persentase. -->
        <ul class="capability-grid reveal" role="list">
<?php foreach ($capabilities as $capability): ?>
          <li class="capability"><?= e($capability) ?></li>
<?php endforeach; ?>
        </ul>
      </div>
    </section>

    <!-- ===================== ECOSYSTEM ===================== -->
    <section class="section section--alt" id="ecosystem" aria-labelledby="ecosystem-title">
      <div class="container">
        <div class="section-head reveal">
          <h2 id="ecosystem-title">Explore the Ecosystem</h2>
        </div>

        <div class="ecosystem-grid reveal">
<?php foreach ($ecosystem as $index => $env): ?>
<?php if ($index > 0): ?>
          <!-- Signal Path: konektor antar panel (horizontal di desktop, vertikal di mobile) -->
          <div class="eco-link" aria-hidden="true">
            <svg viewBox="0 0 64 64" focusable="false">
              <g class="eco-link__h">
                <line class="eco-link__line" x1="0" y1="32" x2="64" y2="32"/>
                <circle class="eco-link__node" cx="32" cy="32" r="4"/>
              </g>
              <g class="eco-link__v">
                <line class="eco-link__line" x1="32" y1="0" x2="32" y2="64"/>
                <circle class="eco-link__node" cx="32" cy="32" r="4"/>
              </g>
            </svg>
          </div>
<?php endif; ?>
          <article class="eco-panel<?= $env['current'] ? ' eco-panel--current' : '' ?>">
            <h3><?= e($env['name']) ?></h3>
            <ul class="eco-panel__list" role="list">
<?php foreach ($env['items'] as $item): ?>
              <li><?= e($item) ?></li>
<?php endforeach; ?>
            </ul>
<?php if ($env['current']): ?>
            <p class="eco-panel__foot eco-panel__foot--current meta">You are here</p>
<?php else: ?>
            <!-- DUMMY: nanti menjadi subdomain <?= e(strtolower($env['name'])) ?>.mohamadlingga.my.id -->
            <p class="eco-panel__foot">
              <a class="link-text" href="#" data-dummy aria-disabled="true"><?= e($env['cta']) ?></a>
            </p>
<?php endif; ?>
          </article>
<?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- ===================== CONTACT ===================== -->
    <section class="section" id="contact" aria-labelledby="contact-title">
      <div class="container contact">
        <div class="section-head reveal">
          <h2 id="contact-title">Let&rsquo;s Build Something Together</h2>
        </div>

        <ul class="contact-grid reveal" role="list">
<?php foreach ($contacts as $contact): ?>
          <li>
<?php if (!empty($contact['dummy'])): ?>
            <!-- DUMMY: tautan final menunggu data owner -->
            <a class="contact-tile" href="#" data-dummy aria-disabled="true">
              <span class="contact-tile__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false"><?= $icons[$contact['icon']] ?></svg>
              </span>
              <span class="contact-tile__body">
                <span class="contact-tile__label"><?= e($contact['label']) ?></span>
                <span class="contact-tile__value placeholder">[CONTENT REQUIRED]</span>
              </span>
            </a>
<?php else: ?>
            <!-- Phase 4.6: dari tabel `contacts` lewat getPublicContacts() -->
            <a class="contact-tile" href="<?= e($contact['href']) ?>"<?= $contact['external'] ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
              <span class="contact-tile__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false"><?= $icons[$contact['icon']] ?? $icons['mail'] ?></svg>
              </span>
              <span class="contact-tile__body">
                <span class="contact-tile__label"><?= e($contact['label']) ?></span>
                <span class="contact-tile__value"><?= e($contact['value']) ?></span>
              </span>
            </a>
<?php endif; ?>
          </li>
<?php endforeach; ?>
        </ul>

        <!-- DUMMY: form kontak dibuat di fase berikutnya -->
        <div class="reveal">
          <a class="btn btn--primary" href="#" data-dummy aria-disabled="true">Send Message</a>
        </div>
      </div>
    </section>

<?php require __DIR__ . '/includes/footer.php'; ?>