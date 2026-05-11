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
        <?= nav_link('Settings', 'bi-gear-fill', APP_URL . '/modules/settings/', 'settings', $current_module) ?>
        <a class="nav-link text-danger" href="<?= APP_URL ?>/logout.php">
            <i class="bi bi-box-arrow-left"></i>
            <span>Logout</span>
        </a>
    </div>
</nav>
<!-- /Sidebar -->
