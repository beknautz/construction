/* ============================================================
   Construction OS — App JavaScript
   ============================================================ */

'use strict';

// ---- Sidebar toggle (mobile) ----
(function () {
    const toggle  = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (!toggle || !sidebar) return;

    // Create overlay
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    function openSidebar() {
        sidebar.classList.add('show');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    toggle.addEventListener('click', openSidebar);
    overlay.addEventListener('click', closeSidebar);
})();

// ---- Toast helper ----
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const iconMap = {
        success: 'bi-check-circle-fill',
        danger:  'bi-x-circle-fill',
        warning: 'bi-exclamation-triangle-fill',
        info:    'bi-info-circle-fill',
    };

    const colorMap = {
        success: 'text-success',
        danger:  'text-danger',
        warning: 'text-warning',
        info:    'text-info',
    };

    const icon  = iconMap[type]  || iconMap.info;
    const color = colorMap[type] || colorMap.info;

    const el = document.createElement('div');
    el.className  = 'toast align-items-center border-0 shadow-sm';
    el.role       = 'alert';
    el.setAttribute('aria-live', 'assertive');
    el.innerHTML  = `
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2">
                <i class="bi ${icon} ${color}"></i>
                <span>${message}</span>
            </div>
            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>`;

    container.appendChild(el);

    const toast = new bootstrap.Toast(el, { delay: 4000 });
    toast.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

// ---- HTMX global events ----
document.body.addEventListener('htmx:responseError', function (e) {
    showToast('An error occurred. Please try again.', 'danger');
});

document.body.addEventListener('htmx:afterRequest', function (e) {
    const xhr = e.detail.xhr;
    // If the server sends X-Toast header, display it
    const toastMsg  = xhr.getResponseHeader('X-Toast-Message');
    const toastType = xhr.getResponseHeader('X-Toast-Type') || 'info';
    if (toastMsg) showToast(toastMsg, toastType);
});

// ---- Confirm-delete pattern ----
document.body.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-confirm]');
    if (!btn) return;
    if (!confirm(btn.dataset.confirm)) {
        e.preventDefault();
        e.stopImmediatePropagation();
    }
});

// ---- Auto-dismiss alerts ----
document.querySelectorAll('.alert-auto-dismiss').forEach(function (el) {
    setTimeout(() => {
        const alert = bootstrap.Alert.getOrCreateInstance(el);
        alert.close();
    }, 5000);
});
