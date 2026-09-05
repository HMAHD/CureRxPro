// assets/js/modules/medicines.js
import * as api   from '../api.js';
import * as state from '../state.js';
import { showToast } from '../components/toast.js';
import { escapeHtml } from '../api.js';
import { openModal, closeModal } from '../components/modal.js';

let currentPage = 0;
const PAGE_SIZE  = 50;

export function init() {
    initCategoryFilterChips();
    loadMedicinesList();
    initDoctorManagement();
    wirePaginationButtons();
    wireMedicineModal();
    loadDoctorsList();
}

function wirePaginationButtons() {
    const prev = document.getElementById('btn-med-prev');
    const next = document.getElementById('btn-med-next');
    if (prev) prev.addEventListener('click', () => {
        if (currentPage > 0) { currentPage--; loadMedicinesList(); }
    });
    if (next) next.addEventListener('click', () => {
        currentPage++;
        loadMedicinesList();
    });
}

function initCategoryFilterChips() {
    const chipsContainer = document.getElementById('med-category-filter-chips');
    if (!chipsContainer) return;

    chipsContainer.querySelectorAll('.template-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            chipsContainer.querySelectorAll('.template-chip').forEach(c => {
                c.classList.remove('active');
                c.style.background = '';
                c.style.color = '';
            });
            chip.classList.add('active');
            chip.style.background = 'var(--teal-600)';
            chip.style.color = '#fff';

            state.set('activeMedCategory', chip.getAttribute('data-cat') || 'all');
            currentPage = 0;
            loadMedicinesList();
        });
    });
}

async function loadMedicinesList() {
    const tbody = document.getElementById('tbody-medicines');
    if (!tbody) return;

    const category = state.get('activeMedCategory') || 'all';

    try {
        const json = await api.get('/medicines.php', {
            type: category,
            limit: PAGE_SIZE,
            offset: currentPage * PAGE_SIZE,
        });

        if (json.status === 'success') {
            tbody.innerHTML = json.data.map(m => `
              <tr>
                <td><strong>${escapeHtml(m.name)}</strong></td>
                <td>${escapeHtml(m.generic_name || '-')}</td>
                <td><span class="template-chip">${escapeHtml(m.type || 'Tablet')}</span></td>
                <td>${escapeHtml(m.default_strength || '-')}</td>
                <td><strong>LKR ${parseFloat(m.unit_price || 0).toFixed(2)}</strong></td>
                <td>
                  <button class="btn btn-danger btn-sm btn-del-med" data-id="${m.id}"><i class="fa-solid fa-trash"></i></button>
                </td>
              </tr>
            `).join('');

            tbody.querySelectorAll('.btn-del-med').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (confirm('Remove drug from master database?')) {
                        const mid = btn.getAttribute('data-id');
                        try {
                            await api.del(`/medicines.php?id=${mid}`);
                            loadMedicinesList();
                        } catch (err) {
                            showToast(err.message || 'Failed to delete medicine.', 'error');
                        }
                    }
                });
            });
        }
    } catch (err) {
        console.error(err);
        showToast('Failed to load medicines list.', 'error');
    }
}

async function loadDoctorsList() {
    const dropdown = document.getElementById('active-doctor-select');
    const tbody = document.getElementById('tbody-profile-doctors');
    if (!dropdown) return;

    try {
        const json = await api.get('/doctors.php');

        if (json.status === 'success' && json.data) {
            const doctors = json.data;

            // 1. Populate top bar switcher dropdown
            const settings = state.get('settings');
            const activeId = settings ? settings.active_doctor_id : 1;
            dropdown.innerHTML = doctors.map(d => `
              <option value="${d.id}" ${d.id == activeId ? 'selected' : ''}>${escapeHtml(d.name)}</option>
            `).join('');

            // 1.5. Populate history doctor filter dropdown
            const filterDropdown = document.getElementById('history-doctor-filter');
            if (filterDropdown) {
                const currentFilter = filterDropdown.value || 'all';
                filterDropdown.innerHTML = '<option value="all">All Doctors</option>' + doctors.map(d => `
                  <option value="${d.id}">${escapeHtml(d.name)}</option>
                `).join('');
                if ([...filterDropdown.options].some(opt => opt.value === currentFilter)) {
                    filterDropdown.value = currentFilter;
                } else {
                    filterDropdown.value = 'all';
                }
            }

            // 1.6. Populate queue assign doctor select dropdown
            const assignDropdown = document.getElementById('queue-assign-doctor-select');
            if (assignDropdown) {
                assignDropdown.innerHTML = doctors.map(d => `
                  <option value="${d.id}">${escapeHtml(d.name)}</option>
                `).join('');
            }

            // 1.7. Populate channeling doctor select dropdown
            const chlDocSelect = document.getElementById('chl-doctor-select');
            if (chlDocSelect) {
                chlDocSelect.innerHTML = doctors.map(d => `
                  <option value="${d.id}">${escapeHtml(d.name)}</option>
                `).join('');
            }

            // 2. Populate settings table
            if (tbody) {
                if (doctors.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;color:var(--text-muted);">No doctors added yet.</td></tr>`;
                    return;
                }

                tbody.innerHTML = doctors.map(d => `
                  <tr>
                    <td><strong>${escapeHtml(d.name)}</strong></td>
                    <td>${escapeHtml(d.qualifications || '-')}</td>
                    <td>${escapeHtml(d.slmc_reg || '-')}</td>
                    <td>
                      <button class="btn btn-outline btn-sm btn-edit-doc" data-id="${d.id}"><i class="fa-solid fa-pen"></i></button>
                      <button class="btn btn-danger btn-sm btn-del-doc" data-id="${d.id}"><i class="fa-solid fa-trash"></i></button>
                    </td>
                  </tr>
                `).join('');

                // Attach edit event listeners
                tbody.querySelectorAll('.btn-edit-doc').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const docId = btn.getAttribute('data-id');
                        const doc = doctors.find(x => x.id == docId);
                        if (doc) {
                            document.getElementById('doc-profile-id').value = doc.id;
                            document.getElementById('doc-profile-name').value = doc.name;
                            document.getElementById('doc-profile-qualifications').value = doc.qualifications;
                            document.getElementById('doc-profile-slmc').value = doc.slmc_reg;

                            document.getElementById('btn-save-doctor-profile').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Changes';
                            document.getElementById('btn-clear-doctor-profile').style.display = 'inline-block';
                        }
                    });
                });

                // Attach delete event listeners
                tbody.querySelectorAll('.btn-del-doc').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        if (confirm('Delete this doctor profile?')) {
                            const docId = btn.getAttribute('data-id');
                            try {
                                const delJson = await api.del(`/doctors.php?id=${docId}`);
                                if (delJson.status === 'success') {
                                    loadDoctorsList();
                                } else {
                                    showToast(delJson.message || 'Failed to delete doctor.', 'error');
                                }
                            } catch (err) {
                                showToast(err.message || 'Failed to delete doctor.', 'error');
                            }
                        }
                    });
                });
            }
        }
    } catch (err) {
        console.error('Failed to load doctors list', err);
    }
}

