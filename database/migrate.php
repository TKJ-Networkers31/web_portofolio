<?php

declare(strict_types=1);

/**
 * database/migrate.php
 *
 * Run from the command line:
 *   php database/migrate.php
 *
 * Applies the schema file matching DB_DRIVER (sqlite by default). All
 * statements are idempotent (CREATE TABLE IF NOT EXISTS), so running this
 * more than once is safe.
 *
 * No SSH access on your host? Use public_html/admin/setup.php instead —
 * see docs/CMS-SETUP.md.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../app/migration-runner.php';

$driver = env('DB_DRIVER', 'sqlite');

try {
    $count = runMigrations(db(), $driver, __DIR__);
    echo "Migration complete ({$driver}): {$count} statements executed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
