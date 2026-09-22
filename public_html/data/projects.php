<?php

declare(strict_types=1);

/**
 * data/projects.php
 *
 * Satu-satunya sumber data project untuk seluruh situs (Home, Work,
 * Project detail). Semua isi di bawah adalah DUMMY PROJECT sesuai brief
 * Phase 3.2 — narasi problem/approach/result/lessons ditulis konkret
 * (bukan lorem ipsum) supaya template dapat dinilai dengan konten yang
 * realistis, tetapi tetap contoh/dummy, bukan achievement asli owner.
 *
 * Menambah project baru = menambah satu array di bawah. Tidak ada
 * project yang di-hardcode di HTML manapun.
 *
 * "result_highlight" adalah field tambahan di luar format yang diberikan
 * brief, dipakai untuk callout sukses di section Result. Opsional — jika
 * tidak diisi, callout tidak dirender (lihat project.php).
 */

if (!defined('SITE_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

return [
    [
        'id'       => 1,
        'slug'     => 'high-availability-network',
        'title'    => 'High Availability Network',
        'category' => 'Networking',
        'status'   => 'Completed',
        'year'     => '2026',
        'featured' => true,

        'summary' => 'Designing a redundant gateway architecture for school infrastructure.',

        'technologies' => ['MikroTik', 'VRRP', 'VLAN', 'OSPF'],

        'problem'  => 'The school network relied on a single router as its only path to the internet. Any hardware failure, firmware update, or scheduled maintenance meant a full outage for every connected classroom and lab.',
        'approach' => 'Two MikroTik routers were configured as a VRRP pair sharing one virtual gateway IP. Two managed switches carry VLAN10 and VLAN20, so client traffic can fail over between routers without any change on the end-user side.',
        'result'   => 'The virtual IP failed over between routers in under three seconds during simulated failure, with no manual intervention and no change required on connected devices.',
        'result_highlight' => 'Redundant gateway successfully simulated',
        'lessons'  => 'VRRP priority and preemption settings matter more than expected — a small misconfiguration can cause both routers to believe they are master at the same time. Testing failover under real traffic, not just at idle, surfaced issues a quiet lab test missed.',
    ],
    [
        'id'       => 2,
        'slug'     => 'aira-os',
        'title'    => 'AIRA OS',
        'category' => 'AI Systems',
        'status'   => 'Active',
        'year'     => '2026',
        'featured' => true,

        'summary' => 'Personal modular AI assistant for networking and automation.',

        'technologies' => ['PHP', 'Python', 'Ollama', 'SQLite'],

        'problem'  => 'Running everyday networking and automation checks meant switching between several separate tools and manually piecing the results together. There was no single assistant that understood the local network context.',
        'approach' => 'AIRA OS pairs a local Ollama model with small PHP and Python modules. Each module handles one task — device lookup, log summaries, automation triggers — and stores state in SQLite so the assistant keeps context between sessions.',
        'result'   => 'The assistant now handles routine lookups and summaries that used to take several manual steps, running entirely on local hardware without sending data to an external API.',
        'result_highlight' => 'Modular assistant running fully on local infrastructure',
        'lessons'  => 'Keeping each module small and single-purpose made the system far easier to debug than one large script would have been. Running the model locally also meant paying close attention to hardware limits from the very start.',
    ],
    [
        'id'       => 3,
        'slug'     => 'ftth-laboratory',
        'title'    => 'FTTH Laboratory',
        'category' => 'Infrastructure',
        'status'   => 'Experimental',
        'year'     => '2026',
        'featured' => true,

        'summary' => 'Learning and documenting FTTH end-to-end deployment.',

        'technologies' => ['GPON', 'OLT', 'Fiber', 'ONT'],

        'problem'  => 'Fiber-to-the-home concepts are easy to read about but harder to understand without hands-on practice with real GPON hardware end to end.',
        'approach' => 'An OLT, a passive splitter, and several ONTs were set up on a lab bench to trace the full signal path — from provisioning on the OLT to activation on each ONT — and to document every step along the way.',
        'result'   => 'All ONTs were successfully provisioned and activated, with signal loss at each splitter stage measured and recorded for future reference.',
        'result_highlight' => 'End-to-end GPON path provisioned and documented',
        'lessons'  => 'Fiber splice quality has an outsized effect on signal loss — a slightly imperfect splice was the source of most of the troubleshooting time. Documenting each step while working, not after the fact, made the final write-up far more accurate.',
    ],
];