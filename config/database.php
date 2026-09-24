<?php

declare(strict_types=1);

/**
 * config/database.php
 *
 * Returns a cached PDO connection. Driver is chosen by DB_DRIVER in .env:
 *   - "mysql" (primary): MySQL/MariaDB named in DB_HOST / DB_NAME / DB_USER.
 *   - "sqlite": optional local/dev parity only, when DB_DRIVER=sqlite.
 * Credentials always come from the environment — never hardcoded.
 *
 * Requires config/config.php (for env()) to be loaded first.
 */

if (!defined('CMS_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

if (!function_exists('db')) {
    function db(): PDO
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $driver = strtolower((string) env('DB_DRIVER', 'mysql'));

        if ($driver === 'mysql') {
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '3306');
            $name = env('DB_NAME', 'portfolio');
            $user = env('DB_USER', '');
            $pass = env('DB_PASS', '');

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            return $pdo;
        }

        // Default: SQLite.
        $configuredPath = env('DB_SQLITE_PATH', '../storage/portfolio.sqlite');
        $absolutePath   = str_starts_with($configuredPath, '/')
            ? $configuredPath
            : __DIR__ . '/' . $configuredPath;

        $dir = dirname($absolutePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $pdo = new PDO('sqlite:' . $absolutePath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }
}
