-- ============================================================
-- Construction OS — Stage 1 Schema
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`          VARCHAR(150) NOT NULL,
    `email`         VARCHAR(255) NOT NULL UNIQUE,
    `password`      VARCHAR(255) NOT NULL,
    `role`          ENUM('admin','manager','estimator','field') NOT NULL DEFAULT 'manager',
    `avatar`        VARCHAR(255) DEFAULT NULL,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `last_login`    DATETIME DEFAULT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Company Settings
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `company_settings` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_name`      VARCHAR(255) NOT NULL DEFAULT 'My Construction Co.',
    `logo`              VARCHAR(255) DEFAULT NULL,
    `address`           VARCHAR(255) DEFAULT NULL,
    `city`              VARCHAR(100) DEFAULT NULL,
    `state`             VARCHAR(50)  DEFAULT NULL,
    `zip`               VARCHAR(20)  DEFAULT NULL,
    `phone`             VARCHAR(30)  DEFAULT NULL,
    `email`             VARCHAR(255) DEFAULT NULL,
    `website`           VARCHAR(255) DEFAULT NULL,
    `default_markup`    DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    `default_tax`       DECIMAL(5,2) NOT NULL DEFAULT 8.00,
    `default_waste`     DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    `proposal_terms`    TEXT DEFAULT NULL,
    `license_number`    VARCHAR(100) DEFAULT NULL,
    `insurance_info`    VARCHAR(255) DEFAULT NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Activity Log  (used across all modules)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_log` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`       INT UNSIGNED DEFAULT NULL,
    `module`        VARCHAR(50)  NOT NULL,
    `record_id`     INT UNSIGNED DEFAULT NULL,
    `action`        VARCHAR(100) NOT NULL,
    `description`   TEXT DEFAULT NULL,
    `ip_address`    VARCHAR(45)  DEFAULT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Stage 2–13 stub tables (created empty so the dashboard
-- COUNT() queries don't fail; populated by later stages)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leads` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `status`     VARCHAR(50) NOT NULL DEFAULT 'New',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `projects` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `status`     VARCHAR(50) NOT NULL DEFAULT 'Planning',
    `budget`     DECIMAL(12,2) DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `estimates` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `status`      VARCHAR(50) NOT NULL DEFAULT 'Draft',
    `grand_total` DECIMAL(12,2) DEFAULT 0,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `proposals` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `status`     VARCHAR(50) NOT NULL DEFAULT 'Draft',
    `total`      DECIMAL(12,2) DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `schedule_tasks` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `status`     VARCHAR(50) NOT NULL DEFAULT 'Pending',
    `due_date`   DATE DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
