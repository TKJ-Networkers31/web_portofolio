<?php

declare(strict_types=1);

/**
 * Shared stroke-icon catalog used by the public contact/social tiles and
 * the admin icon picker. Keys match the existing frontend $icons map
 * (mail, github, linkedin) plus the remaining SOCIAL_TYPES / CONTACT_TYPES.
 */

if (!defined('CMS_BOOT') && !defined('SITE_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

if (!function_exists('iconCatalog')) {
    /** @return array<string, array{label: string, svg: string}> */
    function iconCatalog(): array
    {
        return [
            'mail'      => ['label' => 'Email', 'svg' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>'],
            'phone'     => ['label' => 'Phone', 'svg' => '<path d="M7 3h3l1 4-2 1a12 12 0 0 0 7 7l1-2 4 1v3a2 2 0 0 1-2 2A16 16 0 0 1 3 7a2 2 0 0 1 2-2z"/>'],
            'whatsapp'  => ['label' => 'WhatsApp', 'svg' => '<path d="M5 19 6.2 15A7.5 7.5 0 1 1 9 19Z"/><path d="M9.2 9.5c.3 1.6 1.7 3 3.3 3.3"/>'],
            'address'   => ['label' => 'Address', 'svg' => '<path d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.2"/>'],
            'github'    => ['label' => 'GitHub', 'svg' => '<circle cx="6" cy="6" r="2.5"/><circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="8" r="2.5"/><path d="M6 8.5v7M18 10.5a6 6 0 0 1-6 6H8.5"/>'],
            'linkedin'  => ['label' => 'LinkedIn', 'svg' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 11v5M8 8v.01M12 16v-5M12 13.5c0-1.5 1-2.5 2.5-2.5s2.5 1 2.5 2.5V16"/>'],
            'instagram' => ['label' => 'Instagram', 'svg' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="3.5"/><circle cx="17.2" cy="6.8" r="0.9"/>'],
            'twitter'   => ['label' => 'X / Twitter', 'svg' => '<path d="M4 4h4.2l4.3 5.8L17.2 4H20l-6.4 7.6L20 20h-4.2l-4.6-6.2L6.8 20H4l6.7-8z"/>'],
            'facebook'  => ['label' => 'Facebook', 'svg' => '<path d="M14 8h3V4h-3c-2.8 0-5 2.2-5 5v3H6v4h3v8h4v-8h3.2l.8-4H13V9c0-.6.4-1 1-1z"/>'],
            'youtube'   => ['label' => 'YouTube', 'svg' => '<rect x="3" y="6" width="18" height="12" rx="3"/><path d="m10 9 6 3-6 3z"/>'],
            'tiktok'    => ['label' => 'TikTok', 'svg' => '<path d="M14 4v9.2a3.2 3.2 0 1 1-3.2-3.2"/><path d="M14 7.2c1.4 1.4 3.2 2.2 5 2.4"/>'],
            'website'   => ['label' => 'Website', 'svg' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>'],
            'other'     => ['label' => 'Other', 'svg' => '<circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/>'],
        ];
    }
}

if (!function_exists('iconSvg')) {
    function iconSvg(string $key): string
    {
        $catalog = iconCatalog();
        $item    = $catalog[$key] ?? $catalog['other'];

        return $item['svg'];
    }
}

if (!function_exists('iconKeys')) {
    /** @return list<string> */
    function iconKeys(): array
    {
        return array_keys(iconCatalog());
    }
}
