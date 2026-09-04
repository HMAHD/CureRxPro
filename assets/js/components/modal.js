// assets/js/components/modal.js

export function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'flex';
}

export function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
}

export function closeAllModals() {
    document.querySelectorAll('.modal-overlay').forEach(m => { m.style.display = 'none'; });
}
