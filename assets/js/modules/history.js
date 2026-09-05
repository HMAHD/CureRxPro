// assets/js/modules/history.js
import * as api from '../api.js';
import * as state from '../state.js';
import { escapeHtml } from '../api.js';

export function init() {
    const filterEl = document.getElementById('history-doctor-filter');
    if (filterEl) {
        filterEl.addEventListener('change', () => loadPrescriptionsHistory(1));
    }

    loadPrescriptionsHistory(1);

    // Listen for cross-module requests to open patient history modal
    document.addEventListener('crx:openHistory', (e) => {
        openPatientHistoryModal(e.detail.patientId);
    });
}

async function loadPrescriptionsHistory(page) {
    if (page === undefined) page = state.get('historyCurrentPage') ?? 1;
    const tbody = document.getElementById('tbody-history');
    if (!tbody) return;

    state.set('historyCurrentPage', page);
    const historyPageLimit = state.get('historyPageLimit') ?? 15;
    const filterVal = document.getElementById('history-doctor-filter')?.value || 'all';

    try {
        const json = await api.get(`/prescriptions.php`, {
            doctor_id: filterVal,
            page,
            limit: historyPageLimit
        });

        if (json.status === 'success') {
            if (!json.data || json.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">No prescriptions created yet.</td></tr>`;
                updateHistoryPaginationUI(0, 1, 1);
                return;
            }

            tbody.innerHTML = json.data.map(rx => `
              <tr>
                <td><strong>${escapeHtml(rx.rx_number)}</strong></td>
                <td>${escapeHtml(rx.patient_name)}</td>
                <td>${new Date(rx.created_at).toLocaleDateString()}</td>
                <td><span class="stat-pill"><strong>${rx.item_count}</strong> Items</span></td>
                <td>${escapeHtml(rx.diagnosis || 'N/A')}</td>
                <td>
                  <button class="btn btn-outline btn-sm btn-print-history" data-id="${rx.id}"><i class="fa-solid fa-print"></i> Print</button>
                  <button class="btn btn-danger btn-sm btn-delete-rx" data-id="${rx.id}"><i class="fa-solid fa-trash"></i></button>
                </td>
              </tr>
            `).join('');

            const p = json.pagination || { total_records: json.data.length, page: 1, total_pages: 1 };
            state.set('historyTotalPages', p.total_pages);
            updateHistoryPaginationUI(p.total_records, p.page, p.total_pages);

            tbody.querySelectorAll('.btn-print-history').forEach(btn => {
                btn.addEventListener('click', () => {
                    const rxId = btn.getAttribute('data-id');
                    document.dispatchEvent(new CustomEvent('crx:openPrintPreview', { detail: rxId }));
                });
            });

            tbody.querySelectorAll('.btn-delete-rx').forEach(btn => {
                btn.addEventListener('click', async () => {
                    if (confirm('Are you sure you want to delete this prescription record?')) {
                        const rxId = btn.getAttribute('data-id');
                        await api.del(`/prescriptions.php?id=${rxId}`);
                        loadPrescriptionsHistory(state.get('historyCurrentPage'));
                        document.dispatchEvent(new CustomEvent('crx:reloadStats'));
                    }
                });
            });
        }
    } catch (err) {
        console.error(err);
    }
}

function updateHistoryPaginationUI(total, page, totalPages) {
    const info = document.getElementById('history-pagination-info');
    const btnPrev = document.getElementById('btn-history-prev');
    const btnNext = document.getElementById('btn-history-next');

    if (info) info.textContent = `Page ${page} of ${totalPages} (Total ${total} Records)`;

    if (btnPrev) {
        btnPrev.disabled = (page <= 1);
        btnPrev.onclick = () => { if (page > 1) loadPrescriptionsHistory(page - 1); };
    }

    if (btnNext) {
        btnNext.disabled = (page >= totalPages);
        btnNext.onclick = () => { if (page < totalPages) loadPrescriptionsHistory(page + 1); };
    }
}

