<?php
// ============================================================
// Global Helper Functions  (PHP 7.4+ compatible)
// ============================================================

/** Safely escape HTML output */
function e($val): string
{
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Format a dollar amount */
function money($amount): string
{
    return '$' . number_format((float)$amount, 2);
}

/** Format a date for display */
function fmt_date(?string $date, string $format = 'M j, Y'): string
{
    if (!$date) return '—';
    return (new DateTime($date))->format($format);
}

/** Return a Bootstrap badge class for a lead/project/proposal status */
function status_badge(string $status): string
{
    $map = [
        // lead statuses
        'New'                    => 'bg-primary',
        'Contacted'              => 'bg-info text-dark',
        'Site Visit Scheduled'   => 'bg-warning text-dark',
        'Estimate Needed'        => 'bg-secondary',
        'Proposal Sent'          => 'bg-purple',
        'Won'                    => 'bg-success',
        'Lost'                   => 'bg-danger',
        // project statuses
        'Planning'               => 'bg-secondary',
        'Estimating'             => 'bg-info text-dark',
        'Proposal'               => 'bg-warning text-dark',
        'Contracted'             => 'bg-primary',
        'In Progress'            => 'bg-success',
        'Waiting'                => 'bg-warning text-dark',
        'Completed'              => 'bg-success',
        'Closed'                 => 'bg-dark',
        // proposal statuses
        'Draft'                  => 'bg-secondary',
        'Sent'                   => 'bg-primary',
        'Viewed'                 => 'bg-info text-dark',
        'Approved'               => 'bg-success',
        'Declined'               => 'bg-danger',
    ];
    $cls = $map[$status] ?? 'bg-secondary';
    return '<span class="badge ' . $cls . '">' . e($status) . '</span>';
}

/** Flash message helpers */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = compact('type', 'message');
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/** Log an activity */
function log_activity(string $module, string $action, string $description = '', ?int $record_id = null): void
{
    try {
        $db = get_db();
        $db->prepare(
            'INSERT INTO activity_log (user_id, module, record_id, action, description, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $_SESSION['user_id'] ?? null,
            $module,
            $record_id,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        // Never let logging crash the app
    }
}

/** Get company settings (cached per request) */
function company_settings(): array
{
    static $settings = null;
    if ($settings === null) {
        try {
            $settings = get_db()->query('SELECT * FROM company_settings WHERE id = 1')->fetch() ?: [];
        } catch (Throwable $e) {
            $settings = [];
        }
    }
    return $settings;
}

/** Dashboard stat query helper */
function db_count(string $table, string $where = '', array $params = []): int
{
    try {
        $sql  = "SELECT COUNT(*) FROM `$table`" . ($where ? " WHERE $where" : '');
        $stmt = get_db()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/** Dashboard sum query helper */
function db_sum(string $table, string $column, string $where = '', array $params = []): float
{
    try {
        $sql  = "SELECT COALESCE(SUM(`$column`), 0) FROM `$table`" . ($where ? " WHERE $where" : '');
        $stmt = get_db()->prepare($sql);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0.0;
    }
}
