<?php $user = current_user(); ?>
<header class="topbar d-flex align-items-center px-3 px-lg-4">

    <!-- Mobile sidebar toggle -->
    <button class="btn btn-link sidebar-toggle me-2 d-lg-none p-0" id="sidebarToggle" aria-label="Toggle sidebar">
        <i class="bi bi-list fs-4 text-secondary"></i>
    </button>

    <!-- Page title -->
    <h6 class="topbar-title mb-0 fw-semibold text-secondary">
        <?= e($page_title ?? 'Dashboard') ?>
    </h6>

    <!-- Right side -->
    <div class="ms-auto d-flex align-items-center gap-3">

        <!-- Notifications placeholder -->
        <button class="btn btn-link p-0 position-relative" title="Notifications">
            <i class="bi bi-bell fs-5 text-secondary"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem;">3</span>
        </button>

        <!-- User dropdown -->
        <div class="dropdown">
            <button class="btn btn-link p-0 d-flex align-items-center gap-2 text-decoration-none" data-bs-toggle="dropdown">
                <div class="avatar-circle">
                    <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                </div>
                <span class="d-none d-md-inline text-dark fw-medium small"><?= e($user['name'] ?? '') ?></span>
                <i class="bi bi-chevron-down small text-secondary"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                <li><h6 class="dropdown-header"><?= e($user['email'] ?? '') ?></h6></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/modules/settings/"><i class="bi bi-gear me-2"></i>Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/logout.php"><i class="bi bi-box-arrow-left me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</header>
