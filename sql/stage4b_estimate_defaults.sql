-- ============================================================
-- Stage 4b: Estimate Labor Rate Defaults
-- Run after stage4_estimates.sql and saas_multitenancy.sql
-- ============================================================

ALTER TABLE `company_settings`
    ADD COLUMN IF NOT EXISTS `labor_rate_general`     DECIMAL(8,2) NOT NULL DEFAULT 55.00  AFTER `default_waste`,
    ADD COLUMN IF NOT EXISTS `labor_rate_carpenter`   DECIMAL(8,2) NOT NULL DEFAULT 75.00  AFTER `labor_rate_general`,
    ADD COLUMN IF NOT EXISTS `labor_rate_electrician` DECIMAL(8,2) NOT NULL DEFAULT 110.00 AFTER `labor_rate_carpenter`,
    ADD COLUMN IF NOT EXISTS `labor_rate_plumber`     DECIMAL(8,2) NOT NULL DEFAULT 100.00 AFTER `labor_rate_electrician`,
    ADD COLUMN IF NOT EXISTS `labor_rate_hvac`        DECIMAL(8,2) NOT NULL DEFAULT 95.00  AFTER `labor_rate_plumber`,
    ADD COLUMN IF NOT EXISTS `labor_rate_painter`     DECIMAL(8,2) NOT NULL DEFAULT 55.00  AFTER `labor_rate_hvac`,
    ADD COLUMN IF NOT EXISTS `labor_rate_equipment`   DECIMAL(8,2) NOT NULL DEFAULT 150.00 AFTER `labor_rate_painter`;
