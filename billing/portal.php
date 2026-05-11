<?php
/**
 * billing/portal.php
 * Redirects tenant to their Stripe Customer Portal.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../src/StripeService.php';

require_login();

$tid = current_tenant_id();
if (!$tid) {
    header('Location: ' . APP_URL . '/admin/');
    exit;
}

$db = get_db();
$tr = $db->prepare('SELECT stripe_customer_id FROM tenants WHERE id = ? LIMIT 1');
$tr->execute([$tid]);
$tenant = $tr->fetch();

$customerId = $tenant['stripe_customer_id'] ?? '';
if (!$customerId) {
    set_flash('error', 'No Stripe customer found. Please subscribe first.');
    header('Location: ' . APP_URL . '/billing/');
    exit;
}

$skRow = $db->prepare("SELECT setting_value FROM ai_settings WHERE setting_key = 'stripe_secret_key' LIMIT 1");
$skRow->execute();
$sk = (string)($skRow->fetchColumn() ?: '');

if (!$sk) {
    set_flash('error', 'Stripe is not configured.');
    header('Location: ' . APP_URL . '/billing/');
    exit;
}

try {
    $stripe  = new StripeService($sk);
    $session = $stripe->createBillingPortalSession($customerId, APP_URL . '/billing/');
    header('Location: ' . $session['url']);
    exit;
} catch (RuntimeException $ex) {
    set_flash('error', 'Could not open billing portal: ' . $ex->getMessage());
    header('Location: ' . APP_URL . '/billing/');
    exit;
}
