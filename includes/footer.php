    </div><!-- /.main-content -->
</div><!-- /.app-wrapper -->

<!-- Toast container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index:1090"></div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- HTMX -->
<script src="https://unpkg.com/htmx.org@1.9.12/dist/htmx.min.js"></script>
<!-- App JS -->
<script src="<?= APP_URL ?>/assets/js/app.js"></script>

<?php
// Render flash message as toast
$flash = get_flash();
if ($flash): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    showToast(<?= json_encode($flash['message']) ?>, <?= json_encode($flash['type']) ?>);
});
</script>
<?php endif; ?>

</body>
</html>
