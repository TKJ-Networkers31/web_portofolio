<?php

declare(strict_types=1);

/**
 * app/migration-runner.php
 *
 * Shared logic for applying the schema, used by both:
 *   - database/migrate.php (CLI, preferred when SSH is available)
 *   - public_html/admin/setup.php (browser-based, for hosts without SSH)
 *
 * Keeping this in one place means both entry points always apply the
 * schema the same way.
 */

if (!defined('CMS_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

if (!function_exists('runMigrations')) {
    /** @return int number of SQL statements executed */
    function runMigrations(PDO $pdo, string $driver, string $schemaDir): int
    {
        $schemaFile = $driver === 'mysql'
            ? $schemaDir . '/schema-mysql.sql'
            : $schemaDir . '/schema-sqlite.sql';

        if (!is_file($schemaFile)) {
            throw new RuntimeException("Schema file not found for driver [{$driver}]: {$schemaFile}");
        }

        $sql = file_get_contents($schemaFile);
        if ($sql === false) {
            throw new RuntimeException("Could not read schema file: {$schemaFile}");
        }

        // Strip full-line SQL comments (lines starting with "--") BEFORE
        // splitting on semicolons. This matters because a comment can
        // contain an ordinary English semicolon (e.g. "this phase; the
        // rest are...") which must never be mistaken for a statement
        // boundary.
        $codeLines = [];
        foreach (preg_split('/\r\n|\r|\n/', $sql) as $line) {
            if (str_starts_with(ltrim($line), '--')) {
                continue;
            }
            $codeLines[] = $line;
        }
        $sql = implode("\n", $codeLines);

        $statements = array_filter(array_map('trim', explode(';', $sql)));

        $pdo->beginTransaction();

        try {
            $count = 0;
            foreach ($statements as $statement) {
                if ($statement === '') {
                    continue;
                }
                $pdo->exec($statement);
                $count++;
            }

            $recordSql = $driver === 'mysql'
                ? 'INSERT IGNORE INTO migrations (migration) VALUES (:name)'
                : 'INSERT OR IGNORE INTO migrations (migration) VALUES (:name)';

            $pdo->prepare($recordSql)->execute([
                'name' => basename($schemaFile) . '@' . date('Y-m-d'),
            ]);

            $pdo->commit();

            return $count;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
