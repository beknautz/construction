<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Already logged in → dashboard
if (is_logged_in()) {
    header('Location: ' . APP_URL . '/modules/dashboard/');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } elseif (login_user($email, $password)) {
        log_activity('auth', 'login', 'User logged in');
        header('Location: ' . APP_URL . '/modules/dashboard/');
        exit;
    } else {
        $error = 'Invalid email or password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body class="login-page">

<div class="login-card">
    <div class="text-center mb-4">
        <div class="login-logo mb-2"><i class="bi bi-hammer"></i></div>
        <h1 class="login-title">Construction OS</h1>
        <p class="text-muted small mb-0">Sign in to your account</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 py-2" role="alert">
        <i class="bi bi-exclamation-circle-fill"></i>
        <div><?= e($error) ?></div>
    </div>
    <?php endif; ?>

    <form method="POST" action="" novalidate>
        <div class="mb-3">
            <label for="email" class="form-label fw-medium small">Email Address</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0">
                    <i class="bi bi-envelope text-muted"></i>
                </span>
                <input type="email" class="form-control border-start-0 ps-0"
                       id="email" name="email"
                       value="<?= e($_POST['email'] ?? '') ?>"
                       placeholder="you@company.com"
                       autocomplete="email" required autofocus>
            </div>
        </div>

        <div class="mb-4">
            <label for="password" class="form-label fw-medium small">Password</label>
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0">
                    <i class="bi bi-lock text-muted"></i>
                </span>
                <input type="password" class="form-control border-start-0 ps-0"
                       id="password" name="password"
                       placeholder="••••••••"
                       autocomplete="current-password" required>
            </div>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-accent btn-lg fw-semibold">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>
        </div>
    </form>

    <div class="text-center mt-4">
        <small class="text-muted">
            Default: <code>admin@constructionos.com</code> / <code>admin123</code>
        </small>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