function initDoctorManagement() {
    // 1. Top bar select change
    const select = document.getElementById('active-doctor-select');
    if (select) {
        select.addEventListener('change', async () => {
            const docId = select.value;
            try {
                const json = await api.post('/settings.php', {
                    action: 'set_active_doctor',
                    active_doctor_id: docId,
                });
                if (json.status === 'success') {
                    const settings = state.get('settings');
                    if (settings) {
                        settings.active_doctor_id = docId;
                    }
                    if (typeof loadWaitingQueue === 'function') loadWaitingQueue();
                } else {
                    showToast('Failed to update active doctor profile.', 'error');
                }
            } catch (err) {
                showToast(err.message || 'Failed to update active doctor profile.', 'error');
            }
        });
    }

    // 1.1 History filter change listener
    const historyFilter = document.getElementById('history-doctor-filter');
    if (historyFilter) {
        historyFilter.addEventListener('change', () => {
            if (typeof loadPrescriptionsHistory === 'function') loadPrescriptionsHistory();
        });
    }

    // 2. Clear / Cancel button click
    const btnClear = document.getElementById('btn-clear-doctor-profile');
    if (btnClear) {
        btnClear.addEventListener('click', () => {
            document.getElementById('form-doctor-profile').reset();
            document.getElementById('doc-profile-id').value = '';
            document.getElementById('btn-save-doctor-profile').innerHTML = '<i class="fa-solid fa-plus"></i> Add Doctor Profile';
            btnClear.style.display = 'none';
        });
    }

    // 3. Form submit
    const docForm = document.getElementById('form-doctor-profile');
    if (docForm) {
        docForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                name: document.getElementById('doc-profile-name').value.trim(),
                qualifications: document.getElementById('doc-profile-qualifications').value.trim(),
                slmc_reg: document.getElementById('doc-profile-slmc').value.trim(),
            };

            const docId = document.getElementById('doc-profile-id').value;
            if (docId) {
                payload.id = docId;
            }

            try {
                const json = await api.post('/doctors.php', payload);
                if (json.status === 'success') {
                    docForm.reset();
                    document.getElementById('doc-profile-id').value = '';
                    document.getElementById('btn-save-doctor-profile').innerHTML = '<i class="fa-solid fa-plus"></i> Add Doctor Profile';
                    if (btnClear) btnClear.style.display = 'none';
                    loadDoctorsList();
                } else {
                    showToast(json.message || 'Failed to save doctor profile.', 'error');
                }
            } catch (err) {
                showToast(err.message || 'Failed to save doctor profile.', 'error');
            }
        });
    }
}

function wireMedicineModal() {
    const btnOpen = document.getElementById('btn-new-medicine-modal');
    if (btnOpen) btnOpen.addEventListener('click', () => openModal('modal-medicine'));

    document.querySelectorAll('#modal-medicine .modal-close, #modal-medicine .btn-modal-close').forEach(btn => {
        btn.addEventListener('click', () => closeModal('modal-medicine'));
    });

    const form = document.getElementById('form-medicine-modal');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            name:            document.getElementById('med-name-in').value.trim(),
            generic_name:    document.getElementById('med-generic-in').value.trim(),
            type:            document.getElementById('med-type-in').value,
            default_strength: document.getElementById('med-strength-in').value.trim(),
            unit_price:      parseFloat(document.getElementById('med-price-in').value) || 0,
        };

        try {
            const json = await api.post('/medicines.php', payload);
            if (json.status === 'success') {
                showToast('Medicine added successfully.', 'success');
                closeModal('modal-medicine');
                form.reset();
                document.getElementById('med-price-in').value = '10.00';
                currentPage = 0;
                loadMedicinesList();
            } else {
                showToast(json.message || 'Failed to add medicine.', 'error');
            }
        } catch (err) {
            showToast(err.message || 'Failed to add medicine.', 'error');
        }
    });
}
