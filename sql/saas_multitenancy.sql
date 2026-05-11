-- ============================================================
-- Construction OS — SaaS Multi-Tenancy Layer
-- Run AFTER all previous stage SQL files
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------
-- Subscription Plans
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subscription_plans` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`             VARCHAR(100) NOT NULL,
    `slug`             VARCHAR(50)  NOT NULL UNIQUE,
    `price_monthly`    DECIMAL(8,2) NOT NULL DEFAULT 0,
    `ai_calls_limit`   INT UNSIGNED NOT NULL DEFAULT 100,   -- per billing period
    `projects_limit`   INT UNSIGNED NOT NULL DEFAULT 10,
    `users_limit`      INT UNSIGNED NOT NULL DEFAULT 2,
    `stripe_price_id`  VARCHAR(100) DEFAULT NULL,           -- set after Stripe setup
    `features`         JSON DEFAULT NULL,                   -- feature flags
    `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order`       INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `subscription_plans`
    (`name`, `slug`, `price_monthly`, `ai_calls_limit`, `projects_limit`, `users_limit`, `sort_order`)
VALUES
    ('Starter',  'starter',  49.00,  100,  10,  2, 1),
    ('Pro',      'pro',     149.00,  500,  50,  5, 2),
    ('Business', 'business',349.00, 2000, 999, 20, 3);

-- -------------------------------------------------------
-- Tenants  (one per contractor company)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tenants` (
    `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_name`         VARCHAR(255) NOT NULL,
    `email`                VARCHAR(255) NOT NULL UNIQUE,
    `phone`                VARCHAR(30)  DEFAULT NULL,
    `plan_id`              INT UNSIGNED NOT NULL DEFAULT 1,
    `status`               ENUM('trial','active','past_due','canceled','suspended')
                           NOT NULL DEFAULT 'trial',
    `trial_ends_at`        DATETIME DEFAULT NULL,
    -- Stripe
    `stripe_customer_id`   VARCHAR(100) DEFAULT NULL,
    `stripe_subscription_id` VARCHAR(100) DEFAULT NULL,
    `stripe_status`        VARCHAR(50)  DEFAULT NULL,
    `current_period_start` DATETIME DEFAULT NULL,
    `current_period_end`   DATETIME DEFAULT NULL,
    -- AI usage (reset each billing period)
    `ai_calls_used`        INT UNSIGNED NOT NULL DEFAULT 0,
    `ai_calls_reset_at`    DATETIME DEFAULT NULL,
    -- Settings
    `logo`                 VARCHAR(255) DEFAULT NULL,
    `timezone`             VARCHAR(100) NOT NULL DEFAULT 'America/Chicago',
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Stripe settings (platform keys — stored securely)
-- -------------------------------------------------------
INSERT IGNORE INTO `ai_settings` (`setting_key`, `setting_value`) VALUES
    ('stripe_secret_key',      ''),
    ('stripe_publishable_key', ''),
    ('stripe_webhook_secret',  ''),
    ('platform_name',          'Construction OS'),
    ('trial_days',             '14');

-- -------------------------------------------------------
-- AI usage log (cross-tenant — full audit trail)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tenant_ai_usage` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tenant_id`     INT UNSIGNED NOT NULL,
    `user_id`       INT UNSIGNED DEFAULT NULL,
    `module`        VARCHAR(50)  NOT NULL DEFAULT 'estimate',
    `record_id`     INT UNSIGNED DEFAULT NULL,
    `model`         VARCHAR(100) DEFAULT NULL,
    `input_tokens`  INT UNSIGNED NOT NULL DEFAULT 0,
    `output_tokens` INT UNSIGNED NOT NULL DEFAULT 0,
    `cost_usd`      DECIMAL(10,6) NOT NULL DEFAULT 0,
    `markup_pct`    DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    `billed_usd`    DECIMAL(10,6) NOT NULL DEFAULT 0,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    INDEX `idx_tenant_period` (`tenant_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Stripe event log (idempotency)
-- -------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stripe_events` (
    `id`          VARCHAR(100) PRIMARY KEY,   -- Stripe event ID
    `type`        VARCHAR(100) NOT NULL,
    `payload`     LONGTEXT DEFAULT NULL,
    `processed`   TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Add tenant_id to all user-data tables
-- -------------------------------------------------------
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `tenant_id` INT UNSIGNED DEFAULT NULL AFTER `id`,
    ADD INDEX IF NOT EXISTS `idx_tenant_id` (`tenant_id`);

ALTER TABLE `leads`
    ADD COLUMN IF NOT EXISTS `tenant_id` INT UNSIGNED DEFAULT NULL AFTER `id`,
    ADD INDEX IF NOT EXISTS `idx_tenant_id` (`tenant_id`);

ALTER TABLE `projects`
    ADD COLUMN IF NOT EXISTS `tenant_id` INT UNSIGNED DEFAULT NULL AFTER `id`,
    ADD INDEX IF NOT EXISTS `idx_tenant_id` (`tenant_id`);

ALTER TABLE `estimates`
    ADD COLUMN IF NOT EXISTS `tenant_id` INT UNSIGNED DEFAULT NULL AFTER `id`,
    ADD INDEX IF NOT EXISTS `idx_tenant_id` (`tenant_id`);

ALTER TABLE `proposals`
    ADD COLUMN IF NOT EXISTS `tenant_id` INT UNSIGNED DEFAULT NULL AFTER `id`,
    ADD INDEX IF NOT EXISTS `idx_tenant_id` (`tenant_id`);

ALTER TABLE `company_settings`
    ADD COLUMN IF NOT EXISTS `tenant_id` INT UNSIGNED DEFAULT NULL AFTER `id`,
    ADD INDEX IF NOT EXISTS `idx_tenant_id` (`tenant_id`);

-- -------------------------------------------------------
-- Super-admin user (platform owner — tenant_id = NULL)
-- This user sees all tenants and manages the platform.
-- Update password via setup-admin.php after import.
-- -------------------------------------------------------
UPDATE `users` SET `role` = 'admin' WHERE `email` = 'admin@constructionos.com';

SET FOREIGN_KEY_CHECKS = 1;