export async function openPatientHistoryModal(patientId) {
    const modal = document.getElementById('modal-patient-history');
    if (!modal) return;

    try {
        const json = await api.get('/prescriptions.php', { patient_id: patientId, all: 'true' });

        if (json.status === 'success' && json.data) {
            const rxs = json.data;

            if (rxs.length > 0) {
                const first = rxs[0];
                document.getElementById('hist-pt-name').textContent = first.patient_name || '--';
                document.getElementById('hist-pt-code-gender').textContent = `Patient ID: ${first.patient_code} | Age: ${first.patient_age}y / ${first.patient_gender} | Phone: ${first.patient_phone || 'N/A'}`;
                document.getElementById('hist-pt-blood').textContent = first.patient_blood_group || 'N/A';
                document.getElementById('hist-pt-allergies').textContent = first.patient_allergies || 'None';
            } else {
                const pJson = await api.get('/patients.php');
                if (pJson.status === 'success' && pJson.data) {
                    const p = pJson.data.find(x => x.id == patientId);
                    if (p) {
                        document.getElementById('hist-pt-name').textContent = p.name || '--';
                        document.getElementById('hist-pt-code-gender').textContent = `Patient ID: ${p.patient_code} | Age: ${p.age}y / ${p.gender} | Phone: ${p.phone || 'N/A'}`;
                        document.getElementById('hist-pt-blood').textContent = p.blood_group || 'N/A';
                        document.getElementById('hist-pt-allergies').textContent = p.allergies || 'None';
                    }
                }
            }

            const container = document.getElementById('history-timeline-container');
            if (rxs.length === 0) {
                container.innerHTML = `<div style="padding: 20px 0; text-align: center; color: var(--text-muted); font-weight:700;"><i class="fa-solid fa-circle-info"></i> No past visits found for this patient.</div>`;
            } else {
                container.innerHTML = rxs.map(rx => {
                    const formattedDate = new Date(rx.created_at).toLocaleDateString('en-US', {
                        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
                    });

                    let vitalsHtml = '';
                    if (rx.bp || rx.pulse || rx.weight || rx.temp) {
                        vitalsHtml = `
                          <div style="display:flex; flex-wrap:wrap; gap:12px; margin-top:8px; font-size:12px; font-weight:700; color:var(--teal-700); background:#f0fdf4; border:1px solid #dcfce7; padding:6px 10px; border-radius:6px;">
                            ${rx.bp ? `<span><i class="fa-solid fa-heart"></i> BP: ${escapeHtml(rx.bp)}</span>` : ''}
                            ${rx.pulse ? `<span><i class="fa-solid fa-heart"></i> Pulse: ${escapeHtml(rx.pulse)} bpm</span>` : ''}
                            ${rx.temp ? `<span><i class="fa-solid fa-temperature-half"></i> Temp: ${escapeHtml(rx.temp)} °F</span>` : ''}
                            ${rx.weight ? `<span><i class="fa-solid fa-weight-scale"></i> Weight: ${escapeHtml(rx.weight)} kg</span>` : ''}
                          </div>
                        `;
                    }

                    let medsTable = '';
                    if (rx.items && rx.items.length > 0) {
                        medsTable = `
                          <table class="data-table" style="font-size:12px; margin-top:8px; width:100%;">
                            <thead>
                              <tr>
                                <th>Medicine Name</th>
                                <th>Strength</th>
                                <th>Dosage</th>
                                <th>Instruction</th>
                                <th>Days</th>
                              </tr>
                            </thead>
                            <tbody>
                              ${rx.items.map(item => `
                                <tr>
                                  <td><strong>${escapeHtml(item.medicine_name)}</strong></td>
                                  <td>${escapeHtml(item.strength || '-')}</td>
                                  <td>${escapeHtml(item.dosage || '-')}</td>
                                  <td>${escapeHtml(item.instruction || '-')}</td>
                                  <td>${escapeHtml(item.duration || '-')}</td>
                                </tr>
                              `).join('')}
                            </tbody>
                          </table>
                        `;
                    }

                    return `
                      <div style="position:relative; margin-bottom: 25px;">
                        <div style="position:absolute; left: -30px; top: 4px; width: 12px; height: 12px; border-radius: 50%; background: var(--teal-600); border: 2.5px solid #ffffff; box-shadow: 0 0 0 3px var(--teal-100);"></div>
                        <div class="glass-card" style="padding:15px; margin:0; border-color:var(--border-subtle);">
                          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                            <div>
                              <span class="badge" style="background:var(--teal-600); color:#ffffff; font-weight:800; font-size:11px; padding:3px 8px; border-radius:5px;">${formattedDate}</span>
                              <strong style="margin-left: 10px; font-size: 13px; color: var(--text-heading);"><i class="fa-solid fa-user-doctor"></i> ${escapeHtml(rx.doctor_name || 'Dr. Sampath Perera')}</strong>
                            </div>
                            <button type="button" class="btn btn-outline btn-sm btn-print-hist-rx" data-id="${rx.id}" style="font-size:11px; padding:3px 8px; height:auto;"><i class="fa-solid fa-print"></i> Re-print</button>
                          </div>
                          <div style="margin-top:10px;">
                            ${rx.chief_complaint ? `<p style="margin:4px 0; font-size:13px;"><strong>Chief Complaint:</strong> ${escapeHtml(rx.chief_complaint)}</p>` : ''}
                            ${rx.diagnosis ? `<p style="margin:4px 0; font-size:13px;"><strong>Diagnosis:</strong> <span style="color:var(--teal-700); font-weight:700;">${escapeHtml(rx.diagnosis)}</span></p>` : ''}
                          </div>
                          ${vitalsHtml}
                          ${medsTable}
                          ${rx.advice ? `<p style="margin:8px 0 0 0; font-size:12.5px; color:var(--text-muted);"><strong>Advice:</strong> ${escapeHtml(rx.advice)}</p>` : ''}
                          ${rx.follow_up_date ? `<p style="margin:4px 0 0 0; font-size:12.5px; color:var(--text-muted);"><strong>Follow Up:</strong> ${escapeHtml(rx.follow_up_date)}</p>` : ''}
                        </div>
                      </div>
                    `;
                }).join('');

                container.querySelectorAll('.btn-print-hist-rx').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const rxId = btn.getAttribute('data-id');
                        document.dispatchEvent(new CustomEvent('crx:openPrintPreview', { detail: rxId }));
                    });
                });
            }

            modal.classList.add('active');
        }
    } catch (err) {
        console.error('Failed to load patient history timeline', err);
    }
}
