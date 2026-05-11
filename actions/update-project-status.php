<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$allowed = ['Planning','Estimating','Proposal','Contracted','In Progress','Waiting','Completed','Closed'];
$id      = (int)($_POST['id']     ?? 0);
$status  = trim($_POST['status']  ?? '');

if (!$id || !in_array($status, $allowed, true)) { http_response_code(400); exit; }

$db  = get_db();
$old = $db->prepare('SELECT status FROM projects WHERE id = ?');
$old->execute([$id]);
$old_status = $old->fetchColumn();
if ($old_status === false) { http_response_code(404); exit; }

$db->prepare('UPDATE projects SET status = ?, updated_at = NOW() WHERE id = ?')
   ->execute([$status, $id]);

$db->prepare(
    'INSERT INTO project_activity (project_id, user_id, action, description) VALUES (?,?,?,?)'
)->execute([$id, $_SESSION['user_id'], 'status_changed', "Status: \"$old_status\" → \"$status\""]);

log_activity('projects', 'status_change', "Project #$id: $old_status → $status", $id);

header('X-Toast-Message: Status updated to ' . $status);
header('X-Toast-Type: success');
http_response_code(200);
exit;
