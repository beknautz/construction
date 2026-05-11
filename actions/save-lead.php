<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/modules/leads/');
    exit;
}

$db = get_db();

$id           = (int)($_POST['id'] ?? 0);
$redirect     = $_POST['redirect'] ?? APP_URL . '/modules/leads/';

// Sanitize inputs
$first_name     = trim($_POST['first_name']    ?? '');
$last_name      = trim($_POST['last_name']     ?? '');
$email          = trim($_POST['email']         ?? '');
$phone          = trim($_POST['phone']         ?? '');
$company        = trim($_POST['company']       ?? '');
$address        = trim($_POST['address']       ?? '');
$city           = trim($_POST['city']          ?? '');
$state          = trim($_POST['state']         ?? '');
$zip            = trim($_POST['zip']           ?? '');
$project_type   = trim($_POST['project_type']  ?? 'Remodel');
$source         = trim($_POST['source']        ?? 'Website');
$budget         = strlen(trim($_POST['budget'] ?? '')) ? (float)$_POST['budget'] : null;
$description    = trim($_POST['description']   ?? '');
$follow_up_date = trim($_POST['follow_up_date'] ?? '') ?: null;

// Validation
if (!$first_name) {
    set_flash('danger', 'First name is required.');
    header('Location: ' . $redirect);
    exit;
}

if ($id) {
    // Update existing lead
    $db->prepare(
        'UPDATE leads SET first_name=?, last_name=?, email=?, phone=?, company=?,
         address=?, city=?, state=?, zip=?, project_type=?, source=?,
         budget=?, description=?, follow_up_date=?, updated_at=NOW()
         WHERE id=?'
    )->execute([
        $first_name, $last_name, $email ?: null, $phone ?: null, $company ?: null,
        $address ?: null, $city ?: null, $state ?: null, $zip ?: null,
        $project_type, $source, $budget, $description ?: null, $follow_up_date, $id
    ]);

    // Log activity
    $db->prepare(
        'INSERT INTO lead_activity (lead_id, user_id, action, description) VALUES (?,?,?,?)'
    )->execute([$id, $_SESSION['user_id'], 'updated', 'Lead details updated']);

    log_activity('leads', 'update', "Updated lead #$id", $id);
    set_flash('success', 'Lead updated successfully.');
} else {
    // Insert new lead
    $db->prepare(
        'INSERT INTO leads (first_name, last_name, email, phone, company,
         address, city, state, zip, project_type, source,
         budget, description, follow_up_date, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $first_name, $last_name, $email ?: null, $phone ?: null, $company ?: null,
        $address ?: null, $city ?: null, $state ?: null, $zip ?: null,
        $project_type, $source, $budget, $description ?: null, $follow_up_date,
        $_SESSION['user_id']
    ]);

    $id = (int)$db->lastInsertId();

    // Log activity
    $db->prepare(
        'INSERT INTO lead_activity (lead_id, user_id, action, description) VALUES (?,?,?,?)'
    )->execute([$id, $_SESSION['user_id'], 'created', 'Lead created']);

    log_activity('leads', 'create', "Created lead #$id — $first_name $last_name", $id);
    set_flash('success', 'Lead added successfully.');

    // Redirect to detail page on create
    $redirect = APP_URL . '/modules/leads/detail.php?id=' . $id;
}

header('Location: ' . $redirect);
exit;
