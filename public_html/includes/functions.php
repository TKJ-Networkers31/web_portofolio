<?php

declare(strict_types=1);

/**
 * includes/functions.php
 *
 * Helper procedural untuk data project (Phase 3.2 + 3.3 pretty URL), dan
 * untuk profile + education (Phase 4.2 / 4.3, read-only untuk sisi
 * publik). Prosedural sesuai instruksi brief ("Jangan OOP").
 * data/projects.php tetap satu-satunya sumber data project — file ini
 * hanya berisi fungsi yang membaca dan mengolahnya.
 */

if (!defined('SITE_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

if (!function_exists('loadProjects')) {
    /** Ambil seluruh project dari data/projects.php (di-cache per request). */
    function loadProjects(): array
    {
        static $projects = null;

        if ($projects === null) {
            $projects = require __DIR__ . '/../data/projects.php';
        }

        return $projects;
    }
}

if (!function_exists('findProjectBySlug')) {
    /** Cari satu project berdasarkan slug. Null jika tidak ditemukan. */
    function findProjectBySlug(string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }

        foreach (loadProjects() as $project) {
            if ($project['slug'] === $slug) {
                return $project;
            }
        }

        return null;
    }
}

if (!function_exists('getFeaturedProjects')) {
    /** Project dengan featured = true. Dipakai di section Selected Work, Home. */
    function getFeaturedProjects(): array
    {
        return array_values(array_filter(
            loadProjects(),
            static function (array $project): bool {
                return !empty($project['featured']);
            }
        ));
    }
}

if (!function_exists('getRelatedProjects')) {
    /**
     * Project lain selain slug saat ini, dibatasi $limit.
     * Sengaja tanpa algoritma relevansi (brief: "tidak perlu algoritma kompleks").
     */
    function getRelatedProjects(string $currentSlug, int $limit = 2): array
    {
        $related = array_values(array_filter(
            loadProjects(),
            static function (array $project) use ($currentSlug): bool {
                return $project['slug'] !== $currentSlug;
            }
        ));

        return array_slice($related, 0, $limit);
    }
}

if (!function_exists('projectUrl')) {
    /**
     * URL kanonik pretty untuk satu project: /project/{slug}
     * Satu-satunya tempat format URL project dibentuk (Phase 3.3), dipakai
     * oleh includes/project-card.php dan project.php (canonical/OG).
     */
    function projectUrl(string $slug): string
    {
        return '/project/' . rawurlencode($slug);
    }
}

if (!function_exists('projectStatusMeta')) {
    /**
     * Mapping status → tone warna, label, dan ikon.
     * Status TIDAK PERNAH ditampilkan hanya lewat warna — lihat pemakaian
     * di includes/project-card.php dan includes/project-header.php
     * (.status-badge selalu menyertakan ikon dan teks label).
     */
    function projectStatusMeta(string $status): array
    {
        $map = [
            'Completed' => [
                'tone'  => 'success',
                'label' => 'Completed',
                'icon'  => '<circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.5 2.5L16 9"/>',
            ],
            'Active' => [
                'tone'  => 'accent',
                'label' => 'Active',
                'icon'  => '<circle cx="12" cy="12" r="2.5"/><circle cx="12" cy="12" r="7.5" stroke-opacity="0.5"/>',
            ],
            'Experimental' => [
                'tone'  => 'warning',
                'label' => 'Experimental',
                'icon'  => '<path d="M9 3h6M10 3v5.3l-4.6 8.1A2 2 0 0 0 7.1 19.5h9.8a2 2 0 0 0 1.7-3.1L14 8.3V3"/>',
            ],
        ];

        return $map[$status] ?? [
            'tone'  => 'muted',
            'label' => $status,
            'icon'  => '<circle cx="12" cy="12" r="9"/>',
        ];
    }
}

if (!function_exists('projectTopologySvg')) {
    /**
     * Diagram topology sederhana untuk section "Architecture & Approach",
     * dipilih berdasarkan slug. Semua inline SVG (bukan PNG), stroke 1.5,
     * radius 4 pada tiap node, jalur utama memakai accent, label mono.
     *
     * Slug yang belum punya diagram spesifik jatuh ke diagram generik,
     * supaya sistem tetap berjalan wajar saat project baru ditambahkan
     * tanpa diagram khusus.
     */
    function projectTopologySvg(string $slug): string
    {
        switch ($slug) {
            case 'high-availability-network':
                return <<<'SVG'
<svg class="topology" viewBox="0 0 480 260" role="img" aria-label="Topology diagram: Internet splitting into a master and backup router path, each leading through a switch to its own VLAN" focusable="false">
  <path class="topology__edge" d="M240 44 V64"/>
  <path class="topology__edge topology__edge--main" d="M240 64 H120 V84"/>
  <path class="topology__edge" d="M240 64 H360 V84"/>

  <path class="topology__edge topology__edge--main" d="M120 116 V150"/>
  <path class="topology__edge topology__edge--main" d="M120 182 V214"/>

  <path class="topology__edge" d="M360 116 V150"/>
  <path class="topology__edge" d="M360 182 V214"/>

  <rect class="topology__node" x="180" y="12" width="120" height="32" rx="4"/>
  <text class="topology__label" x="240" y="32" text-anchor="middle">Internet</text>

  <rect class="topology__node topology__node--main" x="40" y="84" width="160" height="32" rx="4"/>
  <text class="topology__label topology__label--main" x="120" y="104" text-anchor="middle">R1 &#183; Master</text>

  <rect class="topology__node" x="280" y="84" width="160" height="32" rx="4"/>
  <text class="topology__label" x="360" y="104" text-anchor="middle">R2 &#183; Backup</text>

  <rect class="topology__node topology__node--main" x="40" y="150" width="160" height="32" rx="4"/>
  <text class="topology__label topology__label--main" x="120" y="170" text-anchor="middle">SW1</text>

  <rect class="topology__node" x="280" y="150" width="160" height="32" rx="4"/>
  <text class="topology__label" x="360" y="170" text-anchor="middle">SW2</text>

  <rect class="topology__node topology__node--main" x="40" y="214" width="160" height="32" rx="4"/>
  <text class="topology__label topology__label--main" x="120" y="234" text-anchor="middle">VLAN10</text>

  <rect class="topology__node" x="280" y="214" width="160" height="32" rx="4"/>
  <text class="topology__label" x="360" y="234" text-anchor="middle">VLAN20</text>
</svg>
SVG;

            case 'aira-os':
                return <<<'SVG'
<svg class="topology" viewBox="0 0 480 170" role="img" aria-label="Diagram: input flowing into a core engine, out to automation modules, with SQLite as local storage" focusable="false">
  <path class="topology__edge topology__edge--main" d="M120 80 H185"/>
  <path class="topology__edge topology__edge--main" d="M315 80 H360"/>
  <path class="topology__edge" d="M250 96 V120"/>

  <rect class="topology__node" x="10" y="64" width="110" height="32" rx="4"/>
  <text class="topology__label" x="65" y="84" text-anchor="middle">Input</text>

  <rect class="topology__node topology__node--main" x="185" y="64" width="130" height="32" rx="4"/>
  <text class="topology__label topology__label--main" x="250" y="84" text-anchor="middle">Core &#183; Ollama</text>

  <rect class="topology__node" x="360" y="64" width="110" height="32" rx="4"/>
  <text class="topology__label" x="415" y="84" text-anchor="middle">Automation</text>

  <rect class="topology__node" x="185" y="120" width="130" height="32" rx="4"/>
  <text class="topology__label" x="250" y="140" text-anchor="middle">SQLite</text>
</svg>
SVG;

            case 'ftth-laboratory':
                return <<<'SVG'
<svg class="topology" viewBox="0 0 480 140" role="img" aria-label="Diagram: OLT connecting through a passive splitter to multiple ONTs" focusable="false">
  <path class="topology__edge topology__edge--main" d="M110 70 H190"/>
  <path class="topology__edge topology__edge--main" d="M290 70 H330 L370 30"/>
  <path class="topology__edge topology__edge--main" d="M290 70 H330 L370 110"/>

  <rect class="topology__node topology__node--main" x="10" y="54" width="100" height="32" rx="4"/>
  <text class="topology__label topology__label--main" x="60" y="74" text-anchor="middle">OLT</text>

  <rect class="topology__node topology__node--main" x="190" y="54" width="100" height="32" rx="4"/>
  <text class="topology__label topology__label--main" x="240" y="74" text-anchor="middle">Splitter</text>

  <rect class="topology__node topology__node--main" x="370" y="14" width="100" height="32" rx="4"/>
  <text class="topology__label topology__label--main" x="420" y="34" text-anchor="middle">ONT &#183; 1</text>

  <rect class="topology__node topology__node--main" x="370" y="94" width="100" height="32" rx="4"/>
  <text class="topology__label topology__label--main" x="420" y="114" text-anchor="middle">ONT &#183; 2</text>
</svg>
SVG;

            default:
                return <<<'SVG'
<svg class="topology" viewBox="0 0 480 120" role="img" aria-label="Simplified process diagram: input, process, output" focusable="false">
  <path class="topology__edge topology__edge--main" d="M120 60 H190"/>
  <path class="topology__edge topology__edge--main" d="M300 60 H360"/>

  <rect class="topology__node topology__node--main" x="10" y="44" width="110" height="32" rx="4"/>
  <text class="topology__label topology__label--main" x="65" y="64" text-anchor="middle">Input</text>

  <rect class="topology__node topology__node--main" x="190" y="44" width="110" height="32" rx="4"/>
  <text class="topology__label topology__label--main" x="245" y="64" text-anchor="middle">Process</text>

  <rect class="topology__node topology__node--main" x="360" y="44" width="110" height="32" rx="4"/>
  <text class="topology__label topology__label--main" x="415" y="64" text-anchor="middle">Output</text>
</svg>
SVG;
        }
    }
}

if (!function_exists('getDbForPublicRead')) {
    /**
     * Phase 4.2/4.3 — bootstrap koneksi DB untuk sisi publik tanpa
     * memaksa file caller melakukan require manual. Mengembalikan null
     * (bukan melempar) jika config atau tabel tidak tersedia, supaya
     * halaman Phase 3 selalu degrade dengan aman ke fallback statis.
     */
    function getDbForPublicRead(): ?PDO
    {
        static $pdo = false; // false = belum pernah dicoba

        if ($pdo !== false) {
            return $pdo;
        }

        $pdo = null;

        try {
            $configFile = __DIR__ . '/../../config/config.php';
            $dbFile     = __DIR__ . '/../../config/database.php';

            if (!is_file($configFile) || !is_file($dbFile)) {
                return $pdo;
            }

            require_once $configFile;
            require_once $dbFile;

            if (!function_exists('db')) {
                return $pdo;
            }

            $pdo = db();
        } catch (Throwable $e) {
            $pdo = null;
        }

        return $pdo;
    }
}

if (!function_exists('getPublicProfile')) {
    /**
     * Phase 4.2 — baca satu-satunya baris `profile` untuk sisi publik.
     * Kegagalan apa pun (config belum ada, tabel belum ada, DB mati)
     * degrade ke null supaya halaman Phase 3 tetap render persis seperti
     * sebelum Phase 4.2.
     */
    function getPublicProfile(): ?array
    {
        static $profile = false;

        if ($profile !== false) {
            return $profile;
        }

        $profile = null;

        $pdo = getDbForPublicRead();
        if ($pdo === null) {
            return $profile;
        }

        try {
            $stmt = $pdo->query(
                'SELECT full_name, headline, bio, location, photo_path
                 FROM profile WHERE id = 1 LIMIT 1'
            );
            $row = $stmt ? $stmt->fetch() : null;

            $profile = $row ?: null;
        } catch (Throwable $e) {
            $profile = null;
        }

        return $profile;
    }
}

if (!function_exists('getEducationList')) {
    /**
     * Phase 4.3 — baca seluruh baris `education` untuk sisi publik,
     * terurut sesuai sort_order lalu id. Kegagalan apa pun degrade ke
     * array kosong, sehingga index.php tetap bisa fallback ke placeholder
     * Phase 3.1 tanpa error.
     */
    function getEducationList(): array
    {
        static $rows = null;

        if ($rows !== null) {
            return $rows;
        }

        $rows = [];

        $pdo = getDbForPublicRead();
        if ($pdo === null) {
            return $rows;
        }

        try {
            $stmt = $pdo->query(
                'SELECT institution, degree, field, start_date, end_date
                 FROM education
                 ORDER BY sort_order ASC, id ASC'
            );

            $rows = $stmt ? $stmt->fetchAll() : [];
        } catch (Throwable $e) {
            $rows = [];
        }

        return $rows;
    }
    if (!function_exists('getPublicContacts')) {
        /**
         * Phase 4.6 — read all visible rows from `contacts` (Contact and
         * Social admin pages share this one table) for the public site.
         * Degrades to an empty array on any failure so index.php's dummy
         * fallback still renders exactly as before Phase 4.6.
         */
        function getPublicContacts(): array
        {
            static $rows = null;

            if ($rows !== null) {
                return $rows;
            }

            $rows = [];

            $pdo = getDbForPublicRead();
            if ($pdo === null) {
                return $rows;
            }

            try {
                $stmt = $pdo->query(
                    'SELECT label, type, value, icon
                    FROM contacts
                    WHERE is_visible = 1
                    ORDER BY sort_order ASC, id ASC'
                );

                $rows = $stmt ? $stmt->fetchAll() : [];
            } catch (Throwable $e) {
                $rows = [];
            }

            return $rows;
        }
    }
}