-- ============================================================
-- Construction OS — Stage 4: AI Estimating Module
-- Run after stage3_projects.sql
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- AI / API settings (shared by Stage 4+)
CREATE TABLE IF NOT EXISTS `ai_settings` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key`    VARCHAR(100) NOT NULL UNIQUE,
    `setting_value`  TEXT DEFAULT NULL,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default AI settings (update anthropic_api_key via Settings page)
INSERT IGNORE INTO `ai_settings` (`setting_key`, `setting_value`) VALUES
    ('anthropic_api_key', ''),
    ('anthropic_model',   'claude-sonnet-4-5'),
    ('ai_enabled',        '1');

-- Drop the Stage 1 stub and create the full estimates table
DROP TABLE IF EXISTS `estimates`;

CREATE TABLE `estimates` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_id`    INT UNSIGNED DEFAULT NULL,
    `lead_id`       INT UNSIGNED DEFAULT NULL,
    `title`         VARCHAR(255) NOT NULL,
    `status`        ENUM('Draft','Review','Approved','Rejected','Archived')
                    NOT NULL DEFAULT 'Draft',
    -- Client
    `client_name`   VARCHAR(200) DEFAULT NULL,
    -- Overhead settings (per estimate)
    `markup_pct`    DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    `tax_pct`       DECIMAL(5,2) NOT NULL DEFAULT 8.00,
    `waste_pct`     DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    -- Calculated totals (denormalized for speed)
    `subtotal`      DECIMAL(12,2) NOT NULL DEFAULT 0,
    `waste_amount`  DECIMAL(12,2) NOT NULL DEFAULT 0,
    `markup_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `tax_amount`    DECIMAL(12,2) NOT NULL DEFAULT 0,
    `grand_total`   DECIMAL(12,2) NOT NULL DEFAULT 0,
    `notes`         TEXT DEFAULT NULL,
    `created_by`    INT UNSIGNED DEFAULT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`lead_id`)    REFERENCES `leads`(`id`)    ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)    ON DELETE SET NULL,
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_status`     (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estimate sections (Demo, Framing, etc.)
CREATE TABLE IF NOT EXISTS `estimate_sections` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `estimate_id` INT UNSIGNED NOT NULL,
    `category`    VARCHAR(100) NOT NULL DEFAULT 'Other',
    `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`estimate_id`) REFERENCES `estimates`(`id`) ON DELETE CASCADE,
    INDEX `idx_estimate_id` (`estimate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estimate line items
CREATE TABLE IF NOT EXISTS `estimate_line_items` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `section_id`   INT UNSIGNED NOT NULL,
    `estimate_id`  INT UNSIGNED NOT NULL,
    `description`  VARCHAR(500) NOT NULL,
    `qty`          DECIMAL(10,3) NOT NULL DEFAULT 1,
    `unit`         VARCHAR(30)   DEFAULT NULL,
    `labor_cost`   DECIMAL(12,2) NOT NULL DEFAULT 0,
    `material_cost`DECIMAL(12,2) NOT NULL DEFAULT 0,
    `equipment_cost`DECIMAL(12,2) NOT NULL DEFAULT 0,
    `sub_cost`     DECIMAL(12,2) NOT NULL DEFAULT 0,
    `line_total`   DECIMAL(12,2) NOT NULL DEFAULT 0,
    `sort_order`   INT UNSIGNED NOT NULL DEFAULT 0,
    `is_allowance` TINYINT(1)   NOT NULL DEFAULT 0,
    `notes`        VARCHAR(500)  DEFAULT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`section_id`)  REFERENCES `estimate_sections`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`estimate_id`) REFERENCES `estimates`(`id`)         ON DELETE CASCADE,
    INDEX `idx_section_id`  (`section_id`),
    INDEX `idx_estimate_id` (`estimate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI suggestion log (stores raw suggestions for review)
CREATE TABLE IF NOT EXISTS `estimate_ai_suggestions` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `estimate_id` INT UNSIGNED NOT NULL,
    `user_id`     INT UNSIGNED DEFAULT NULL,
    `prompt`      TEXT NOT NULL,
    `response`    LONGTEXT DEFAULT NULL,
    `model`       VARCHAR(100) DEFAULT NULL,
    `input_tokens`  INT UNSIGNED DEFAULT NULL,
    `output_tokens` INT UNSIGNED DEFAULT NULL,
    `cost_usd`    DECIMAL(10,6) DEFAULT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`estimate_id`) REFERENCES `estimates`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
