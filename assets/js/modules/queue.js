// assets/js/modules/queue.js
import * as api   from '../api.js';
import * as state from '../state.js';
import { showToast } from '../components/toast.js';
import { escapeHtml } from '../api.js';
import { selectPatient } from './patients.js';

export function init() {
    // Wire queue monitor refresh button
    const btn = document.getElementById('btn-refresh-queue-monitor');
    if (btn) btn.addEventListener('click', loadQueueMonitorDashboard);

    // Listen for cross-module "Add to Queue" event dispatched by patients.js
    document.addEventListener('crx:addToQueue', (e) => {
        addPatientToQueue(e.detail.patientId, e.detail.patientName);
    });

    loadWaitingQueue();
}

// Add Patient to Waiting Queue (pre-fills Channeling Billing tab and switches to it)
async function addPatientToQueue(patientId, patientName) {
    try {
        const json = await api.get('/patients.php');
        if (json.status === 'success' && json.data) {
            const p = json.data.find(x => x.id == patientId);
            if (p) {
                document.getElementById('chl-selected-patient-id').value = p.id;
                document.getElementById('chl-selected-patient-name').textContent = p.name;
                document.getElementById('chl-selected-patient-meta').textContent =
                    `Patient ID: ${p.patient_code} | Age: ${p.age}y / ${p.gender} | Phone: ${p.phone || 'N/A'}`;
                document.getElementById('chl-selected-patient-badge').style.display = 'block';

                // Switch to Channeling Billing tab
                document.querySelector('[data-tab="tab-channeling-billing"]').click();
            }
        }
    } catch (err) {
        console.error(err);
    }
}

// Load and Render Waiting Queue
async function loadWaitingQueue() {
    const container = document.getElementById('queue-list-container');
    const badge = document.getElementById('queue-count-badge');
    if (!container) return;

    const docId = parseInt(document.getElementById('active-doctor-select')?.value || 1);

    try {
        const json = await api.get('/queue.php', { doctor_id: docId });

        if (json.status === 'success' && json.data) {
            const queue = json.data;
            if (badge) badge.textContent = queue.length;

            if (queue.length === 0) {
                container.innerHTML = `<div style="padding: 10px; text-align: center; color: var(--text-muted); font-size:12.5px;">No patients in queue.</div>`;
                return;
            }

            container.innerHTML = queue.map(item => `
          <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid var(--border-subtle); gap:10px;">
            <div style="flex:1; min-width:0;">
              <div style="display:flex; align-items:center; gap:6px;">
                <span class="badge" style="background:${item.status === 'Serving' ? 'var(--emerald-600)' : 'var(--teal-600)'}; color:#ffffff; font-weight:800; font-size:11px; padding:2px 6px; border-radius:4px;">Token ${escapeHtml(item.token_number)}</span>
                <strong style="font-size:13px; color:var(--text-heading); text-overflow:ellipsis; overflow:hidden; white-space:nowrap;">${escapeHtml(item.patient_name)}</strong>
              </div>
              <small style="color:var(--text-muted); font-size:11px;">ID: ${escapeHtml(item.patient_code)} | ${escapeHtml(item.patient_age)}y / ${escapeHtml(item.patient_gender)}</small>
            </div>
            <div style="display:flex; gap:4px;">
              <button type="button" class="btn btn-primary btn-sm btn-serve-queue" data-id="${item.id}" data-pid="${item.patient_id}" style="font-size:11px; padding:3px 6px; height:auto; background:${item.status === 'Serving' ? 'var(--emerald-600)' : ''};">
                <i class="fa-solid ${item.status === 'Serving' ? 'fa-stethoscope fa-spin' : 'fa-play'}"></i> ${item.status === 'Serving' ? 'Serving' : 'Call'}
              </button>
              <button type="button" class="btn btn-danger btn-sm btn-cancel-queue" data-id="${item.id}" style="font-size:11px; padding:3px 6px; height:auto;"><i class="fa-solid fa-times"></i></button>
            </div>
          </div>
        `).join('');

            // Attach listeners to serve button
            container.querySelectorAll('.btn-serve-queue').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const queueId = btn.getAttribute('data-id');

                    try {
                        await api.put('/queue.php', { id: queueId, status: 'Serving' });
                    } catch (err) {
                        console.error(err);
                    }

                    // Fetch patient object and select it
                    const p = queue.find(x => x.id == queueId);
                    if (p) {
                        selectPatient({
                            id: p.patient_id,
                            name: p.patient_name,
                            patient_code: p.patient_code,
                            age: p.patient_age,
                            gender: p.patient_gender,
                            phone: p.patient_phone,
                            allergies: p.patient_allergies,
                            blood_group: p.patient_blood_group
                        });
                    }
                    loadWaitingQueue();
                });
            });

            // Attach listeners to cancel button
            container.querySelectorAll('.btn-cancel-queue').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const queueId = btn.getAttribute('data-id');
                    if (window.confirm('Cancel this patient\'s queue token?')) {
                        try {
                            await api.del(`/queue.php?id=${queueId}`);
                        } catch (err) {
                            console.error(err);
                        }
                        loadWaitingQueue();
                    }
                });
            });
        }
    } catch (err) {
        console.error(err);
    }
}

