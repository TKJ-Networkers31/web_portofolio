<?php

declare(strict_types=1);

/**
 * app/admin-auth.php
 *
 * Session-based admin authentication + CSRF helpers. Requires
 * config/config.php (session bootstrap, env()) and config/database.php
 * (db()) to already be loaded by the caller.
 */

if (!defined('CMS_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

const ADMIN_SESSION_IDLE_LIMIT    = 1800; // seconds of inactivity before forced logout
const ADMIN_SESSION_REGEN_AFTER   = 300;  // rotate session id every 5 minutes of activity
const ADMIN_LOGIN_MAX_ATTEMPTS    = 5;
const ADMIN_LOGIN_LOCKOUT_SECONDS = 60;

/* ==================== CSRF ==================== */

if (!function_exists('csrfToken')) {
    function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrfField')) {
    function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
    }
}

if (!function_exists('verifyCsrf')) {
    function verifyCsrf(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}

/* ==================== Login throttling ====================
 * Session-scoped rate limiting for Phase 4.1. Good enough to slow down
 * casual brute forcing; IP-based/persistent throttling can be layered on
 * later without changing this API.
 */

if (!function_exists('loginIsLockedOut')) {
    function loginIsLockedOut(): bool
    {
        $attempts = $_SESSION['login_attempts'] ?? 0;
        $lockedUntil = $_SESSION['login_locked_until'] ?? 0;

        return $attempts >= ADMIN_LOGIN_MAX_ATTEMPTS && time() < $lockedUntil;
    }
}

if (!function_exists('registerFailedLogin')) {
    function registerFailedLogin(): void
    {
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;

        if ($_SESSION['login_attempts'] >= ADMIN_LOGIN_MAX_ATTEMPTS) {
            $_SESSION['login_locked_until'] = time() + ADMIN_LOGIN_LOCKOUT_SECONDS;
        }
    }
}

if (!function_exists('clearLoginAttempts')) {
    function clearLoginAttempts(): void
    {
        unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
    }
}

/* ==================== Auth ==================== */

if (!function_exists('attemptLogin')) {
    function attemptLogin(string $username, string $password): bool
    {
        if ($username === '' || $password === '') {
            return false;
        }

        $pdo  = db();
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, (string) $admin['password_hash'])) {
            return false;
        }

        // New session id on every privilege change — prevents session fixation.
        session_regenerate_id(true);

        $_SESSION['admin_id']       = (int) $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['last_activity']  = time();
        $_SESSION['last_regen']     = time();

        $update = $pdo->prepare('UPDATE admins SET last_login_at = :now WHERE id = :id');
        $update->execute(['now' => date('Y-m-d H:i:s'), 'id' => $admin['id']]);

        clearLoginAttempts();

        return true;
    }
}

if (!function_exists('isAdminLoggedIn')) {
    function isAdminLoggedIn(): bool
    {
        if (empty($_SESSION['admin_id'])) {
            return false;
        }

        $lastActivity = $_SESSION['last_activity'] ?? 0;
        if (time() - (int) $lastActivity > ADMIN_SESSION_IDLE_LIMIT) {
            logoutAdmin();
            return false;
        }

        $_SESSION['last_activity'] = time();

        $lastRegen = (int) ($_SESSION['last_regen'] ?? 0);
        if (time() - $lastRegen > ADMIN_SESSION_REGEN_AFTER) {
            session_regenerate_id(true);
            $_SESSION['last_regen'] = time();
        }

        return true;
    }
}

if (!function_exists('requireAdmin')) {
    function requireAdmin(): void
    {
        if (!isAdminLoggedIn()) {
            $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/admin/index.php');
            header('Location: /admin/login.php?redirect=' . $redirect);
            exit;
        }
    }
}

if (!function_exists('currentAdmin')) {
    function currentAdmin(): ?array
    {
        if (empty($_SESSION['admin_id'])) {
            return null;
        }

        return [
            'id'       => (int) $_SESSION['admin_id'],
            'username' => (string) ($_SESSION['admin_username'] ?? ''),
        ];
    }
}

if (!function_exists('logoutAdmin')) {
    function logoutAdmin(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}
