# Phase 4.1 — CMS Foundation: Setup

## 1. Folder layout on the server

Place these folders **side by side**, with `public_html/` as the web
server's document root (as it already is today):

```
project-root/            <- NOT web-accessible
├── .env                 <- copy from .env.example, fill in real values
├── config/
├── app/
├── database/
├── storage/              <- must be writable by PHP (chmod 750 or 770)
└── public_html/          <- web-accessible (existing site + new /admin)
```

If your host only lets you upload into `public_html/`, ask it to point the
document root at a folder that has `public_html/` as a *subfolder*, with
`config/`, `app/`, `database/`, and `storage/` as siblings one level up.
This keeps secrets and the database file permanently outside anything a
browser can request — the `.htaccess` files included are only a backup in
case that ever changes.

## 2. Configure

```bash
cp .env.example .env
# edit .env: set SESSION_NAME, and DB_* only if you're using MySQL instead
# of the SQLite default.
```

## 3. Create the database schema

```bash
php database/migrate.php
```

This creates `storage/portfolio.sqlite` (SQLite, default) or applies
`database/schema-mysql.sql` to the database named in `.env` (if
`DB_DRIVER=mysql`).

## 4. Create your admin account

```bash
php database/create_admin.php
```

You'll be prompted for a username, an optional email, and a password
(hidden while typing, minimum 10 characters). The password is hashed with
`password_hash()` before it touches the database — nothing is ever stored
in plain text, and no credentials are hardcoded anywhere in the code.

### No SSH / terminal access on your host?

If you can't run PHP from the command line, use the browser-based
one-time setup page instead:

1. In `.env`, set `SETUP_TOKEN` to a long random string you make up
   yourself (32+ characters — mash the keyboard or use any password
   generator). Keep it secret; anyone with this token can create the
   first admin account.
2. Visit `https://your-domain/admin/setup.php?token=THE_TOKEN_YOU_SET`.
3. The page runs the migration automatically, then shows a form to create
   your admin account (username, optional email, password).
4. Submit the form. Once the account is created, the page writes a marker
   file (`storage/.setup_complete`) and will refuse to run again — even
   with the correct token — so it can't be reused to add another admin
   later.
5. **Delete `public_html/admin/setup.php` immediately after use.** It is
   token-protected and self-locking, but a bootstrap script with no
   further purpose is still better off gone.

If you have SSH, prefer `database/migrate.php` + `database/create_admin.php`
instead — the password never touches an HTTP request that way.

## 5. Log in

Visit `https://your-domain/admin/login.php`, sign in, and you'll land on
the dashboard skeleton at `/admin/index.php`.

## What Phase 4.1 does **not** do yet

- No editing screens for Profile / Education / Experience / Skills /
  Certifications / Projects / Contact / CV / Media — those are separate
  phases, module by module.
- `data/projects.php` still powers the public site exactly as before;
  nothing has been migrated into the `projects` table yet.
