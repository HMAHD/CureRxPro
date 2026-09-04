// assets/js/modules/patients.js
import * as api from '../api.js';
import * as state from '../state.js';
import { showToast } from '../components/toast.js';
import { createAutocomplete } from '../components/autocomplete.js';
import { openModal, closeModal } from '../components/modal.js';
import { escapeHtml } from '../api.js';

export function init() {
    initPatientSearch();
    initPatientsList();
    initPatientCrud();
}

// Exported so other modules (e.g. billing) can programmatically select a patient
export function selectPatient(p) {
    state.set('selectedPatient', p);
    document.getElementById('selected-patient-id').value = p.id;
    const badge = document.getElementById('selected-patient-badge');
    badge.innerHTML = `
      <div style="flex:1;">
        <h4>${escapeHtml(p.name)} <small>(${escapeHtml(p.patient_code)})</small></h4>
        <p>Age: ${escapeHtml(p.age)} Yrs | Gender: ${escapeHtml(p.gender)} | Phone: ${escapeHtml(p.phone || 'N/A')} ${p.allergies ? `| <strong style="color:var(--rose)">Allergies: ${escapeHtml(p.allergies)}</strong>` : ''}</p>
      </div>
      <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <span id="repeat-rx-container"></span>
        <button type="button" class="btn btn-outline btn-sm" id="btn-view-pt-history" style="color:var(--teal-600); border-color:var(--teal-600);"><i class="fa-solid fa-clock-rotate-left"></i> Medical History</button>
        <button type="button" class="btn btn-outline btn-sm" id="btn-change-patient">Change</button>
      </div>
    `;
    badge.style.display = 'flex';

    // Render High-Priority Allergy Alert Banner (Issue #6)
    const allergyContainer = document.getElementById('patient-allergy-alert-container');
    if (allergyContainer) {
        if (p.allergies && p.allergies.trim() !== '') {
            allergyContainer.innerHTML = `
          <div class="allergy-alert-banner">
            <i class="fa-solid fa-triangle-exclamation" style="font-size:20px;"></i>
            <div>
              <strong>HIGH PATIENT ALLERGY WARNING:</strong>
              <div style="font-weight:600; font-size:12.5px; margin-top:2px;">Known Allergies / Reactions: ${escapeHtml(p.allergies)}</div>
            </div>
          </div>
        `;
            allergyContainer.style.display = 'block';
        } else {
            allergyContainer.innerHTML = '';
            allergyContainer.style.display = 'none';
        }
    }

    document.getElementById('btn-view-pt-history')?.addEventListener('click', () => {
        document.dispatchEvent(new CustomEvent('crx:openHistory', { detail: { patientId: p.id } }));
    });

    document.getElementById('btn-change-patient')?.addEventListener('click', () => {
        state.set('selectedPatient', null);
        document.getElementById('selected-patient-id').value = '';
        badge.style.display = 'none';
        if (allergyContainer) {
            allergyContainer.innerHTML = '';
            allergyContainer.style.display = 'none';
        }
    });

    // Check if patient has last prescription in the background (Non-blocking UI)
    loadRepeatLastRxButton(p.id);
}

function initPatientSearch() {
    createAutocomplete({
        inputId: 'patient-search-input',
        dropdownId: 'patient-dropdown',
        fetchFn: async (q) => {
            const r = await api.get('/patients.php', { q });
            if (r.status === 'success') return r.data;
            return [];
        },
        onSelect: selectPatient,
        minChars: 1,
    });
}

async function loadRepeatLastRxButton(patientId) {
    const repContainer = document.getElementById('repeat-rx-container');
    if (!repContainer) return;
    repContainer.innerHTML = '';

    try {
        const json = await api.get('/prescriptions.php', { patient_id: patientId });
        if (json.status === 'success' && json.data && json.data.items && json.data.items.length > 0) {
            const lastRx = json.data;
            repContainer.innerHTML = `
          <button type="button" class="btn btn-primary btn-sm" id="btn-repeat-last-rx" style="background:var(--teal-600); border-color:var(--teal-600);">
            <i class="fa-solid fa-rotate-left"></i> Repeat Last Rx
          </button>
        `;

            document.getElementById('btn-repeat-last-rx')?.addEventListener('click', () => {
                if (confirm('Load medicines from last prescription?')) {
                    if (lastRx.chief_complaint && !document.getElementById('rx-complaint').value) {
                        document.getElementById('rx-complaint').value = lastRx.chief_complaint;
                    }
                    if (lastRx.diagnosis && !document.getElementById('rx-diagnosis').value) {
                        document.getElementById('rx-diagnosis').value = lastRx.diagnosis;
                    }
                    if (lastRx.advice && !document.getElementById('rx-advice').value) {
                        document.getElementById('rx-advice').value = lastRx.advice;
                    }
                    if (lastRx.follow_up_date && !document.getElementById('rx-followup').value) {
                        document.getElementById('rx-followup').value = lastRx.follow_up_date;
                    }

                    const tbody = document.getElementById('rx-items-tbody');
                    if (tbody) {
                        tbody.innerHTML = '';
                        lastRx.items.forEach(item => {
                            document.dispatchEvent(new CustomEvent('crx:addRxRow', { detail: item }));
                        });
                        document.dispatchEvent(new CustomEvent('crx:recalculateTotal'));
                    }
                }
            });
        }
    } catch (e) {
        console.error('Failed to load last prescription for repeat', e);
    }
}

