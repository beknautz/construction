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

if (!$section_id || !$estimate_id) { http_response_code(400); exit; }

$db->prepare('DELETE FROM estimate_sections WHERE id = ?')->execute([$section_id]);
recalc_estimate($estimate_id, $db);

http_response_code(200);
echo '';
