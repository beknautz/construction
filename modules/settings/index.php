<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$db  = get_db();
$tid = tid();

// Load company settings for this tenant
$coStmt = $db->prepare('SELECT * FROM company_settings WHERE ' . ($tid !== null ? 'tenant_id = ?' : 'id = 1') . ' LIMIT 1');
$coStmt->execute($tid !== null ? [$tid] : []);
$co = $coStmt->fetch();

// Load AI settings
$aiRows = $db->query("SELECT setting_key, setting_value FROM ai_settings WHERE setting_key IN ('anthropic_api_key','anthropic_model')")->fetchAll();
$aiSettings = [];
foreach ($aiRows as $r) { $aiSettings[$r['setting_key']] = $r['setting_value']; }

$flash          = get_flash();
$page_title     = 'Settings';
$current_module = 'settings';

include __DIR__ . '/../../includes/header.php';
?>
<div class="app-wrapper">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../../includes/topbar.php'; ?>
        <div class="page-content">

            <div class="page-header">
                <h1>Settings</h1>
                <p class="text-muted small mb-0">Manage your company info, estimate defaults, and labor rates</p>
            </div>

            <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                <?= e($flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="settingsTabs">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-company">Company Info</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-rates">Estimate Defaults</a></li>
                <?php if ($tid === null): ?>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-ai">AI Settings</a></li>
                <?php endif; ?>
            </ul>

            <div class="tab-content">

                <!-- Company Info -->
                <div class="tab-pane fade show active" id="tab-company">
                    <div class="card" style="max-width:700px;">
                        <div class="card-body">
                            <form action="<?= APP_URL ?>/actions/save-settings.php" method="POST">
                                <input type="hidden" name="tab" value="company">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label fw-medium small">Company Name</label>
                                        <input type="text" name="company_name" class="form-control"
                                               value="<?= e($co['company_name'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-medium small">Phone</label>
                                        <input type="text" name="phone" class="form-control"
                                               value="<?= e($co['phone'] ?? '') ?>" placeholder="(555) 555-5555">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-medium small">Email</label>
                                        <input type="email" name="email" class="form-control"
                                               value="<?= e($co['email'] ?? '') ?>" placeholder="info@company.com">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-medium small">Address</label>
                                        <input type="text" name="address" class="form-control"
                                               value="<?= e($co['address'] ?? '') ?>" placeholder="123 Main St">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label fw-medium small">City</label>
                                        <input type="text" name="city" class="form-control"
                                               value="<?= e($co['city'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-medium small">State</label>
                                        <input type="text" name="state" class="form-control"
                                               value="<?= e($co['state'] ?? '') ?>" maxlength="2" placeholder="TX">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-medium small">ZIP</label>
                                        <input type="text" name="zip" class="form-control"
                                               value="<?= e($co['zip'] ?? '') ?>" placeholder="75001">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-medium small">Website</label>
                                        <input type="text" name="website" class="form-control"
                                               value="<?= e($co['website'] ?? '') ?>" placeholder="https://...">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-medium small">License Number</label>
                                        <input type="text" name="license_number" class="form-control"
                                               value="<?= e($co['license_number'] ?? '') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-medium small">Insurance Info</label>
                                        <input type="text" name="insurance_info" class="form-control"
                                               value="<?= e($co['insurance_info'] ?? '') ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-medium small">Default Proposal Terms</label>
                                        <textarea name="proposal_terms" class="form-control" rows="4"><?= e($co['proposal_terms'] ?? '') ?></textarea>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" class="btn btn-accent">
                                        <i class="bi bi-save me-1"></i>Save Company Info
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Estimate Defaults -->
                <div class="tab-pane fade" id="tab-rates">
                    <div class="card" style="max-width:700px;">
                        <div class="card-body">
                            <p class="text-muted small mb-4">
                                These rates are used when Claude generates AI estimates, so the dollar amounts
                                reflect your actual cost structure — not generic national averages.
                                They also pre-fill new estimate markup/tax/waste settings.
                            </p>
                            <form action="<?= APP_URL ?>/actions/save-settings.php" method="POST">
                                <input type="hidden" name="tab" value="rates">

                                <h6 class="fw-bold mb-3 border-bottom pb-2">
                                    <i class="bi bi-percent text-muted me-2"></i>Estimate Percentages
                                </h6>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-4">
                                        <label class="form-label fw-medium small">Default Markup %</label>
                                        <div class="input-group">
                                            <input type="number" name="default_markup" class="form-control"
                                                   step="0.5" min="0" max="200"
                                                   value="<?= (float)($co['default_markup'] ?? 20) ?>">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <div class="form-text">Applied to subtotal + waste</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-medium small">Default Tax %</label>
                                        <div class="input-group">
                                            <input type="number" name="default_tax" class="form-control"
                                                   step="0.25" min="0" max="30"
                                                   value="<?= (float)($co['default_tax'] ?? 8) ?>">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-medium small">Default Waste %</label>
                                        <div class="input-group">
                                            <input type="number" name="default_waste" class="form-control"
                                                   step="0.5" min="0" max="50"
                                                   value="<?= (float)($co['default_waste'] ?? 5) ?>">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <div class="form-text">Material overage buffer</div>
                                    </div>
                                </div>

                                <h6 class="fw-bold mb-3 border-bottom pb-2">
                                    <i class="bi bi-person-workspace text-muted me-2"></i>Labor Rates (per hour)
                                </h6>
                                <p class="text-muted small mb-3">Claude uses these rates to calculate realistic labor costs for each trade.</p>
                                <div class="row g-3 mb-4">
                                    <?php
                                    $rateFields = [
                                        'labor_rate_general'     => 'General Laborer',
                                        'labor_rate_carpenter'   => 'Carpenter / Framer',
                                        'labor_rate_electrician' => 'Electrician',
                                        'labor_rate_plumber'     => 'Plumber',
                                        'labor_rate_hvac'        => 'HVAC Technician',
                                        'labor_rate_painter'     => 'Painter',
                                        'labor_rate_equipment'   => 'Equipment Operator',
                                    ];
                                    foreach ($rateFields as $field => $label):
                                    ?>
                                    <div class="col-md-6">
                                        <label class="form-label fw-medium small"><?= e($label) ?></label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" name="<?= e($field) ?>" class="form-control"
                                                   step="1" min="0" max="500"
                                                   value="<?= (float)($co[$field] ?? 0) ?>"
                                                   placeholder="0.00">
                                            <span class="input-group-text">/hr</span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="alert alert-info-subtle border border-info-subtle small mb-4 d-flex gap-2">
                                    <i class="bi bi-info-circle-fill text-info flex-shrink-0 mt-1"></i>
                                    <div>
                                        <strong>How this works:</strong> When you click "Ask Claude" on an estimate,
                                        these rates are sent to the AI so it calculates labor based on your actual
                                        costs — not national averages. You can still adjust any line item after
                                        Claude generates the estimate.
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-accent">
                                    <i class="bi bi-save me-1"></i>Save Rate Defaults
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <?php if ($tid === null): ?>
                <!-- AI Settings (super-admin only) -->
                <div class="tab-pane fade" id="tab-ai">
                    <div class="card" style="max-width:600px;">
                        <div class="card-body">
                            <form action="<?= APP_URL ?>/actions/save-settings.php" method="POST">
                                <input type="hidden" name="tab" value="ai">
                                <div class="mb-3">
                                    <label class="form-label fw-medium small">Anthropic API Key</label>
                                    <input type="password" name="anthropic_api_key" class="form-control font-monospace"
                                           placeholder="sk-ant-… (leave blank to keep current)" autocomplete="off">
                                    <div class="form-text">
                                        Get from <a href="https://console.anthropic.com" target="_blank" rel="noopener">console.anthropic.com</a>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-medium small">Model</label>
                                    <select name="anthropic_model" class="form-select">
                                        <?php
                                        $models = ['claude-haiku-4-5-20251001','claude-sonnet-4-5','claude-opus-4-5'];
                                        $cur    = $aiSettings['anthropic_model'] ?? 'claude-sonnet-4-5';
                                        foreach ($models as $m): ?>
                                        <option value="<?= e($m) ?>" <?= $cur === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-accent">
                                    <i class="bi bi-save me-1"></i>Save AI Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- /tab-content -->
        </div>
        <?php include __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
