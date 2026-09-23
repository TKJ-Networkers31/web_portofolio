<?php

declare(strict_types=1);

/**
 * database/create_admin.php
 *
 * Run from the command line to create an admin account:
 *   php database/create_admin.php [username] [email]
 *
 * The password is always entered interactively (never as a CLI argument)
 * so it never ends up in shell history or process listings, and it is
 * always stored as a password_hash(), never in plain text.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/database.php';

$username = $argv[1] ?? null;
$email    = $argv[2] ?? null;

if (!$username) {
    fwrite(STDOUT, 'Admin username: ');
    $username = trim((string) fgets(STDIN));
}

if ($email === null) {
    fwrite(STDOUT, 'Admin email (optional, press Enter to skip): ');
    $email = trim((string) fgets(STDIN));
}

fwrite(STDOUT, 'Admin password (min 10 characters): ');
// Best-effort hidden input on Unix-like shells; falls back to visible input
// on platforms where "stty" isn't available (e.g. some Windows shells).
$isUnixLike = stripos(PHP_OS, 'WIN') === false;
if ($isUnixLike) {
    system('stty -echo 2>/dev/null');
}
$password = trim((string) fgets(STDIN));
if ($isUnixLike) {
    system('stty echo 2>/dev/null');
}
fwrite(STDOUT, "\n");

if ($username === '' || strlen($password) < 10) {
    fwrite(STDERR, "Username is required and password must be at least 10 characters.\n");
    exit(1);
}

$pdo = db();

$existing = $pdo->prepare('SELECT id FROM admins WHERE username = :username');
$existing->execute(['username' => $username]);

if ($existing->fetch()) {
    fwrite(STDERR, "An admin with that username already exists.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$insert = $pdo->prepare(
    'INSERT INTO admins (username, email, password_hash) VALUES (:username, :email, :hash)'
);
$insert->execute([
    'username' => $username,
    'email'    => $email !== '' ? $email : null,
    'hash'     => $hash,
]);

echo "Admin '{$username}' created.\n";
