<?php
/**
 * layout-header.php
 * Full-page layout wrapper — includes header, sidebar, topbar, and opens .page-content.
 * Set $pageTitle and $current_module before including.
 * Companion: layout-footer.php
 */
$page_title     = $pageTitle     ?? 'Page';
$current_module = $current_module ?? '';
include __DIR__ . '/header.php';
?>
<div class="app-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/topbar.php'; ?>
        <div class="page-content">
