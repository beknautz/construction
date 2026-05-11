<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/modules/settings/');
    exit;
}

$db  = get_db();
$tab = $_POST['tab'] ?? 'company';
$tid = tid();

if ($tab === 'company') {
    $fields = [
        'company_name'   => trim($_POST['company_name']   ?? ''),
        'phone'          => trim($_POST['phone']          ?? ''),
        'email'          => trim($_POST['email']          ?? ''),
        'address'        => trim($_POST['address']        ?? ''),
        'city'           => trim($_POST['city']           ?? ''),
        'state'          => strtoupper(trim($_POST['state'] ?? '')),
        'zip'            => trim($_POST['zip']            ?? ''),
        'website'        => trim($_POST['website']        ?? ''),
        'license_number' => trim($_POST['license_number'] ?? ''),
        'insurance_info' => trim($_POST['insurance_info'] ?? ''),
        'proposal_terms' => trim($_POST['proposal_terms'] ?? ''),
    ];

    if (!$fields['company_name']) {
        set_flash('danger', 'Company name is required.');
        header('Location: ' . APP_URL . '/modules/settings/');
        exit;
    }

    // Check if row exists
    $existing = $db->prepare('SELECT id FROM company_settings WHERE ' . ($tid !== null ? 'tenant_id = ?' : 'id = 1') . ' LIMIT 1');
    $existing->execute($tid !== null ? [$tid] : []);
    $row = $existing->fetch();

    if ($row) {
        $set = implode(', ', array_map(function($k) { return "`$k` = ?"; }, array_keys($fields)));
        $db->prepare("UPDATE company_settings SET {$set} WHERE id = ?")
           ->execute(array_merge(array_values($fields), [$row['id']]));
    } else {
        $cols = implode(', ', array_map(function($k) { return "`$k`"; }, array_keys($fields)));
        $phs  = implode(', ', array_fill(0, count($fields), '?'));
        $vals = array_values($fields);
        if ($tid !== null) {
            $cols .= ', tenant_id';
            $phs  .= ', ?';
            $vals[] = $tid;
        }
        $db->prepare("INSERT INTO company_settings ({$cols}) VALUES ({$phs})")->execute($vals);
    }

    set_flash('success', 'Company info saved.');

} elseif ($tab === 'rates') {
    $rateFields = [
        'default_markup'       => max(0, (float)($_POST['default_markup']       ?? 20)),
        'default_tax'          => max(0, (float)($_POST['default_tax']          ?? 8)),
        'default_waste'        => max(0, (float)($_POST['default_waste']        ?? 5)),
        'labor_rate_general'   => max(0, (float)($_POST['labor_rate_general']   ?? 0)),
        'labor_rate_carpenter' => max(0, (float)($_POST['labor_rate_carpenter'] ?? 0)),
        'labor_rate_electrician'=>max(0, (float)($_POST['labor_rate_electrician']?? 0)),
        'labor_rate_plumber'   => max(0, (float)($_POST['labor_rate_plumber']   ?? 0)),
        'labor_rate_hvac'      => max(0, (float)($_POST['labor_rate_hvac']      ?? 0)),
        'labor_rate_painter'   => max(0, (float)($_POST['labor_rate_painter']   ?? 0)),
        'labor_rate_equipment' => max(0, (float)($_POST['labor_rate_equipment'] ?? 0)),
    ];

    $existing = $db->prepare('SELECT id FROM company_settings WHERE ' . ($tid !== null ? 'tenant_id = ?' : 'id = 1') . ' LIMIT 1');
    $existing->execute($tid !== null ? [$tid] : []);
    $row = $existing->fetch();

    if ($row) {
        $set = implode(', ', array_map(function($k) { return "`$k` = ?"; }, array_keys($rateFields)));
        $db->prepare("UPDATE company_settings SET {$set} WHERE id = ?")
           ->execute(array_merge(array_values($rateFields), [$row['id']]));
    } else {
        // No company row yet — create one with defaults
        $cols = 'company_name, ' . implode(', ', array_map(function($k) { return "`$k`"; }, array_keys($rateFields)));
        $phs  = '?, ' . implode(', ', array_fill(0, count($rateFields), '?'));
        $vals = array_merge(['My Construction Co.'], array_values($rateFields));
        if ($tid !== null) { $cols .= ', tenant_id'; $phs .= ', ?'; $vals[] = $tid; }
        $db->prepare("INSERT INTO company_settings ({$cols}) VALUES ({$phs})")->execute($vals);
    }

    set_flash('success', 'Estimate defaults saved. Claude will use these rates on the next AI estimate.');

} elseif ($tab === 'ai' && $tid === null) {
    // Super-admin only
    $apiKey = trim($_POST['anthropic_api_key'] ?? '');
    $model  = trim($_POST['anthropic_model']   ?? '');

    if ($apiKey) {
        $db->prepare("INSERT INTO ai_settings (setting_key, setting_value) VALUES ('anthropic_api_key',?)
                      ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$apiKey]);
    }
    if ($model) {
        $db->prepare("INSERT INTO ai_settings (setting_key, setting_value) VALUES ('anthropic_model',?)
                      ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$model]);
    }
    set_flash('success', 'AI settings saved.');
}

header('Location: ' . APP_URL . '/modules/settings/');
exit;
