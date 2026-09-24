-- ==========================================================================
-- database/schema-sqlite.sql
-- Phase 4.1 — CMS foundation schema (default driver: SQLite).
--
-- All CREATE TABLE statements are idempotent (IF NOT EXISTS) so migrate.php
-- can be run safely more than once. Only "admins" is actively used by this
-- phase; the rest are extensible placeholders for later phases' CRUD work.
--
-- data/projects.php (Phase 3) is NOT touched by this schema and keeps
-- powering the public site until a later phase explicitly migrates it.
-- ==========================================================================

CREATE TABLE IF NOT EXISTS migrations (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  migration   TEXT NOT NULL UNIQUE,
  applied_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ---- Auth ----
CREATE TABLE IF NOT EXISTS admins (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  username       TEXT NOT NULL UNIQUE,
  email          TEXT,
  password_hash  TEXT NOT NULL,
  last_login_at  TEXT,
  created_at     TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at     TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ---- Profile (single row, id fixed to 1) ----
CREATE TABLE IF NOT EXISTS profile (
  id          INTEGER PRIMARY KEY CHECK (id = 1),
  full_name   TEXT,
  headline    TEXT,
  bio         TEXT,
  location    TEXT,
  photo_path  TEXT,
  updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ---- Education ----
CREATE TABLE IF NOT EXISTS education (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  institution  TEXT NOT NULL,
  degree       TEXT,
  field        TEXT,
  start_date   TEXT,
  end_date     TEXT,
  description  TEXT,
  sort_order   INTEGER NOT NULL DEFAULT 0,
  created_at   TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at   TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ---- Experience ----
CREATE TABLE IF NOT EXISTS experience (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  company      TEXT NOT NULL,
  role         TEXT NOT NULL,
  start_date   TEXT,
  end_date     TEXT,
  description  TEXT,
  sort_order   INTEGER NOT NULL DEFAULT 0,
  created_at   TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at   TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ---- Skills ----
CREATE TABLE IF NOT EXISTS skills (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  name        TEXT NOT NULL,
  category    TEXT,
  level       TEXT,
  sort_order  INTEGER NOT NULL DEFAULT 0,
  created_at  TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ---- Certifications ----
CREATE TABLE IF NOT EXISTS certifications (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  name            TEXT NOT NULL,
  issuer          TEXT,
  issue_date      TEXT,
  expire_date     TEXT,
  credential_url  TEXT,
  sort_order      INTEGER NOT NULL DEFAULT 0,
  created_at      TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at      TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ---- Projects (shape mirrors data/projects.php for a future migration) ----
CREATE TABLE IF NOT EXISTS projects (
  id                INTEGER PRIMARY KEY AUTOINCREMENT,
  slug              TEXT NOT NULL UNIQUE,
  title             TEXT NOT NULL,
  category          TEXT,
  status            TEXT,
  year              TEXT,
  featured          INTEGER NOT NULL DEFAULT 0,
  summary           TEXT,
  technologies      TEXT,   -- JSON-encoded array, e.g. ["MikroTik","VRRP"]
  problem           TEXT,
  approach          TEXT,
  result            TEXT,
  result_highlight  TEXT,
  lessons           TEXT,
  sort_order        INTEGER NOT NULL DEFAULT 0,
  created_at        TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at        TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ---- Project media (screenshots/diagrams attached to a project) ----
CREATE TABLE IF NOT EXISTS project_media (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  project_id  INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
  type        TEXT NOT NULL DEFAULT 'image',
  path        TEXT NOT NULL,
  alt_text    TEXT,
  sort_order  INTEGER NOT NULL DEFAULT 0,
  created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ---- Contact / social links ----
CREATE TABLE IF NOT EXISTS contacts (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  label       TEXT NOT NULL,
  type        TEXT,
  value       TEXT NOT NULL,
  icon        TEXT,
  is_visible  INTEGER NOT NULL DEFAULT 1,
  sort_order  INTEGER NOT NULL DEFAULT 0,
  created_at  TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ---- CV / documents ----
CREATE TABLE IF NOT EXISTS documents (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  type         TEXT NOT NULL DEFAULT 'cv',
  title        TEXT,
  file_path    TEXT NOT NULL,
  is_current   INTEGER NOT NULL DEFAULT 1,
  uploaded_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ---- General media library ----
CREATE TABLE IF NOT EXISTS media (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  filename     TEXT NOT NULL,
  path         TEXT NOT NULL,
  mime_type    TEXT,
  size         INTEGER,
  alt_text     TEXT,
  uploaded_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS contact_messages (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  name        TEXT NOT NULL,
  email       TEXT NOT NULL,
  message     TEXT NOT NULL,
  created_at  TEXT NOT NULL DEFAULT (datetime('now')),
  is_read     INTEGER NOT NULL DEFAULT 0
);
