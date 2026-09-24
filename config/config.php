<?php

declare(strict_types=1);

/**
 * config/config.php
 *
 * Loads .env into the environment and starts the ADMIN session securely.
 * Lives outside public_html so it can never be requested directly over
 * HTTP even under a hosting misconfiguration.
 *
 * Only public_html/admin/*.php requires this file. The public portfolio
 * (index.php, work.php, project.php, includes/*) never touches it, so
 * Phase 3 behaviour and its own request lifecycle are unaffected.
 */

if (!defined('CMS_BOOT')) {
    define('CMS_BOOT', true);
}

/* ---------- .env loader (no external dependency) ---------- */

if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            $key   = trim($parts[0]);
            $value = trim($parts[1] ?? '');
            $value = trim($value, "\"'");

            if ($key === '') {
                continue;
            }

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
        }
    }
}

if (!function_exists('env')) {
    /** Read a config value from the real environment first, then .env, then $default. */
    function env(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? null;
        }

        return ($value !== null && $value !== '') ? $value : $default;
    }
}

loadEnv(__DIR__ . '/../.env');

if (!defined('APP_ENV')) {
    define('APP_ENV', env('APP_ENV', 'production'));
}
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', env('APP_DEBUG', 'false') === 'true');
}

if (!defined('CMS_ADMIN_BASE')) {
    $adminScript = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'));
    if (preg_match('#^(.*?)/admin(?:/|$)#', $adminScript, $adminPathMatch)) {
        define('CMS_ADMIN_BASE', $adminPathMatch[1] . '/admin');
    } else {
        define('CMS_ADMIN_BASE', '/admin');
    }
}

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

/* ---------- Secure admin session ----------
 *
 * Public pages (SITE_BOOT) load this file via getDbForPublicRead() so they
 * can share env()/db(). They must NOT start the admin session: a fresh
 * session id written with Path=/admin would overwrite the logged-in cookie
 * and force a re-login when navigating between admin pages after viewing
 * the public site — or even after any public bootstrap in the same browser.
 */

if (!defined('SITE_BOOT') && session_status() === PHP_SESSION_NONE) {
    $sessionName     = env('SESSION_NAME', 'portfolio_admin_sess');
    $sessionLifetime = (int) env('SESSION_LIFETIME', '1800');
    $isHttps         = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) === '443')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) $sessionLifetime);

    session_name($sessionName);

    session_set_cookie_params([
        'lifetime' => 0,        // browser-session cookie; idle timeout is enforced in app/admin-auth.php
        'path'     => CMS_ADMIN_BASE,
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}
