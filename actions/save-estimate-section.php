<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/estimate-helpers.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$db          = get_db();
$estimate_id = (int)($_POST['estimate_id'] ?? 0);
$category    = trim($_POST['category']     ?? 'Other');
$items_json  = $_POST['items']             ?? '[]';

if (!$estimate_id) { http_response_code(400); exit; }

// Fetch full estimate row — section.php partial needs $est['id'] and percentages
$estStmt = $db->prepare('SELECT * FROM estimates WHERE id = ?');
$estStmt->execute([$estimate_id]);
$est = $estStmt->fetch();
if (!$est) { http_response_code(404); exit; }

// Insert section
$db->prepare('INSERT INTO estimate_sections (estimate_id, category) VALUES (?,?)')->execute([$estimate_id, $category]);
$section_id = (int)$db->lastInsertId();

// Insert AI-suggested items if provided
$suggested = json_decode($items_json, true) ?: [];
foreach ($suggested as $item) {
    $desc  = trim($item['description'] ?? '');
    if (!$desc) continue;
    $labor = max(0, (float)($item['labor_cost']     ?? 0));
    $mat   = max(0, (float)($item['material_cost']  ?? 0));
    $equip = max(0, (float)($item['equipment_cost'] ?? 0));
    $sub   = max(0, (float)($item['sub_cost']       ?? 0));
    $qty   = max(0, (float)($item['qty']            ?? 1));
    $unit  = trim($item['unit'] ?? '');
    $total = round(($labor + $mat + $equip + $sub) * $qty, 2);

    $db->prepare(
        'INSERT INTO estimate_line_items
         (section_id, estimate_id, description, qty, unit, labor_cost, material_cost, equipment_cost, sub_cost, line_total)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    )->execute([$section_id, $estimate_id, $desc, $qty, $unit ?: null, $labor, $mat, $equip, $sub, $total]);
}

recalc_estimate($estimate_id, $db);

// Reload section with total
$section = $db->prepare(
    'SELECT s.*, COALESCE(SUM(li.line_total),0) AS section_total
     FROM estimate_sections s
     LEFT JOIN estimate_line_items li ON li.section_id = s.id
     WHERE s.id = ? GROUP BY s.id'
);
$section->execute([$section_id]);
$section = $section->fetch();

$all_items = $db->prepare('SELECT * FROM estimate_line_items WHERE section_id = ? ORDER BY sort_order, id');
$all_items->execute([$section_id]);
$items_by_section[$section_id] = $all_items->fetchAll();

// Render section partial (main HTMX response)
ob_start();
include __DIR__ . '/../modules/estimates/partials/section.php';
$sectionHtml = ob_get_clean();
echo $sectionHtml;

// OOB: update estimate totals panel
$estRow = $db->prepare('SELECT subtotal, waste_amount, markup_amount, tax_amount, grand_total FROM estimates WHERE id = ?');
$estRow->execute([$estimate_id]);
$estTotals = $estRow->fetch();
if ($estTotals) {
    echo '<span hx-swap-oob="true" id="tot-subtotal">' . money($estTotals['subtotal'])     . '</span>';
    echo '<span hx-swap-oob="true" id="tot-waste">'    . money($estTotals['waste_amount'])  . '</span>';
    echo '<span hx-swap-oob="true" id="tot-markup">'   . money($estTotals['markup_amount']) . '</span>';
    echo '<span hx-swap-oob="true" id="tot-tax">'      . money($estTotals['tax_amount'])    . '</span>';
    echo '<span hx-swap-oob="true" id="tot-grand">'    . money($estTotals['grand_total'])   . '</span>';
}
