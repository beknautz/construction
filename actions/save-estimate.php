<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/modules/estimates/');
    exit;
}

$db       = get_db();
$id       = (int)($_POST['id'] ?? 0);
$redirect = $_POST['redirect'] ?? APP_URL . '/modules/estimates/';

$title      = trim($_POST['title']      ?? '');
$project_id = (int)($_POST['project_id'] ?? 0) ?: null;
$client_name= trim($_POST['client_name'] ?? '');
$status     = trim($_POST['status']     ?? 'Draft');
$markup_pct = (float)($_POST['markup_pct'] ?? 20);
$tax_pct    = (float)($_POST['tax_pct']    ?? 8);
$waste_pct  = (float)($_POST['waste_pct']  ?? 5);
$notes      = trim($_POST['notes']      ?? '');

if (!$title) {
    set_flash('danger', 'Estimate title is required.');
    header('Location: ' . $redirect);
    exit;
}

if ($id) {
    $db->prepare(
        'UPDATE estimates SET title=?, project_id=?, client_name=?, status=?,
         markup_pct=?, tax_pct=?, waste_pct=?, notes=?, updated_at=NOW()
         WHERE id=?'
    )->execute([$title, $project_id, $client_name ?: null, $status,
                $markup_pct, $tax_pct, $waste_pct, $notes ?: null, $id]);

    // Recalculate totals with new percentages
    recalc_estimate($id, $db);
    set_flash('success', 'Estimate updated.');
} else {
    $db->prepare(
        'INSERT INTO estimates (title, project_id, client_name, status,
         markup_pct, tax_pct, waste_pct, notes, created_by)
         VALUES (?,?,?,?,?,?,?,?,?)'
    )->execute([$title, $project_id, $client_name ?: null, $status,
                $markup_pct, $tax_pct, $waste_pct, $notes ?: null, $_SESSION['user_id']]);
    $id = (int)$db->lastInsertId();
    log_activity('estimates', 'create', "Created estimate #$id — $title", $id);
    set_flash('success', 'Estimate created.');
    $redirect = APP_URL . '/modules/estimates/detail.php?id=' . $id;
}

header('Location: ' . $redirect);
exit;

// ---------------------------------------------------------------
function recalc_estimate(int $estimate_id, PDO $db): void
{
    $est = $db->prepare('SELECT markup_pct, tax_pct, waste_pct FROM estimates WHERE id = ?');
    $est->execute([$estimate_id]);
    $est = $est->fetch();
    if (!$est) return;

    $subtotal = (float)$db->prepare(
        'SELECT COALESCE(SUM(line_total),0) FROM estimate_line_items WHERE estimate_id = ?'
    )->execute([$estimate_id]) ? 0 : 0;

    $s = $db->prepare('SELECT COALESCE(SUM(line_total),0) FROM estimate_line_items WHERE estimate_id = ?');
    $s->execute([$estimate_id]);
    $subtotal = (float)$s->fetchColumn();

    $waste    = round($subtotal  * ($est['waste_pct']  / 100), 2);
    $base     = $subtotal + $waste;
    $markup   = round($base      * ($est['markup_pct'] / 100), 2);
    $tax      = round(($base + $markup) * ($est['tax_pct'] / 100), 2);
    $grand    = round($base + $markup + $tax, 2);

    $db->prepare(
        'UPDATE estimates SET subtotal=?, waste_amount=?, markup_amount=?, tax_amount=?, grand_total=?, updated_at=NOW()
         WHERE id=?'
    )->execute([$subtotal, $waste, $markup, $tax, $grand, $estimate_id]);
}
