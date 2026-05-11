<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$db = get_db();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . APP_URL . '/modules/estimates/'); exit; }

$est = $db->prepare('SELECT e.*, p.title AS project_title FROM estimates e LEFT JOIN projects p ON p.id = e.project_id WHERE e.id = ?');
$est->execute([$id]);
$est = $est->fetch();
if (!$est) { header('Location: ' . APP_URL . '/modules/estimates/'); exit; }

// Sections + line items
$sections = $db->prepare(
    'SELECT s.*, COALESCE(SUM(li.line_total),0) AS section_total
     FROM estimate_sections s
     LEFT JOIN estimate_line_items li ON li.section_id = s.id
     WHERE s.estimate_id = ?
     GROUP BY s.id
     ORDER BY s.sort_order, s.id'
);
$sections->execute([$id]);
$sections = $sections->fetchAll();

$items_by_section = [];
$all_items = $db->prepare('SELECT * FROM estimate_line_items WHERE estimate_id = ? ORDER BY sort_order, id');
$all_items->execute([$id]);
foreach ($all_items->fetchAll() as $item) {
    $items_by_section[$item['section_id']][] = $item;
}

$page_title     = 'Estimate — ' . e($est['title']);
$current_module = 'estimates';

$categories = ['Demo','Framing','Concrete','Plumbing','Electrical','HVAC','Drywall',
               'Tile','Flooring','Cabinets','Finish Carpentry','Paint','Excavation','Permits','Other'];
$statuses   = ['Draft','Review','Approved','Rejected','Archived'];

