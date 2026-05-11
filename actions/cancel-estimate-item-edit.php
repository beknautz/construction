<?php
/**
 * GET ?item_id=&estimate_id=
 * Returns the original display <tr> when the user cancels an inline edit.
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

$appUrl  = APP_URL;
$descEsc = htmlspecialchars($li['description'], ENT_QUOTES, 'UTF-8');
$unitEsc = htmlspecialchars($li['unit'] ?? '', ENT_QUOTES, 'UTF-8');
$badge   = $li['is_allowance'] ? ' <span class="badge bg-info-subtle text-info">Allowance</span>' : '';

echo '<tr id="li-' . $item_id . '">';
echo '<td>' . $descEsc . $badge . '</td>';
echo '<td class="text-muted small">' . $li['qty'] . '</td>';
echo '<td class="text-muted small">' . $unitEsc . '</td>';
echo '<td class="small">' . money($li['labor_cost'])     . '</td>';
echo '<td class="small">' . money($li['material_cost'])  . '</td>';
echo '<td class="small">' . money($li['equipment_cost']) . '</td>';
echo '<td class="small">' . money($li['sub_cost'])       . '</td>';
echo '<td class="fw-medium">' . money($li['line_total']) . '</td>';
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
