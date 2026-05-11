<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title     = 'Projects';
$current_module = 'projects';

$db = get_db();

// ---- Convert lead to project (from leads detail page) ----
$from_lead = (int)($_GET['from_lead'] ?? 0);
$prefill   = [];
if ($from_lead) {
    $ls = $db->prepare('SELECT * FROM leads WHERE id = ?');
    $ls->execute([$from_lead]);
    $prefill = $ls->fetch() ?: [];
}

// ---- Filters ----
$search  = trim($_GET['q']      ?? '');
$status  = trim($_GET['status'] ?? '');
$type    = trim($_GET['type']   ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = ITEMS_PER_PAGE;
$offset  = ($page - 1) * $limit;

$where  = ['1=1'];
$params = [];

// Tenant scope
if (tid() !== null) { $where[] = 'p.tenant_id = ?'; $params[] = tid(); }

if ($search) {
    $where[]  = '(p.title LIKE ? OR p.client_name LIKE ? OR p.client_email LIKE ? OR p.client_phone LIKE ?)';
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s]);
}
if ($status) { $where[] = 'p.status = ?';       $params[] = $status; }
if ($type)   { $where[] = 'p.project_type = ?'; $params[] = $type; }

$whereStr = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM projects p WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));

$stmt = $db->prepare(
    "SELECT p.*, u.name AS assigned_name
     FROM projects p
     LEFT JOIN users u ON u.id = p.assigned_to
     WHERE $whereStr
     ORDER BY p.created_at DESC
     LIMIT $limit OFFSET $offset"
);
$stmt->execute($params);
$projects = $stmt->fetchAll();

// Status counts (tenant-scoped)
$plWhere  = tid() !== null ? 'WHERE tenant_id = ?' : '';
$plParams = tid() !== null ? [tid()] : [];
$plStmt   = $db->prepare("SELECT status, COUNT(*) as cnt FROM projects {$plWhere} GROUP BY status");
$plStmt->execute($plParams);
$pipeline = $plStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Revenue totals
$contracted_total = db_sum('projects', 'contract_amount',
    "status IN ('Contracted','In Progress','Waiting','Completed')");

