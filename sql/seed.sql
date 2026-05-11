-- ============================================================
-- Construction OS — Stage 1 Seed Data
-- ============================================================

-- Default admin user — run setup-admin.php on first deploy to create this correctly
-- INSERT handled by setup-admin.php (generates proper bcrypt hash at runtime)

-- Default company settings
INSERT IGNORE INTO `company_settings`
    (`id`, `company_name`, `address`, `city`, `state`, `zip`, `phone`, `email`, `default_markup`, `default_tax`, `default_waste`, `proposal_terms`)
VALUES (1,
    'My Construction Co.',
    '123 Main Street',
    'Anytown',
    'TX',
    '75001',
    '(555) 000-0000',
    'info@myconstructionco.com',
    20.00,
    8.00,
    5.00,
    'Payment is due within 30 days of invoice. A 1.5% monthly finance charge will be added to all past due accounts.'
);
