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

if (!$item_id || !$estimate_id) { http_response_code(400); exit; }

// Get section_id before deleting
$secStmt = $db->prepare('SELECT section_id FROM estimate_line_items WHERE id = ?');
$secStmt->execute([$item_id]);
$section_id = (int)($secStmt->fetchColumn() ?: 0);

$db->prepare('DELETE FROM estimate_line_items WHERE id = ?')->execute([$item_id]);
recalc_estimate($estimate_id, $db);

// HTMX replaces the row with empty string (removes it from DOM)
// OOB: update section total badge
if ($section_id) {
    $secRow = $db->prepare('SELECT COALESCE(SUM(line_total),0) FROM estimate_line_items WHERE section_id = ?');
    $secRow->execute([$section_id]);
    $secTotal = (float)$secRow->fetchColumn();
    echo '<span hx-swap-oob="true" id="section-badge-' . $section_id . '">' . money($secTotal) . '</span>';
}

// OOB: update estimate totals panel
$estRow = $db->prepare('SELECT subtotal, waste_amount, markup_amount, tax_amount, grand_total FROM estimates WHERE id = ?');
$estRow->execute([$estimate_id]);
$est = $estRow->fetch();
if ($est) {
    echo '<span hx-swap-oob="true" id="tot-subtotal">' . money($est['subtotal'])     . '</span>';
    echo '<span hx-swap-oob="true" id="tot-waste">'    . money($est['waste_amount'])  . '</span>';
    echo '<span hx-swap-oob="true" id="tot-markup">'   . money($est['markup_amount']) . '</span>';
    echo '<span hx-swap-oob="true" id="tot-tax">'      . money($est['tax_amount'])    . '</span>';
    echo '<span hx-swap-oob="true" id="tot-grand">'    . money($est['grand_total'])   . '</span>';
}
