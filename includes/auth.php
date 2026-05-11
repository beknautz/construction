<?php
// ============================================================
// Authentication Helpers  (multi-tenant aware)
// ============================================================

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

/** Redirect if the current user is not the super-admin (tenant_id = NULL, role = admin) */
function require_superadmin(): void
{
    require_login();
    $u = current_user();
    if (($u['role'] ?? '') !== 'admin' || !empty($u['tenant_id'])) {
        http_response_code(403);
        die('Access denied.');
    }
}

function current_user(): array
{
    return $_SESSION['user'] ?? [];
}

/** Return the current tenant_id (null for super-admin) */
function current_tenant_id(): ?int
{
    return $_SESSION['tenant_id'] ?? null;
}

/**
 * login_user — authenticates and loads tenant context into session.
 * Returns false on bad credentials, or 'suspended'/'trial_expired' for tenant issues.
 */
function login_user(string $email, string $password)
{
    $db   = get_db();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    // Super-admin has no tenant
    $tenant    = null;
    $tenant_id = null;

    if (!empty($user['tenant_id'])) {
        $ts = $db->prepare('SELECT t.*, p.ai_calls_limit, p.projects_limit, p.users_limit, p.name AS plan_name
                            FROM tenants t
                            JOIN subscription_plans p ON p.id = t.plan_id
                            WHERE t.id = ? LIMIT 1');
        $ts->execute([$user['tenant_id']]);
        $tenant = $ts->fetch();

        if (!$tenant) return false;

        if ($tenant['status'] === 'suspended' || $tenant['status'] === 'canceled') {
            return 'suspended';
        }

        // Trial expired?
        if ($tenant['status'] === 'trial' && $tenant['trial_ends_at'] && strtotime($tenant['trial_ends_at']) < time()) {
            return 'trial_expired';
        }

        $tenant_id = (int)$tenant['id'];
    }

    // Update last login
    $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['tenant_id'] = $tenant_id;
    $_SESSION['user']      = [
        'id'        => $user['id'],
        'name'      => $user['name'],
        'email'     => $user['email'],
        'role'      => $user['role'],
        'tenant_id' => $tenant_id,
    ];

    if ($tenant) {
        $_SESSION['tenant'] = [
            'id'              => $tenant['id'],
            'company_name'    => $tenant['company_name'],
            'status'          => $tenant['status'],
            'plan_name'       => $tenant['plan_name'],
            'ai_calls_used'   => $tenant['ai_calls_used'],
            'ai_calls_limit'  => $tenant['ai_calls_limit'],
            'trial_ends_at'   => $tenant['trial_ends_at'],
        ];
    }

    return true;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function has_role(string ...$roles): bool
{
    $user = current_user();
    return in_array($user['role'] ?? '', $roles, true);
}

/** Refresh tenant data in session (call after plan/status changes) */
function refresh_tenant_session(): void
{
    $tid = current_tenant_id();
    if (!$tid) return;

    $db = get_db();
    $ts = $db->prepare('SELECT t.*, p.ai_calls_limit, p.name AS plan_name
                        FROM tenants t JOIN subscription_plans p ON p.id = t.plan_id
                        WHERE t.id = ? LIMIT 1');
    $ts->execute([$tid]);
    $tenant = $ts->fetch();
    if (!$tenant) return;

    $_SESSION['tenant'] = [
        'id'             => $tenant['id'],
        'company_name'   => $tenant['company_name'],
        'status'         => $tenant['status'],
        'plan_name'      => $tenant['plan_name'],
        'ai_calls_used'  => $tenant['ai_calls_used'],
        'ai_calls_limit' => $tenant['ai_calls_limit'],
        'trial_ends_at'  => $tenant['trial_ends_at'],
    ];
}
