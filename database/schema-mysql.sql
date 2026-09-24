-- ==========================================================================
-- database/schema-mysql.sql
-- Phase 4.1 — CMS foundation schema (alternate driver: MySQL/MariaDB).
-- Used only when .env sets DB_DRIVER=mysql. Table shapes match
-- schema-sqlite.sql exactly so the rest of the app never needs to care
-- which driver is active.
-- ==========================================================================

CREATE TABLE IF NOT EXISTS migrations (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  migration   VARCHAR(191) NOT NULL UNIQUE,
  applied_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username       VARCHAR(64) NOT NULL UNIQUE,
  email          VARCHAR(191),
  password_hash  VARCHAR(255) NOT NULL,
  last_login_at  DATETIME NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS profile (
  id          TINYINT UNSIGNED PRIMARY KEY,
  full_name   VARCHAR(191),
  headline    VARCHAR(191),
  bio         TEXT,
  location    VARCHAR(191),
  photo_path  VARCHAR(255),
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT profile_single_row CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS education (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution  VARCHAR(191) NOT NULL,
  degree       VARCHAR(191),
  field        VARCHAR(191),
  start_date   DATE NULL,
  end_date     DATE NULL,
  description  TEXT,
  sort_order   INT NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS experience (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company      VARCHAR(191) NOT NULL,
  role         VARCHAR(191) NOT NULL,
  start_date   DATE NULL,
  end_date     DATE NULL,
  description  TEXT,
  sort_order   INT NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS skills (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(191) NOT NULL,
  category    VARCHAR(191),
  level       VARCHAR(64),
  sort_order  INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS certifications (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(191) NOT NULL,
  issuer          VARCHAR(191),
  issue_date      DATE NULL,
  expire_date     DATE NULL,
  credential_url  VARCHAR(255),
  sort_order      INT NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS projects (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug              VARCHAR(191) NOT NULL UNIQUE,
  title             VARCHAR(191) NOT NULL,
  category          VARCHAR(191),
  status            VARCHAR(64),
  year              VARCHAR(16),
  featured          TINYINT(1) NOT NULL DEFAULT 0,
  summary           TEXT,
  technologies      TEXT,
  problem           TEXT,
  approach          TEXT,
  result            TEXT,
  result_highlight  VARCHAR(255),
  lessons           TEXT,
  sort_order        INT NOT NULL DEFAULT 0,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_media (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id  INT UNSIGNED NOT NULL,
  type        VARCHAR(32) NOT NULL DEFAULT 'image',
  path        VARCHAR(255) NOT NULL,
  alt_text    VARCHAR(255),
  sort_order  INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_project_media_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contacts (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label       VARCHAR(191) NOT NULL,
  type        VARCHAR(64),
  value       VARCHAR(255) NOT NULL,
  icon        VARCHAR(64),
  is_visible  TINYINT(1) NOT NULL DEFAULT 1,
  sort_order  INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS documents (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type         VARCHAR(32) NOT NULL DEFAULT 'cv',
  title        VARCHAR(191),
  file_path    VARCHAR(255) NOT NULL,
  is_current   TINYINT(1) NOT NULL DEFAULT 1,
  uploaded_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS media (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  filename     VARCHAR(191) NOT NULL,
  path         VARCHAR(255) NOT NULL,
  mime_type    VARCHAR(127),
  size         INT UNSIGNED,
  alt_text     VARCHAR(255),
  uploaded_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contact_messages (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(191) NOT NULL,
  email       VARCHAR(191) NOT NULL,
  message     TEXT NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_read     TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
