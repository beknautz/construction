-- ============================================================
-- Construction OS — Stage 3: Projects Module
-- Run after stage2_leads.sql
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Drop the Stage 1 stub and create the full projects table
DROP TABLE IF EXISTS `projects`;

CREATE TABLE `projects` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lead_id`           INT UNSIGNED DEFAULT NULL,
    `title`             VARCHAR(255) NOT NULL,
    `status`            ENUM('Planning','Estimating','Proposal','Contracted','In Progress','Waiting','Completed','Closed')
                        NOT NULL DEFAULT 'Planning',
    -- Client info (copied from lead or entered directly)
    `client_name`       VARCHAR(200) NOT NULL,
    `client_email`      VARCHAR(255) DEFAULT NULL,
    `client_phone`      VARCHAR(30)  DEFAULT NULL,
    `client_company`    VARCHAR(150) DEFAULT NULL,
    -- Project address
    `address`           VARCHAR(255) DEFAULT NULL,
    `city`              VARCHAR(100) DEFAULT NULL,
    `state`             VARCHAR(50)  DEFAULT NULL,
    `zip`               VARCHAR(20)  DEFAULT NULL,
    -- Project details
    `project_type`      ENUM('Remodel','Bathroom','Kitchen','Addition','New Build','Excavation','Other')
                        NOT NULL DEFAULT 'Remodel',
    `scope_summary`     TEXT DEFAULT NULL,
    `budget`            DECIMAL(12,2) DEFAULT NULL,
    `contract_amount`   DECIMAL(12,2) DEFAULT NULL,
    `start_date`        DATE DEFAULT NULL,
    `end_date`          DATE DEFAULT NULL,
    `assigned_to`       INT UNSIGNED DEFAULT NULL,
    `created_by`        INT UNSIGNED DEFAULT NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`lead_id`)     REFERENCES `leads`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_status`     (`status`),
    INDEX `idx_start_date` (`start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Project notes
CREATE TABLE IF NOT EXISTS `project_notes` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED DEFAULT NULL,
    `note`       TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Project file attachments
CREATE TABLE IF NOT EXISTS `project_files` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_id`    INT UNSIGNED NOT NULL,
    `user_id`       INT UNSIGNED DEFAULT NULL,
    `filename`      VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `file_type`     VARCHAR(100) DEFAULT NULL,
    `file_size`     INT UNSIGNED DEFAULT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Project photos
CREATE TABLE IF NOT EXISTS `project_photos` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_id`  INT UNSIGNED NOT NULL,
    `user_id`     INT UNSIGNED DEFAULT NULL,
    `filename`    VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `category`    ENUM('Before','During','After','Inspection','Damage','Materials','Other')
                  NOT NULL DEFAULT 'During',
    `caption`     VARCHAR(255) DEFAULT NULL,
    `file_size`   INT UNSIGNED DEFAULT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Project activity log
CREATE TABLE IF NOT EXISTS `project_activity` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_id`  INT UNSIGNED NOT NULL,
    `user_id`     INT UNSIGNED DEFAULT NULL,
    `action`      VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
