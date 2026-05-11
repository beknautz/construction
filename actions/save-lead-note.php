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

$lead_id = (int)($_POST['lead_id'] ?? 0);
$note    = trim($_POST['note']     ?? '');

if (!$lead_id || !$note) {
    http_response_code(400);
    exit;
}

$db = get_db();

// Verify lead exists
$check = $db->prepare('SELECT id FROM leads WHERE id = ?');
$check->execute([$lead_id]);
if (!$check->fetch()) {
    http_response_code(404);
    exit;
}

// Insert note
$db->prepare(
    'INSERT INTO lead_notes (lead_id, user_id, note) VALUES (?,?,?)'
)->execute([$lead_id, $_SESSION['user_id'], $note]);

// Log activity
$db->prepare(
    'INSERT INTO lead_activity (lead_id, user_id, action, description) VALUES (?,?,?,?)'
)->execute([$lead_id, $_SESSION['user_id'], 'note_added', 'Note added']);

$user = current_user();
$now  = date('M j, g:ia');

// Return the new note HTML for HTMX to inject
echo <<<HTML
<div class="note-item p-3 rounded bg-light border-start border-3 border-warning">
    <div class="d-flex justify-content-between mb-1">
        <span class="small fw-medium">{$user['name']}</span>
        <span class="small text-muted">{$now}</span>
    </div>
    <p class="mb-0 small">{$note}</p>
</div>
HTML;
