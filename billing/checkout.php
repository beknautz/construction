<?php
/**
 * billing/checkout.php
 * Creates a Stripe Checkout session and redirects the tenant.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../src/StripeService.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/billing/');
    exit;
}

$tid = current_tenant_id();
if ($tid === null) {
    header('Location: ' . APP_URL . '/admin/');
    exit;
}

$priceId = trim($_POST['price_id'] ?? '');
if (!$priceId) {
    set_flash('error', 'Invalid plan selected.');
    header('Location: ' . APP_URL . '/billing/');
    exit;
}

$db = get_db();

// Get tenant's Stripe customer ID
$tr = $db->prepare('SELECT stripe_customer_id, email, company_name FROM tenants WHERE id = ? LIMIT 1');
$tr->execute([$tid]);
$tenant = $tr->fetch();

// Load Stripe secret
$skRow = $db->prepare("SELECT setting_value FROM ai_settings WHERE setting_key = 'stripe_secret_key' LIMIT 1");
$skRow->execute();
$sk = (string)($skRow->fetchColumn() ?: '');

if (!$sk) {
    set_flash('error', 'Stripe is not configured. Contact support.');
    header('Location: ' . APP_URL . '/billing/');
    exit;
}

$stripe = new StripeService($sk);

try {
    // Ensure customer exists
    $customerId = $tenant['stripe_customer_id'] ?? '';
    if (!$customerId) {
        $user = current_user();
        $cust = $stripe->createCustomer($tenant['email'], $user['name'], ['tenant_id' => $tid]);
        $customerId = $cust['id'];
        $db->prepare('UPDATE tenants SET stripe_customer_id = ? WHERE id = ?')->execute([$customerId, $tid]);
    }

    $session = $stripe->createCheckoutSession(
        $customerId,
        $priceId,
        APP_URL . '/billing/?success=1',
        APP_URL . '/billing/?canceled=1',
        ['tenant_id' => $tid]
    );

    header('Location: ' . $session['url']);
    exit;

} catch (RuntimeException $ex) {
    set_flash('error', 'Could not start checkout: ' . $ex->getMessage());
    header('Location: ' . APP_URL . '/billing/');
    exit;
}