// Load tenant labor rates for AI prompt context
$coRates = [];
$tid = tid();
$coStmt = $db->prepare('SELECT * FROM company_settings WHERE ' . ($tid !== null ? 'tenant_id = ?' : 'id = 1') . ' LIMIT 1');
$coStmt->execute($tid !== null ? [$tid] : []);
$coRates = $coStmt->fetch() ?: [];

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
                        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/estimates/">Estimates</a></li>
                        <?php if ($est['project_title']): ?>
                        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/projects/detail.php?id=<?= $est['project_id'] ?>"><?= e($est['project_title']) ?></a></li>
                        <?php endif; ?>
                        <li class="breadcrumb-item active"><?= e($est['title']) ?></li>
                    </ol>
                </nav>
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mt-1">
                    <div>
                        <h1 class="mb-0"><?= e($est['title']) ?></h1>
                        <p class="text-muted small mb-0">
                            Markup <?= $est['markup_pct'] ?>% · Tax <?= $est['tax_pct'] ?>% · Waste <?= $est['waste_pct'] ?>%
                        </p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <?= status_badge($est['status']) ?>
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#settingsModal">
                            <i class="bi bi-sliders me-1"></i>Settings
                        </button>
                        <a href="<?= APP_URL ?>/modules/proposals/?from_estimate=<?= $est['id'] ?>" class="btn btn-sm btn-accent">
                            <i class="bi bi-file-earmark-text me-1"></i>Generate Proposal
                        </a>
                    </div>
                </div>
            </div>

            <div class="row g-3">

                <!-- Left: Totals + AI -->
                <div class="col-lg-3">

                    <!-- Totals Card -->
                    <div class="app-card mb-3">
                        <h6 class="fw-bold mb-3"><i class="bi bi-receipt me-2 text-muted"></i>Totals</h6>
                        <table class="table table-sm mb-0 small">
                            <tr><td class="text-muted">Subtotal</td><td class="text-end fw-medium"><span id="tot-subtotal"><?= money($est['subtotal']) ?></span></td></tr>
                            <tr><td class="text-muted">Waste (<?= $est['waste_pct'] ?>%)</td><td class="text-end"><span id="tot-waste"><?= money($est['waste_amount']) ?></span></td></tr>
                            <tr><td class="text-muted">Markup (<?= $est['markup_pct'] ?>%)</td><td class="text-end"><span id="tot-markup"><?= money($est['markup_amount']) ?></span></td></tr>
                            <tr><td class="text-muted">Tax (<?= $est['tax_pct'] ?>%)</td><td class="text-end"><span id="tot-tax"><?= money($est['tax_amount']) ?></span></td></tr>
                            <tr class="table-active">
                                <td class="fw-bold">Grand Total</td>
                                <td class="text-end fw-bold fs-5 text-success"><span id="tot-grand"><?= money($est['grand_total']) ?></span></td>
                            </tr>
                        </table>
                    </div>

                    <!-- AI Assist Card -->
                    <div class="app-card mb-3 border border-warning-subtle">
                        <h6 class="fw-bold mb-2">
                            <i class="bi bi-stars me-2 text-warning"></i>AI Estimating Assist
                        </h6>
                        <p class="small text-muted mb-2">Describe the project in plain English and Claude will suggest scope, line items, and risks.</p>
                        <?php if (!empty($coRates['labor_rate_carpenter']) || !empty($coRates['labor_rate_general'])): ?>
                        <div class="alert alert-success-subtle border border-success-subtle py-1 px-2 small mb-2">
                            <i class="bi bi-check-circle-fill text-success me-1"></i>
                            Using your labor rates
                            <a href="<?= APP_URL ?>/modules/settings/#tab-rates" class="float-end text-muted">edit</a>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-light border py-1 px-2 small mb-2">
                            <i class="bi bi-info-circle text-muted me-1"></i>
                            Using generic market rates.
                            <a href="<?= APP_URL ?>/modules/settings/">Set your rates</a>
                        </div>
                        <?php endif; ?>
                        <textarea id="ai-prompt" class="form-control form-control-sm mb-2" rows="4"
                                  placeholder="e.g. Full kitchen remodel, 200 sqft. Remove existing cabinets and tile floor. Install new cabinets, quartz countertops, subway tile backsplash, LVP flooring, and repaint."></textarea>
                        <button class="btn btn-sm btn-warning w-100 fw-semibold" id="ai-ask-btn" onclick="runAiEstimate()">
                            <i class="bi bi-stars me-1"></i> Ask Claude
                        </button>
                        <div id="ai-loading" class="text-center mt-2 d-none">
                            <div class="spinner-border spinner-border-sm text-warning me-2"></div>
                            <small class="text-muted">Claude is thinking…</small>
                        </div>
                    </div>

                    <!-- Add Section -->
                    <div class="app-card">
                        <h6 class="fw-bold mb-2"><i class="bi bi-plus-circle me-2 text-muted"></i>Add Section</h6>
                        <form hx-post="<?= APP_URL ?>/actions/save-estimate-section.php"
                              hx-target="#sections-container"
                              hx-swap="beforeend"
                              hx-on::after-request="this.reset()">
                            <input type="hidden" name="estimate_id" value="<?= $est['id'] ?>">
                            <select name="category" class="form-select form-select-sm mb-2">
                                <?php foreach ($categories as $c): ?>
                                <option value="<?= e($c) ?>"><?= e($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-primary w-100">Add Section</button>
                        </form>
                    </div>

                </div>

                <!-- Right: Line Item Builder -->
                <div class="col-lg-9">

                    <!-- AI Suggestions Panel (hidden until AI responds) -->
                    <div id="ai-suggestions" class="app-card mb-3 d-none border border-warning-subtle">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-stars me-2 text-warning"></i>Claude's Suggestions</h6>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('ai-suggestions').classList.add('d-none')">
                                    <i class="bi bi-x"></i> Dismiss
                                </button>
                            </div>
                        </div>
                        <div class="alert alert-warning-subtle border border-warning-subtle small py-2 mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Review the suggestions below. Click <strong>Add All</strong> on each section to add it to your estimate, then adjust any line items inline.
                        </div>
                        <div id="ai-suggestions-body"></div>
                    </div>

                    <!-- Sections -->
                    <div id="sections-container">
                        <?php foreach ($sections as $section): ?>
                        <?php include __DIR__ . '/partials/section.php'; ?>
                        <?php endforeach; ?>
                    </div>

                    <?php if (empty($sections)): ?>
                    <div class="app-card text-center py-5 text-muted" id="empty-msg">
                        <i class="bi bi-layers fs-1 opacity-25"></i>
                        <p class="mt-2">No sections yet. Add a category on the left or use AI Assist.</p>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
        <?php include __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>

<!-- Estimate Settings Modal -->
<div class="modal fade" id="settingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Estimate Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= APP_URL ?>/actions/save-estimate.php" method="POST">
                <input type="hidden" name="id" value="<?= $est['id'] ?>">
                <input type="hidden" name="redirect" value="<?= APP_URL ?>/modules/estimates/detail.php?id=<?= $est['id'] ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-medium">Title</label>
                            <input type="text" name="title" class="form-control" value="<?= e($est['title']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach ($statuses as $s): ?>
                                <option value="<?= e($s) ?>" <?= $est['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Markup %</label>
                            <input type="number" name="markup_pct" class="form-control" step="0.5" min="0" value="<?= $est['markup_pct'] ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Tax %</label>
                            <input type="number" name="tax_pct" class="form-control" step="0.5" min="0" value="<?= $est['tax_pct'] ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Waste %</label>
                            <input type="number" name="waste_pct" class="form-control" step="0.5" min="0" value="<?= $est['waste_pct'] ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"><?= e($est['notes']) ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-accent">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const ESTIMATE_ID = <?= $est['id'] ?>;
const API_URL     = '<?= APP_URL ?>/api/ai-estimate.php';
const TENANT_RATES = <?= json_encode([
    'labor_rate_general'     => (float)($coRates['labor_rate_general']     ?? 0),
    'labor_rate_carpenter'   => (float)($coRates['labor_rate_carpenter']   ?? 0),
    'labor_rate_electrician' => (float)($coRates['labor_rate_electrician'] ?? 0),
    'labor_rate_plumber'     => (float)($coRates['labor_rate_plumber']     ?? 0),
    'labor_rate_hvac'        => (float)($coRates['labor_rate_hvac']        ?? 0),
    'labor_rate_painter'     => (float)($coRates['labor_rate_painter']     ?? 0),
    'labor_rate_equipment'   => (float)($coRates['labor_rate_equipment']   ?? 0),
]) ?>;

// AI response is stored here — buttons reference by index, never embed JSON in HTML attributes
let aiData = null;

// ── Ask Claude ────────────────────────────────────────────────
function runAiEstimate() {
    const prompt = document.getElementById('ai-prompt').value.trim();
    if (!prompt) { alert('Please describe the project first.'); return; }

    document.getElementById('ai-loading').classList.remove('d-none');
    document.getElementById('ai-ask-btn').disabled = true;

    fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'estimate_id=' + ESTIMATE_ID
            + '&prompt='     + encodeURIComponent(prompt)
            + '&rates='      + encodeURIComponent(JSON.stringify(TENANT_RATES))
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('ai-loading').classList.add('d-none');
        document.getElementById('ai-ask-btn').disabled = false;
        if (data.error) { alert('AI Error: ' + data.error); return; }
        aiData = data;
        renderAiSuggestions(data);
        document.getElementById('ai-suggestions').classList.remove('d-none');
        document.getElementById('ai-suggestions').scrollIntoView({ behavior: 'smooth', block: 'start' });
    })
    .catch(err => {
        document.getElementById('ai-loading').classList.add('d-none');
        document.getElementById('ai-ask-btn').disabled = false;
        alert('Request failed: ' + err.message);
    });
}

