<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . APP_URL . '/modules/projects/'); exit; }

$proj = $db->prepare('SELECT * FROM projects WHERE id = ?');
$proj->execute([$id]);
$proj = $proj->fetch();
if (!$proj) { header('Location: ' . APP_URL . '/modules/projects/'); exit; }

$notes = $db->prepare(
    'SELECT n.*, u.name AS user_name FROM project_notes n
     LEFT JOIN users u ON u.id = n.user_id
     WHERE n.project_id = ? ORDER BY n.created_at DESC'
);
$notes->execute([$id]);
$notes = $notes->fetchAll();

$photos = $db->prepare(
    'SELECT * FROM project_photos WHERE project_id = ? ORDER BY created_at DESC'
);
$photos->execute([$id]);
$photos = $photos->fetchAll();

$files = $db->prepare(
    'SELECT f.*, u.name AS user_name FROM project_files f
     LEFT JOIN users u ON u.id = f.user_id
     WHERE f.project_id = ? ORDER BY f.created_at DESC'
);
$files->execute([$id]);
$files = $files->fetchAll();

$activity = $db->prepare(
    'SELECT a.*, u.name AS user_name FROM project_activity a
     LEFT JOIN users u ON u.id = a.user_id
     WHERE a.project_id = ? ORDER BY a.created_at DESC LIMIT 25'
);
$activity->execute([$id]);
$activity = $activity->fetchAll();

$page_title     = e($proj['title']) . ' — Project';
$current_module = 'projects';

$statuses = ['Planning','Estimating','Proposal','Contracted','In Progress','Waiting','Completed','Closed'];
$types    = ['Remodel','Bathroom','Kitchen','Addition','New Build','Excavation','Other'];
$photo_cats = ['Before','During','After','Inspection','Damage','Materials','Other'];

include __DIR__ . '/../../includes/header.php';
?>

