// assets/js/components/modal.js

export function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
}

export function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
}

export function closeAllModals() {
    document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
}
