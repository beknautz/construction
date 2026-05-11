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

$db       = get_db();
$id       = (int)($_POST['id']      ?? 0);
$redirect = $_POST['redirect']      ?? APP_URL . '/modules/projects/';

$title          = trim($_POST['title']          ?? '');
$client_name    = trim($_POST['client_name']    ?? '');
$client_email   = trim($_POST['client_email']   ?? '');
$client_phone   = trim($_POST['client_phone']   ?? '');
$client_company = trim($_POST['client_company'] ?? '');
$address        = trim($_POST['address']        ?? '');
$city           = trim($_POST['city']           ?? '');
$state          = trim($_POST['state']          ?? '');
$zip            = trim($_POST['zip']            ?? '');
$project_type   = trim($_POST['project_type']   ?? 'Remodel');
$status         = trim($_POST['status']         ?? 'Planning');
$scope_summary  = trim($_POST['scope_summary']  ?? '');
$budget         = strlen(trim($_POST['budget']          ?? '')) ? (float)$_POST['budget']          : null;
$contract_amount= strlen(trim($_POST['contract_amount'] ?? '')) ? (float)$_POST['contract_amount'] : null;
$start_date     = trim($_POST['start_date']     ?? '') ?: null;
$end_date       = trim($_POST['end_date']       ?? '') ?: null;
$lead_id        = (int)($_POST['lead_id']       ?? 0) ?: null;

if (!$title || !$client_name) {
    set_flash('danger', 'Project title and client name are required.');
    header('Location: ' . $redirect);
    exit;
}

if ($id) {
    $db->prepare(
        'UPDATE projects SET title=?, client_name=?, client_email=?, client_phone=?,
         client_company=?, address=?, city=?, state=?, zip=?, project_type=?, status=?,
         scope_summary=?, budget=?, contract_amount=?, start_date=?, end_date=?,
         updated_at=NOW() WHERE id=?'
    )->execute([
        $title, $client_name, $client_email ?: null, $client_phone ?: null,
        $client_company ?: null, $address ?: null, $city ?: null, $state ?: null,
        $zip ?: null, $project_type, $status, $scope_summary ?: null,
        $budget, $contract_amount, $start_date, $end_date, $id
    ]);

    $db->prepare(
        'INSERT INTO project_activity (project_id, user_id, action, description) VALUES (?,?,?,?)'
    )->execute([$id, $_SESSION['user_id'], 'updated', 'Project details updated']);

    log_activity('projects', 'update', "Updated project #$id", $id);
    set_flash('success', 'Project updated.');
} else {
    $db->prepare(
        'INSERT INTO projects (title, lead_id, client_name, client_email, client_phone,
         client_company, address, city, state, zip, project_type, status,
         scope_summary, budget, contract_amount, start_date, end_date, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $title, $lead_id, $client_name, $client_email ?: null, $client_phone ?: null,
        $client_company ?: null, $address ?: null, $city ?: null, $state ?: null,
        $zip ?: null, $project_type, $status, $scope_summary ?: null,
        $budget, $contract_amount, $start_date, $end_date, $_SESSION['user_id']
    ]);

    $id = (int)$db->lastInsertId();

    // If converted from a lead, mark the lead as Won
    if ($lead_id) {
        $db->prepare('UPDATE leads SET status = ? WHERE id = ?')->execute(['Won', $lead_id]);
        $db->prepare(
            'INSERT INTO lead_activity (lead_id, user_id, action, description) VALUES (?,?,?,?)'
        )->execute([$lead_id, $_SESSION['user_id'], 'converted', "Converted to project #$id"]);
    }

    $db->prepare(
        'INSERT INTO project_activity (project_id, user_id, action, description) VALUES (?,?,?,?)'
    )->execute([$id, $_SESSION['user_id'], 'created', 'Project created']);

    log_activity('projects', 'create', "Created project #$id — $title", $id);
    set_flash('success', 'Project created successfully.');
    $redirect = APP_URL . '/modules/projects/detail.php?id=' . $id;
}

header('Location: ' . $redirect);
exit;
