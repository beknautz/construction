<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/estimate-helpers.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$db          = get_db();
$section_id  = (int)($_POST['section_id']  ?? 0);
$estimate_id = (int)($_POST['estimate_id'] ?? 0);
$description = trim($_POST['description']  ?? '');

if (!$section_id || !$estimate_id || !$description) { http_response_code(400); exit; }

$qty      = max(0, (float)($_POST['qty']            ?? 1));
$unit     = trim($_POST['unit']                     ?? '');
$labor    = max(0, (float)($_POST['labor_cost']     ?? 0));
$material = max(0, (float)($_POST['material_cost']  ?? 0));
$equip    = max(0, (float)($_POST['equipment_cost'] ?? 0));
$sub      = max(0, (float)($_POST['sub_cost']       ?? 0));
$allowance= isset($_POST['is_allowance']) ? 1 : 0;
$notes    = trim($_POST['notes'] ?? '');

$line_total = round(($labor + $material + $equip + $sub) * $qty, 2);

$db->prepare(
    'INSERT INTO estimate_line_items
     (section_id, estimate_id, description, qty, unit, labor_cost, material_cost,
      equipment_cost, sub_cost, line_total, is_allowance, notes)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
)->execute([$section_id, $estimate_id, $description, $qty, $unit ?: null,
            $labor, $material, $equip, $sub, $line_total, $allowance, $notes ?: null]);

$item_id = (int)$db->lastInsertId();
recalc_estimate($estimate_id, $db);

// Return the new row HTML for HTMX
$badge = $allowance ? ' <span class="badge bg-info-subtle text-info">Allowance</span>' : '';
echo <<<HTML
<tr id="li-{$item_id}">
    <td>{$description}{$badge}</td>
    <td class="text-muted small">{$qty}</td>
    <td class="text-muted small">{$unit}</td>
    <td class="small">\${$labor}</td>
    <td class="small">\${$material}</td>
    <td class="small">\${$equip}</td>
    <td class="small">\${$sub}</td>
    <td class="fw-medium">\${$line_total}</td>
    <td>
        <button class="btn btn-sm btn-link text-danger p-0"
                hx-post="{APP_URL}/actions/delete-estimate-item.php"
                hx-vals='{"item_id": "{$item_id}", "estimate_id": "{$estimate_id}"}'
                hx-target="#li-{$item_id}"
                hx-swap="outerHTML"
                title="Delete item">
            <i class="bi bi-x-circle"></i>
        </button>
    </td>
</tr>
HTML;
// Replace placeholder since heredoc can't reference constants
echo str_replace('{APP_URL}', APP_URL, '');
