<?php
/**
 * billing/index.php
 * Tenant billing dashboard — shows current plan, AI usage, upgrade options.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$db     = get_db();
$tid    = current_tenant_id();
$tenant = $_SESSION['tenant'] ?? null;

// Super-admin has no billing
if ($tid === null) {
    header('Location: ' . APP_URL . '/admin/');
    exit;
}

// Reload fresh tenant data
$ts = $db->prepare('SELECT t.*, p.name AS plan_name, p.price_monthly,
                           p.ai_calls_limit, p.projects_limit, p.users_limit,
                           p.stripe_price_id
                    FROM tenants t JOIN subscription_plans p ON p.id = t.plan_id
                    WHERE t.id = ? LIMIT 1');
$ts->execute([$tid]);
$tenant = $ts->fetch();

// AI usage history (last 30 days)
$usage = $db->prepare(
    'SELECT DATE(created_at) AS day, COUNT(*) AS calls, SUM(billed_usd) AS revenue
     FROM tenant_ai_usage
     WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY day ORDER BY day DESC LIMIT 30'
);
$usage->execute([$tid]);
$usageRows = $usage->fetchAll();

// All plans for upgrade picker
$plans = $db->query('SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order')->fetchAll();

$flash = get_flash();

$pageTitle = 'Billing & Plan';
require_once __DIR__ . '/../includes/layout-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title mb-1">Billing &amp; Plan</h1>
        <p class="text-muted mb-0"><?= e($tenant['company_name']) ?></p>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
    <?= e($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Current Plan Card -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-subtitle text-muted mb-1">Current Plan</h6>
                <h3 class="fw-bold mb-0"><?= e($tenant['plan_name']) ?></h3>
                <p class="text-muted mb-3"><?= money($tenant['price_monthly']) ?>/month</p>

                <?php
                $statusClass = match($tenant['status']) {
                    'active'    => 'success',
                    'trial'     => 'warning',
                    'past_due'  => 'danger',
                    'canceled'  => 'secondary',
                    default     => 'secondary',
                };
                ?>
                <span class="badge bg-<?= $statusClass ?> mb-3">
                    <?= e(ucfirst($tenant['status'])) ?>
                    <?php if ($tenant['status'] === 'trial' && $tenant['trial_ends_at']): ?>
                        — expires <?= fmt_date($tenant['trial_ends_at']) ?>
                    <?php endif; ?>
                </span>

                <?php if ($tenant['current_period_end']): ?>
                <p class="small text-muted mb-0">
                    Next renewal: <?= fmt_date($tenant['current_period_end']) ?>
                </p>
                <?php endif; ?>

                <?php if ($tenant['stripe_subscription_id']): ?>
                <form action="<?= APP_URL ?>/billing/portal.php" method="POST" class="mt-3">
                    <button class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-credit-card me-1"></i>Manage Payment Method
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-subtitle text-muted mb-3">AI Usage This Period</h6>
                <?php
                $used  = (int)$tenant['ai_calls_used'];
                $limit = (int)$tenant['ai_calls_limit'];
                $pct   = $limit > 0 ? min(100, round($used / $limit * 100)) : 0;
                $barClass = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success');
                ?>
                <div class="d-flex justify-content-between align-items-baseline mb-1">
                    <span class="fs-4 fw-bold"><?= number_format($used) ?></span>
                    <span class="text-muted small">of <?= number_format($limit) ?> calls</span>
                </div>
                <div class="progress mb-3" style="height:10px;">
                    <div class="progress-bar <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
                </div>
                <?php if ($pct >= 90): ?>
                <div class="alert alert-warning py-2 small mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    You're almost out of AI calls. Consider upgrading.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Plan Upgrade -->
<div class="card mb-4">
    <div class="card-header fw-semibold">Change Plan</div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($plans as $plan): ?>
            <?php $isCurrent = ($plan['name'] === $tenant['plan_name']); ?>
            <div class="col-md-4">
                <div class="card border-<?= $isCurrent ? 'warning' : 'light' ?> h-100">
                    <div class="card-body text-center">
                        <h5 class="fw-bold mb-0"><?= e($plan['name']) ?></h5>
                        <div class="fs-3 fw-bold my-2"><?= money($plan['price_monthly']) ?><span class="fs-6 fw-normal text-muted">/mo</span></div>
                        <ul class="list-unstyled small text-muted mb-3">
                            <li><i class="bi bi-robot me-1"></i><?= number_format($plan['ai_calls_limit']) ?> AI calls</li>
                            <li><i class="bi bi-folder me-1"></i><?= $plan['projects_limit'] >= 999 ? 'Unlimited' : $plan['projects_limit'] ?> projects</li>
                            <li><i class="bi bi-people me-1"></i><?= $plan['users_limit'] ?> users</li>
                        </ul>
                        <?php if ($isCurrent): ?>
                        <span class="badge bg-warning text-dark">Current Plan</span>
                        <?php elseif ($plan['stripe_price_id']): ?>
                        <form action="<?= APP_URL ?>/billing/checkout.php" method="POST">
                            <input type="hidden" name="price_id" value="<?= e($plan['stripe_price_id']) ?>">
                            <input type="hidden" name="plan_id"  value="<?= (int)$plan['id'] ?>">
                            <button class="btn btn-accent btn-sm w-100">
                                <?= (float)$plan['price_monthly'] > (float)$tenant['price_monthly'] ? 'Upgrade' : 'Downgrade' ?>
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="text-muted small">Coming soon</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Usage History -->
<?php if ($usageRows): ?>
<div class="card">
    <div class="card-header fw-semibold">AI Usage — Last 30 Days</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th class="text-end">Calls</th>
                    <th class="text-end">Cost (billed)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($usageRows as $row): ?>
            <tr>
                <td><?= fmt_date($row['day']) ?></td>
                <td class="text-end"><?= number_format($row['calls']) ?></td>
                <td class="text-end"><?= money($row['revenue']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