$statuses = ['Planning','Estimating','Proposal','Contracted','In Progress','Waiting','Completed','Closed'];
$types    = ['Remodel','Bathroom','Kitchen','Addition','New Build','Excavation','Other'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="app-wrapper">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include __DIR__ . '/../../includes/topbar.php'; ?>

        <div class="page-content">

            <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h1>Projects</h1>
                    <p class="text-muted mb-0 small"><?= number_format($total) ?> total · <?= money($contracted_total) ?> contracted</p>
                </div>
                <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                    <i class="bi bi-plus-lg me-1"></i> New Project
                </button>
            </div>

            <!-- Status Pipeline Bar -->
            <div class="row g-2 mb-3">
                <?php
                $pColors = [
                    'Planning'=>'stat-icon-blue','Estimating'=>'stat-icon-teal',
                    'Proposal'=>'stat-icon-yellow','Contracted'=>'stat-icon-orange',
                    'In Progress'=>'stat-icon-green','Waiting'=>'stat-icon-purple',
                    'Completed'=>'stat-icon-green','Closed'=>'stat-icon-red',
                ];
                foreach ($statuses as $s):
                    $cnt = $pipeline[$s] ?? 0;
                    $active = ($status === $s) ? 'border-accent' : '';
                ?>
                <div class="col-6 col-sm-3 col-lg">
                    <a href="?status=<?= urlencode($s) ?>" class="text-decoration-none">
                        <div class="app-card text-center py-2 px-1 <?= $active ?>">
                            <div class="fw-bold fs-5"><?= $cnt ?></div>
                            <div class="text-muted" style="font-size:.7rem;"><?= e($s) ?></div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Search + Filter -->
            <form method="GET" class="app-card mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <input type="text" name="q" class="form-control form-control-sm"
                               placeholder="Search title, client name, email, phone…"
                               value="<?= e($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <?php foreach ($statuses as $s): ?>
                            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="type" class="form-select form-select-sm">
                            <option value="">All Types</option>
                            <?php foreach ($types as $t): ?>
                            <option value="<?= e($t) ?>" <?= $type === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                        <a href="?" class="btn btn-sm btn-outline-secondary">Clear</a>
                    </div>
                </div>
            </form>

            <!-- Projects Table -->
            <div class="app-card p-0">
                <?php if (empty($projects)): ?>
                    <div class="empty-state">
                        <i class="bi bi-building"></i>
                        <p>No projects yet. <a href="#" data-bs-toggle="modal" data-bs-target="#addProjectModal">Create your first project.</a></p>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-app table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Client</th>
                                <th>Type</th>
                                <th>Budget</th>
                                <th>Start Date</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($projects as $p): ?>
                            <tr>
                                <td>
                                    <a href="detail.php?id=<?= $p['id'] ?>" class="fw-medium text-decoration-none text-dark">
                                        <?= e($p['title']) ?>
                                    </a>
                                    <?php if ($p['address']): ?>
                                        <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($p['city'] . ', ' . $p['state']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small fw-medium"><?= e($p['client_name']) ?></div>
                                    <?php if ($p['client_phone']): ?>
                                        <a href="tel:<?= e($p['client_phone']) ?>" class="small text-muted text-decoration-none"><?= e($p['client_phone']) ?></a>
                                    <?php endif; ?>
                                </td>
                                <td><span class="small"><?= e($p['project_type']) ?></span></td>
                                <td>
                                    <?php if ($p['contract_amount']): ?>
                                        <div class="small fw-medium"><?= money($p['contract_amount']) ?></div>
                                        <div class="small text-muted">contracted</div>
                                    <?php elseif ($p['budget']): ?>
                                        <div class="small text-muted"><?= money($p['budget']) ?> est.</div>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= $p['start_date'] ? fmt_date($p['start_date']) : '—' ?></td>
                                <td>
                                    <select class="form-select form-select-sm"
                                            style="min-width:120px;"
                                            hx-post="<?= APP_URL ?>/actions/update-project-status.php"
                                            hx-trigger="change"
                                            hx-swap="none"
                                            name="status">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <?php foreach ($statuses as $s): ?>
                                        <option value="<?= e($s) ?>" data-id="<?= $p['id'] ?>" <?= $p['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <a href="detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                    <small class="text-muted">Page <?= $page ?> of <?= $pages ?></small>
                    <nav><ul class="pagination pagination-sm mb-0">
                        <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul></nav>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

        </div>
        <?php include __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<!-- Add / Convert Project Modal -->
<div class="modal fade" id="addProjectModal" tabindex="-1" <?= $from_lead ? 'data-bs-backdrop="static"' : '' ?>>
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-building me-2"></i>
                    <?= $from_lead ? 'Convert Lead to Project' : 'New Project' ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= APP_URL ?>/actions/save-project.php" method="POST">
                <input type="hidden" name="lead_id" value="<?= $from_lead ?>">
                <input type="hidden" name="redirect" value="<?= APP_URL ?>/modules/projects/">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-medium">Project Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required
                                   placeholder="e.g. Johnson Kitchen Remodel"
                                   value="<?= $prefill ? e(($prefill['first_name'] ?? '') . ' ' . ($prefill['last_name'] ?? '') . ' — ' . ($prefill['project_type'] ?? '')) : '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Client Name <span class="text-danger">*</span></label>
                            <input type="text" name="client_name" class="form-control" required
                                   value="<?= $prefill ? e(($prefill['first_name'] ?? '') . ' ' . ($prefill['last_name'] ?? '')) : '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Client Phone</label>
                            <input type="tel" name="client_phone" class="form-control"
                                   value="<?= e($prefill['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Client Email</label>
                            <input type="email" name="client_email" class="form-control"
                                   value="<?= e($prefill['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Client Company</label>
                            <input type="text" name="client_company" class="form-control"
                                   value="<?= e($prefill['company'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Project Type</label>
                            <select name="project_type" class="form-select">
                                <?php foreach ($types as $t): ?>
                                <option value="<?= e($t) ?>" <?= ($prefill['project_type'] ?? '') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach ($statuses as $s): ?>
                                <option value="<?= e($s) ?>"><?= e($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Est. Start Date</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Budget Estimate</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="budget" class="form-control" step="100" min="0"
                                       value="<?= e($prefill['budget'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Contract Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="contract_amount" class="form-control" step="100" min="0">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Address</label>
                            <input type="text" name="address" class="form-control" placeholder="Street address"
                                   value="<?= e($prefill['address'] ?? '') ?>">
                        </div>
                        <div class="col-md-5">
                            <input type="text" name="city" class="form-control" placeholder="City"
                                   value="<?= e($prefill['city'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="state" class="form-control" placeholder="State"
                                   value="<?= e($prefill['state'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="zip" class="form-control" placeholder="ZIP"
                                   value="<?= e($prefill['zip'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Scope Summary</label>
                            <textarea name="scope_summary" class="form-control" rows="3"
                                      placeholder="Brief description of work to be done…"><?= e($prefill['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent"><i class="bi bi-check-lg me-1"></i>Save Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.border-accent { border: 2px solid var(--accent) !important; }
.stat-icon-red { background: rgba(220,53,69,.12); color: #dc3545; }
</style>

<?php if ($from_lead): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById('addProjectModal')).show();
});
</script>
<?php endif; ?>