// ── Render suggestions panel ──────────────────────────────────
function renderAiSuggestions(data) {
    let html = '';

    if (data.summary) {
        html += '<div class="alert alert-light border mb-3"><strong>Summary:</strong> ' + esc(data.summary) + '</div>';
    }

    // "Add All Sections" shortcut
    if (data.sections && data.sections.length > 1) {
        html += '<div class="d-flex justify-content-between align-items-center mb-2">'
              + '<h6 class="small fw-bold text-muted text-uppercase mb-0">Suggested Sections &amp; Line Items</h6>'
              + '<button class="btn btn-sm btn-success" onclick="addAllSections()">'
              + '<i class="bi bi-layers me-1"></i>Add All Sections</button>'
              + '</div>';
    } else if (data.sections && data.sections.length) {
        html += '<h6 class="small fw-bold text-muted text-uppercase mb-2">Suggested Sections &amp; Line Items</h6>';
    }

    (data.sections || []).forEach(function(sec, idx) {
        const sectionTotal = (sec.items || []).reduce(function(sum, it) {
            return sum + (parseFloat(it.labor_cost) || 0) + (parseFloat(it.material_cost) || 0)
                       + (parseFloat(it.equipment_cost) || 0) + (parseFloat(it.sub_cost) || 0);
        }, 0);

        html += '<div class="border rounded p-2 mb-2" id="ai-sec-' + idx + '">'
              + '<div class="d-flex align-items-center gap-2 mb-2">'
              + '<span class="badge bg-primary">' + esc(sec.category) + '</span>'
              + '<span class="text-muted small ms-auto me-2">' + fmt(sectionTotal) + ' est.</span>'
              + '<button class="btn btn-sm btn-outline-success py-0 px-2" '
              +         'onclick="addSectionByIndex(' + idx + ')" '
              +         'id="add-btn-' + idx + '">'
              + '<i class="bi bi-plus-lg"></i> Add All</button>'
              + '</div>';

        (sec.items || []).forEach(function(item) {
            const lineTotal = ((parseFloat(item.labor_cost) || 0) + (parseFloat(item.material_cost) || 0)
                             + (parseFloat(item.equipment_cost) || 0) + (parseFloat(item.sub_cost) || 0))
                             * (parseFloat(item.qty) || 1);
            html += '<div class="d-flex align-items-start gap-2 ps-2 py-1 border-start border-2 border-light mb-1 small">'
                  + '<div class="flex-grow-1">' + esc(item.description) + '</div>'
                  + '<div class="text-muted text-nowrap">' + fmt(lineTotal) + '</div>'
                  + '</div>';
        });

        html += '</div>';
    });

    if (data.risks && data.risks.length) {
        html += '<h6 class="small fw-bold text-danger text-uppercase mb-1 mt-3">⚠ Risk Items to Verify</h6><ul class="small mb-2">';
        data.risks.forEach(function(r) { html += '<li>' + esc(r) + '</li>'; });
        html += '</ul>';
    }

    if (data.missing && data.missing.length) {
        html += '<h6 class="small fw-bold text-warning text-uppercase mb-1">Possibly Missing</h6><ul class="small mb-0">';
        data.missing.forEach(function(m) { html += '<li>' + esc(m) + '</li>'; });
        html += '</ul>';
    }

    if (data.allowances && data.allowances.length) {
        html += '<h6 class="small fw-bold text-info text-uppercase mb-1 mt-2">Suggested Allowances</h6><ul class="small mb-0">';
        data.allowances.forEach(function(a) { html += '<li>' + esc(a) + '</li>'; });
        html += '</ul>';
    }

    if (data.cost_usd) {
        html += '<div class="text-end mt-3 border-top pt-2"><small class="text-muted">AI cost: $' + parseFloat(data.cost_usd).toFixed(4) + '</small></div>';
    }

    document.getElementById('ai-suggestions-body').innerHTML = html;
}

