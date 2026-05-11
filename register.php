<?php
/**
 * register.php
 * Tenant self-registration — creates a tenant + admin user, then redirects to billing.
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/src/StripeService.php';

if (is_logged_in()) {
    header('Location: ' . APP_URL . '/modules/dashboard/');
    exit;
}

$error   = '';
$success = '';
$plans   = [];

$db = get_db();
$ps = $db->query('SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order');
if ($ps) $plans = $ps->fetchAll();

// Stripe publishable key for display
$pkRow = $db->prepare("SELECT setting_value FROM ai_settings WHERE setting_key = 'stripe_publishable_key' LIMIT 1");
$pkRow->execute();
$stripePk = (string)($pkRow->fetchColumn() ?: '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company  = trim($_POST['company_name'] ?? '');
    $name     = trim($_POST['name']         ?? '');
    $email    = trim($_POST['email']        ?? '');
    $password = trim($_POST['password']     ?? '');
    $planSlug = trim($_POST['plan']         ?? 'starter');

    // Validation
    if (!$company || !$name || !$email || !$password) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        // Check duplicate email
        $dup = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $dup->execute([$email]);
        if ($dup->fetch()) {
            $error = 'An account with that email already exists. <a href="' . APP_URL . '/login.php">Sign in</a>';
        }
    }

    if (!$error) {
        // Look up plan
        $planStmt = $db->prepare('SELECT * FROM subscription_plans WHERE slug = ? AND is_active = 1 LIMIT 1');
        $planStmt->execute([$planSlug]);
        $plan = $planStmt->fetch();
        if (!$plan) {
            $planStmt = $db->prepare('SELECT * FROM subscription_plans WHERE sort_order = 1 LIMIT 1');
            $planStmt->execute();
            $plan = $planStmt->fetch();
        }

        $trialDays = 14;
        $tdRow = $db->prepare("SELECT setting_value FROM ai_settings WHERE setting_key = 'trial_days' LIMIT 1");
        $tdRow->execute();
        $trialDays = max(1, (int)($tdRow->fetchColumn() ?: 14));

        $trialEndsAt = date('Y-m-d H:i:s', strtotime("+{$trialDays} days"));

        try {
            $db->beginTransaction();

            // Create tenant
            $db->prepare(
                'INSERT INTO tenants (company_name, email, plan_id, status, trial_ends_at)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([$company, $email, $plan['id'], 'trial', $trialEndsAt]);
            $tenantId = (int)$db->lastInsertId();

            // Create admin user for this tenant
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $db->prepare(
                'INSERT INTO users (tenant_id, name, email, password, role, is_active)
                 VALUES (?, ?, ?, ?, ?, 1)'
            )->execute([$tenantId, $name, $email, $hash, 'admin']);
            $userId = (int)$db->lastInsertId();

            // Create company_settings row for this tenant
            $db->prepare(
                'INSERT INTO company_settings (tenant_id, company_name) VALUES (?, ?)'
            )->execute([$tenantId, $company]);

            // Create Stripe customer (if key configured)
            $skStmt = $db->prepare("SELECT setting_value FROM ai_settings WHERE setting_key = 'stripe_secret_key' LIMIT 1");
            $skStmt->execute();
            $sk = (string)($skStmt->fetchColumn() ?: '');

            if ($sk) {
                $stripe   = new StripeService($sk);
                $customer = $stripe->createCustomer($email, $name, ['tenant_id' => $tenantId]);
                $db->prepare('UPDATE tenants SET stripe_customer_id = ? WHERE id = ?')
                   ->execute([$customer['id'], $tenantId]);
            }

            $db->commit();

            // Auto-login
            $result = login_user($email, $password);
            if ($result === true) {
                set_flash('success', "Welcome to Construction OS! You have a {$trialDays}-day free trial.");
                header('Location: ' . APP_URL . '/modules/dashboard/');
                exit;
            }

            header('Location: ' . APP_URL . '/login.php');
            exit;

        } catch (Throwable $ex) {
            $db->rollBack();
            $error = 'Registration failed. Please try again.';
            error_log('Registration error: ' . $ex->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Start Free Trial — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
    <style>
        body { background: #f0f4f8; min-height: 100vh; }
        .register-card { max-width: 780px; margin: 40px auto; padding: 0 16px 40px; }
        .plan-card { cursor: pointer; border: 2px solid transparent; transition: all .2s; }
        .plan-card:hover, .plan-card.selected { border-color: #e67e22; background: #fff9f5; }
        .plan-card.selected .plan-badge { display: block !important; }
    </style>
</head>
<body>

<div class="register-card">
    <div class="text-center mb-4">
        <div class="login-logo mb-2" style="font-size:2.5rem;"><i class="bi bi-hammer text-warning"></i></div>
        <h1 class="fw-bold fs-3">Start Your Free Trial</h1>
        <p class="text-muted">14 days free · No credit card required</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="row g-3 mb-4">
            <?php foreach ($plans as $plan): ?>
            <?php $sel = (($_POST['plan'] ?? 'starter') === $plan['slug']); ?>
            <div class="col-md-4">
                <label class="plan-card card h-100 p-3 <?= $sel ? 'selected' : '' ?>"
                       for="plan_<?= e($plan['slug']) ?>">
                    <input type="radio" name="plan" id="plan_<?= e($plan['slug']) ?>"
                           value="<?= e($plan['slug']) ?>"
                           class="d-none" <?= $sel ? 'checked' : '' ?>>
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <strong><?= e($plan['name']) ?></strong>
                        <span class="plan-badge badge bg-warning text-dark <?= $sel ? '' : 'd-none' ?>">Selected</span>
                    </div>
                    <div class="fs-4 fw-bold text-accent"><?= money($plan['price_monthly']) ?><span class="fs-6 fw-normal text-muted">/mo</span></div>
                    <ul class="small text-muted mt-2 mb-0 ps-3">
                        <li><?= number_format($plan['ai_calls_limit']) ?> AI calls/month</li>
                        <li>Up to <?= $plan['projects_limit'] >= 999 ? 'unlimited' : $plan['projects_limit'] ?> projects</li>
                        <li><?= $plan['users_limit'] ?> team member<?= $plan['users_limit'] > 1 ? 's' : '' ?></li>
                    </ul>
                </label>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="card p-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-medium small">Company Name *</label>
                    <input type="text" class="form-control" name="company_name"
                           value="<?= e($_POST['company_name'] ?? '') ?>"
                           placeholder="Acme Construction" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium small">Your Name *</label>
                    <input type="text" class="form-control" name="name"
                           value="<?= e($_POST['name'] ?? '') ?>"
                           placeholder="Jane Smith" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium small">Work Email *</label>
                    <input type="email" class="form-control" name="email"
                           value="<?= e($_POST['email'] ?? '') ?>"
                           placeholder="jane@company.com" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium small">Password *</label>
                    <input type="password" class="form-control" name="password"
                           placeholder="Min 8 characters" minlength="8" required>
                </div>
            </div>

            <div class="mt-4 d-grid">
                <button type="submit" class="btn btn-accent btn-lg fw-semibold">
                    <i class="bi bi-rocket-takeoff me-2"></i>Start Free Trial
                </button>
            </div>
            <p class="text-center text-muted small mt-3 mb-0">
                Already have an account? <a href="<?= APP_URL ?>/login.php">Sign in</a>
            </p>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Plan card click to select radio
document.querySelectorAll('.plan-card').forEach(function(card) {
    card.addEventListener('click', function() {
        document.querySelectorAll('.plan-card').forEach(function(c) {
            c.classList.remove('selected');
            c.querySelector('.plan-badge').classList.add('d-none');
        });
        card.classList.add('selected');
        card.querySelector('.plan-badge').classList.remove('d-none');
        card.querySelector('input[type=radio]').checked = true;
    });
});
</script>
</body>
</html>
