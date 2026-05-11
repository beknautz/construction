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

if (!$estimate_id) { http_response_code(400); echo json_encode(['error' => 'missing estimate_id']); exit; }

recalc_estimate($estimate_id, $db);

$est = $db->prepare('SELECT subtotal, waste_amount, markup_amount, tax_amount, grand_total FROM estimates WHERE id = ?');
$est->execute([$estimate_id]);
$est = $est->fetch();

header('Content-Type: application/json');
echo json_encode($est ?: ['error' => 'not found']);