<div class="app-wrapper">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include __DIR__ . '/../../includes/topbar.php'; ?>

        <div class="page-content">

            <!-- Header -->
            <div class="page-header">
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 small">
                        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/projects/">Projects</a></li>
                        <li class="breadcrumb-item active"><?= e($proj['title']) ?></li>
                    </ol>
                </nav>
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mt-1">
                    <div>
                        <h1 class="mb-0"><?= e($proj['title']) ?></h1>
                        <p class="text-muted mb-0 small">
                            <?= e($proj['project_type']) ?>
                            <?php if ($proj['address']): ?> · <?= e($proj['city'] . ', ' . $proj['state']) ?><?php endif; ?>
                        </p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <?= status_badge($proj['status']) ?>
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editProjectModal">
                            <i class="bi bi-pencil me-1"></i>Edit
                        </button>
                        <a href="<?= APP_URL ?>/modules/estimates/?project_id=<?= $proj['id'] ?>" class="btn btn-sm btn-accent">
                            <i class="bi bi-calculator me-1"></i>New Estimate
                        </a>
                    </div>
                </div>
            </div>

            <!-- Stat Row -->
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-green"><i class="bi bi-currency-dollar"></i></div>
                        <div>
                            <div class="stat-value" style="font-size:1.1rem;"><?= $proj['contract_amount'] ? money($proj['contract_amount']) : '—' ?></div>
                            <div class="stat-label">Contract</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-blue"><i class="bi bi-piggy-bank"></i></div>
                        <div>
                            <div class="stat-value" style="font-size:1.1rem;"><?= $proj['budget'] ? money($proj['budget']) : '—' ?></div>
                            <div class="stat-label">Budget Est.</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-orange"><i class="bi bi-calendar-event"></i></div>
                        <div>
                            <div class="stat-value" style="font-size:1rem;"><?= $proj['start_date'] ? fmt_date($proj['start_date'], 'M j, Y') : '—' ?></div>
                            <div class="stat-label">Start Date</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-purple"><i class="bi bi-images"></i></div>
                        <div>
                            <div class="stat-value"><?= count($photos) ?></div>
                            <div class="stat-label">Photos</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">

                <!-- Left Column -->
                <div class="col-lg-4">

                    <!-- Client Info -->
                    <div class="app-card mb-3">
                        <h6 class="fw-bold mb-3"><i class="bi bi-person me-2 text-muted"></i>Client</h6>
                        <dl class="row mb-0 small">
                            <dt class="col-5 text-muted">Name</dt>
                            <dd class="col-7 fw-medium"><?= e($proj['client_name']) ?></dd>
                            <?php if ($proj['client_company']): ?>
                            <dt class="col-5 text-muted">Company</dt>
                            <dd class="col-7"><?= e($proj['client_company']) ?></dd>
                            <?php endif; ?>
                            <?php if ($proj['client_phone']): ?>
                            <dt class="col-5 text-muted">Phone</dt>
                            <dd class="col-7"><a href="tel:<?= e($proj['client_phone']) ?>"><?= e($proj['client_phone']) ?></a></dd>
                            <?php endif; ?>
                            <?php if ($proj['client_email']): ?>
                            <dt class="col-5 text-muted">Email</dt>
                            <dd class="col-7 text-truncate"><a href="mailto:<?= e($proj['client_email']) ?>"><?= e($proj['client_email']) ?></a></dd>
                            <?php endif; ?>
                        </dl>
                    </div>

                    <!-- Project Address -->
                    <?php if ($proj['address']): ?>
                    <div class="app-card mb-3">
                        <h6 class="fw-bold mb-2"><i class="bi bi-geo-alt me-2 text-muted"></i>Site Address</h6>
                        <p class="small mb-0">
                            <?= e($proj['address']) ?><br>
                            <?= e($proj['city']) ?>, <?= e($proj['state']) ?> <?= e($proj['zip']) ?>
                        </p>
                        <a href="https://maps.google.com/?q=<?= urlencode($proj['address'] . ' ' . $proj['city'] . ' ' . $proj['state']) ?>"
                           target="_blank" class="btn btn-sm btn-outline-secondary mt-2">
                            <i class="bi bi-map me-1"></i>Map It
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- Status -->
                    <div class="app-card mb-3">
                        <h6 class="fw-bold mb-2"><i class="bi bi-arrow-repeat me-2 text-muted"></i>Status</h6>
                        <form hx-post="<?= APP_URL ?>/actions/update-project-status.php"
                              hx-trigger="change" hx-swap="none">
                            <input type="hidden" name="id" value="<?= $proj['id'] ?>">
                            <select name="status" class="form-select">
                                <?php foreach ($statuses as $s): ?>
                                <option value="<?= e($s) ?>" <?= $proj['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="htmx-indicator mt-1 small text-muted">Saving…</div>
                        </form>
                    </div>

                    <!-- Scope -->
                    <?php if ($proj['scope_summary']): ?>
                    <div class="app-card mb-3">
                        <h6 class="fw-bold mb-2"><i class="bi bi-file-text me-2 text-muted"></i>Scope Summary</h6>
                        <p class="small mb-0"><?= nl2br(e($proj['scope_summary'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Quick Links -->
                    <div class="app-card">
                        <h6 class="fw-bold mb-2"><i class="bi bi-lightning me-2 text-muted"></i>Quick Actions</h6>
                        <div class="d-grid gap-2">
                            <a href="<?= APP_URL ?>/modules/estimates/?project_id=<?= $proj['id'] ?>" class="btn btn-sm btn-outline-secondary text-start">
                                <i class="bi bi-calculator me-2"></i>View Estimates
                            </a>
                            <a href="<?= APP_URL ?>/modules/proposals/?project_id=<?= $proj['id'] ?>" class="btn btn-sm btn-outline-secondary text-start">
                                <i class="bi bi-file-earmark-text me-2"></i>View Proposals
                            </a>
                            <a href="<?= APP_URL ?>/modules/change-orders/?project_id=<?= $proj['id'] ?>" class="btn btn-sm btn-outline-secondary text-start">
                                <i class="bi bi-arrow-repeat me-2"></i>Change Orders
                            </a>
                        </div>
                    </div>

                </div>

                <!-- Right Column -->
                <div class="col-lg-8">

                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-3" id="projectTabs">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-notes">
                                <i class="bi bi-chat-left-text me-1"></i>Notes
                                <span class="badge bg-secondary ms-1"><?= count($notes) ?></span>
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-photos">
                                <i class="bi bi-images me-1"></i>Photos
                                <span class="badge bg-secondary ms-1"><?= count($photos) ?></span>
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-files">
                                <i class="bi bi-paperclip me-1"></i>Files
                                <span class="badge bg-secondary ms-1"><?= count($files) ?></span>
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-activity">
                                <i class="bi bi-clock-history me-1"></i>Activity
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">

                        <!-- Notes Tab -->
                        <div class="tab-pane fade show active" id="tab-notes">
                            <div class="app-card">
                                <form hx-post="<?= APP_URL ?>/actions/save-project-note.php"
                                      hx-target="#project-notes-list"
                                      hx-swap="afterbegin"
                                      hx-on::after-request="this.reset()">
                                    <input type="hidden" name="project_id" value="<?= $proj['id'] ?>">
                                    <div class="d-flex gap-2 mb-3">
                                        <textarea name="note" class="form-control form-control-sm"
                                                  rows="2" placeholder="Add a note…" required></textarea>
                                        <button type="submit" class="btn btn-sm btn-accent align-self-end px-3">
                                            <i class="bi bi-send"></i>
                                        </button>
                                    </div>
                                </form>
                                <div id="project-notes-list" class="d-flex flex-column gap-2">
                                    <?php foreach ($notes as $note): ?>
                                    <div class="note-item p-3 rounded bg-light border-start border-3 border-primary">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="small fw-medium"><?= e($note['user_name'] ?? 'System') ?></span>
                                            <span class="small text-muted"><?= fmt_date($note['created_at'], 'M j, g:ia') ?></span>
                                        </div>
                                        <p class="mb-0 small"><?= nl2br(e($note['note'])) ?></p>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($notes)): ?>
                                    <p class="text-muted small mb-0">No notes yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Photos Tab -->
                        <div class="tab-pane fade" id="tab-photos">
                            <div class="app-card">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h6 class="fw-bold mb-0">Project Photos</h6>
                                    <button class="btn btn-sm btn-accent" data-bs-toggle="modal" data-bs-target="#uploadPhotoModal">
                                        <i class="bi bi-upload me-1"></i>Upload
                                    </button>
                                </div>
                                <?php if (empty($photos)): ?>
                                    <div class="empty-state py-4">
                                        <i class="bi bi-images"></i>
                                        <p class="small">No photos yet. Upload before, during, and after shots.</p>
                                    </div>
                                <?php else: ?>
                                <div class="row g-2" id="photo-gallery">
                                    <?php foreach ($photos as $ph): ?>
                                    <div class="col-6 col-md-4">
                                        <div class="position-relative photo-thumb rounded overflow-hidden">
                                            <img src="<?= APP_URL ?>/assets/uploads/projects/<?= e($ph['filename']) ?>"
                                                 class="w-100 rounded" style="height:140px;object-fit:cover;"
                                                 alt="<?= e($ph['caption'] ?: $ph['category']) ?>">
                                            <div class="photo-overlay p-2">
                                                <span class="badge bg-dark bg-opacity-75"><?= e($ph['category']) ?></span>
                                                <?php if ($ph['caption']): ?>
                                                <div class="small text-white mt-1"><?= e($ph['caption']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Files Tab -->
                        <div class="tab-pane fade" id="tab-files">
                            <div class="app-card">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h6 class="fw-bold mb-0">Documents</h6>
                                    <button class="btn btn-sm btn-accent" data-bs-toggle="modal" data-bs-target="#uploadFileModal">
                                        <i class="bi bi-upload me-1"></i>Upload
                                    </button>
                                </div>
                                <?php if (empty($files)): ?>
                                    <div class="empty-state py-4">
                                        <i class="bi bi-paperclip"></i>
                                        <p class="small">No files uploaded yet.</p>
                                    </div>
                                <?php else: ?>
                                <div class="d-flex flex-column gap-2">
                                    <?php foreach ($files as $f): ?>
                                    <div class="d-flex align-items-center gap-3 p-2 border rounded">
                                        <i class="bi bi-file-earmark-text fs-4 text-muted"></i>
                                        <div class="flex-grow-1 min-width-0">
                                            <div class="small fw-medium text-truncate"><?= e($f['original_name']) ?></div>
                                            <div class="small text-muted"><?= e($f['user_name'] ?? '') ?> · <?= fmt_date($f['created_at']) ?></div>
                                        </div>
                                        <a href="<?= APP_URL ?>/assets/uploads/projects/<?= e($f['filename']) ?>"
                                           class="btn btn-sm btn-outline-secondary" download>
                                            <i class="bi bi-download"></i>
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Activity Tab -->
                        <div class="tab-pane fade" id="tab-activity">
                            <div class="app-card">
                                <?php if (empty($activity)): ?>
                                    <div class="empty-state py-4"><i class="bi bi-clock-history"></i><p class="small">No activity yet.</p></div>
                                <?php else: ?>
                                <div class="d-flex flex-column gap-2">
                                    <?php foreach ($activity as $a): ?>
                                    <div class="d-flex gap-3 align-items-start">
                                        <i class="bi bi-dot text-muted fs-4 mt-1"></i>
                                        <div>
                                            <div class="small"><?= e($a['description'] ?: $a['action']) ?></div>
                                            <div class="small text-muted"><?= e($a['user_name'] ?? 'System') ?> · <?= fmt_date($a['created_at'], 'M j, g:ia') ?></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div><!-- /tab-content -->
                </div><!-- /col -->
            </div><!-- /row -->
        </div><!-- /page-content -->
        <?php include __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<!-- Edit Project Modal -->
<div class="modal fade" id="editProjectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= APP_URL ?>/actions/save-project.php" method="POST">
                <input type="hidden" name="id" value="<?= $proj['id'] ?>">
                <input type="hidden" name="redirect" value="<?= APP_URL ?>/modules/projects/detail.php?id=<?= $proj['id'] ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-medium">Project Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" value="<?= e($proj['title']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Client Name <span class="text-danger">*</span></label>
                            <input type="text" name="client_name" class="form-control" value="<?= e($proj['client_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Client Phone</label>
                            <input type="tel" name="client_phone" class="form-control" value="<?= e($proj['client_phone']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Client Email</label>
                            <input type="email" name="client_email" class="form-control" value="<?= e($proj['client_email']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Client Company</label>
                            <input type="text" name="client_company" class="form-control" value="<?= e($proj['client_company']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Project Type</label>
                            <select name="project_type" class="form-select">
                                <?php foreach ($types as $t): ?>
                                <option value="<?= e($t) ?>" <?= $proj['project_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach ($statuses as $s): ?>
                                <option value="<?= e($s) ?>" <?= $proj['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Est. Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?= e($proj['start_date']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Est. End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?= e($proj['end_date']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Budget Estimate</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="budget" class="form-control" step="100" value="<?= e($proj['budget']) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Contract Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="contract_amount" class="form-control" step="100" value="<?= e($proj['contract_amount']) ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Address</label>
                            <input type="text" name="address" class="form-control" value="<?= e($proj['address']) ?>">
                        </div>
                        <div class="col-md-5"><input type="text" name="city" class="form-control" placeholder="City" value="<?= e($proj['city']) ?>"></div>
                        <div class="col-md-3"><input type="text" name="state" class="form-control" placeholder="State" value="<?= e($proj['state']) ?>"></div>
                        <div class="col-md-4"><input type="text" name="zip" class="form-control" placeholder="ZIP" value="<?= e($proj['zip']) ?>"></div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Scope Summary</label>
                            <textarea name="scope_summary" class="form-control" rows="3"><?= e($proj['scope_summary']) ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Photo Modal -->
<div class="modal fade" id="uploadPhotoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-images me-2"></i>Upload Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= APP_URL ?>/actions/upload-photo.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="project_id" value="<?= $proj['id'] ?>">
                <input type="hidden" name="redirect" value="<?= APP_URL ?>/modules/projects/detail.php?id=<?= $proj['id'] ?>#tab-photos">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Photo <span class="text-danger">*</span></label>
                        <input type="file" name="photo" class="form-control" accept="image/*" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Category</label>
                        <select name="category" class="form-select">
                            <?php foreach ($photo_cats as $c): ?>
                            <option value="<?= e($c) ?>"><?= e($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small fw-medium">Caption</label>
                        <input type="text" name="caption" class="form-control" placeholder="Optional caption">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent"><i class="bi bi-upload me-1"></i>Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.photo-thumb { cursor: pointer; }
.photo-overlay {
    position: absolute; bottom: 0; left: 0; right: 0;
    background: linear-gradient(transparent, rgba(0,0,0,.6));
}
</style>
