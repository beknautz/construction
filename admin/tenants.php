<?php
/**
 * admin/tenants.php
 * Full tenant list + individual tenant management.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_superadmin();

$db = get_db();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action']    ?? '';
    $tenantId = (int)($_POST['tenant_id'] ?? 0);

    if ($tenantId) {
        switch ($action) {
            case 'suspend':
                $db->prepare("UPDATE tenants SET status = 'suspended' WHERE id = ?")->execute([$tenantId]);
                set_flash('success', 'Tenant suspended.');
                break;
            case 'activate':
                $db->prepare("UPDATE tenants SET status = 'active' WHERE id = ?")->execute([$tenantId]);
                set_flash('success', 'Tenant activated.');
                break;
            case 'reset_trial':
                $db->prepare("UPDATE tenants SET status = 'trial', trial_ends_at = DATE_ADD(NOW(), INTERVAL 14 DAY) WHERE id = ?")
                   ->execute([$tenantId]);
                set_flash('success', 'Trial reset to 14 days.');
                break;
            case 'reset_ai':
                $db->prepare('UPDATE tenants SET ai_calls_used = 0, ai_calls_reset_at = NOW() WHERE id = ?')
                   ->execute([$tenantId]);
                set_flash('success', 'AI usage counter reset.');
                break;
            case 'change_plan':
                $planId = (int)($_POST['plan_id'] ?? 0);
                if ($planId) {
                    $db->prepare('UPDATE tenants SET plan_id = ? WHERE id = ?')->execute([$planId, $tenantId]);
                    set_flash('success', 'Plan updated.');
                }
                break;
        }
    }
    header('Location: ' . APP_URL . '/admin/tenants.php' . ($tenantId ? "?id={$tenantId}" : ''));
    exit;
}

// Single tenant view
$viewId = (int)($_GET['id'] ?? 0);
$tenant = null;
if ($viewId) {
    $ts = $db->prepare('SELECT t.*, p.name AS plan_name, p.price_monthly
                        FROM tenants t JOIN subscription_plans p ON p.id = t.plan_id
                        WHERE t.id = ? LIMIT 1');
    $ts->execute([$viewId]);
    $tenant = $ts->fetch();
}

// Full tenant list (paginated)
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;
$search  = trim($_GET['q'] ?? '');

$whereClause = $search ? 'WHERE t.company_name LIKE ? OR t.email LIKE ?' : '';
$params      = $search ? ["%{$search}%", "%{$search}%"] : [];

$total = (int)$db->prepare("SELECT COUNT(*) FROM tenants t {$whereClause}")->execute($params) ? 0 : 0;
$countStmt = $db->prepare("SELECT COUNT(*) FROM tenants t {$whereClause}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$listStmt = $db->prepare(
    "SELECT t.*, p.name AS plan_name FROM tenants t
     JOIN subscription_plans p ON p.id = t.plan_id
     {$whereClause} ORDER BY t.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
);
$listStmt->execute($params);
$tenants = $listStmt->fetchAll();

$plans = $db->query('SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order')->fetchAll();

$flash     = get_flash();
$pageTitle = $tenant ? ('Tenant: ' . $tenant['company_name']) : 'Manage Tenants';
require_once __DIR__ . '/../includes/layout-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/">Admin</a></li>
                <?php if ($tenant): ?>
                <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/tenants.php">Tenants</a></li>
                <li class="breadcrumb-item active"><?= e($tenant['company_name']) ?></li>
                <?php else: ?>
                <li class="breadcrumb-item active">Tenants</li>
                <?php endif; ?>
            </ol>
        </nav>
        <h1 class="page-title mb-0"><?= e($pageTitle) ?></h1>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
    <?= e($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($tenant): ?>
<!-- Single Tenant Detail -->
<div class="row g-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold"><?= e($tenant['company_name']) ?></h5>
                <p class="text-muted mb-1"><?= e($tenant['email']) ?></p>
                <p class="text-muted mb-3 small"><?= e($tenant['phone'] ?? '') ?></p>

                <?php
                $sc = match($tenant['status']) {
                    'active'    => 'success',
                    'trial'     => 'warning',
                    'past_due'  => 'danger',
                    'suspended' => 'dark',
                    default     => 'secondary',
                };
                ?>
                <div class="mb-3">
                    <span class="badge bg-<?= $sc ?> me-2"><?= e(ucfirst($tenant['status'])) ?></span>
                    <span class="badge bg-light text-dark border"><?= e($tenant['plan_name']) ?></span>
                </div>

                <dl class="row small mb-0">
                    <dt class="col-6 text-muted">Joined</dt>
                    <dd class="col-6"><?= fmt_date($tenant['created_at']) ?></dd>
                    <?php if ($tenant['trial_ends_at']): ?>
                    <dt class="col-6 text-muted">Trial ends</dt>
                    <dd class="col-6"><?= fmt_date($tenant['trial_ends_at']) ?></dd>
                    <?php endif; ?>
                    <?php if ($tenant['current_period_end']): ?>
                    <dt class="col-6 text-muted">Next renewal</dt>
                    <dd class="col-6"><?= fmt_date($tenant['current_period_end']) ?></dd>
                    <?php endif; ?>
                    <dt class="col-6 text-muted">AI calls used</dt>
                    <dd class="col-6"><?= number_format($tenant['ai_calls_used']) ?></dd>
                    <?php if ($tenant['stripe_customer_id']): ?>
                    <dt class="col-6 text-muted">Stripe customer</dt>
                    <dd class="col-6"><code class="small"><?= e(substr($tenant['stripe_customer_id'], 0, 20)) ?>…</code></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <!-- Actions -->
        <div class="card mt-3">
            <div class="card-header fw-semibold">Actions</div>
            <div class="card-body d-grid gap-2">
                <?php if ($tenant['status'] !== 'suspended'): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="suspend">
                    <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                    <button class="btn btn-outline-danger btn-sm w-100"
                            onclick="return confirm('Suspend this tenant?')">
                        <i class="bi bi-slash-circle me-1"></i>Suspend Account
                    </button>
                </form>
                <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="activate">
                    <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                    <button class="btn btn-outline-success btn-sm w-100">
                        <i class="bi bi-check-circle me-1"></i>Activate Account
                    </button>
                </form>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="reset_trial">
                    <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                    <button class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Trial (14 days)
                    </button>
                </form>

                <form method="POST">
                    <input type="hidden" name="action" value="reset_ai">
                    <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                    <button class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-robot me-1"></i>Reset AI Counter
                    </button>
                </form>

                <form method="POST" class="d-flex gap-2">
                    <input type="hidden" name="action" value="change_plan">
                    <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                    <select name="plan_id" class="form-select form-select-sm">
                        <?php foreach ($plans as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $p['id'] == $tenant['plan_id'] ? 'selected' : '' ?>>
                            <?= e($p['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-accent btn-sm text-nowrap">Change</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <?php
        // Tenant users
        $users = $db->prepare('SELECT * FROM users WHERE tenant_id = ? ORDER BY created_at');
        $users->execute([$tenant['id']]);
        $users = $users->fetchAll();

        // Tenant AI usage
        $aiLog = $db->prepare(
            'SELECT * FROM tenant_ai_usage WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 20'
        );
        $aiLog->execute([$tenant['id']]);
        $aiLog = $aiLog->fetchAll();
        ?>

        <div class="card mb-4">
            <div class="card-header fw-semibold">Users (<?= count($users) ?>)</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Name</th><th>Email</th><th>Role</th><th>Last Login</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= e($u['name']) ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= e($u['role']) ?></span></td>
                        <td class="text-muted small"><?= $u['last_login'] ? fmt_date($u['last_login'], 'M j, Y g:i a') : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold">Recent AI Usage</div>
            <?php if ($aiLog): ?>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Date</th><th>Module</th><th>Model</th><th class="text-end">Tokens</th><th class="text-end">Billed</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($aiLog as $r): ?>
                    <tr>
                        <td class="small"><?= fmt_date($r['created_at'], 'M j g:i a') ?></td>
                        <td><?= e($r['module']) ?></td>
                        <td class="small text-muted"><?= e($r['model']) ?></td>
                        <td class="text-end small"><?= number_format($r['input_tokens'] + $r['output_tokens']) ?></td>
                        <td class="text-end"><?= money($r['billed_usd']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-body text-center text-muted py-3">No AI usage recorded</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Tenant List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <form class="d-flex gap-2" method="GET">
            <input type="text" name="q" class="form-control form-control-sm" style="width:220px;"
                   value="<?= e($search) ?>" placeholder="Search company or email…">
            <button class="btn btn-sm btn-outline-secondary">Search</button>
            <?php if ($search): ?>
            <a href="<?= APP_URL ?>/admin/tenants.php" class="btn btn-sm btn-outline-danger">Clear</a>
            <?php endif; ?>
        </form>
        <span class="text-muted small"><?= number_format($total) ?> tenant<?= $total !== 1 ? 's' : '' ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Company</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th class="text-end">AI Used</th>
                    <th>Trial Ends</th>
                    <th>Joined</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tenants as $t): ?>
            <?php
            $sc = match($t['status']) {
                'active'    => 'success',
                'trial'     => 'warning',
                'past_due'  => 'danger',
                'suspended' => 'dark',
                default     => 'secondary',
            };
            ?>
            <tr>
                <td>
                    <div class="fw-medium"><?= e($t['company_name']) ?></div>
                    <div class="text-muted small"><?= e($t['email']) ?></div>
                </td>
                <td><?= e($t['plan_name']) ?></td>
                <td><span class="badge bg-<?= $sc ?>"><?= e(ucfirst($t['status'])) ?></span></td>
                <td class="text-end"><?= number_format($t['ai_calls_used']) ?></td>
                <td class="small text-muted"><?= $t['trial_ends_at'] ? fmt_date($t['trial_ends_at']) : '—' ?></td>
                <td class="small text-muted"><?= fmt_date($t['created_at']) ?></td>
                <td>
                    <a href="?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-eye"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$tenants): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No tenants found</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total > $perPage): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <span class="text-muted small">Page <?= $page ?> of <?= ceil($total / $perPage) ?></span>
        <div class="d-flex gap-2">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page-1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-sm btn-outline-secondary">Prev</a>
            <?php endif; ?>
            <?php if ($page < ceil($total / $perPage)): ?>
            <a href="?page=<?= $page+1 ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-sm btn-outline-secondary">Next</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
