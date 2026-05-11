<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title     = 'Dashboard';
$current_module = 'dashboard';

// ---- Dashboard stats ----
$stats = [
    'new_leads'         => db_count('leads',     "status = 'New'"),
    'active_estimates'  => db_count('estimates', "status = 'Draft'"),
    'open_projects'     => db_count('projects',  "status IN ('Planning','Estimating','Proposal','Contracted','In Progress','Waiting')"),
    'pending_proposals' => db_count('proposals', "status IN ('Draft','Sent','Viewed')"),
    'upcoming_tasks'    => db_count('schedule_tasks', "status = 'Pending' AND (due_date IS NULL OR due_date >= CURDATE())"),
    'pipeline_value'    => db_sum('proposals',   'total', "status IN ('Draft','Sent','Viewed')"),
];

include __DIR__ . '/../../includes/header.php';
?>

<div class="app-wrapper">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include __DIR__ . '/../../includes/topbar.php'; ?>

        <div class="page-content">

            <!-- Page Header -->
            <div class="page-header d-flex align-items-center justify-content-between">
                <div>
                    <h1>Dashboard</h1>
                    <p class="text-muted mb-0 small">Welcome back, <?= e(current_user()['name']) ?>. Here's what's happening.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= APP_URL ?>/modules/leads/" class="btn btn-accent">
                        <i class="bi bi-plus-lg me-1"></i> Add Lead
                    </a>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="row g-3 mb-4">

                <div class="col-6 col-md-4 col-xl-2">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-orange">
                            <i class="bi bi-funnel-fill"></i>
                        </div>
                        <div>
                            <div class="stat-value"><?= $stats['new_leads'] ?></div>
                            <div class="stat-label">New Leads</div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-4 col-xl-2">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-blue">
                            <i class="bi bi-calculator-fill"></i>
                        </div>
                        <div>
                            <div class="stat-value"><?= $stats['active_estimates'] ?></div>
                            <div class="stat-label">Estimates</div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-4 col-xl-2">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-green">
                            <i class="bi bi-building-fill"></i>
                        </div>
                        <div>
                            <div class="stat-value"><?= $stats['open_projects'] ?></div>
                            <div class="stat-label">Open Projects</div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-4 col-xl-2">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-yellow">
                            <i class="bi bi-file-earmark-text-fill"></i>
                        </div>
                        <div>
                            <div class="stat-value"><?= $stats['pending_proposals'] ?></div>
                            <div class="stat-label">Proposals Out</div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-4 col-xl-2">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-purple">
                            <i class="bi bi-calendar3"></i>
                        </div>
                        <div>
                            <div class="stat-value"><?= $stats['upcoming_tasks'] ?></div>
                            <div class="stat-label">Upcoming Tasks</div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-4 col-xl-2">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon-teal">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <div>
                            <div class="stat-value" style="font-size:1.1rem;"><?= money($stats['pipeline_value']) ?></div>
                            <div class="stat-label">Pipeline</div>
                        </div>
                    </div>
                </div>

            </div><!-- /stat cards -->

            <!-- Content Row -->
            <div class="row g-3">

                <!-- Recent Activity -->
                <div class="col-lg-8">
                    <div class="app-card h-100">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-muted"></i>Recent Activity</h6>
                        </div>
                        <?php
                        try {
                            $activity = get_db()->query(
                                'SELECT a.*, u.name as user_name
                                 FROM activity_log a
                                 LEFT JOIN users u ON u.id = a.user_id
                                 ORDER BY a.created_at DESC LIMIT 10'
                            )->fetchAll();
                        } catch (Throwable) {
                            $activity = [];
                        }
                        ?>
                        <?php if (empty($activity)): ?>
                            <div class="empty-state">
                                <i class="bi bi-activity"></i>
                                <p class="small">No activity yet. Start by adding a lead or creating a project.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-app table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Action</th>
                                            <th>Module</th>
                                            <th>User</th>
                                            <th>Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activity as $row): ?>
                                        <tr>
                                            <td><?= e($row['description'] ?: $row['action']) ?></td>
                                            <td><span class="badge bg-secondary-subtle text-secondary"><?= e($row['module']) ?></span></td>
                                            <td><?= e($row['user_name'] ?? '—') ?></td>
                                            <td class="text-muted small"><?= fmt_date($row['created_at'], 'M j, g:ia') ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions + Getting Started -->
                <div class="col-lg-4">
                    <div class="app-card mb-3">
                        <h6 class="fw-bold mb-3"><i class="bi bi-lightning-fill me-2 text-warning"></i>Quick Actions</h6>
                        <div class="d-grid gap-2">
                            <a href="<?= APP_URL ?>/modules/leads/" class="btn btn-outline-primary btn-sm text-start">
                                <i class="bi bi-funnel me-2"></i>New Lead
                            </a>
                            <a href="<?= APP_URL ?>/modules/projects/" class="btn btn-outline-success btn-sm text-start">
                                <i class="bi bi-building me-2"></i>New Project
                            </a>
                            <a href="<?= APP_URL ?>/modules/estimates/" class="btn btn-outline-secondary btn-sm text-start">
                                <i class="bi bi-calculator me-2"></i>New Estimate
                            </a>
                            <a href="<?= APP_URL ?>/modules/proposals/" class="btn btn-outline-warning btn-sm text-start">
                                <i class="bi bi-file-earmark-text me-2"></i>New Proposal
                            </a>
                            <a href="<?= APP_URL ?>/modules/photos/" class="btn btn-outline-info btn-sm text-start">
                                <i class="bi bi-images me-2"></i>Upload Photos
                            </a>
                        </div>
                    </div>

                    <!-- Phase Roadmap teaser -->
                    <div class="app-card">
                        <h6 class="fw-bold mb-3"><i class="bi bi-map me-2 text-muted"></i>Platform Roadmap</h6>
                        <div class="d-flex flex-column gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success">Live</span>
                                <small class="fw-medium">Phase 1 — Estimating, CRM, Leads, Proposals</small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-warning text-dark">Soon</span>
                                <small class="text-muted">Phase 2 — Takeoffs, Suppliers, Scheduling</small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-secondary">Planned</span>
                                <small class="text-muted">Phase 3 — Permits, Field Docs, Analytics</small>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /content row -->

        </div><!-- /page-content -->
        <?php include __DIR__ . '/../../includes/footer.php'; ?>
    </div><!-- /main-content -->
</div><!-- /app-wrapper -->
