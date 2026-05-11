<?php
/**
 * admin/index.php
 * Super-admin dashboard — platform overview.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_superadmin();

$db = get_db();

// Platform stats
$totalTenants  = (int)$db->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
$activeTenants = (int)$db->query("SELECT COUNT(*) FROM tenants WHERE status = 'active'")->fetchColumn();
$trialTenants  = (int)$db->query("SELECT COUNT(*) FROM tenants WHERE status = 'trial'")->fetchColumn();
$mrrRow        = $db->query("SELECT COALESCE(SUM(p.price_monthly),0) FROM tenants t JOIN subscription_plans p ON p.id = t.plan_id WHERE t.status = 'active'")->fetchColumn();
$mrr           = (float)$mrrRow;

$totalAiCalls = (int)$db->query("SELECT COALESCE(SUM(ai_calls_used),0) FROM tenants")->fetchColumn();
$totalBilled  = (float)$db->query("SELECT COALESCE(SUM(billed_usd),0) FROM tenant_ai_usage")->fetchColumn();

// Recent tenants
$recent = $db->query(
    'SELECT t.*, p.name AS plan_name FROM tenants t
     JOIN subscription_plans p ON p.id = t.plan_id
     ORDER BY t.created_at DESC LIMIT 10'
)->fetchAll();

// AI usage last 30 days
$dailyUsage = $db->query(
    'SELECT DATE(created_at) AS day, COUNT(*) AS calls, SUM(billed_usd) AS revenue
     FROM tenant_ai_usage
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY day ORDER BY day DESC LIMIT 30'
)->fetchAll();

$flash     = get_flash();
$pageTitle = 'Platform Admin';

require_once __DIR__ . '/../includes/layout-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title mb-1">Platform Admin</h1>
        <p class="text-muted mb-0">Super-admin view — all tenants</p>
    </div>
    <a href="<?= APP_URL ?>/admin/tenants.php" class="btn btn-accent btn-sm">
        <i class="bi bi-people me-1"></i>Manage Tenants
    </a>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
    <?= e($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- KPI row -->
<div class="row g-3 mb-4">
    <?php
    $kpis = [
        ['icon'=>'bi-buildings', 'color'=>'text-primary',   'value'=> number_format($totalTenants),  'label'=>'Total Tenants'],
        ['icon'=>'bi-check-circle', 'color'=>'text-success','value'=> number_format($activeTenants), 'label'=>'Active Subscriptions'],
        ['icon'=>'bi-hourglass',  'color'=>'text-warning',  'value'=> number_format($trialTenants),  'label'=>'On Free Trial'],
        ['icon'=>'bi-currency-dollar','color'=>'text-success','value'=>money($mrr),                  'label'=>'MRR (Subscriptions)'],
        ['icon'=>'bi-robot',      'color'=>'text-info',     'value'=> number_format($totalAiCalls),  'label'=>'Total AI Calls Used'],
        ['icon'=>'bi-graph-up-arrow','color'=>'text-accent','value'=> money($totalBilled),           'label'=>'Total AI Revenue'],
    ];
    foreach ($kpis as $k): ?>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card text-center p-3 h-100">
            <i class="bi <?= $k['icon'] ?> fs-4 <?= $k['color'] ?> mb-1"></i>
            <div class="fw-bold fs-5"><?= $k['value'] ?></div>
            <div class="text-muted" style="font-size:.72rem"><?= $k['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Recent Tenants -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center fw-semibold">
                Recent Tenants
                <a href="<?= APP_URL ?>/admin/tenants.php" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Company</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>AI Used</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent as $t): ?>
                    <tr>
                        <td>
                            <a href="<?= APP_URL ?>/admin/tenants.php?id=<?= $t['id'] ?>" class="fw-medium text-decoration-none">
                                <?= e($t['company_name']) ?>
                            </a>
                            <div class="text-muted" style="font-size:.8rem"><?= e($t['email']) ?></div>
                        </td>
                        <td><?= e($t['plan_name']) ?></td>
                        <td>
                            <?php
                            $sc = match($t['status']) {
                                'active'   => 'success',
                                'trial'    => 'warning',
                                'past_due' => 'danger',
                                default    => 'secondary',
                            };
                            ?>
                            <span class="badge bg-<?= $sc ?>"><?= e(ucfirst($t['status'])) ?></span>
                        </td>
                        <td><?= number_format($t['ai_calls_used']) ?></td>
                        <td class="text-muted small"><?= fmt_date($t['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No tenants yet</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- AI Usage Log -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header fw-semibold">Platform AI Usage — Last 30 Days</div>
            <?php if ($dailyUsage): ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Date</th><th class="text-end">Calls</th><th class="text-end">Revenue</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($dailyUsage as $row): ?>
                    <tr>
                        <td><?= fmt_date($row['day']) ?></td>
                        <td class="text-end"><?= number_format($row['calls']) ?></td>
                        <td class="text-end"><?= money($row['revenue']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-body text-center text-muted py-4">No AI usage yet</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
