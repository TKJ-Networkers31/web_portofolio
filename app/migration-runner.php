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
 *
 * FIX: previously wrapped the whole schema application in an explicit
 * PDO transaction (beginTransaction/commit/rollBack). That works for
 * SQLite, but on MySQL/InnoDB every CREATE TABLE statement triggers an
 * *implicit commit* — so by the time the loop finished, the transaction
 * PHP thought was still open had already been silently closed by the
 * first CREATE TABLE, and the final $pdo->commit() failed with
 * "There is no active transaction" (visible as "Migration failed:
 * There is no active transaction" on admin/setup.php). Every statement
 * here is CREATE TABLE IF NOT EXISTS / INSERT OR IGNORE — already
 * idempotent — so there is nothing an explicit transaction protects
 * that re-running the (safe) statements one at a time doesn't already
 * give us. The transaction wrapper is removed; statements execute
 * directly, and any failure still throws (so migrate.php / setup.php
 * still reports a failure exactly as before) — it just no longer tries
 * to commit/roll back a transaction MySQL already closed on its own.
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

        // No explicit transaction here (see FIX note above): MySQL's
        // CREATE TABLE implicitly commits anyway, so beginTransaction()/
        // commit() around DDL is not meaningful on that driver, and
        // caused a hard failure. Every statement is idempotent
        // (IF NOT EXISTS / INSERT OR IGNORE), so executing them plainly,
        // one at a time, is safe to re-run and still throws immediately
        // — and with the same message as before — if a statement fails.
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

        return $count;
    }
}