async function loadPatientsList() {
    const tbody = document.getElementById('tbody-patients');
    if (!tbody) return;

    try {
        const json = await api.get('/patients.php');

        if (json.status === 'success') {
            if (json.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--text-muted);">No registered patients found.</td></tr>`;
                return;
            }

            tbody.innerHTML = json.data.map(p => `
          <tr>
            <td><strong>${escapeHtml(p.patient_code)}</strong></td>
            <td>${escapeHtml(p.name)}</td>
            <td>${escapeHtml(p.age)} Yrs / ${escapeHtml(p.gender)}</td>
            <td>${escapeHtml(p.phone || 'N/A')}</td>
            <td>${escapeHtml(p.blood_group || 'N/A')}</td>
            <td><span style="color:var(--rose)">${escapeHtml(p.allergies || 'None')}</span></td>
            <td>
              <div style="display:flex; gap:6px;">
                <button class="btn btn-primary btn-sm btn-select-pt" data-id="${p.id}"><i class="fa-solid fa-file-medical"></i> Write Rx</button>
                <button class="btn btn-success btn-sm btn-add-queue" data-id="${p.id}" style="background:var(--emerald-600); border-color:var(--emerald-600);"><i class="fa-solid fa-plus"></i> Queue</button>
                <button class="btn btn-outline btn-sm btn-view-history" data-id="${p.id}" style="color:var(--teal-600); border-color:var(--teal-600);"><i class="fa-solid fa-clock-rotate-left"></i> History</button>
                <button class="btn btn-danger btn-sm btn-del-patient" data-id="${p.id}"><i class="fa-solid fa-trash"></i></button>
              </div>
            </td>
          </tr>
        `).join('');

            tbody.querySelectorAll('.btn-select-pt').forEach(btn => {
                btn.addEventListener('click', () => {
                    const pid = btn.getAttribute('data-id');
                    const pt = json.data.find(x => x.id == pid);
                    selectPatient(pt);
                    document.querySelector('[data-tab="tab-builder"]').click();
                });
            });

            tbody.querySelectorAll('.btn-add-queue').forEach(btn => {
                btn.addEventListener('click', () => {
                    const pid = btn.getAttribute('data-id');
                    document.dispatchEvent(new CustomEvent('crx:addToQueue', { detail: { patientId: pid } }));
                });
            });

            tbody.querySelectorAll('.btn-view-history').forEach(btn => {
                btn.addEventListener('click', () => {
                    const pid = btn.getAttribute('data-id');
                    document.dispatchEvent(new CustomEvent('crx:openHistory', { detail: { patientId: pid } }));
                });
            });

            tbody.querySelectorAll('.btn-del-patient').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (confirm('Delete patient record? This will also remove their prescription history.')) {
                        const pid = btn.getAttribute('data-id');
                        await api.del(`/patients.php?id=${pid}`);
                        loadPatientsList();
                        document.dispatchEvent(new CustomEvent('crx:reloadStats'));
                    }
                });
            });
        }
    } catch (err) {
        console.error(err);
    }
}

function initPatientsList() {
    // Initial load when patients tab becomes visible
    const patientsTab = document.querySelector('[data-tab="tab-patients"]');
    if (patientsTab) {
        patientsTab.addEventListener('click', loadPatientsList);
    }
}

function initPatientCrud() {
    const btnNewPatient = document.getElementById('btn-new-patient-modal');
    if (btnNewPatient) {
        btnNewPatient.addEventListener('click', () => {
            document.getElementById('form-patient-modal').reset();
            document.getElementById('modal-patient').classList.add('active');
        });
    }

    const formPatient = document.getElementById('form-patient-modal');
    if (formPatient) {
        formPatient.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                name: document.getElementById('patient-name-in').value,
                age: document.getElementById('patient-age-in').value,
                gender: document.getElementById('patient-gender-in').value,
                phone: document.getElementById('patient-phone-in').value,
                address: document.getElementById('patient-address-in').value,
                allergies: document.getElementById('patient-allergies-in').value,
                blood_group: document.getElementById('patient-blood-in').value,
            };

            try {
                const json = await api.post('/patients.php', payload);
                if (json.status === 'success' && json.data) {
                    selectPatient(json.data);
                    document.getElementById('modal-patient').classList.remove('active');
                    loadPatientsList();
                    document.dispatchEvent(new CustomEvent('crx:reloadStats'));
                } else {
                    showToast(json.message || 'Failed to save patient', 'error');
                }
            } catch (err) {
                showToast(err.message || 'Failed to save patient', 'error');
            }
        });
    }
}
