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
