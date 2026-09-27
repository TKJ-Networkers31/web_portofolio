<?php

declare(strict_types=1);

/**
 * Upload / library helpers for the existing `media` table.
 * Files live under public_html/assets/media/ so stored `path` values stay
 * the same convention already used by project_media and documents
 * ("assets/media/filename.ext").
 *
 * PHASE 5.3 additions (Universal Media Library):
 *   - mediaTypeCategory() / mediaTypeIconSvg() — the same derived
 *     image/video/document/other category used everywhere a thumbnail
 *     or filter needs to know "what kind of file is this", without a
 *     schema change (media.type does not exist, only mime_type).
 *   - mediaSearchRows() — the search/filter listing query, pulled out of
 *     admin/media.php so admin/profile.php's photo picker and
 *     admin/project-media.php's "Add Existing Media" picker can reuse it
 *     instead of duplicating the WHERE-building logic three times.
 *   - mediaCategoryToProjectMediaType() — maps a derived category onto
 *     project_media's existing `type` column values (image/video/document)
 *     for the new "Upload New Media" project-media flow.
 * storeUploadedMedia(), mediaEnsureStorage(), export/import — unchanged.
 */

if (!defined('CMS_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

if (!function_exists('mediaAllowedMimeMap')) {
    /** @return array<string, string> mime => extension */
    function mediaAllowedMimeMap(): array
    {
        return [
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/gif'       => 'gif',
            'image/webp'      => 'webp',
            'application/pdf' => 'pdf',
            'video/mp4'       => 'mp4',
            'text/plain'      => 'txt',
        ];
    }
}

if (!function_exists('mediaMaxBytes')) {
    function mediaMaxBytes(): int
    {
        return 8 * 1024 * 1024;
    }
}

if (!function_exists('mediaStorageDir')) {
    function mediaStorageDir(): string
    {
        return dirname(__DIR__) . '/public_html/assets/media';
    }
}

if (!function_exists('mediaIsSafePath')) {
    function mediaIsSafePath(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '..')) {
            return false;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $path) === 1) {
            return filter_var($path, FILTER_VALIDATE_URL) !== false;
        }

        return true;
    }
}

if (!function_exists('mediaAbsolutePath')) {
    function mediaAbsolutePath(string $path): ?string
    {
        if ($path === '' || preg_match('#^https?://#i', $path) === 1) {
            return null;
        }

        $relative = ltrim(str_replace('\\', '/', $path), '/');
        $root     = dirname(__DIR__) . '/public_html/';
        $absolute = $root . $relative;
        $realRoot = realpath($root);
        $realFile = realpath($absolute);

        if ($realRoot === false) {
            return $absolute;
        }

        if ($realFile !== false && str_starts_with($realFile, $realRoot)) {
            return $realFile;
        }

        $realDir = realpath(dirname($absolute));
        if ($realDir !== false && str_starts_with($realDir, $realRoot)) {
            return $absolute;
        }

        return null;
    }
}

if (!function_exists('mediaFileExists')) {
    function mediaFileExists(string $path): bool
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return true;
        }

        $absolute = mediaAbsolutePath($path);

        return $absolute !== null && is_file($absolute);
    }
}

if (!function_exists('mediaEnsureStorage')) {
    /**
     * FIX: mkdir() used to be called with mode 0750 (no access for
     * "other"). On this host that combined with the platform's umask
     * left the directory (and/or its .htaccess) without the execute/
     * read bits Apache needs just to traverse into the folder and parse
     * .htaccess — visible in the error log as:
     *   "Permission denied ... assets/media/.htaccess ... ensure it is
     *    readable and that '.../assets/media/' is executable"
     * which made every request for an uploaded file (including the
     * Profile Photo preview) fail with a 500, even though the file
     * itself uploaded fine. chmod() is now called explicitly and
     * unconditionally (every time this runs, not just on first
     * creation) so a previously-broken directory self-heals the next
     * time any upload happens, regardless of the host's umask.
     */
    function mediaEnsureStorage(): bool
    {
        $dir = mediaStorageDir();
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                return false;
            }
        }

        // Force 0755 regardless of umask — this directory must stay
        // world-readable/traversable since it serves public assets.
        @chmod($dir, 0755);

        $deny = $dir . '/.htaccess';
        $denyContent = "<FilesMatch \"\\.(php|phtml|phar|cgi|pl)$\">\n  Require all denied\n</FilesMatch>\n";
        if (!is_file($deny)) {
            file_put_contents($deny, $denyContent);
        }
        // Same umask issue can leave .htaccess itself unreadable by
        // Apache even though the directory is fine — force 0644.
        @chmod($deny, 0644);

        return is_dir($dir) && is_writable($dir);
    }
}

if (!function_exists('mediaDetectMime')) {
    function mediaDetectMime(string $tmpPath): ?string
    {
        if (!is_file($tmpPath)) {
            return null;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmpPath);

        return is_string($mime) ? strtolower($mime) : null;
    }
}

