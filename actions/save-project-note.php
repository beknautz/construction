<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$project_id = (int)($_POST['project_id'] ?? 0);
$note       = trim($_POST['note']        ?? '');

if (!$project_id || !$note) { http_response_code(400); exit; }

$db = get_db();
$check = $db->prepare('SELECT id FROM projects WHERE id = ?');
$check->execute([$project_id]);
if (!$check->fetch()) { http_response_code(404); exit; }

$db->prepare('INSERT INTO project_notes (project_id, user_id, note) VALUES (?,?,?)')
   ->execute([$project_id, $_SESSION['user_id'], $note]);

$db->prepare(
    'INSERT INTO project_activity (project_id, user_id, action, description) VALUES (?,?,?,?)'
)->execute([$project_id, $_SESSION['user_id'], 'note_added', 'Note added']);

$user = current_user();
$now  = date('M j, g:ia');
$safe_note = nl2br(htmlspecialchars($note, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

echo <<<HTML
<div class="note-item p-3 rounded bg-light border-start border-3 border-primary">
    <div class="d-flex justify-content-between mb-1">
        <span class="small fw-medium">{$user['name']}</span>
        <span class="small text-muted">{$now}</span>
    </div>
    <p class="mb-0 small">{$safe_note}</p>
</div>
HTML;