// Real-Time Queue Auto-Refresh Bridge
function renderQueueMonitorData(data) {
    const grid = document.getElementById('queue-monitor-grid');
    if (!grid || !data) return;

    if (data.length === 0) {
        grid.innerHTML = `<div style="grid-column: 1/-1; padding: 30px 0; text-align: center; color: var(--text-muted); font-weight:700;"><i class="fa-solid fa-circle-info"></i> No doctors added yet. Go to Settings tab to add doctors.</div>`;
        return;
    }

    grid.innerHTML = data.map(item => {
        const doc = item.doctor;
        const queue = item.queue;
        const serving = queue.find(x => x.status === 'Serving');
        const waiting = queue.filter(x => x.status === 'Waiting');

        return `
        <div class="glass-card" style="display:flex; flex-direction:column; border-color:${serving ? 'var(--emerald-500)' : 'var(--border-subtle)'}; min-height: 280px; padding: 18px;">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:1px solid var(--border-subtle); padding-bottom:10px; margin-bottom:12px;">
            <div>
              <h3 style="margin:0; font-size:15px; color:var(--text-heading); font-weight:800;">${escapeHtml(doc.name)}</h3>
              <small style="color:var(--text-muted); font-size:11px; font-weight:700;">${escapeHtml(doc.qualifications || 'General Practitioner')}</small>
            </div>
            <span class="badge" style="background:${waiting.length > 0 ? 'var(--rose)' : 'var(--text-muted)'}; color:#ffffff; font-size:10px; font-weight:800; border-radius:4px; padding:2px 6px;">
              ${waiting.length} Waiting
            </span>
          </div>

          <!-- Currently Serving -->
          <div style="background:${serving ? '#f0fdf4' : '#f8fafc'}; border:1px solid ${serving ? '#bbf7d0' : 'var(--border-subtle)'}; border-radius:8px; padding:10px 12px; margin-bottom:12px;">
            <div style="font-size:11px; font-weight:700; text-transform:uppercase; color:${serving ? 'var(--emerald-700)' : 'var(--text-muted)'}; display:flex; align-items:center; gap:5px;">
              <i class="fa-solid ${serving ? 'fa-spinner fa-spin' : 'fa-circle-notch'}"></i> Currently Serving
            </div>
            <div style="font-size:14px; font-weight:800; color:var(--text-heading); margin-top:4px;">
              ${serving ? `Token ${serving.token_number} - ${escapeHtml(serving.patient_name)}` : '<span style="color:var(--text-muted); font-weight:500;">No patient called yet</span>'}
            </div>
          </div>

          <!-- Waiting Queue List -->
          <div style="flex:1; min-height:100px; max-height:180px; overflow-y:auto; margin-bottom:12px;">
            <div style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:6px;">Waiting Patients</div>
            ${waiting.length === 0 ? `
              <div style="color:var(--text-muted); font-size:12px; padding:10px 0; text-align:center;">No patients waiting.</div>
            ` : waiting.map(pt => `
              <div style="display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-bottom:1px dashed var(--border-subtle);">
                <span style="font-size:12.5px; font-weight:700; color:var(--text-heading);">Token ${pt.token_number} - ${escapeHtml(pt.patient_name)}</span>
                <small style="color:var(--text-muted); font-size:10.5px;">${escapeHtml(pt.patient_age)}y / ${escapeHtml(pt.patient_gender)}</small>
              </div>
            `).join('')}
          </div>

          <!-- Action button for receptionist -->
          <button type="button" class="btn btn-outline btn-sm btn-monitor-add-queue" data-docid="${doc.id}" style="width:100%; font-size:11.5px; border-color:var(--teal-600); color:var(--teal-600); margin-top: auto;">
            <i class="fa-solid fa-user-plus"></i> Add Patient to ${escapeHtml(doc.name)}'s Queue
          </button>
        </div>
      `;
    }).join('');

    grid.querySelectorAll('.btn-monitor-add-queue').forEach(btn => {
        btn.addEventListener('click', () => {
            const docId = btn.getAttribute('data-docid');
            openAddToQueueModal(docId);
        });
    });
}

async function loadQueueMonitorDashboard() {
    const grid = document.getElementById('queue-monitor-grid');
    if (!grid) return;

    try {
        const json = await api.get('/queue.php', { all_doctors: 'true' });
        if (json.status === 'success' && json.data) {
            renderQueueMonitorData(json.data);
        }
    } catch (err) {
        console.error(err);
        showToast('Failed to load queue monitor data.', 'error');
    }
}

// Interval management — called by main.js nav handler when switching to/from queue-monitor tab
export function startQueueMonitorPolling() {
    stopQueueMonitorPolling();
    loadQueueMonitorDashboard();
    state.set('queueMonitorInterval', setInterval(loadQueueMonitorDashboard, 5000));
}

export function stopQueueMonitorPolling() {
    const interval = state.get('queueMonitorInterval');
    if (interval) {
        clearInterval(interval);
        state.set('queueMonitorInterval', null);
    }
}