// ── Add one section by its index in aiData ────────────────────
function addSectionByIndex(idx) {
    if (!aiData || !aiData.sections || !aiData.sections[idx]) return;
    const sec = aiData.sections[idx];
    const btn = document.getElementById('add-btn-' + idx);
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Adding…'; }

    addSuggestedSection(sec.category, sec.items || [], function() {
        if (btn) { btn.innerHTML = '<i class="bi bi-check-lg"></i> Added'; btn.classList.replace('btn-outline-success','btn-success'); }
    });
}

// ── Add all sections at once ──────────────────────────────────
function addAllSections() {
    if (!aiData || !aiData.sections) return;
    aiData.sections.forEach(function(sec, idx) { addSectionByIndex(idx); });
}

// ── Core: POST one section to the server ─────────────────────
function addSuggestedSection(category, items, callback) {
    fetch('<?= APP_URL ?>/actions/save-estimate-section.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'estimate_id=' + ESTIMATE_ID
            + '&category='   + encodeURIComponent(category)
            + '&items='      + encodeURIComponent(JSON.stringify(items))
    })
    .then(function(r) { return r.text(); })
    .then(function(html) {
        const container = document.getElementById('sections-container');
        const empty     = document.getElementById('empty-msg');
        if (empty) empty.remove();

        // Process HTMX OOB swaps (totals panel) before injecting section HTML
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        tmp.querySelectorAll('[hx-swap-oob]').forEach(function(el) {
            const target = document.getElementById(el.id);
            if (target) target.innerHTML = el.innerHTML;
            el.remove();
        });

        container.insertAdjacentHTML('beforeend', tmp.innerHTML);
        htmx.process(container); // wire up HTMX on the new section

        if (typeof callback === 'function') callback();
    })
    .catch(function(err) { alert('Failed to add section: ' + err.message); });
}

// ── Live totals update (no page reload) ──────────────────────
function recalcTotals() {
    fetch('<?= APP_URL ?>/actions/recalc-estimate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'estimate_id=' + ESTIMATE_ID
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.subtotal !== undefined) {
            document.getElementById('tot-subtotal').textContent = fmt(data.subtotal);
            document.getElementById('tot-waste').textContent    = fmt(data.waste_amount);
            document.getElementById('tot-markup').textContent   = fmt(data.markup_amount);
            document.getElementById('tot-tax').textContent      = fmt(data.tax_amount);
            document.getElementById('tot-grand').textContent    = fmt(data.grand_total);
        }
    });
}

// ── Helpers ───────────────────────────────────────────────────
function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmt(n) {
    return '$' + parseFloat(n || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
</script>
