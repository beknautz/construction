<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/estimate-helpers.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$db          = get_db();
$item_id     = (int)($_POST['item_id']     ?? 0);
$estimate_id = (int)($_POST['estimate_id'] ?? 0);
$section_id  = (int)($_POST['section_id']  ?? 0);
$description = trim($_POST['description']  ?? '');

if (!$item_id || !$estimate_id || !$description) { http_response_code(400); exit; }

$qty      = max(0, (float)($_POST['qty']            ?? 1));
$unit     = trim($_POST['unit']                     ?? '');
$labor    = max(0, (float)($_POST['labor_cost']     ?? 0));
$material = max(0, (float)($_POST['material_cost']  ?? 0));
$equip    = max(0, (float)($_POST['equipment_cost'] ?? 0));
$sub      = max(0, (float)($_POST['sub_cost']       ?? 0));
$allowance= isset($_POST['is_allowance']) ? 1 : 0;

$line_total = round(($labor + $material + $equip + $sub) * $qty, 2);

$db->prepare(
    'UPDATE estimate_line_items
     SET description=?, qty=?, unit=?, labor_cost=?, material_cost=?,
         equipment_cost=?, sub_cost=?, line_total=?, is_allowance=?
     WHERE id=?'
)->execute([$description, $qty, $unit ?: null, $labor, $material, $equip, $sub, $line_total, $allowance, $item_id]);

recalc_estimate($estimate_id, $db);

// Fetch updated section total
$secRow = $db->prepare('SELECT COALESCE(SUM(line_total),0) FROM estimate_line_items WHERE section_id = ?');
$secRow->execute([$section_id]);
$secTotal = (float)$secRow->fetchColumn();

// Fetch updated estimate totals
$estRow = $db->prepare('SELECT subtotal, waste_amount, markup_amount, tax_amount, grand_total FROM estimates WHERE id = ?');
$estRow->execute([$estimate_id]);
$estTotals = $estRow->fetch();

$appUrl   = APP_URL;
$descEsc  = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
$unitEsc  = htmlspecialchars($unit, ENT_QUOTES, 'UTF-8');
$badge    = $allowance ? ' <span class="badge bg-info-subtle text-info">Allowance</span>' : '';

// Return updated display row
echo '<tr id="li-' . $item_id . '">';
echo '<td>' . $descEsc . $badge . '</td>';
echo '<td class="text-muted small">' . $qty . '</td>';
echo '<td class="text-muted small">' . $unitEsc . '</td>';
echo '<td class="small">' . money($labor)    . '</td>';
echo '<td class="small">' . money($material) . '</td>';
echo '<td class="small">' . money($equip)    . '</td>';
echo '<td class="small">' . money($sub)      . '</td>';
echo '<td class="fw-medium">' . money($line_total) . '</td>';
echo '<td class="text-nowrap">';
echo '  <button class="btn btn-sm btn-link text-secondary p-0 me-1"';
echo '          hx-get="' . $appUrl . '/actions/get-estimate-item-edit.php?item_id=' . $item_id . '&estimate_id=' . $estimate_id . '"';
echo '          hx-target="#li-' . $item_id . '"';
echo '          hx-swap="outerHTML"';
echo '          title="Edit item"><i class="bi bi-pencil"></i></button>';
echo '  <button class="btn btn-sm btn-link text-danger p-0"';
echo '          hx-post="' . $appUrl . '/actions/delete-estimate-item.php"';
echo '          hx-vals=\'{"item_id": "' . $item_id . '", "estimate_id": "' . $estimate_id . '"}\' ';
echo '          hx-target="#li-' . $item_id . '"';
echo '          hx-swap="outerHTML"';
echo '          hx-confirm="Delete this item?"';
echo '          title="Delete item"><i class="bi bi-x-circle"></i></button>';
echo '</td>';
echo '</tr>';

// OOB: section total badge
echo '<span hx-swap-oob="true" id="section-badge-' . $section_id . '">' . money($secTotal) . '</span>';

// OOB: estimate totals panel
if ($estTotals) {
    echo '<span hx-swap-oob="true" id="tot-subtotal">' . money($estTotals['subtotal'])     . '</span>';
    echo '<span hx-swap-oob="true" id="tot-waste">'    . money($estTotals['waste_amount'])  . '</span>';
    echo '<span hx-swap-oob="true" id="tot-markup">'   . money($estTotals['markup_amount']) . '</span>';
    echo '<span hx-swap-oob="true" id="tot-tax">'      . money($estTotals['tax_amount'])    . '</span>';
    echo '<span hx-swap-oob="true" id="tot-grand">'    . money($estTotals['grand_total'])   . '</span>';
}
