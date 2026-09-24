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
 *
 * PHASE 3.2: section Selected Work sekarang membaca data/projects.php
 * (lewat getFeaturedProjects()) dan me-render tiap kartu lewat
 * includes/project-card.php.
 *
 * PHASE 4.2: hero statement, About lead, dan Location dari `profile`.
 * PHASE 4.3: Education dari `education`.
 * PHASE 4.6: Contact tiles dari `contacts` (email/phone/whatsapp/address
 *            DAN social — keduanya disimpan di tabel yang sama, lihat
 *            admin/contact.php vs admin/social.php).
 *
 * FINAL QA (item #3 — Social):
 *   Icon library diperluas ($icons) supaya cocok dengan seluruh
 *   SOCIAL_TYPES di admin/social.php (sebelumnya hanya mail/github/
 *   linkedin — tipe lain jatuh ke fallback mail, salah secara visual).
 *   Untuk contact yang bertipe sosial ($contact['external'] === true),
 *   nilai yang ditampilkan bukan lagi URL mentah — memenuhi requirement
 *   "Jangan tampilkan URL panjang sebagai teks utama". Klik tile tetap
 *   membuka URL asli (href tidak berubah).
 *
 * FINAL QA (item #4 — Send Message):
 *   Audit menemukan tombol "Send Message" sebelumnya adalah
 *   `<a href="#" data-dummy aria-disabled="true">` — TIDAK ADA form,
 *   TIDAK ADA handler. Diganti dengan <form method="post"> nyata yang
 *   diproses di bagian atas file ini lewat submitContactMessage()
 *   (includes/functions.php, additive ke tabel `messages` baru). Jika
 *   penyimpanan gagal (mis. migrasi belum dijalankan / DB tidak
 *   tersedia), pengguna diberi tahu APA ADANYA — tidak ada pesan sukses
 *   palsu.
 */

define('SITE_BOOT', true);
require __DIR__ . '/includes/functions.php';

/* ---------- Contact form handling (item #4) ----------
 * PRG (Post/Redirect/Get) sederhana: form submit ke index.php#contact,
 * lalu redirect ke index.php?sent=1#contact atau ?sent=0#contact supaya
 * refresh tidak mengirim ulang pesan yang sama.
 */
$contactFormOld = ['name' => '', 'email' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    // Honeypot: field tersembunyi via CSS, bot pengisi form otomatis
    // biasanya tetap mengisinya. Manusia tidak pernah melihatnya.
    $honeypot = trim((string) ($_POST['website'] ?? ''));

    $name    = trim((string) ($_POST['name'] ?? ''));
    $email   = trim((string) ($_POST['email'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    $contactFormOld = ['name' => $name, 'email' => $email, 'message' => $message];

    if ($honeypot !== '') {
        // Diam-diam anggap "berhasil" untuk bot, tanpa benar-benar
        // menyimpan apa pun — bukan sukses palsu untuk pengguna asli,
        // hanya tidak membocorkan bahwa ini honeypot.
        header('Location: /index.php?sent=1#contact');
        exit;
    }

    $ok = submitContactMessage($name, $email, $message);

    header('Location: /index.php?' . ($ok ? 'sent=1' : 'sent=0') . '#contact');
    exit;
}

$contactSent = isset($_GET['sent']) ? ($_GET['sent'] === '1') : null; // null = belum submit

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

/*
 * FINAL QA item #3: ikon SVG sederhana (stroke), markup statis
 * tepercaya. Diperluas supaya cocok dengan seluruh SOCIAL_TYPES di
 * admin/social.php (github, linkedin, instagram, twitter, facebook,
 * youtube, tiktok, website, other) + CONTACT_TYPES (mail dipakai untuk
 * email). Kunci array ini adalah "library ikon yang benar-benar dipakai
 * project" yang dipilih dari admin/social.php lewat <select> (bukan
 * ketik bebas lagi — lihat admin/social.php).
 */
$icons = [
    'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
    'github'    => '<circle cx="6" cy="6" r="2.5"/><circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="8" r="2.5"/><path d="M6 8.5v7M18 10.5a6 6 0 0 1-6 6H8.5"/>',
    'linkedin'  => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 11v5M8 8v.01M12 16v-5M12 13.5c0-1.5 1-2.5 2.5-2.5s2.5 1 2.5 2.5V16"/>',
    'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17" cy="7" r="1"/>',
    'twitter'   => '<path d="M20 6.5c-.7.3-1.4.5-2.2.6a3.7 3.7 0 0 0 1.6-2 7.5 7.5 0 0 1-2.4 1 3.7 3.7 0 0 0-6.4 3.4A10.5 10.5 0 0 1 3.2 5.4a3.7 3.7 0 0 0 1.2 5 3.6 3.6 0 0 1-1.7-.5v.1a3.7 3.7 0 0 0 3 3.6 3.7 3.7 0 0 1-1.7.1 3.7 3.7 0 0 0 3.5 2.6A7.5 7.5 0 0 1 2 17.8a10.6 10.6 0 0 0 5.7 1.7c6.9 0 10.6-5.7 10.6-10.6v-.5A7.6 7.6 0 0 0 20 6.5Z"/>',
    'facebook'  => '<path d="M14 21v-7h2.5l.5-3H14V9c0-.9.3-1.5 1.7-1.5H17V4.9c-.3 0-1.3-.1-2.4-.1-2.4 0-4.1 1.5-4.1 4.2V11H8v3h2.5v7"/>',
    'youtube'   => '<rect x="3" y="6" width="18" height="12" rx="3"/><path d="m10 9.5 5 2.5-5 2.5Z"/>',
    'tiktok'    => '<path d="M14 3v10.5a3.5 3.5 0 1 1-3-3.46M14 3a5 5 0 0 0 5 5"/>',
    'website'   => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18Z"/>',
    'other'     => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.8.4-1 .7-1 1.7M12 17v.01"/>',
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

        // FINAL QA item #3: is_social menandai apakah entri ini social
        // link (bukan email/phone/whatsapp/address) — dipakai untuk
        // memilih teks yang ditampilkan (bukan URL mentah, lihat markup
        // di bawah) sekaligus untuk target="_blank".
        $isSocial = !in_array($type, ['email', 'phone', 'whatsapp', 'address'], true);

        return [
            'label'    => (string) ($row['label'] ?? ''),
            'icon'     => (string) ($row['icon'] ?? '') !== '' ? (string) $row['icon'] : 'mail',
            'value'    => $value,
            'href'     => $href,
            'external' => $isSocial,
            'is_social' => $isSocial,
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
            <a class="btn btn--primary" href="/work.php">View Work</a>
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
          <a class="link-text" href="/work.php">View all work &rarr;</a>
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
            <!--
              Phase 4.6 / FINAL QA item #3: dari tabel `contacts` lewat
              getPublicContacts(). Untuk link social ($contact['is_social']),
              teks utama BUKAN URL mentah (requirement #3) — cukup label +
              ikon; klik tile tetap membuka href aslinya.
            -->
            <a class="contact-tile" href="<?= e($contact['href']) ?>"<?= $contact['external'] ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
              <span class="contact-tile__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false"><?= $icons[$contact['icon']] ?? $icons['other'] ?></svg>
              </span>
              <span class="contact-tile__body">
                <span class="contact-tile__label"><?= e($contact['label']) ?></span>
                <span class="contact-tile__value"><?= $contact['is_social'] ? 'Visit profile &rarr;' : e($contact['value']) ?></span>
              </span>
            </a>
<?php endif; ?>
          </li>
<?php endforeach; ?>
        </ul>

        <!--
          FINAL QA item #4: form nyata (sebelumnya dummy <a>, lihat
          catatan di kepala file). PRG lewat ?sent=1 / ?sent=0.
        -->
<?php if ($contactSent === true): ?>
        <p class="contact-form__notice contact-form__notice--success" role="status">
          Thanks — your message has been sent. I&rsquo;ll get back to you soon.
        </p>
<?php elseif ($contactSent === false): ?>
        <p class="contact-form__notice contact-form__notice--error" role="alert">
          Sorry, your message couldn&rsquo;t be sent right now. Please try again in a moment, or email me directly.
        </p>
<?php endif; ?>

        <form class="contact-form reveal" method="post" action="/index.php#contact" novalidate>
          <input type="hidden" name="contact_submit" value="1">

          <!-- Honeypot anti-bot: tersembunyi dari manusia (CSS), terisi oleh bot. -->
          <div class="contact-form__honeypot" aria-hidden="true">
            <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
          </div>

          <label class="contact-form__field">
            <span class="meta">Name</span>
            <input type="text" name="name" required maxlength="191" value="<?= e($contactFormOld['name']) ?>">
          </label>

          <label class="contact-form__field">
            <span class="meta">Email</span>
            <input type="email" name="email" required maxlength="191" value="<?= e($contactFormOld['email']) ?>">
          </label>

          <label class="contact-form__field">
            <span class="meta">Message</span>
            <textarea name="message" required rows="4" maxlength="5000"><?= e($contactFormOld['message']) ?></textarea>
          </label>

          <button class="btn btn--primary" type="submit">Send Message</button>
        </form>
      </div>
    </section>

<?php require __DIR__ . '/includes/footer.php'; ?>
