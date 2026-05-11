<?php
/**
 * api/stripe-webhook.php
 * Receives Stripe webhook events and updates tenant subscription state.
 * Set endpoint URL in Stripe Dashboard → Webhooks → Add endpoint.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../src/StripeService.php';

// Read raw body BEFORE any output
$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$db = get_db();

// Load webhook secret from settings
$whSecret = '';
$row = $db->prepare("SELECT setting_value FROM ai_settings WHERE setting_key = 'stripe_webhook_secret' LIMIT 1");
$row->execute();
$whSecret = (string)($row->fetchColumn() ?: '');

if (!$whSecret) {
    http_response_code(500);
    exit('Webhook secret not configured');
}

// Read Stripe secret key for StripeService
$skRow = $db->prepare("SELECT setting_value FROM ai_settings WHERE setting_key = 'stripe_secret_key' LIMIT 1");
$skRow->execute();
$secretKey = (string)($skRow->fetchColumn() ?: '');

$stripe = new StripeService($secretKey);
$event  = $stripe->verifyWebhook($payload, $sigHeader, $whSecret);

if ($event === null) {
    http_response_code(400);
    exit('Invalid signature');
}

$eventId   = $event['id']   ?? '';
$eventType = $event['type'] ?? '';

// Idempotency — skip already-processed events
$check = $db->prepare('SELECT processed FROM stripe_events WHERE id = ?');
$check->execute([$eventId]);
$existing = $check->fetch();
if ($existing && $existing['processed']) {
    http_response_code(200);
    exit('already processed');
}

// Log the event
$db->prepare(
    'INSERT INTO stripe_events (id, type, payload, processed) VALUES (?,?,?,0)
     ON DUPLICATE KEY UPDATE type = VALUES(type), payload = VALUES(payload)'
)->execute([$eventId, $eventType, $payload]);

// ---------------------------------------------------------------
// Handle events
// ---------------------------------------------------------------
$data = $event['data']['object'] ?? [];

try {
    switch ($eventType) {

        case 'checkout.session.completed':
            // Subscription created via checkout
            $customerId     = $data['customer']     ?? '';
            $subscriptionId = $data['subscription'] ?? '';
            $metadata       = $data['metadata']     ?? [];
            $tenantId       = (int)($metadata['tenant_id'] ?? 0);

            if ($tenantId && $subscriptionId) {
                $sub = $stripe->getSubscription($subscriptionId);
                _update_tenant_subscription($db, $tenantId, $sub, $customerId);
            }
            break;

        case 'customer.subscription.updated':
        case 'customer.subscription.created':
            $customerId = $data['customer'] ?? '';
            $subId      = $data['id']       ?? '';

            $tenant = _find_tenant_by_customer($db, $customerId);
            if ($tenant) {
                _update_tenant_subscription($db, $tenant['id'], $data, $customerId);
            }
            break;

        case 'customer.subscription.deleted':
            $customerId = $data['customer'] ?? '';
            $tenant = _find_tenant_by_customer($db, $customerId);
            if ($tenant) {
                $db->prepare(
                    "UPDATE tenants SET status = 'canceled', stripe_status = 'canceled' WHERE id = ?"
                )->execute([$tenant['id']]);
            }
            break;

        case 'invoice.payment_failed':
            $customerId = $data['customer'] ?? '';
            $tenant = _find_tenant_by_customer($db, $customerId);
            if ($tenant) {
                $db->prepare(
                    "UPDATE tenants SET status = 'past_due', stripe_status = 'past_due' WHERE id = ?"
                )->execute([$tenant['id']]);
            }
            break;

        case 'invoice.payment_succeeded':
            $customerId = $data['customer'] ?? '';
            $tenant = _find_tenant_by_customer($db, $customerId);
            if ($tenant && $tenant['status'] === 'past_due') {
                $db->prepare(
                    "UPDATE tenants SET status = 'active', stripe_status = 'active' WHERE id = ?"
                )->execute([$tenant['id']]);
            }
            // Reset AI usage counter on successful renewal
            if ($tenant) {
                $db->prepare(
                    'UPDATE tenants SET ai_calls_used = 0, ai_calls_reset_at = NOW() WHERE id = ?'
                )->execute([$tenant['id']]);
            }
            break;
    }

    // Mark processed
    $db->prepare('UPDATE stripe_events SET processed = 1 WHERE id = ?')->execute([$eventId]);
    http_response_code(200);
    echo 'ok';

} catch (Throwable $ex) {
    error_log('Stripe webhook error: ' . $ex->getMessage());
    http_response_code(500);
    echo 'error';
}

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------
function _find_tenant_by_customer(PDO $db, string $customerId): ?array
{
    if (!$customerId) return null;
    $s = $db->prepare('SELECT * FROM tenants WHERE stripe_customer_id = ? LIMIT 1');
    $s->execute([$customerId]);
    $row = $s->fetch();
    return $row ?: null;
}

function _update_tenant_subscription(PDO $db, int $tenantId, array $sub, string $customerId): void
{
    $stripeStatus = $sub['status'] ?? 'active';
    $appStatus    = 'active';

    if (in_array($stripeStatus, ['past_due', 'unpaid'], true)) {
        $appStatus = 'past_due';
    } elseif (in_array($stripeStatus, ['canceled', 'incomplete_expired'], true)) {
        $appStatus = 'canceled';
    }

    $periodStart = isset($sub['current_period_start'])
        ? date('Y-m-d H:i:s', $sub['current_period_start']) : null;
    $periodEnd   = isset($sub['current_period_end'])
        ? date('Y-m-d H:i:s', $sub['current_period_end']) : null;

    // Map Stripe price_id → plan
    $priceId  = $sub['items']['data'][0]['price']['id'] ?? ($sub['plan']['id'] ?? '');
    $planRow  = null;
    if ($priceId) {
        $ps = $db->prepare('SELECT id FROM subscription_plans WHERE stripe_price_id = ? LIMIT 1');
        $ps->execute([$priceId]);
        $planRow = $ps->fetch();
    }

    $db->prepare(
        'UPDATE tenants SET
            stripe_customer_id      = ?,
            stripe_subscription_id  = ?,
            stripe_status           = ?,
            status                  = ?,
            current_period_start    = ?,
            current_period_end      = ?,
            plan_id                 = COALESCE(?, plan_id)
         WHERE id = ?'
    )->execute([
        $customerId,
        $sub['id'] ?? null,
        $stripeStatus,
        $appStatus,
        $periodStart,
        $periodEnd,
        $planRow ? $planRow['id'] : null,
        $tenantId,
    ]);
}
