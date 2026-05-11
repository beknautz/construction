<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/modules/projects/');
    exit;
}

$db         = get_db();
$project_id = (int)($_POST['project_id'] ?? 0);
$redirect   = $_POST['redirect'] ?? APP_URL . '/modules/projects/';
$category   = trim($_POST['category'] ?? 'During');
$caption    = trim($_POST['caption']  ?? '');

$allowed_cats = ['Before','During','After','Inspection','Damage','Materials','Other'];
if (!in_array($category, $allowed_cats, true)) $category = 'During';

if (!$project_id) {
    set_flash('danger', 'Invalid project.');
    header('Location: ' . $redirect);
    exit;
}

if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    set_flash('danger', 'Upload failed. Please try again.');
    header('Location: ' . $redirect);
    exit;
}

$file     = $_FILES['photo'];
$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed  = ALLOWED_IMAGES;

if (!in_array($ext, $allowed, true)) {
    set_flash('danger', 'Only image files are allowed (jpg, png, gif, webp).');
    header('Location: ' . $redirect);
    exit;
}

if ($file['size'] > MAX_UPLOAD_SIZE) {
    set_flash('danger', 'File is too large. Maximum 10MB.');
    header('Location: ' . $redirect);
    exit;
}

// Generate unique filename
$filename = uniqid('photo_', true) . '.' . $ext;
$dest     = UPLOAD_PATH . '/projects/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    set_flash('danger', 'Could not save file. Check server upload permissions.');
    header('Location: ' . $redirect);
    exit;
}

$db->prepare(
    'INSERT INTO project_photos (project_id, user_id, filename, original_name, category, caption, file_size)
     VALUES (?,?,?,?,?,?,?)'
)->execute([
    $project_id, $_SESSION['user_id'], $filename, $file['name'],
    $category, $caption ?: null, $file['size']
]);

$db->prepare(
    'INSERT INTO project_activity (project_id, user_id, action, description) VALUES (?,?,?,?)'
)->execute([$project_id, $_SESSION['user_id'], 'photo_uploaded', "Photo uploaded: {$file['name']} ($category)"]);

log_activity('projects', 'photo_upload', "Photo uploaded for project #$project_id", $project_id);
set_flash('success', 'Photo uploaded successfully.');
header('Location: ' . $redirect);
exit;
