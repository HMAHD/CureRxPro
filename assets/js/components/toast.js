// assets/js/components/toast.js

let container;

function ensureContainer() {
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText =
            'position:fixed;top:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;';
        document.body.appendChild(container);
    }
}

export function showToast(message, type = 'info', duration = 3000) {
    ensureContainer();
    const colors = { info: '#3b82f6', success: '#22c55e', error: '#ef4444', warning: '#f59e0b' };
    const toast = document.createElement('div');
    toast.style.cssText =
        `background:${colors[type] ?? colors.info};color:#fff;padding:.75rem 1.25rem;` +
        `border-radius:.5rem;max-width:320px;font-size:.875rem;box-shadow:0 2px 8px rgba(0,0,0,.2);` +
        `opacity:1;transition:opacity .3s;`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}
