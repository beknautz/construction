<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$db          = get_db();
$estimate_id = (int)($_POST['estimate_id'] ?? 0);
$category    = trim($_POST['category']     ?? 'Other');
$items_json  = $_POST['items']             ?? '[]';

if (!$estimate_id) { http_response_code(400); exit; }

// Verify estimate belongs to session user's accessible data
$est = $db->prepare('SELECT markup_pct, tax_pct, waste_pct FROM estimates WHERE id = ?');
$est->execute([$estimate_id]);
$est = $est->fetch();
if (!$est) { http_response_code(404); exit; }

// Insert section
$db->prepare('INSERT INTO estimate_sections (estimate_id, category) VALUES (?,?)')->execute([$estimate_id, $category]);
$section_id = (int)$db->lastInsertId();

// If AI suggested items were passed, insert them too
$suggested = json_decode($items_json, true) ?: [];
foreach ($suggested as $item) {
    $desc   = trim($item['description'] ?? '');
    if (!$desc) continue;
    $labor  = (float)($item['labor_cost']    ?? 0);
    $mat    = (float)($item['material_cost'] ?? 0);
    $equip  = (float)($item['equipment_cost']?? 0);
    $sub    = (float)($item['sub_cost']      ?? 0);
    $qty    = (float)($item['qty']           ?? 1);
    $unit   = trim($item['unit']             ?? '');
    $total  = round(($labor + $mat + $equip + $sub) * $qty, 2);

    $db->prepare(
        'INSERT INTO estimate_line_items
         (section_id, estimate_id, description, qty, unit, labor_cost, material_cost, equipment_cost, sub_cost, line_total)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    )->execute([$section_id, $estimate_id, $desc, $qty, $unit ?: null, $labor, $mat, $equip, $sub, $total]);
}

// Recalculate estimate totals
require_once __DIR__ . '/../includes/estimate-helpers.php';
recalc_estimate($estimate_id, $db);

// Reload the section from DB for the partial render
$section = $db->prepare(
    'SELECT s.*, COALESCE(SUM(li.line_total),0) AS section_total
     FROM estimate_sections s
     LEFT JOIN estimate_line_items li ON li.section_id = s.id
     WHERE s.id = ?
     GROUP BY s.id'
);
$section->execute([$section_id]);
$section = $section->fetch();

$all_items = $db->prepare('SELECT * FROM estimate_line_items WHERE section_id = ? ORDER BY sort_order, id');
$all_items->execute([$section_id]);
$items_by_section[$section_id] = $all_items->fetchAll();

define('APP_URL_DEFINED', true); // prevent re-define

ob_start();
include __DIR__ . '/../modules/estimates/partials/section.php';
echo ob_get_clean();
