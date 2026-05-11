<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title     = 'Estimates';
$current_module = 'estimates';

$db = get_db();

$project_id = (int)($_GET['project_id'] ?? 0);
$search     = trim($_GET['q']      ?? '');
$status     = trim($_GET['status'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = ITEMS_PER_PAGE;
$offset     = ($page - 1) * $limit;

$where  = ['1=1'];
$params = [];

if ($project_id) { $where[] = 'e.project_id = ?'; $params[] = $project_id; }
if ($search)     { $where[] = '(e.title LIKE ? OR e.client_name LIKE ?)'; $s = "%$search%"; $params = array_merge($params, [$s, $s]); }
if ($status)     { $where[] = 'e.status = ?'; $params[] = $status; }

$whereStr = implode(' AND ', $where);

$total = (int)$db->prepare("SELECT COUNT(*) FROM estimates e WHERE $whereStr")->execute($params) ? 0 : 0;
$countStmt = $db->prepare("SELECT COUNT(*) FROM estimates e WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));

$stmt = $db->prepare(
    "SELECT e.*, p.title AS project_title, u.name AS created_by_name
     FROM estimates e
     LEFT JOIN projects p ON p.id = e.project_id
     LEFT JOIN users u ON u.id = e.created_by
     WHERE $whereStr
     ORDER BY e.created_at DESC
     LIMIT $limit OFFSET $offset"
);
$stmt->execute($params);
$estimates = $stmt->fetchAll();

// Project for prefill in modal
$project = null;
if ($project_id) {
    $ps = $db->prepare('SELECT * FROM projects WHERE id = ?');
    $ps->execute([$project_id]);
    $project = $ps->fetch();
}

$statuses   = ['Draft','Review','Approved','Rejected','Archived'];
$co         = company_settings();

include __DIR__ . '/../../includes/header.php';
?>

<div class="app-wrapper">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../../includes/topbar.php'; ?>
        <div class="page-content">

            <div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h1>Estimates<?= $project ? ' — ' . e($project['title']) : '' ?></h1>
                    <p class="text-muted mb-0 small"><?= number_format($total) ?> total</p>
                </div>
                <button class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#addEstimateModal">
                    <i class="bi bi-plus-lg me-1"></i> New Estimate
                </button>
            </div>

            <!-- Filter -->
            <form method="GET" class="app-card mb-3">
                <?php if ($project_id): ?><input type="hidden" name="project_id" value="<?= $project_id ?>"><?php endif; ?>
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <input type="text" name="q" class="form-control form-control-sm"
                               placeholder="Search title or client…" value="<?= e($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <?php foreach ($statuses as $s): ?>
                            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                        <a href="?" class="btn btn-sm btn-outline-secondary">Clear</a>
                    </div>
                </div>
            </form>

            <!-- Estimates Table -->
            <div class="app-card p-0">
                <?php if (empty($estimates)): ?>
                    <div class="empty-state">
                        <i class="bi bi-calculator"></i>
                        <p>No estimates yet. <a href="#" data-bs-toggle="modal" data-bs-target="#addEstimateModal">Create your first estimate.</a></p>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-app table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Project / Client</th>
                                <th>Markup</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($estimates as $est): ?>
                            <tr>
                                <td>
                                    <a href="detail.php?id=<?= $est['id'] ?>" class="fw-medium text-decoration-none text-dark">
                                        <?= e($est['title']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($est['project_title']): ?>
                                        <a href="<?= APP_URL ?>/modules/projects/detail.php?id=<?= $est['project_id'] ?>" class="small text-decoration-none">
                                            <i class="bi bi-building me-1"></i><?= e($est['project_title']) ?>
                                        </a>
                                    <?php elseif ($est['client_name']): ?>
                                        <span class="small text-muted"><?= e($est['client_name']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="small"><?= $est['markup_pct'] ?>%</span></td>
                                <td><span class="fw-medium"><?= money($est['grand_total']) ?></span></td>
                                <td><?= status_badge($est['status']) ?></td>
                                <td class="small text-muted"><?= fmt_date($est['created_at']) ?></td>
                                <td>
                                    <a href="detail.php?id=<?= $est['id'] ?>" class="btn btn-sm btn-outline-secondary">
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

<!-- New Estimate Modal -->
<div class="modal fade" id="addEstimateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calculator me-2"></i>New Estimate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= APP_URL ?>/actions/save-estimate.php" method="POST">
                <input type="hidden" name="redirect" value="<?= APP_URL ?>/modules/estimates/">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-medium">Estimate Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Johnson Kitchen — Estimate v1">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Link to Project (optional)</label>
                            <select name="project_id" class="form-select">
                                <option value="">— No project —</option>
                                <?php
                                $projs = $db->query("SELECT id, title FROM projects WHERE status NOT IN ('Closed') ORDER BY title")->fetchAll();
                                foreach ($projs as $p):
                                ?>
                                <option value="<?= $p['id'] ?>" <?= ($project_id == $p['id']) ? 'selected' : '' ?>><?= e($p['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Client Name</label>
                            <input type="text" name="client_name" class="form-control"
                                   value="<?= e($project['client_name'] ?? '') ?>" placeholder="If not linked to a project">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Markup %</label>
                            <input type="number" name="markup_pct" class="form-control" step="0.5" min="0" value="<?= $co['default_markup'] ?? 20 ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Tax %</label>
                            <input type="number" name="tax_pct" class="form-control" step="0.5" min="0" value="<?= $co['default_tax'] ?? 8 ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Waste %</label>
                            <input type="number" name="waste_pct" class="form-control" step="0.5" min="0" value="<?= $co['default_waste'] ?? 5 ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent"><i class="bi bi-check-lg me-1"></i>Create Estimate</button>
                </div>
            </form>
        </div>
    </div>
</div>
