<?php

declare(strict_types=1);

/**
 * app/helpers.php
 *
 * Small helpers shared by admin pages only. Mirrors the style of
 * includes/header.php's e() on the public site, but defined separately
 * so the admin area has no dependency on public_html/includes/*.
 */

if (!defined('CMS_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

if (!function_exists('e')) {
    /** Escape a value for safe HTML output. */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('isValidDateString')) {
    /**
     * Phase 4.3 — validate an optional "YYYY-MM-DD" date string.
     * Empty string is treated as valid (field is optional); a non-empty
     * string must be a real calendar date in that exact format.
     */
    function isValidDateString(string $value): bool
    {
        if ($value === '') {
            return true;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }
}

if (!function_exists('adminUrl')) {
    /** Absolute admin path, respecting subdirectory installs. */
    function adminUrl(string $path = ''): string
    {
        $base = defined('CMS_ADMIN_BASE') ? CMS_ADMIN_BASE : '/admin';
        $path = ltrim($path, '/');

        return $path === '' ? $base : $base . '/' . $path;
    }
}

if (!function_exists('formatBytes')) {
    function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '';
        }

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        $unit  = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return round($value, 1) . ' ' . $units[$unit];
    }
}

if (!function_exists('publicMediaUrl')) {
    /** Root-absolute URL for a stored media path, or the original http(s) URL. */
    function publicMediaUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        return '/' . ltrim(str_replace('\\', '/', $path), '/');
    }
}