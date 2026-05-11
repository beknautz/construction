<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$db = get_db();
$id = (int)($_GET['id'] ?? 0);

if (!$id) { header('Location: ' . APP_URL . '/modules/leads/'); exit; }

$lead = $db->prepare('SELECT l.*, CONCAT(l.first_name,\' \',l.last_name) AS full_name FROM leads l WHERE l.id = ?');
$lead->execute([$id]);
$lead = $lead->fetch();

if (!$lead) { header('Location: ' . APP_URL . '/modules/leads/'); exit; }

// Notes
$notes = $db->prepare(
    'SELECT n.*, u.name AS user_name FROM lead_notes n
     LEFT JOIN users u ON u.id = n.user_id
     WHERE n.lead_id = ? ORDER BY n.created_at DESC'
);
$notes->execute([$id]);
$notes = $notes->fetchAll();

// Activity
$activity = $db->prepare(
    'SELECT a.*, u.name AS user_name FROM lead_activity a
     LEFT JOIN users u ON u.id = a.user_id
     WHERE a.lead_id = ? ORDER BY a.created_at DESC LIMIT 20'
);
$activity->execute([$id]);
$activity = $activity->fetchAll();

$page_title     = e($lead['full_name']) . ' — Lead';
$current_module = 'leads';

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

            <!-- Breadcrumb + Header -->
            <div class="page-header">
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 small">
                        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/leads/">Leads</a></li>
                        <li class="breadcrumb-item active"><?= e($lead['full_name']) ?></li>
                    </ol>
                </nav>
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-1">
                    <div>
                        <h1 class="mb-0"><?= e($lead['full_name']) ?></h1>
                        <?php if ($lead['company']): ?>
                            <p class="text-muted mb-0 small"><?= e($lead['company']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <?= status_badge($lead['status']) ?>
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editLeadModal">
                            <i class="bi bi-pencil me-1"></i>Edit
                        </button>
                        <a href="<?= APP_URL ?>/modules/projects/?from_lead=<?= $lead['id'] ?>" class="btn btn-sm btn-accent">
                            <i class="bi bi-building me-1"></i>Convert to Project
                        </a>
                    </div>
                </div>
            </div>

            <div class="row g-3">

                <!-- Left: Details -->
                <div class="col-lg-4">

                    <!-- Contact Info -->
                    <div class="app-card mb-3">
                        <h6 class="fw-bold mb-3"><i class="bi bi-person me-2 text-muted"></i>Contact</h6>
                        <dl class="row mb-0 small">
                            <?php if ($lead['phone']): ?>
                            <dt class="col-5 text-muted">Phone</dt>
                            <dd class="col-7"><a href="tel:<?= e($lead['phone']) ?>"><?= e($lead['phone']) ?></a></dd>
                            <?php endif; ?>
                            <?php if ($lead['email']): ?>
                            <dt class="col-5 text-muted">Email</dt>
                            <dd class="col-7 text-truncate"><a href="mailto:<?= e($lead['email']) ?>"><?= e($lead['email']) ?></a></dd>
                            <?php endif; ?>
                            <?php if ($lead['address']): ?>
                            <dt class="col-5 text-muted">Address</dt>
                            <dd class="col-7"><?= e($lead['address']) ?><?php if ($lead['city']): ?>, <?= e($lead['city']) ?> <?= e($lead['state']) ?> <?= e($lead['zip']) ?><?php endif; ?></dd>
                            <?php endif; ?>
                        </dl>
                    </div>

                    <!-- Project Info -->
                    <div class="app-card mb-3">
                        <h6 class="fw-bold mb-3"><i class="bi bi-building me-2 text-muted"></i>Project Details</h6>
                        <dl class="row mb-0 small">
                            <dt class="col-5 text-muted">Type</dt>
                            <dd class="col-7"><?= e($lead['project_type']) ?></dd>
                            <dt class="col-5 text-muted">Budget</dt>
                            <dd class="col-7"><?= $lead['budget'] ? money($lead['budget']) : '—' ?></dd>
                            <dt class="col-5 text-muted">Source</dt>
                            <dd class="col-7"><?= e($lead['source']) ?></dd>
                            <dt class="col-5 text-muted">Follow Up</dt>
                            <dd class="col-7 <?= ($lead['follow_up_date'] && $lead['follow_up_date'] < date('Y-m-d')) ? 'text-danger fw-semibold' : '' ?>">
                                <?= $lead['follow_up_date'] ? fmt_date($lead['follow_up_date']) : '—' ?>
                            </dd>
                            <dt class="col-5 text-muted">Created</dt>
                            <dd class="col-7"><?= fmt_date($lead['created_at']) ?></dd>
                        </dl>
                    </div>

                    <!-- Inline Status Update -->
                    <div class="app-card mb-3">
                        <h6 class="fw-bold mb-2"><i class="bi bi-arrow-repeat me-2 text-muted"></i>Pipeline Status</h6>
                        <form hx-post="<?= APP_URL ?>/actions/update-lead-status.php"
                              hx-trigger="change"
                              hx-swap="none">
                            <input type="hidden" name="id" value="<?= $lead['id'] ?>">
                            <select name="status" class="form-select">
                                <?php foreach ($statuses as $s): ?>
                                <option value="<?= e($s) ?>" <?= $lead['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="htmx-indicator mt-1 small text-muted">Saving…</div>
                        </form>
                    </div>

                    <!-- Description -->
                    <?php if ($lead['description']): ?>
                    <div class="app-card mb-3">
                        <h6 class="fw-bold mb-2"><i class="bi bi-file-text me-2 text-muted"></i>Description</h6>
                        <p class="small mb-0"><?= nl2br(e($lead['description'])) ?></p>
                    </div>
                    <?php endif; ?>

                </div>

                <!-- Right: Notes + Activity -->
                <div class="col-lg-8">

                    <!-- Add Note -->
                    <div class="app-card mb-3">
                        <h6 class="fw-bold mb-3"><i class="bi bi-chat-left-text me-2 text-muted"></i>Notes</h6>

                        <!-- HTMX note form -->
                        <form hx-post="<?= APP_URL ?>/actions/save-lead-note.php"
                              hx-target="#notes-list"
                              hx-swap="afterbegin"
                              hx-on::after-request="this.reset()">
                            <input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">
                            <div class="d-flex gap-2">
                                <textarea name="note" class="form-control form-control-sm"
                                          rows="2" placeholder="Add a note…" required></textarea>
                                <button type="submit" class="btn btn-sm btn-accent align-self-end px-3">
                                    <i class="bi bi-send"></i>
                                </button>
                            </div>
                        </form>

                        <!-- Notes list -->
                        <div id="notes-list" class="mt-3 d-flex flex-column gap-2">
                            <?php foreach ($notes as $note): ?>
                            <div class="note-item p-3 rounded bg-light border-start border-3 border-warning">
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

                    <!-- Activity Timeline -->
                    <?php if (!empty($activity)): ?>
                    <div class="app-card">
                        <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-muted"></i>Activity</h6>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($activity as $a): ?>
                            <div class="d-flex gap-3 align-items-start">
                                <div class="mt-1"><i class="bi bi-dot text-muted fs-4"></i></div>
                                <div>
                                    <div class="small"><?= e($a['description'] ?: $a['action']) ?></div>
                                    <div class="small text-muted"><?= e($a['user_name'] ?? 'System') ?> · <?= fmt_date($a['created_at'], 'M j, g:ia') ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

        </div><!-- /page-content -->
        <?php include __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<!-- Edit Lead Modal -->
<div class="modal fade" id="editLeadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= APP_URL ?>/actions/save-lead.php" method="POST">
                <input type="hidden" name="id" value="<?= $lead['id'] ?>">
                <input type="hidden" name="redirect" value="<?= APP_URL ?>/modules/leads/detail.php?id=<?= $lead['id'] ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" value="<?= e($lead['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="<?= e($lead['last_name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Phone</label>
                            <input type="tel" name="phone" class="form-control" value="<?= e($lead['phone']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= e($lead['email']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Company</label>
                            <input type="text" name="company" class="form-control" value="<?= e($lead['company']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Budget</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="budget" class="form-control" step="100" min="0" value="<?= e($lead['budget']) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Project Type</label>
                            <select name="project_type" class="form-select">
                                <?php foreach ($types as $t): ?>
                                <option value="<?= e($t) ?>" <?= $lead['project_type'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Source</label>
                            <select name="source" class="form-select">
                                <?php foreach ($sources as $s): ?>
                                <option value="<?= e($s) ?>" <?= $lead['source'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Follow-Up Date</label>
                            <input type="date" name="follow_up_date" class="form-control" value="<?= e($lead['follow_up_date']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Address</label>
                            <input type="text" name="address" class="form-control" value="<?= e($lead['address']) ?>">
                        </div>
                        <div class="col-md-5">
                            <input type="text" name="city" class="form-control" placeholder="City" value="<?= e($lead['city']) ?>">
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="state" class="form-control" placeholder="State" value="<?= e($lead['state']) ?>">
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="zip" class="form-control" placeholder="ZIP" value="<?= e($lead['zip']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Description</label>
                            <textarea name="description" class="form-control" rows="3"><?= e($lead['description']) ?></textarea>
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
