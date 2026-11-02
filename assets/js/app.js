/**
 * assets/js/app.js
 * Shared JavaScript utilities for the AI Voice Summarizer.
 * Page-specific scripts are embedded inline in each PHP page.
 */

// ─────────────────────────────────────────────────────────────
// Global helpers
// ─────────────────────────────────────────────────────────────

/**
 * Flash a SweetAlert2 toast notification.
 * @param {string} message - The message to show.
 * @param {'success'|'error'|'info'|'warning'} [type] - Toast colour.
 */
function flashToast(message, type = 'success') {
    const bgMap = {
        success: '#28a745',
        error:   '#dc3545',
        info:    '#0d6efd',
        warning: '#ffc107',
    };
    const iconMap = {
        success: 'fa-check',
        error:   'fa-xmark',
        info:    'fa-circle-info',
        warning: 'fa-triangle-exclamation',
    };
    Swal.fire({
        toast:            true,
        position:         'top-end',
        showConfirmButton: false,
        timer:            3000,
        timerProgressBar: true,
        background:       bgMap[type] || bgMap.info,
        html:             `<i class="fa-solid ${iconMap[type] || iconMap.info} me-2"></i>${message}`,
    });
}

/**
 * Escape a string for safe HTML insertion.
 * @param {string} s
 * @returns {string}
 */
function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
}

/**
 * Build a query string from an object (useful for pagination links).
 * @param {object} obj
 * @returns {string}
 */
function buildQueryString(obj) {
    return new URLSearchParams(
        Object.fromEntries(
            Object.entries(obj).filter(([, v]) => v !== null && v !== undefined && v !== '')
        )
    ).toString();
}

/**
 * Copy text to clipboard with a fallback for older browsers.
 * @param {string} text
 */
function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text)
            .then(() => flashToast('Copied to clipboard.'))
            .catch(() => fallbackCopy(text));
    } else {
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity  = '0';
    document.body.appendChild(ta);
    ta.select();
    try {
        document.execCommand('copy');
        flashToast('Copied to clipboard.');
    } catch {
        flashToast('Copy failed – please copy manually.', 'error');
    }
    document.body.removeChild(ta);
}

// ─────────────────────────────────────────────────────────────
// Auto-dismiss inline alerts
// ─────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Hide auto-dismiss alerts after 8 seconds
    document.querySelectorAll('.alert.auto-dismiss').forEach(el => {
        setTimeout(() => {
            el.classList.add('fade');
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        }, 8000);
    });

    // Auto-focus first visible form input on auth pages
    if (document.body.classList.contains('auth-body')) {
        const firstInput = document.querySelector('input[type="text"], input[type="email"], input[type="password"]');
        if (firstInput) firstInput.focus();
    }
});
