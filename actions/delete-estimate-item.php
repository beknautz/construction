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

$db->prepare('DELETE FROM estimate_line_items WHERE id = ?')->execute([$item_id]);
recalc_estimate($estimate_id, $db);

// HTMX replaces the row with nothing (removes it)
http_response_code(200);
echo '';
