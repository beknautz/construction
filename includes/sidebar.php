<?php
$current_module = $current_module ?? '';
$co = company_settings();

function nav_link(string $label, string $icon, string $href, string $module, string $current): string {
    $active = ($module === $current) ? 'active' : '';
    return <<<HTML
    <li class="nav-item">
        <a class="nav-link {$active}" href="{$href}">
            <i class="bi {$icon}"></i>
            <span>{$label}</span>
        </a>
    </li>
    HTML;
}
?>
<!-- Sidebar -->
<nav id="sidebar" class="sidebar d-flex flex-column">

    <!-- Brand -->
    <div class="sidebar-brand">
        <a href="<?= APP_URL ?>/modules/dashboard/" class="brand-link">
            <i class="bi bi-hammer brand-icon"></i>
            <span class="brand-name"><?= e($co['company_name'] ?? APP_NAME) ?></span>
        </a>
    </div>

    <!-- Navigation -->
    <ul class="nav flex-column sidebar-nav mt-2">

        <li class="nav-section-label">Main</li>
        <?= nav_link('Dashboard',  'bi-speedometer2',    APP_URL . '/modules/dashboard/',         'dashboard',    $current_module) ?>
        <?= nav_link('CRM',        'bi-people-fill',     APP_URL . '/modules/crm/',               'crm',          $current_module) ?>
        <?= nav_link('Leads',      'bi-funnel-fill',     APP_URL . '/modules/leads/',             'leads',        $current_module) ?>
        <?= nav_link('Projects',   'bi-building-fill',   APP_URL . '/modules/projects/',          'projects',     $current_module) ?>

        <li class="nav-section-label mt-2">Financials</li>
        <?= nav_link('Estimates',  'bi-calculator-fill', APP_URL . '/modules/estimates/',         'estimates',    $current_module) ?>
        <?= nav_link('Proposals',  'bi-file-earmark-text-fill', APP_URL . '/modules/proposals/', 'proposals',    $current_module) ?>
        <?= nav_link('Change Orders', 'bi-arrow-repeat', APP_URL . '/modules/change-orders/',    'change-orders',$current_module) ?>

        <li class="nav-section-label mt-2">Operations</li>
        <?= nav_link('Schedule',      'bi-calendar3',        APP_URL . '/modules/schedule/',      'schedule',     $current_module) ?>
        <?= nav_link('Photos',        'bi-images',           APP_URL . '/modules/photos/',        'photos',       $current_module) ?>
        <?= nav_link('Takeoffs',      'bi-rulers',           APP_URL . '/modules/takeoffs/',      'takeoffs',     $current_module) ?>

        <li class="nav-section-label mt-2">Sourcing</li>
        <?= nav_link('Suppliers',       'bi-shop-window',    APP_URL . '/modules/suppliers/',     'suppliers',    $current_module) ?>
        <?= nav_link('Subcontractors',  'bi-person-badge',   APP_URL . '/modules/subcontractors/','subcontractors',$current_module) ?>

        <li class="nav-section-label mt-2">Insights</li>
        <?= nav_link('Analytics',  'bi-bar-chart-fill',  APP_URL . '/modules/analytics/',        'analytics',    $current_module) ?>

    </ul>

    <!-- Bottom links -->
    <div class="sidebar-footer mt-auto">
        <?php if (current_tenant_id() !== null): ?>
        <?php
        // Show AI usage mini-bar for tenants
        $tenantMeta = $_SESSION['tenant'] ?? [];
        $usedCalls  = (int)($tenantMeta['ai_calls_used']  ?? 0);
        $limitCalls = (int)($tenantMeta['ai_calls_limit'] ?? 100);
        $barPct     = $limitCalls > 0 ? min(100, round($usedCalls / $limitCalls * 100)) : 0;
        $barCls     = $barPct >= 90 ? 'bg-danger' : ($barPct >= 70 ? 'bg-warning' : 'bg-success');
        ?>
        <a href="<?= APP_URL ?>/billing/" class="nav-link <?= $current_module === 'billing' ? 'active' : '' ?> position-relative py-2">
            <i class="bi bi-credit-card"></i>
            <span>
                Billing
                <small class="d-block text-muted" style="font-size:.7rem;line-height:1.2">
                    <?= number_format($usedCalls) ?> / <?= number_format($limitCalls) ?> AI calls
                </small>
            </span>
        </a>
        <div class="px-3 pb-1" style="margin-top:-6px;">
            <div class="progress" style="height:4px;">
                <div class="progress-bar <?= $barCls ?>" style="width:<?= $barPct ?>%"></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (current_tenant_id() === null && has_role('admin')): ?>
        <?= nav_link('Admin', 'bi-shield-lock-fill', APP_URL . '/admin/', 'admin', $current_module) ?>
        <?php endif; ?>

        <?= nav_link('Settings', 'bi-gear-fill', APP_URL . '/modules/settings/', 'settings', $current_module) ?>
        <a class="nav-link text-danger" href="<?= APP_URL ?>/logout.php">
            <i class="bi bi-box-arrow-left"></i>
            <span>Logout</span>
        </a>
    </div>
</nav>
<!-- /Sidebar -->