if (!function_exists('mediaTypeCategory')) {
    /**
     * Derived UI category from a stored mime_type — the same rule the
     * original admin/media.php filter used, now shared so the Library
     * grid, the Profile Photo picker, and the Project Media picker all
     * agree on what counts as "image" / "video" / "document" / "other".
     * No schema change: `media` still has no discrete type column.
     */
    function mediaTypeCategory(?string $mime): string
    {
        $mime = strtolower((string) $mime);

        if ($mime === '') {
            return 'other';
        }
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mime, 'application/') || str_starts_with($mime, 'text/')) {
            return 'document';
        }

        return 'other';
    }
}

if (!function_exists('mediaCategoryToProjectMediaType')) {
    /**
     * Maps the derived media category onto project_media.type, whose
     * allowed values (see admin/project-media.php MEDIA_ALLOWED_TYPES)
     * are only image/video/document. Used only when auto-creating a
     * project_media row from a freshly uploaded or picked media asset —
     * project_media's schema/values are unchanged.
     */
    function mediaCategoryToProjectMediaType(string $category): string
    {
        return match ($category) {
            'image', 'video' => $category,
            default           => 'document',
        };
    }
}

if (!function_exists('mediaTypeIconSvg')) {
    /**
     * Inner <svg> markup (no wrapping <svg> tag) for a non-image asset's
     * placeholder thumbnail in the Library grid / pickers, keyed by the
     * same derived category as mediaTypeCategory(). Matches the stroke
     * icon style already used across the site (app/icons.php).
     */
    function mediaTypeIconSvg(string $category): string
    {
        return match ($category) {
            'video'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3Z"/>',
            'document' => '<path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M14 3v5h5"/>',
            default    => '<circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/>',
        };
    }
}

if (!function_exists('mediaSearchRows')) {
    /**
     * Shared search/filter listing for the `media` table. $filter is a
     * derived category (image/video/document/other) — same rule as
     * mediaTypeCategory(). Reused by:
     *   - admin/media.php (the Library grid itself)
     *   - admin/profile.php (the "Choose Existing" photo picker, filter=image)
     *   - admin/project-media.php (the "Add Existing Media" picker)
     * Previously this WHERE-building logic lived only inside
     * admin/media.php as buildMediaListWhere()/loadMediaRows(); moved
     * here so the three pickers stay in sync instead of drifting.
     */
    function mediaSearchRows(PDO $pdo, string $q = '', string $filter = ''): array
    {
        $conditions = [];
        $params     = [];

        if ($q !== '') {
            $conditions[] = '(filename LIKE :q OR alt_text LIKE :q)';
            $params['q']  = '%' . $q . '%';
        }

        switch ($filter) {
            case 'image':
                $conditions[] = "mime_type LIKE 'image/%'";
                break;
            case 'video':
                $conditions[] = "mime_type LIKE 'video/%'";
                break;
            case 'document':
                $conditions[] = "(mime_type LIKE 'application/%' OR mime_type LIKE 'text/%')";
                break;
            case 'other':
                $conditions[] = "(mime_type IS NULL OR mime_type = '' OR (
                    mime_type NOT LIKE 'image/%'
                    AND mime_type NOT LIKE 'video/%'
                    AND mime_type NOT LIKE 'application/%'
                    AND mime_type NOT LIKE 'text/%'
                ))";
                break;
        }

        $where = $conditions !== [] ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $stmt = $pdo->prepare(
            "SELECT id, filename, path, mime_type, size, alt_text, uploaded_at
             FROM media
             {$where}
             ORDER BY uploaded_at DESC, id DESC"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}

if (!function_exists('mediaInsertRow')) {
    function mediaInsertRow(PDO $pdo, string $filename, string $path, ?string $mime, ?int $size, ?string $alt): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO media (filename, path, mime_type, size, alt_text)
             VALUES (:filename, :path, :mime_type, :size, :alt_text)'
        );
        $stmt->execute([
            'filename'  => $filename,
            'path'      => $path,
            'mime_type' => $mime,
            'size'      => $size,
            'alt_text'  => $alt !== null && $alt !== '' ? $alt : null,
        ]);

        return (int) $pdo->lastInsertId();
    }
}

