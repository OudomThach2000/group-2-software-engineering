-- Community Issue Reporting System — database schema (MySQL 8 / MariaDB)
-- Group 2 · CSCI 841 Advanced Software Engineering.  Owner: Pichponleur Pen.
--
-- Setup:
--   CREATE DATABASE community_issues CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   USE community_issues;
--   SOURCE db/schema.sql;      -- then optionally: SOURCE db/seed.sql;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS status_history;
DROP TABLE IF EXISTS assignments;
DROP TABLE IF EXISTS issues;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- Users: residents, field staff, supervisors/admins  (FR7, UC6)
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,          -- bcrypt via PHP password_hash(); never store plain text
  role          ENUM('resident','staff','supervisor') NOT NULL DEFAULT 'resident',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Issue categories  (FR1) — bilingual for NFR2 (Khmer + English)
CREATE TABLE categories (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name_en    VARCHAR(80) NOT NULL,
  name_km    VARCHAR(80) NULL,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Issues (reports) — the core entity  (FR1, FR2, FR4)
CREATE TABLE issues (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  tracking_ref     VARCHAR(20)  NOT NULL UNIQUE,   -- code the resident uses to track (UC2)
  category_id      INT          NOT NULL,
  description      TEXT         NOT NULL,
  location_text    VARCHAR(255) NULL,
  latitude         DECIMAL(9,6) NULL,
  longitude        DECIMAL(9,6) NULL,
  photo_path       VARCHAR(255) NULL,              -- optional photo (FR1)
  status           ENUM('New','Assigned','In-progress','Resolved','Closed') NOT NULL DEFAULT 'New',
  reporter_user_id INT          NULL,              -- NULL = anonymous submission
  reporter_contact VARCHAR(190) NULL,              -- optional email/phone for anonymous reporters
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  resolved_at      DATETIME NULL,                  -- set when Resolved (for FR6 average time)
  CONSTRAINT fk_issue_category FOREIGN KEY (category_id)      REFERENCES categories(id),
  CONSTRAINT fk_issue_reporter FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_issue_status   (status),
  INDEX idx_issue_category (category_id),
  INDEX idx_issue_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assignments — supervisor assigns an issue to a field worker  (FR3, UC3)
CREATE TABLE assignments (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  issue_id            INT NOT NULL,
  assigned_to_user_id INT NOT NULL,                -- field staff
  assigned_by_user_id INT NOT NULL,                -- supervisor
  note                VARCHAR(255) NULL,
  assigned_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_assign_issue FOREIGN KEY (issue_id)            REFERENCES issues(id) ON DELETE CASCADE,
  CONSTRAINT fk_assign_to    FOREIGN KEY (assigned_to_user_id) REFERENCES users(id),
  CONSTRAINT fk_assign_by    FOREIGN KEY (assigned_by_user_id) REFERENCES users(id),
  INDEX idx_assign_issue (issue_id),
  INDEX idx_assign_to    (assigned_to_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Status history — every status change, for the resident timeline + audit  (FR2, FR4)
CREATE TABLE status_history (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  issue_id           INT NOT NULL,
  old_status         ENUM('New','Assigned','In-progress','Resolved','Closed') NULL,
  new_status         ENUM('New','Assigned','In-progress','Resolved','Closed') NOT NULL,
  changed_by_user_id INT NULL,
  note               VARCHAR(255) NULL,
  changed_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_history_issue FOREIGN KEY (issue_id)           REFERENCES issues(id) ON DELETE CASCADE,
  CONSTRAINT fk_history_user  FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_history_issue (issue_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications — messages to residents on status change  (FR5, UC7)
CREATE TABLE notifications (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  issue_id   INT NOT NULL,
  recipient  VARCHAR(190) NOT NULL,                -- email/phone/user reference
  channel    ENUM('email','sms','none') NOT NULL DEFAULT 'none',
  message    VARCHAR(500) NOT NULL,
  status     ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at    DATETIME NULL,
  CONSTRAINT fk_notify_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
  INDEX idx_notify_issue  (issue_id),
  INDEX idx_notify_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
