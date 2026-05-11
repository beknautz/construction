<?php
/**
 * GET ?item_id=&estimate_id=
 * Returns an editable <tr> for inline line-item editing via HTMX.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$db          = get_db();
$item_id     = (int)($_GET['item_id']     ?? 0);
$estimate_id = (int)($_GET['estimate_id'] ?? 0);

if (!$item_id || !$estimate_id) { http_response_code(400); exit; }

$stmt = $db->prepare('SELECT * FROM estimate_line_items WHERE id = ? AND estimate_id = ? LIMIT 1');
$stmt->execute([$item_id, $estimate_id]);
$li = $stmt->fetch();
if (!$li) { http_response_code(404); exit; }

$appUrl = APP_URL;
?>
<tr id="li-<?= $item_id ?>" class="table-warning">
    <form hx-post="<?= $appUrl ?>/actions/update-estimate-item.php"
          hx-target="#li-<?= $item_id ?>"
          hx-swap="outerHTML"
          style="display:contents;">
        <input type="hidden" name="item_id"     value="<?= $item_id ?>">
        <input type="hidden" name="estimate_id" value="<?= $estimate_id ?>">
        <input type="hidden" name="section_id"  value="<?= (int)$li['section_id'] ?>">
        <td><input type="text"   name="description"    class="form-control form-control-sm" value="<?= e($li['description']) ?>" required></td>
        <td><input type="number" name="qty"            class="form-control form-control-sm" value="<?= $li['qty'] ?>"            step="0.01" min="0" style="width:60px;"></td>
        <td><input type="text"   name="unit"           class="form-control form-control-sm" value="<?= e($li['unit'] ?? '') ?>"  style="width:55px;" placeholder="ea"></td>
        <td><input type="number" name="labor_cost"     class="form-control form-control-sm" value="<?= $li['labor_cost'] ?>"     step="0.01" min="0"></td>
        <td><input type="number" name="material_cost"  class="form-control form-control-sm" value="<?= $li['material_cost'] ?>"  step="0.01" min="0"></td>
        <td><input type="number" name="equipment_cost" class="form-control form-control-sm" value="<?= $li['equipment_cost'] ?>" step="0.01" min="0"></td>
        <td><input type="number" name="sub_cost"       class="form-control form-control-sm" value="<?= $li['sub_cost'] ?>"       step="0.01" min="0"></td>
        <td class="text-muted small fst-italic">recalc on save</td>
        <td class="text-nowrap">
            <button type="submit" class="btn btn-sm btn-success p-1 me-1" title="Save">
                <i class="bi bi-check-lg"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary p-1"
                    hx-get="<?= $appUrl ?>/actions/cancel-estimate-item-edit.php?item_id=<?= $item_id ?>&estimate_id=<?= $estimate_id ?>"
                    hx-target="#li-<?= $item_id ?>"
                    hx-swap="outerHTML"
                    title="Cancel">
                <i class="bi bi-x-lg"></i>
            </button>
        </td>
    </form>
</tr>
