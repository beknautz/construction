<?php
// Partial: renders one estimate section with its line items.
// Required vars: $section, $items_by_section, $est
$sid       = $section['id'];
$sec_items = $items_by_section[$sid] ?? [];
$appUrl    = APP_URL;
?>
<div class="app-card mb-3 section-block" id="section-<?= $sid ?>">

    <div class="d-flex align-items-center gap-2 mb-3">
        <h6 class="fw-bold mb-0 flex-grow-1">
            <i class="bi bi-tag me-2 text-muted"></i><?= e($section['category']) ?>
        </h6>
        <span class="badge bg-light text-dark border" id="section-badge-<?= $sid ?>">
            <?= money($section['section_total']) ?>
        </span>
        <button class="btn btn-sm btn-outline-danger py-0"
                hx-post="<?= $appUrl ?>/actions/delete-estimate-section.php"
                hx-vals='{"section_id": "<?= $sid ?>", "estimate_id": "<?= $est['id'] ?>"}'
                hx-target="#section-<?= $sid ?>"
                hx-swap="outerHTML"
                hx-confirm="Delete this section and all its line items?"
                title="Delete section">
            <i class="bi bi-trash"></i>
        </button>
    </div>

    <!-- Line Items Table -->
    <div class="table-responsive mb-2">
        <table class="table table-sm table-app mb-0" id="items-<?= $sid ?>">
            <thead>
                <tr>
                    <th style="min-width:220px;">Description</th>
                    <th style="width:65px;">Qty</th>
                    <th style="width:55px;">Unit</th>
                    <th style="width:100px;">Labor</th>
                    <th style="width:100px;">Material</th>
                    <th style="width:90px;">Equip</th>
                    <th style="width:90px;">Sub</th>
                    <th style="width:100px;">Total</th>
                    <th style="width:60px;"></th>
                </tr>
            </thead>
            <tbody id="items-body-<?= $sid ?>">
            <?php foreach ($sec_items as $li): ?>
            <tr id="li-<?= $li['id'] ?>">
                <td>
                    <?= e($li['description']) ?>
                    <?= $li['is_allowance'] ? ' <span class="badge bg-info-subtle text-info">Allowance</span>' : '' ?>
                </td>
                <td class="text-muted small"><?= $li['qty'] ?></td>
                <td class="text-muted small"><?= e($li['unit'] ?? '') ?></td>
                <td class="small"><?= money($li['labor_cost']) ?></td>
                <td class="small"><?= money($li['material_cost']) ?></td>
                <td class="small"><?= money($li['equipment_cost']) ?></td>
                <td class="small"><?= money($li['sub_cost']) ?></td>
                <td class="fw-medium"><?= money($li['line_total']) ?></td>
                <td class="text-nowrap">
                    <button class="btn btn-sm btn-link text-secondary p-0 me-1"
                            hx-get="<?= $appUrl ?>/actions/get-estimate-item-edit.php?item_id=<?= $li['id'] ?>&estimate_id=<?= $est['id'] ?>"
                            hx-target="#li-<?= $li['id'] ?>"
                            hx-swap="outerHTML"
                            title="Edit item">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-link text-danger p-0"
                            hx-post="<?= $appUrl ?>/actions/delete-estimate-item.php"
                            hx-vals='{"item_id": "<?= $li['id'] ?>", "estimate_id": "<?= $est['id'] ?>"}'
                            hx-target="#li-<?= $li['id'] ?>"
                            hx-swap="outerHTML"
                            hx-confirm="Delete this item?"
                            title="Delete item">
                        <i class="bi bi-x-circle"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Line Item Form -->
    <form hx-post="<?= $appUrl ?>/actions/save-estimate-item.php"
          hx-target="#items-body-<?= $sid ?>"
          hx-swap="beforeend"
          hx-on::after-request="this.reset()"
          class="border-top pt-2">
        <input type="hidden" name="section_id"  value="<?= $sid ?>">
        <input type="hidden" name="estimate_id" value="<?= $est['id'] ?>">
        <div class="row g-1 align-items-end">
            <div class="col-md-3">
                <input type="text" name="description" class="form-control form-control-sm"
                       placeholder="Description *" required>
            </div>
            <div class="col-auto" style="width:70px;">
                <input type="number" name="qty" class="form-control form-control-sm"
                       placeholder="Qty" value="1" step="0.01" min="0">
            </div>
            <div class="col-auto" style="width:65px;">
                <input type="text" name="unit" class="form-control form-control-sm" placeholder="unit">
            </div>
            <div class="col-auto" style="width:100px;">
                <input type="number" name="labor_cost" class="form-control form-control-sm"
                       placeholder="Labor $" step="0.01" min="0" value="0">
            </div>
            <div class="col-auto" style="width:100px;">
                <input type="number" name="material_cost" class="form-control form-control-sm"
                       placeholder="Mat. $" step="0.01" min="0" value="0">
            </div>
            <div class="col-auto" style="width:90px;">
                <input type="number" name="equipment_cost" class="form-control form-control-sm"
                       placeholder="Equip $" step="0.01" min="0" value="0">
            </div>
            <div class="col-auto" style="width:90px;">
                <input type="number" name="sub_cost" class="form-control form-control-sm"
                       placeholder="Sub $" step="0.01" min="0" value="0">
            </div>
            <div class="col-auto">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" name="is_allowance" value="1" id="allow-<?= $sid ?>">
                    <label class="form-check-label small" for="allow-<?= $sid ?>">Allow.</label>
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-success px-3">
                    <i class="bi bi-plus-lg"></i> Add
                </button>
            </div>
        </div>
    </form>
</div>
