<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title     = 'Leads';
$current_module = 'leads';

$db = get_db();

// ---- Filters ----
$search  = trim($_GET['q']      ?? '');
$status  = trim($_GET['status'] ?? '');
$source  = trim($_GET['source'] ?? '');
$type    = trim($_GET['type']   ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = ITEMS_PER_PAGE;
$offset  = ($page - 1) * $limit;

$where  = ['1=1'];
$params = [];

// Tenant scope
if (tid() !== null) { $where[] = 'l.tenant_id = ?'; $params[] = tid(); }

if ($search) {
    $where[]  = '(l.first_name LIKE ? OR l.last_name LIKE ? OR l.email LIKE ? OR l.phone LIKE ? OR l.company LIKE ?)';
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
}
if ($status) { $where[] = 'l.status = ?';       $params[] = $status; }
if ($source) { $where[] = 'l.source = ?';       $params[] = $source; }
if ($type)   { $where[] = 'l.project_type = ?'; $params[] = $type; }

$whereStr = implode(' AND ', $where);

// Total count
$countStmt = $db->prepare("SELECT COUNT(*) FROM leads l WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));

// Leads
$stmt = $db->prepare(
    "SELECT l.*, CONCAT(l.first_name, ' ', l.last_name) AS full_name,
            u.name AS assigned_name
     FROM leads l
     LEFT JOIN users u ON u.id = l.assigned_to
     WHERE $whereStr
     ORDER BY l.created_at DESC
     LIMIT $limit OFFSET $offset"
);
$stmt->execute($params);
$leads = $stmt->fetchAll();

// Pipeline counts for header bar (scoped to tenant)
$pipelineWhere  = tid() !== null ? 'WHERE tenant_id = ?' : '';
$pipelineParams = tid() !== null ? [tid()] : [];
$pipelineStmt   = $db->prepare("SELECT status, COUNT(*) as cnt FROM leads {$pipelineWhere} GROUP BY status");
$pipelineStmt->execute($pipelineParams);
$pipeline = $pipelineStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$statuses = ['New','Contacted','Site Visit Scheduled','Estimate Needed','Proposal Sent','Won','Lost'];
$sources  = ['Google Ads','Website','Referral','Facebook','Phone Call','Repeat Client','Other'];
$types    = ['Remodel','Bathroom','Kitchen','Addition','New Build','Excavation','Other'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="app-wrapper">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include __DIR__ . '/../../includes/topbar.php'; ?>

        <div class="page-content">

            <!-- Page Header -->
            <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h1>Leads</h1>
                    <p class="text-muted mb-0 small"><?= number_format($total) ?> total leads</p>
                </div>
                <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#addLeadModal">
                    <i class="bi bi-plus-lg me-1"></i> Add Lead
                </button>
            </div>

            <!-- Pipeline Bar -->
            <div class="row g-2 mb-3">
                <?php
                $pipeColors = [
                    'New' => 'stat-icon-orange', 'Contacted' => 'stat-icon-blue',
                    'Site Visit Scheduled' => 'stat-icon-teal', 'Estimate Needed' => 'stat-icon-purple',
                    'Proposal Sent' => 'stat-icon-yellow', 'Won' => 'stat-icon-green', 'Lost' => 'stat-icon-red',
                ];
                foreach ($statuses as $s):
                    $cnt = $pipeline[$s] ?? 0;
                    $active = ($status === $s) ? 'border-accent' : '';
                ?>
                <div class="col-6 col-sm-4 col-lg">
                    <a href="?status=<?= urlencode($s) ?>" class="text-decoration-none">
                        <div class="app-card text-center py-2 px-1 <?= $active ?>">
                            <div class="fw-bold fs-5"><?= $cnt ?></div>
                            <div class="small text-muted" style="font-size:.72rem;"><?= e($s) ?></div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Search + Filter -->
            <form method="GET" class="app-card mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <input type="text" name="q" class="form-control form-control-sm"
                               placeholder="Search name, email, phone, company…"
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
                        <select name="source" class="form-select form-select-sm">
                            <option value="">All Sources</option>
                            <?php foreach ($sources as $s): ?>
                            <option value="<?= e($s) ?>" <?= $source === $s ? 'selected' : '' ?>><?= e($s) ?></option>
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
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                        <a href="?" class="btn btn-sm btn-outline-secondary">Clear</a>
                    </div>
                </div>
            </form>

            <!-- Leads Table -->
            <div class="app-card p-0">
                <?php if (empty($leads)): ?>
                    <div class="empty-state">
                        <i class="bi bi-funnel"></i>
                        <p>No leads found. <a href="#" data-bs-toggle="modal" data-bs-target="#addLeadModal">Add your first lead.</a></p>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-app table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Type</th>
                                <th>Source</th>
                                <th>Budget</th>
                                <th>Status</th>
                                <th>Follow Up</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($leads as $lead): ?>
                            <tr>
                                <td>
                                    <a href="detail.php?id=<?= $lead['id'] ?>" class="fw-medium text-decoration-none text-dark">
                                        <?= e($lead['full_name']) ?>
                                    </a>
                                    <?php if ($lead['company']): ?>
                                        <div class="small text-muted"><?= e($lead['company']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($lead['phone']): ?>
                                        <a href="tel:<?= e($lead['phone']) ?>" class="d-block small text-decoration-none">
                                            <i class="bi bi-telephone me-1 text-muted"></i><?= e($lead['phone']) ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($lead['email']): ?>
                                        <a href="mailto:<?= e($lead['email']) ?>" class="d-block small text-decoration-none text-muted">
                                            <?= e($lead['email']) ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td><span class="small"><?= e($lead['project_type']) ?></span></td>
                                <td><span class="small text-muted"><?= e($lead['source']) ?></span></td>
                                <td><?= $lead['budget'] ? money($lead['budget']) : '<span class="text-muted">—</span>' ?></td>
                                <td>
                                    <!-- HTMX inline status update -->
                                    <select class="form-select form-select-sm status-select"
                                            style="min-width:130px;"
                                            hx-post="<?= APP_URL ?>/actions/update-lead-status.php"
                                            hx-trigger="change"
                                            hx-include="this"
                                            hx-swap="none"
                                            name="status"
                                            data-id="<?= $lead['id'] ?>">
                                        <input type="hidden" name="id" value="<?= $lead['id'] ?>">
                                        <?php foreach ($statuses as $s): ?>
                                        <option value="<?= e($s) ?>" <?= $lead['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="small <?= ($lead['follow_up_date'] && $lead['follow_up_date'] < date('Y-m-d')) ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                    <?= $lead['follow_up_date'] ? fmt_date($lead['follow_up_date']) : '—' ?>
                                </td>
                                <td>
                                    <a href="detail.php?id=<?= $lead['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                    <small class="text-muted">Page <?= $page ?> of <?= $pages ?></small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php for ($i = 1; $i <= $pages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

        </div><!-- /page-content -->
        <?php include __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<!-- Add Lead Modal -->
<div class="modal fade" id="addLeadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-funnel me-2"></i>Add New Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= APP_URL ?>/actions/save-lead.php" method="POST">
                <input type="hidden" name="redirect" value="<?= APP_URL ?>/modules/leads/">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Last Name</label>
                            <input type="text" name="last_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Phone</label>
                            <input type="tel" name="phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Company</label>
                            <input type="text" name="company" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Budget</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="budget" class="form-control" step="100" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Project Type</label>
                            <select name="project_type" class="form-select">
                                <?php foreach ($types as $t): ?>
                                <option value="<?= e($t) ?>"><?= e($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Source</label>
                            <select name="source" class="form-select">
                                <?php foreach ($sources as $s): ?>
                                <option value="<?= e($s) ?>"><?= e($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Follow-Up Date</label>
                            <input type="date" name="follow_up_date" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Address</label>
                            <input type="text" name="address" class="form-control" placeholder="Street address">
                        </div>
                        <div class="col-md-5">
                            <input type="text" name="city" class="form-control" placeholder="City">
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="state" class="form-control" placeholder="State">
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="zip" class="form-control" placeholder="ZIP">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Description / Initial Notes</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="What does the client need?"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent"><i class="bi bi-check-lg me-1"></i>Save Lead</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.border-accent { border: 2px solid var(--accent) !important; }
.stat-icon-red { background: rgba(220,53,69,.12); color: #dc3545; }
</style>

<script>
// HTMX status select needs the hidden id field to travel with it
document.querySelectorAll('.status-select').forEach(function(sel) {
    sel.addEventListener('htmx:beforeRequest', function() {
        // ensure id is included via a hidden sibling input already in the DOM
    });
});
</script>
