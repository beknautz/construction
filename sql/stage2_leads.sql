-- ============================================================
-- Construction OS — Stage 2: CRM + Lead Intake
-- Run after schema.sql
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Drop the Stage 1 stub and create the full leads table
DROP TABLE IF EXISTS `leads`;

CREATE TABLE `leads` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `first_name`      VARCHAR(100) NOT NULL,
    `last_name`       VARCHAR(100) NOT NULL DEFAULT '',
    `email`           VARCHAR(255) DEFAULT NULL,
    `phone`           VARCHAR(30)  DEFAULT NULL,
    `company`         VARCHAR(150) DEFAULT NULL,
    `address`         VARCHAR(255) DEFAULT NULL,
    `city`            VARCHAR(100) DEFAULT NULL,
    `state`           VARCHAR(50)  DEFAULT NULL,
    `zip`             VARCHAR(20)  DEFAULT NULL,
    `status`          ENUM('New','Contacted','Site Visit Scheduled','Estimate Needed','Proposal Sent','Won','Lost')
                      NOT NULL DEFAULT 'New',
    `source`          ENUM('Google Ads','Website','Referral','Facebook','Phone Call','Repeat Client','Other')
                      NOT NULL DEFAULT 'Website',
    `project_type`    ENUM('Remodel','Bathroom','Kitchen','Addition','New Build','Excavation','Other')
                      NOT NULL DEFAULT 'Remodel',
    `budget`          DECIMAL(12,2) DEFAULT NULL,
    `description`     TEXT DEFAULT NULL,
    `follow_up_date`  DATE DEFAULT NULL,
    `assigned_to`     INT UNSIGNED DEFAULT NULL,
    `created_by`      INT UNSIGNED DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_status`       (`status`),
    INDEX `idx_follow_up`    (`follow_up_date`),
    INDEX `idx_created_at`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lead notes
CREATE TABLE IF NOT EXISTS `lead_notes` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lead_id`    INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED DEFAULT NULL,
    `note`       TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_lead_id` (`lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lead file attachments
CREATE TABLE IF NOT EXISTS `lead_files` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lead_id`       INT UNSIGNED NOT NULL,
    `user_id`       INT UNSIGNED DEFAULT NULL,
    `filename`      VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `file_type`     VARCHAR(100) DEFAULT NULL,
    `file_size`     INT UNSIGNED DEFAULT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lead activity log
CREATE TABLE IF NOT EXISTS `lead_activity` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lead_id`     INT UNSIGNED NOT NULL,
    `user_id`     INT UNSIGNED DEFAULT NULL,
    `action`      VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_lead_id` (`lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
