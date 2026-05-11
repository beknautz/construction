<?php
/**
 * admin/settings.php
 * Platform-level settings — Stripe keys, trial days, etc.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_superadmin();

$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = [
        'stripe_secret_key',
        'stripe_publishable_key',
        'stripe_webhook_secret',
        'platform_name',
        'trial_days',
    ];
    foreach ($keys as $k) {
        $val = trim($_POST[$k] ?? '');
        // Don't blank out secret key if left empty (user didn't want to change it)
        if ($val === '' && in_array($k, ['stripe_secret_key', 'stripe_webhook_secret'], true)) {
            continue;
        }
        $db->prepare(
            "INSERT INTO ai_settings (setting_key, setting_value) VALUES (?,?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        )->execute([$k, $val]);
    }

    // Update plan stripe_price_id values
    $plans = $db->query('SELECT id, slug FROM subscription_plans')->fetchAll();
    foreach ($plans as $p) {
        $pid = trim($_POST['price_id_' . $p['slug']] ?? '');
        $db->prepare('UPDATE subscription_plans SET stripe_price_id = ? WHERE id = ?')
           ->execute([$pid ?: null, $p['id']]);
    }

    set_flash('success', 'Settings saved.');
    header('Location: ' . APP_URL . '/admin/settings.php');
    exit;
}

// Load current values
$settingKeys = ['stripe_secret_key','stripe_publishable_key','stripe_webhook_secret','platform_name','trial_days'];
$settings    = [];
$rows        = $db->query("SELECT setting_key, setting_value FROM ai_settings WHERE setting_key IN ('"
    . implode("','", $settingKeys) . "')")->fetchAll();
foreach ($rows as $r) { $settings[$r['setting_key']] = $r['setting_value']; }

$plans = $db->query('SELECT * FROM subscription_plans ORDER BY sort_order')->fetchAll();

$flash     = get_flash();
$pageTitle = 'Platform Settings';
require_once __DIR__ . '/../includes/layout-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/">Admin</a></li>
                <li class="breadcrumb-item active">Settings</li>
            </ol>
        </nav>
        <h1 class="page-title mb-0">Platform Settings</h1>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
    <?= e($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<form method="POST">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Stripe Integration</div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Get these keys from your
                        <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener">Stripe Dashboard → Developers → API keys</a>.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-medium small">Secret Key <span class="text-danger">*</span></label>
                        <input type="password" class="form-control font-monospace" name="stripe_secret_key"
                               placeholder="sk_live_… (leave blank to keep current)" autocomplete="off">
                        <div class="form-text">Starts with <code>sk_live_</code> (production) or <code>sk_test_</code> (testing). Never shown here after saving.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium small">Publishable Key</label>
                        <input type="text" class="form-control font-monospace" name="stripe_publishable_key"
                               value="<?= e($settings['stripe_publishable_key'] ?? '') ?>"
                               placeholder="pk_live_…">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium small">Webhook Signing Secret <span class="text-danger">*</span></label>
                        <input type="password" class="form-control font-monospace" name="stripe_webhook_secret"
                               placeholder="whsec_… (leave blank to keep current)" autocomplete="off">
                        <div class="form-text">
                            Set webhook endpoint to: <code><?= APP_URL ?>/api/stripe-webhook.php</code><br>
                            Events to listen for: <code>checkout.session.completed</code>, <code>customer.subscription.*</code>, <code>invoice.payment_*</code>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Plan → Stripe Price IDs</div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        Create products in Stripe Dashboard → Products, then paste the recurring Price ID (starts with <code>price_</code>) for each plan.
                    </p>
                    <?php foreach ($plans as $p): ?>
                    <div class="mb-3">
                        <label class="form-label fw-medium small">
                            <?= e($p['name']) ?> — <?= money($p['price_monthly']) ?>/month
                        </label>
                        <input type="text" class="form-control font-monospace" name="price_id_<?= e($p['slug']) ?>"
                               value="<?= e($p['stripe_price_id'] ?? '') ?>"
                               placeholder="price_…">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">General</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium small">Platform Name</label>
                            <input type="text" class="form-control" name="platform_name"
                                   value="<?= e($settings['platform_name'] ?? 'Construction OS') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium small">Free Trial Days</label>
                            <input type="number" class="form-control" name="trial_days" min="1" max="90"
                                   value="<?= (int)($settings['trial_days'] ?? 14) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-accent">
                    <i class="bi bi-save me-1"></i>Save Settings
                </button>
                <a href="<?= APP_URL ?>/admin/" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header fw-semibold">Setup Checklist</div>
                <div class="card-body">
                    <ol class="small">
                        <li class="mb-2">Create a Stripe account at <a href="https://stripe.com" target="_blank" rel="noopener">stripe.com</a></li>
                        <li class="mb-2">Go to Stripe → Products → Add product for each plan (Starter, Pro, Business) with monthly recurring prices</li>
                        <li class="mb-2">Copy each Price ID (price_…) and paste above</li>
                        <li class="mb-2">Copy your Secret Key and Publishable Key from Stripe → Developers → API Keys</li>
                        <li class="mb-2">Add a webhook endpoint in Stripe → Developers → Webhooks pointing to:<br>
                            <code class="small"><?= APP_URL ?>/api/stripe-webhook.php</code>
                        </li>
                        <li class="mb-2">Copy the webhook signing secret and paste above</li>
                        <li class="mb-2">Enable the Stripe Customer Portal in Stripe → Settings → Customer portal</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
