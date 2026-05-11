-- ============================================================
-- Construction OS — Stage 1 Seed Data
-- ============================================================

-- Default admin user  (password: admin123)
INSERT IGNORE INTO `users` (`name`, `email`, `password`, `role`) VALUES
('Admin User', 'admin@constructionos.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uMpF8190W', 'admin');

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