if (!function_exists('mediaFindByPath')) {
    function mediaFindByPath(PDO $pdo, string $path): ?array
    {
        $stmt = $pdo->prepare('SELECT id, filename, path, mime_type, size, alt_text FROM media WHERE path = :path LIMIT 1');
        $stmt->execute(['path' => $path]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}

if (!function_exists('mediaFindById')) {
    function mediaFindById(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare('SELECT id, filename, path, mime_type, size, alt_text FROM media WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}

if (!function_exists('storeUploadedMedia')) {
    /**
     * @param array<string, mixed> $file One $_FILES slot
     * @return array{ok: bool, error?: string, id?: int, path?: string, filename?: string, mime_type?: string, size?: int}
     */
    function storeUploadedMedia(PDO $pdo, array $file, string $altText = ''): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'No file was selected.'];
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'error' => 'The file is larger than the allowed size.'];
        }
        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'The upload failed. Please try again.'];
        }

        $tmp  = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'The upload is invalid.'];
        }

        if ($size <= 0 || $size > mediaMaxBytes()) {
            return ['ok' => false, 'error' => 'File must be between 1 byte and ' . formatBytes(mediaMaxBytes()) . '.'];
        }

        $mime = mediaDetectMime($tmp);
        $map  = mediaAllowedMimeMap();
        if ($mime === null || !isset($map[$mime])) {
            return ['ok' => false, 'error' => 'That file type is not allowed. Use JPEG, PNG, GIF, WebP, PDF, MP4, or TXT.'];
        }

        if (!mediaEnsureStorage()) {
            return ['ok' => false, 'error' => 'Media storage is not writable.'];
        }

        $original = basename((string) ($file['name'] ?? 'upload'));
        $original = preg_replace('/[^A-Za-z0-9._-]+/', '-', $original) ?: 'upload';
        $generated = bin2hex(random_bytes(16)) . '.' . $map[$mime];
        $relative  = 'assets/media/' . $generated;
        $destination = mediaStorageDir() . '/' . $generated;

        if (!move_uploaded_file($tmp, $destination)) {
            return ['ok' => false, 'error' => 'The file could not be stored.'];
        }

        $id = mediaInsertRow($pdo, $original, $relative, $mime, $size, $altText);

        return [
            'ok'        => true,
            'id'        => $id,
            'path'      => $relative,
            'filename'  => $original,
            'mime_type' => $mime,
            'size'      => $size,
        ];
    }
}

if (!function_exists('exportMediaManifest')) {
    /** @return array<string, mixed> */
    function exportMediaManifest(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT filename, path, mime_type, size, alt_text FROM media ORDER BY id ASC'
        );
        $rows = $stmt ? $stmt->fetchAll() : [];

        $items = [];
        foreach ($rows as $row) {
            $path = (string) $row['path'];
            $items[] = [
                'filename'  => (string) $row['filename'],
                'path'      => $path,
                'mime_type' => $row['mime_type'],
                'size'      => $row['size'] !== null ? (int) $row['size'] : null,
                'alt_text'  => $row['alt_text'],
                'file_present' => mediaFileExists($path),
            ];
        }

        return [
            'format'      => 'portfolio-media-v1',
            'exported_at' => date('c'),
            'items'       => $items,
        ];
    }
}

if (!function_exists('importMediaManifest')) {
    /**
     * @return array{imported: int, skipped: int, missing: list<string>, errors: list<string>}
     */
    function importMediaManifest(PDO $pdo, string $json): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'missing' => [], 'errors' => []];
        $data   = json_decode($json, true);

        if (!is_array($data) || ($data['format'] ?? '') !== 'portfolio-media-v1' || !isset($data['items']) || !is_array($data['items'])) {
            $result['errors'][] = 'Import file must be a portfolio-media-v1 JSON export.';

            return $result;
        }

        foreach ($data['items'] as $index => $item) {
            if (!is_array($item)) {
                $result['errors'][] = 'Item #' . ($index + 1) . ' is invalid.';
                continue;
            }

            $filename = trim((string) ($item['filename'] ?? ''));
            $path     = trim((string) ($item['path'] ?? ''));
            $mime     = trim((string) ($item['mime_type'] ?? ''));
            $alt      = trim((string) ($item['alt_text'] ?? ''));
            $size     = $item['size'] ?? null;

            if ($filename === '' || $path === '') {
                $result['errors'][] = 'Item #' . ($index + 1) . ' is missing filename or path.';
                continue;
            }

            if (!mediaIsSafePath($path) || mb_strlen($path) > 255 || mb_strlen($filename) > 191) {
                $result['errors'][] = 'Item "' . $filename . '" has an unsafe or too-long path.';
                continue;
            }

            // Backward compatible & non-destructive: an existing row for
            // this exact path is left untouched (never overwritten), so
            // re-running an import is always safe and never clobbers
            // metadata edited locally since the export was made.
            if (mediaFindByPath($pdo, $path) !== null) {
                $result['skipped']++;
                continue;
            }

            if (!mediaFileExists($path)) {
                $result['missing'][] = $path;
            }

            $sizeInt = is_numeric($size) ? (int) $size : null;
            $mimeVal = $mime !== '' ? $mime : null;
            mediaInsertRow($pdo, $filename, $path, $mimeVal, $sizeInt, $alt !== '' ? $alt : null);
            $result['imported']++;
        }

        return $result;
    }
}

if (!function_exists('ensureContactMessagesTable')) {
    function ensureContactMessagesTable(PDO $pdo): void
    {
        $driver = strtolower((string) env('DB_DRIVER', 'mysql'));

        if ($driver === 'mysql') {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS contact_messages (
                  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  name        VARCHAR(191) NOT NULL,
                  email       VARCHAR(191) NOT NULL,
                  message     TEXT NOT NULL,
                  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  is_read     TINYINT(1) NOT NULL DEFAULT 0
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );

            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS contact_messages (
              id          INTEGER PRIMARY KEY AUTOINCREMENT,
              name        TEXT NOT NULL,
              email       TEXT NOT NULL,
              message     TEXT NOT NULL,
              created_at  TEXT NOT NULL DEFAULT (datetime(\'now\')),
              is_read     INTEGER NOT NULL DEFAULT 0
            )'
        );
    }
}