<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$allowed_statuses = ['New','Contacted','Site Visit Scheduled','Estimate Needed','Proposal Sent','Won','Lost'];

$id     = (int)($_POST['id']     ?? 0);
$status = trim($_POST['status']  ?? '');

if (!$id || !in_array($status, $allowed_statuses, true)) {
    http_response_code(400);
    exit;
}

$db = get_db();

// Get old status for activity log
$old = $db->prepare('SELECT status FROM leads WHERE id = ?');
$old->execute([$id]);
$old_status = $old->fetchColumn();

if ($old_status === false) {
    http_response_code(404);
    exit;
}

// Update status
$db->prepare('UPDATE leads SET status = ?, updated_at = NOW() WHERE id = ?')
   ->execute([$status, $id]);

// Log activity
$db->prepare(
    'INSERT INTO lead_activity (lead_id, user_id, action, description) VALUES (?,?,?,?)'
)->execute([
    $id,
    $_SESSION['user_id'],
    'status_changed',
    "Status changed from \"$old_status\" to \"$status\""
]);

log_activity('leads', 'status_change', "Lead #$id: $old_status → $status", $id);

// HTMX expects a 200 with optional response headers for toast
header('X-Toast-Message: Status updated to ' . $status);
header('X-Toast-Type: success');
http_response_code(200);
exit;
