// assets/js/modules/prescriptions.js
import * as api from '../api.js';
import * as state from '../state.js';
import { showToast } from '../components/toast.js';
import { escapeHtml, debounce } from '../api.js';
import { openModal, closeModal } from '../components/modal.js';

const DRAFT_KEY = 'rx_draft';

// Print preview state
let activeRxData = null;
let currentPrintPaperSize = 'A5';
let currentPrintMode = 'rx_bill'; // 'rx_bill' or 'rx_only'

export function init() {
    initDiseaseChips();
    initKeyboardShortcuts();
    initPediatricCalculator();
    initRxTable();
    wireSaveButton();
    wirePrintModalControls();
    restoreDraft();
    wireDraftAutoSave();
    loadTemplates();

    // Listen for cross-module events dispatched by patients.js
    document.addEventListener('crx:addRxRow', (e) => {
        addRxRow(e.detail);
    });
    document.addEventListener('crx:recalculateTotal', () => {
        recalculateGrandTotal();
    });
}

// ─── Draft Auto-Save ───────────────────────────────────────────────────────────

function saveDraft() {
    const draft = {
        patientId: state.get('selectedPatient')?.id ?? null,
        rxItems:   state.get('rxItems'),
        diagnosis: document.getElementById('rx-diagnosis')?.value ?? '',
        followUp:  document.getElementById('rx-followup')?.value ?? '',
    };
    localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
}

function restoreDraft() {
    const raw = localStorage.getItem(DRAFT_KEY);
    if (!raw) return;
    try {
        const draft = JSON.parse(raw);
        if (draft.rxItems?.length) {
            draft.rxItems.forEach(item => addRxRow(item));
            showToast('Unsaved prescription draft restored.', 'info');
        }
    } catch { /* ignore corrupt draft */ }
}

function clearDraft() {
    localStorage.removeItem(DRAFT_KEY);
}

function wireDraftAutoSave() {
    const debouncedSave = debounce(saveDraft, 800);

    // Wire to all current and future Rx table inputs via event delegation
    const rxForm = document.getElementById('rx-items-tbody');
    if (rxForm) {
        rxForm.addEventListener('input', debouncedSave);
        rxForm.addEventListener('change', debouncedSave);
    }

    // Wire to prescription header fields
    const fieldIds = [
        'rx-diagnosis', 'rx-complaint', 'rx-advice', 'rx-followup',
        'vitals-bp', 'vitals-pulse', 'vitals-weight', 'vitals-temp',
        'bill-consultation-fee', 'bill-hospital-fee', 'bill-discount'
    ];
    fieldIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', debouncedSave);
    });
}

// ─── Disease Chips ─────────────────────────────────────────────────────────────

function initDiseaseChips() {
    const diagInput = document.getElementById('rx-diagnosis');
    if (!diagInput) return;

    document.querySelectorAll('.disease-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            const val = chip.getAttribute('data-val');
            if (diagInput.value.trim() === '') {
                diagInput.value = val;
            } else if (!diagInput.value.includes(val)) {
                diagInput.value += ', ' + val;
            }
        });
    });
}

// ─── Keyboard Shortcuts ────────────────────────────────────────────────────────

function initKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        // Ctrl + N or Cmd + N: New Patient / Reset Rx Form
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'n') {
            e.preventDefault();
            const tabBuilder = document.querySelector('[data-tab="tab-builder"]');
            if (tabBuilder) tabBuilder.click();
            const btnChange = document.getElementById('btn-change-patient');
            if (btnChange) btnChange.click();
            const ptSearch = document.getElementById('patient-search-input');
            if (ptSearch) { ptSearch.value = ''; ptSearch.focus(); }
            return;
        }

        // Ctrl + F or Cmd + F: Focus Patient Search
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') {
            e.preventDefault();
            const tabBuilder = document.querySelector('[data-tab="tab-builder"]');
            if (tabBuilder) tabBuilder.click();
            const ptSearch = document.getElementById('patient-search-input');
            if (ptSearch) ptSearch.focus();
            return;
        }

        // Ctrl + D or Cmd + D: Jump to Drug Builder
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'd') {
            e.preventDefault();
            const tabBuilder = document.querySelector('[data-tab="tab-builder"]');
            if (tabBuilder) tabBuilder.click();
            const firstMedInp = document.querySelector('.rx-med-name');
            if (firstMedInp) firstMedInp.focus();
            return;
        }

        // Ctrl + Enter or Cmd + Enter: Instant Save & Print
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            const btnSavePrint = document.getElementById('btn-save-print-rx');
            if (btnSavePrint && !btnSavePrint.disabled) {
                btnSavePrint.click();
            }
            return;
        }

        // Escape: Close active dropdowns & modals
        if (e.key === 'Escape') {
            document.querySelectorAll('.autocomplete-dropdown').forEach(d => d.style.display = 'none');
            document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
            return;
        }

        // Fast-Tab Enter Key Sequence inside Prescription Table Inputs
        if (e.key === 'Enter' && e.target.classList.contains('form-control')) {
            const tr = e.target.closest('tr');
            if (tr && tr.parentNode && tr.parentNode.id === 'rx-items-tbody') {
                e.preventDefault();
                if (e.target.classList.contains('rx-med-name')) {
                    tr.querySelector('.rx-strength')?.focus();
                } else if (e.target.classList.contains('rx-strength')) {
                    tr.querySelector('.rx-dosage')?.focus();
                } else if (e.target.classList.contains('rx-dosage')) {
                    tr.querySelector('.rx-instruction')?.focus();
                } else if (e.target.classList.contains('rx-instruction')) {
                    tr.querySelector('.rx-duration')?.focus();
                } else if (e.target.classList.contains('rx-duration')) {
                    tr.querySelector('.rx-qty')?.focus();
                } else if (e.target.classList.contains('rx-qty')) {
                    // Add next row and focus new medicine input
                    addRxRow();
                    const lastTr = document.querySelector('#rx-items-tbody tr:last-child');
                    if (lastTr) {
                        const newMedInp = lastTr.querySelector('.rx-med-name');
                        if (newMedInp) newMedInp.focus();
                    }
                }
            }
        }
    });
}

// ─── Pediatric Calculator ──────────────────────────────────────────────────────

function initPediatricCalculator() {
    const btnCalc = document.getElementById('btn-pediatric-calc');
    const modal = document.getElementById('modal-pediatric-calc');
    const weightInp = document.getElementById('ped-weight-in');
    const drugSelect = document.getElementById('ped-drug-select');
    const doseMlEl = document.getElementById('ped-calc-dose-ml');
    const detailsEl = document.getElementById('ped-calc-details');
    const btnApply = document.getElementById('btn-apply-ped-dose');

    if (!btnCalc || !modal) return;

    let calculatedData = null;

    btnCalc.addEventListener('click', () => {
        const ptWeight = document.getElementById('vitals-weight')?.value.trim();
        if (ptWeight && !isNaN(parseFloat(ptWeight))) {
            weightInp.value = parseFloat(ptWeight);
        }
        calculateDose();
        modal.classList.add('active');
    });

    function calculateDose() {
        const weight = parseFloat(weightInp.value) || 10;
        const drug = drugSelect.value;

        let medName = 'Paracetamol Syrup';
        let strength = '120mg/5ml';
        let mgPerKg = 15;
        let mgPer5ml = 120;
        let freq = '3-4 times daily after meals';
        let dosageCode = '7.5ml tds (1-1-1)';
        let instruction = window.i18n?.t('ins_after_meals') || 'කෑමට පසු (After meals)';

        if (drug === 'paracetamol_120') {
            medName = 'Paracetamol Syrup';
            strength = '120mg/5ml';
            mgPerKg = 15;
            mgPer5ml = 120;
            freq = '3-4 times daily after meals';
            dosageCode = 'tds (1-1-1)';
        } else if (drug === 'paracetamol_250') {
            medName = 'Paracetamol Syrup (Fort)';
            strength = '250mg/5ml';
            mgPerKg = 15;
            mgPer5ml = 250;
            freq = '3-4 times daily after meals';
            dosageCode = 'tds (1-1-1)';
        } else if (drug === 'amoxicillin_125') {
            medName = 'Amoxicillin Syrup';
            strength = '125mg/5ml';
            mgPerKg = 15;
            mgPer5ml = 125;
            freq = '3 times daily after meals';
            dosageCode = 'tds (1-1-1)';
        } else if (drug === 'amoxicillin_250') {
            medName = 'Amoxicillin Syrup (Fort)';
            strength = '250mg/5ml';
            mgPerKg = 15;
            mgPer5ml = 250;
            freq = '3 times daily after meals';
            dosageCode = 'tds (1-1-1)';
        } else if (drug === 'ibuprofen_100') {
            medName = 'Ibuprofen Syrup';
            strength = '100mg/5ml';
            mgPerKg = 10;
            mgPer5ml = 100;
            freq = '3 times daily after meals';
            dosageCode = 'tds (1-1-1)';
        } else if (drug === 'cetirizine_5') {
            medName = 'Cetirizine Syrup';
            strength = '5mg/5ml';
            mgPerKg = 0.25;
            mgPer5ml = 5;
            freq = 'Once daily at night';
            dosageCode = 'nocte (0-0-1)';
            instruction = window.i18n?.t('ins_at_night') || 'රාත්‍රියට කෑමට පසු (Night, after meals)';
        } else if (drug === 'domperidone_5') {
            medName = 'Domperidone Syrup';
            strength = '5mg/5ml';
            mgPerKg = 0.25;
            mgPer5ml = 5;
            freq = '3 times daily before meals';
            dosageCode = 'tds (1-1-1)';
            instruction = window.i18n?.t('ins_before_meals') || 'කෑමට පැය භාගයකට පෙර (Before meals)';
        }

        const totalMg = weight * mgPerKg;
        const volumeMl = (totalMg / mgPer5ml) * 5;
        const roundedMl = (Math.round(volumeMl * 2) / 2).toFixed(1);

        doseMlEl.textContent = `${roundedMl} ml per dose`;
        detailsEl.textContent = `Target dose: ${totalMg.toFixed(1)} mg (${mgPerKg} mg/kg for ${weight} kg child) | ${freq}`;

        calculatedData = {
            medicine_name: medName,
            strength: strength,
            dosage: `${roundedMl} ml ${dosageCode}`,
            instruction: instruction,
            duration: '3 Days',
            quantity: '1 Bottle',
            item_source: 'clinic',
            unit_price: 250.00,
            item_total: 250.00
        };
    }

    if (weightInp) weightInp.addEventListener('input', calculateDose);
    if (drugSelect) drugSelect.addEventListener('change', calculateDose);

    if (btnApply) {
        btnApply.addEventListener('click', () => {
            if (calculatedData) {
                addRxRow(calculatedData);
                recalculateGrandTotal();
                modal.classList.remove('active');
            }
        });
    }
}

// ─── Templates (local — templates module not yet extracted) ───────────────────

async function loadTemplates() {
    try {
        const json = await api.get('/templates.php');
        if (json.status === 'success') {
            state.set('templates', json.data);
            renderTemplateChips(json.data);
        }
    } catch (err) {
        console.error(err);
    }
}

function renderTemplateChips(templates) {
    const container = document.getElementById('template-chips-container');
    if (!container) return;

    if (templates.length === 0) {
        container.innerHTML = `<small style="color:var(--text-muted)">No quick templates saved.</small>`;
        return;
    }

    container.innerHTML = templates.map(t => `
        <div class="template-chip" data-id="${t.id}">
            <i class="fa-solid fa-bolt"></i> ${escapeHtml(t.name)}
        </div>
    `).join('');

    container.querySelectorAll('.template-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            const id = chip.getAttribute('data-id');
            const tpl = state.get('templates').find(x => x.id == id);
            if (tpl && tpl.items) {
                applyTemplate(tpl.items);
            }
        });
    });
}

function applyTemplate(items) {
    const tbody = document.getElementById('rx-items-tbody');
    tbody.innerHTML = '';
    items.forEach(item => {
        addRxRow(item);
    });
}

// ─── Rx Table ─────────────────────────────────────────────────────────────────

function initRxTable() {
    const btnAddRow = document.getElementById('btn-add-rx-row');
    if (btnAddRow) {
        btnAddRow.addEventListener('click', () => {
            addRxRow();
        });
    }

    // Wire billing calc inputs
    document.querySelectorAll('.bill-calc-input').forEach(inp => {
        inp.addEventListener('input', recalculateGrandTotal);
    });

    // Add initial blank row
    addRxRow();
}

function addRxRow(data = {}) {
    const tbody = document.getElementById('rx-items-tbody');
    const rowId = 'rx-row-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
    const itemSource = data.item_source || 'clinic';

    const t = window.i18n?.t.bind(window.i18n) ?? ((k) => k);

    const tr = document.createElement('tr');
    tr.id = rowId;
    tr.innerHTML = `
      <td>
        <div class="autocomplete-wrapper">
          <input type="text" class="form-control rx-med-name" placeholder="Drug Name (e.g. Paracetamol)" value="${escapeHtml(data.medicine_name || '')}" required autocomplete="off">
          <div class="autocomplete-dropdown med-search-dropdown" style="display:none;"></div>
        </div>
      </td>
      <td>
        <input type="text" class="form-control rx-strength" placeholder="e.g. 500mg" value="${escapeHtml(data.strength || '')}">
      </td>
      <td>
        <input type="text" class="form-control rx-dosage" placeholder="1-0-1 / 1 Tab" value="${escapeHtml(data.dosage || '')}">
      </td>
      <td>
        <select class="form-control rx-instruction">
          <option value="${t('ins_after_meals')}">${t('ins_after_meals')}</option>
          <option value="${t('ins_before_meals')}">${t('ins_before_meals')}</option>
          <option value="${t('ins_at_night')}">${t('ins_at_night')}</option>
          <option value="${t('ins_morning')}">${t('ins_morning')}</option>
          <option value="${t('ins_with_meals')}">${t('ins_with_meals')}</option>
          <option value="${t('ins_prn')}">${t('ins_prn')}</option>
          <option value="${t('ins_as_directed')}">${t('ins_as_directed')}</option>
        </select>
      </td>
      <td>
        <input type="text" class="form-control rx-duration" placeholder="e.g. 5 Days" value="${escapeHtml(data.duration || '')}">
      </td>
      <td>
        <input type="text" class="form-control rx-qty" placeholder="15" value="${escapeHtml(data.quantity || '10')}">
      </td>
      <td>
        <select class="form-control rx-source" style="font-weight:700; font-size:12px; padding:6px 4px;">
          <option value="clinic" ${itemSource === 'clinic' ? 'selected' : ''}>${t('opt_source_clinic')}</option>
          <option value="pharmacy" ${itemSource === 'pharmacy' ? 'selected' : ''}>${t('opt_source_pharmacy')}</option>
        </select>
      </td>
      <td>
        <input type="number" class="form-control rx-uprice" placeholder="10.00" value="${data.unit_price !== undefined ? data.unit_price : '10.00'}" step="0.50">
      </td>
      <td>
        <input type="number" class="form-control rx-itotal" placeholder="0.00" value="${data.item_total !== undefined ? data.item_total : '100.00'}" readonly style="background:#f1f5f9;font-weight:700;">
      </td>
      <td>
        <button type="button" class="btn btn-danger btn-sm btn-delete-row"><i class="fa-solid fa-trash"></i></button>
      </td>
    `;

    tbody.appendChild(tr);

    // Calculate row item total
    const calcRow = () => {
        const src = tr.querySelector('.rx-source')?.value || 'clinic';
        const uPriceInp = tr.querySelector('.rx-uprice');
        const iTotalInp = tr.querySelector('.rx-itotal');

        if (src === 'pharmacy') {
            if (uPriceInp) { uPriceInp.value = '0.00'; uPriceInp.disabled = true; uPriceInp.style.opacity = '0.5'; }
            if (iTotalInp) { iTotalInp.value = '0.00'; iTotalInp.style.opacity = '0.5'; }
        } else {
            if (uPriceInp) { uPriceInp.disabled = false; uPriceInp.style.opacity = '1'; }
            if (iTotalInp) { iTotalInp.style.opacity = '1'; }
            const qtyStr = tr.querySelector('.rx-qty').value;
            const numQty = parseFloat(qtyStr.replace(/[^0-9.]/g, '')) || 1;
            const uPrice = parseFloat(tr.querySelector('.rx-uprice')?.value) || 0;
            const total = numQty * uPrice;
            if (iTotalInp) iTotalInp.value = total.toFixed(2);
        }
        recalculateGrandTotal();
    };

    tr.querySelector('.rx-qty').addEventListener('input', calcRow);
    tr.querySelector('.rx-uprice').addEventListener('input', calcRow);
    tr.querySelector('.rx-source').addEventListener('change', calcRow);

    // Initial calc
    calcRow();

    // Autocomplete drug search
    const medInput = tr.querySelector('.rx-med-name');
    const dropdown = tr.querySelector('.med-search-dropdown');

    medInput.addEventListener('input', debounce(async () => {
        const q = medInput.value.trim();
        if (q.length < 1) {
            dropdown.style.display = 'none';
            tr.classList.remove('active-dropdown');
            return;
        }

        try {
            const json = await api.get('/medicines.php', { q });

            if (json.status === 'success' && json.data.length > 0) {
                dropdown.innerHTML = json.data.map(m => {
                    const qty = parseInt(m.stock_qty !== undefined ? m.stock_qty : 500);
                    let stockBadge = '';
                    if (qty <= 0) {
                        stockBadge = `<span class="stock-badge stock-out"><i class="fa-solid fa-circle-xmark"></i> OUT OF STOCK</span>`;
                    } else if (qty <= 20) {
                        stockBadge = `<span class="stock-badge stock-low"><i class="fa-solid fa-triangle-exclamation"></i> Low Stock (${qty})</span>`;
                    } else {
                        stockBadge = `<span class="stock-badge stock-ok"><i class="fa-solid fa-check"></i> Stock: ${qty}</span>`;
                    }

                    return `
                        <div class="autocomplete-item" data-id="${m.id}">
                          <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                            <strong>${escapeHtml(m.name)}</strong>
                            ${stockBadge}
                          </div>
                          <small>Type: ${escapeHtml(m.type || 'Tablet')} ${m.generic_name ? `| ${escapeHtml(m.generic_name)}` : ''} | Price: LKR ${parseFloat(m.unit_price || 0).toFixed(2)}</small>
                        </div>
                    `;
                }).join('');
                dropdown.style.display = 'block';
                tr.classList.add('active-dropdown');

                dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
                    item.addEventListener('click', () => {
                        const mid = item.getAttribute('data-id');
                        const med = json.data.find(x => x.id == mid);
                        medInput.value = med.name;
                        if (med.default_strength) tr.querySelector('.rx-strength').value = med.default_strength;
                        if (med.default_dosage) tr.querySelector('.rx-dosage').value = med.default_dosage;
                        if (med.default_instruction) tr.querySelector('.rx-instruction').value = med.default_instruction;
                        if (med.unit_price !== undefined) tr.querySelector('.rx-uprice').value = med.unit_price;
                        dropdown.style.display = 'none';
                        tr.classList.remove('active-dropdown');
                        calcRow();
                    });
                });
            } else {
                dropdown.style.display = 'none';
                tr.classList.remove('active-dropdown');
            }
        } catch {
            dropdown.style.display = 'none';
            tr.classList.remove('active-dropdown');
        }
    }, 250));

    document.addEventListener('click', (e) => {
        if (!medInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
            tr.classList.remove('active-dropdown');
        }
    });

    tr.querySelector('.btn-delete-row').addEventListener('click', () => {
        if (tbody.querySelectorAll('tr').length > 1) {
            tr.remove();
            recalculateGrandTotal();
        } else {
            showToast('At least one medicine row is required.', 'warning');
        }
    });
}

// ─── Grand Total ───────────────────────────────────────────────────────────────

function recalculateGrandTotal() {
    let medsTotal = 0;
    document.querySelectorAll('#rx-items-tbody tr').forEach(tr => {
        const itotal = parseFloat(tr.querySelector('.rx-itotal')?.value) || 0;
        medsTotal += itotal;
    });

    const consultFee = parseFloat(document.getElementById('bill-consultation-fee')?.value) || 0;
    const hospitalFee = parseFloat(document.getElementById('bill-hospital-fee')?.value) || 0;
    const discount = parseFloat(document.getElementById('bill-discount')?.value) || 0;
    const grandTotal = Math.max(0, consultFee + hospitalFee + medsTotal - discount);

    if (document.getElementById('bill-meds-total')) document.getElementById('bill-meds-total').value = medsTotal.toFixed(2);
    if (document.getElementById('bill-grand-total')) document.getElementById('bill-grand-total').value = grandTotal.toFixed(2);
}

// ─── Prescription Save Button ─────────────────────────────────────────────────

function wireSaveButton() {
    const btnSavePrint = document.getElementById('btn-save-print-rx');
    if (!btnSavePrint) return;

    btnSavePrint.addEventListener('click', async () => {
        const patientId = document.getElementById('selected-patient-id').value;
        if (!patientId) {
            showToast('Please search & select a patient first!', 'error');
            return;
        }

        // Collect Rows
        const rows = document.querySelectorAll('#rx-items-tbody tr');
        const items = [];
        rows.forEach(tr => {
            const name = tr.querySelector('.rx-med-name').value.trim();
            if (name) {
                items.push({
                    medicine_name: name,
                    strength: tr.querySelector('.rx-strength').value.trim(),
                    dosage: tr.querySelector('.rx-dosage').value.trim(),
                    instruction: tr.querySelector('.rx-instruction').value,
                    duration: tr.querySelector('.rx-duration').value.trim(),
                    quantity: tr.querySelector('.rx-qty').value.trim(),
                    unit_price: parseFloat(tr.querySelector('.rx-uprice')?.value) || 0,
                    item_total: parseFloat(tr.querySelector('.rx-itotal')?.value) || 0,
                    item_source: tr.querySelector('.rx-source')?.value || 'clinic'
                });
            }
        });

        if (items.length === 0) {
            showToast('Please enter at least one drug in the prescription.', 'error');
            return;
        }

        const payload = {
            patient_id: patientId,
            doctor_id: parseInt(document.getElementById('active-doctor-select')?.value || 1),
            bp: document.getElementById('vitals-bp').value.trim(),
            pulse: document.getElementById('vitals-pulse').value.trim(),
            weight: document.getElementById('vitals-weight').value.trim(),
            temp: document.getElementById('vitals-temp').value.trim(),
            chief_complaint: document.getElementById('rx-complaint').value.trim(),
            diagnosis: document.getElementById('rx-diagnosis').value.trim(),
            advice: document.getElementById('rx-advice').value.trim(),
            follow_up_date: document.getElementById('rx-followup').value,
            consultation_fee: parseFloat(document.getElementById('bill-consultation-fee')?.value) || 0,
            hospital_fee: parseFloat(document.getElementById('bill-hospital-fee')?.value) || 0,
            medicines_total: parseFloat(document.getElementById('bill-meds-total')?.value) || 0,
            discount: parseFloat(document.getElementById('bill-discount')?.value) || 0,
            grand_total: parseFloat(document.getElementById('bill-grand-total')?.value) || 0,
            items: items
        };

        try {
            btnSavePrint.disabled = true;
            btnSavePrint.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

            const json = await api.post('/prescriptions.php', payload);
            btnSavePrint.disabled = false;
            btnSavePrint.innerHTML = '<i class="fa-solid fa-print"></i> Save & Print Prescription';

            if (json.status === 'success' && json.prescription_id) {
                document.dispatchEvent(new CustomEvent('crx:reloadStats'));
                openPrintPreviewModal(json.prescription_id);
                clearDraft();
                clearPrescriptionForm();
            } else {
                showToast('Error: ' + json.message, 'error');
            }
        } catch (err) {
            btnSavePrint.disabled = false;
            btnSavePrint.innerHTML = '<i class="fa-solid fa-print"></i> Save & Print Prescription';
            showToast('An unexpected error occurred: ' + err.message, 'error');
        }
    });
}

// ─── Print Preview ─────────────────────────────────────────────────────────────

function renderPrintDocument(rx, paperSize, printMode) {
    const s = rx.settings || {};
    const formattedDate = new Date(rx.created_at).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
    });

    const isRxOnly = (printMode === 'rx_only');

    return `
      <div class="print-prescription-doc paper-size-${paperSize} ${isRxOnly ? 'print-mode-rx-only' : 'print-mode-rx-bill'}" id="doc-print-wrapper">

        <!-- Clinic & Doctor Header -->
        <div class="print-header">
          <!-- THERMAL CENTER BLOCK (hidden on A4/A5) -->
          <div class="thermal-header-block">
            <h3 class="thermal-clinic-name">${escapeHtml(s.clinic_name || 'Clinic Name')}</h3>
            <p class="thermal-clinic-address">${escapeHtml(s.clinic_address || '')}</p>
            <p class="thermal-clinic-phone">Tel: ${escapeHtml(s.clinic_phone || '')}</p>
            <div class="thermal-divider"></div>
            <h2 class="thermal-doctor-name">${escapeHtml(s.doctor_name || 'Doctor Name')}</h2>
            <p class="thermal-qualifications">${escapeHtml(s.qualifications || '')}</p>
            <p class="thermal-slmc">SLMC Reg No: ${escapeHtml(s.slmc_reg || '')}</p>
          </div>
          <!-- A4/A5 SIDE-BY-SIDE BLOCK (hidden on Thermal) -->
          <div class="doctor-info standard-only">
            <h2>${escapeHtml(s.doctor_name || 'Doctor Name')}</h2>
            <p><strong>${escapeHtml(s.qualifications || '')}</strong></p>
            <p>SLMC Reg No: ${escapeHtml(s.slmc_reg || '')}</p>
          </div>
          <div class="clinic-info standard-only">
            ${s.clinic_logo ? `<img src="${escapeHtml(s.clinic_logo)}" style="max-height:45px; max-width:120px; object-fit:contain; margin-bottom:4px;" alt="Clinic Logo">` : ''}
            <h3>${escapeHtml(s.clinic_name || 'Clinic Name')}</h3>
            <p>${escapeHtml(s.clinic_address || '')}</p>
            <p>Tel: ${escapeHtml(s.clinic_phone || '')}</p>
          </div>
        </div>

        <!-- Patient Info Bar -->
        <div class="print-patient-bar">
          <div><label>Rx No:</label><span>${escapeHtml(rx.rx_number)}</span></div>
          <div><label>Date & Time:</label><span>${formattedDate}</span></div>
          <div><label>Patient Name:</label><span>${escapeHtml(rx.patient_name)} (${escapeHtml(rx.patient_age)}y / ${escapeHtml(rx.patient_gender)})</span></div>
          <div><label>Patient ID:</label><span>${escapeHtml(rx.patient_code)}</span></div>
        </div>

        <!-- Vitals Bar (if present) -->
        ${(rx.bp || rx.pulse || rx.weight || rx.temp) ? `
          <div class="print-vitals-row">
            ${rx.bp ? `<span>BP: ${escapeHtml(rx.bp)}</span>` : ''}
            ${rx.pulse ? `<span>Pulse: ${escapeHtml(rx.pulse)} bpm</span>` : ''}
            ${rx.weight ? `<span>Weight: ${escapeHtml(rx.weight)} kg</span>` : ''}
            ${rx.temp ? `<span>Temp: ${escapeHtml(rx.temp)} °F</span>` : ''}
          </div>
        ` : ''}

        <!-- Diagnosis & Complaints -->
        ${(rx.chief_complaint || rx.diagnosis) ? `
          <div class="print-clinical-notes">
            ${rx.chief_complaint ? `<p><strong>Complaints:</strong> ${escapeHtml(rx.chief_complaint)}</p>` : ''}
            ${rx.diagnosis ? `<p><strong>Diagnosis:</strong> ${escapeHtml(rx.diagnosis)}</p>` : ''}
          </div>
        ` : ''}

        <!-- Rx Symbol & Medicines Table (Separated by Clinic & Outside Pharmacy) -->
        ${(() => {
            const clinicItems = rx.items.filter(i => (i.item_source || 'clinic') !== 'pharmacy');
            const pharmacyItems = rx.items.filter(i => (i.item_source || 'clinic') === 'pharmacy');

            let html = '';

            // 1. Clinic In-House Medicines
            if (clinicItems.length > 0) {
                html += `
                  <div class="print-rx-section">
                    <div class="rx-header-row" style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                      <span class="rx-symbol" style="font-family:'Times New Roman',serif; font-weight:900; font-size:22pt; line-height:1; color:#000000; margin:0;">℞</span>
                      ${pharmacyItems.length > 0 ? `<span style="font-size:8.5pt; font-family:'Plus Jakarta Sans',sans-serif; font-weight:800; color:#000000;">(Issued from Clinic / සායනයෙන් නිකුත් කළ ඖෂධ)</span>` : ''}
                    </div>
                    <table class="print-med-table">
                      <thead>
                        <tr>
                          <th style="width: 5%;">#</th>
                          <th style="${isRxOnly ? 'width: 42%;' : 'width: 32%;'}">Medicine & Strength</th>
                          <th style="${isRxOnly ? 'width: 18%;' : 'width: 15%;'}">Dosage</th>
                          <th style="${isRxOnly ? 'width: 25%;' : 'width: 20%;'}">Inst.</th>
                          <th style="${isRxOnly ? 'width: 10%;' : 'width: 10%;'}">Days</th>
                          ${!isRxOnly ? `<th style="width: 18%; text-align:right;">Amount</th>` : ''}
                        </tr>
                      </thead>
                      <tbody>
                        ${clinicItems.map((item, idx) => `
                          <tr>
                            <td>${idx + 1}.</td>
                            <td class="med-name-cell">${escapeHtml(item.medicine_name)} <span style="font-weight:700;color:#000000;">${escapeHtml(item.strength)}</span></td>
                            <td>${escapeHtml(item.dosage)}</td>
                            <td class="med-instruction-cell">${escapeHtml(item.instruction)}</td>
                            <td>${escapeHtml(item.duration || '-')}</td>
                            ${!isRxOnly ? `<td style="text-align:right; font-weight:700; color:#000000;">LKR ${parseFloat(item.item_total || 0).toFixed(2)}</td>` : ''}
                          </tr>
                        `).join('')}
                      </tbody>
                    </table>
                  </div>
                `;
            }

            // 2. Outside Pharmacy Medicines Section
            if (pharmacyItems.length > 0) {
                html += `
                  <div class="print-rx-section print-pharmacy-section" style="margin-top:10px; border-top:1.5px dashed #000000; padding-top:6px;">
                    <div style="font-size:8.5pt; font-weight:900; margin-bottom:4px; color:#000000; text-transform:uppercase;">
                      🏪 To Purchase from Outside Pharmacy (බාහිර ඖෂධසැලෙන් ලබාගත යුතුයි):
                    </div>
                    <table class="print-med-table">
                      <thead>
                        <tr>
                          <th style="width: 5%;">#</th>
                          <th style="width: 42%;">Medicine & Strength</th>
                          <th style="width: 18%;">Dosage</th>
                          <th style="width: 25%;">Inst.</th>
                          <th style="width: 10%;">Days</th>
                        </tr>
                      </thead>
                      <tbody>
                        ${pharmacyItems.map((item, idx) => `
                          <tr>
                            <td>${idx + 1}.</td>
                            <td class="med-name-cell">${escapeHtml(item.medicine_name)} <span style="font-weight:700;color:#000000;">${escapeHtml(item.strength)}</span></td>
                            <td>${escapeHtml(item.dosage)}</td>
                            <td class="med-instruction-cell">${escapeHtml(item.instruction)}</td>
                            <td>${escapeHtml(item.duration || '-')}</td>
                          </tr>
                        `).join('')}
                      </tbody>
                    </table>
                  </div>
                `;
            }

            return html;
        })()}

        <!-- Dispensary Billing Summary Box (Hidden if Prescription Only) -->
        ${!isRxOnly ? `
        <div class="print-bill-summary-box" style="margin-top:10px; border-top:1px solid #000000; padding-top:10px; font-size:9.5pt;">
          <div style="display:flex; justify-content:flex-end;">
            <table style="width:260px; border-collapse:collapse;">
              <tr>
                <td style="padding:2px 0; color:#000000; font-weight:700;">Doctor Fee:</td>
                <td style="padding:2px 0; text-align:right; font-weight:700; color:#000000;">LKR ${parseFloat(rx.consultation_fee || 0).toFixed(2)}</td>
              </tr>
              ${parseFloat(rx.hospital_fee || 0) > 0 ? `
              <tr>
                <td style="padding:2px 0; color:#000000; font-weight:700;">Center Fee:</td>
                <td style="padding:2px 0; text-align:right; font-weight:700; color:#000000;">LKR ${parseFloat(rx.hospital_fee).toFixed(2)}</td>
              </tr>
              ` : ''}
              <tr>
                <td style="padding:2px 0; color:#000000; font-weight:700;">Medicines Total:</td>
                <td style="padding:2px 0; text-align:right; font-weight:700; color:#000000;">LKR ${parseFloat(rx.medicines_total || 0).toFixed(2)}</td>
              </tr>
              ${parseFloat(rx.discount || 0) > 0 ? `
              <tr>
                <td style="padding:2px 0; color:#000000; font-weight:700;">Discount:</td>
                <td style="padding:2px 0; text-align:right; font-weight:700; color:#000000;">- LKR ${parseFloat(rx.discount).toFixed(2)}</td>
              </tr>
              ` : ''}
              <tr class="net-total-row" style="border-top:2px solid #000000; font-weight:900; font-size:12pt; color:#000000;">
                <td style="padding:4px 0; color:#000000;">NET TOTAL:</td>
                <td style="padding:4px 0; text-align:right; color:#000000;">LKR ${parseFloat(rx.grand_total || 0).toFixed(2)}</td>
              </tr>
            </table>
          </div>
        </div>
        ` : ''}

        <!-- Footer & Signature -->
        <div class="print-footer">
          <div class="print-advice-box">
            ${rx.advice ? `<h4>Doctor's Advice:</h4><p>${escapeHtml(rx.advice)}</p>` : ''}
            ${rx.follow_up_date ? `<p style="margin-top:6px;font-weight:600;color:#000000;">Follow-up Date: ${escapeHtml(rx.follow_up_date)}</p>` : ''}
          </div>
          <div class="signature-box">
            <div class="signature-line">
              ${escapeHtml(s.doctor_name || 'Doctor Signature')}
            </div>
          </div>
        </div>
      </div>
    `;
}

async function openPrintPreviewModal(rxId) {
    try {
        const json = await api.get(`/prescriptions.php`, { id: rxId });

        if (json.status === 'success' && json.data) {
            activeRxData = json.data;
            const s = activeRxData.settings || {};
            currentPrintPaperSize = s.print_paper_size || 'A5';

            const chkBill = document.getElementById('chk-include-bill');
            if (s.practice_mode === 'channeling' || (chkBill && !chkBill.checked)) {
                currentPrintMode = 'rx_only';
            } else {
                currentPrintMode = 'rx_bill';
            }

            refreshPrintView();

            const modal = document.getElementById('modal-print-preview');
            modal.classList.add('active');
        }
    } catch (err) {
        showToast('Failed to load print preview: ' + err.message, 'error');
    }
}

function refreshPrintView() {
    if (!activeRxData) return;
    const printHTML = renderPrintDocument(activeRxData, currentPrintPaperSize, currentPrintMode);

    document.getElementById('print-modal-body').innerHTML = printHTML;
    document.getElementById('print-area').innerHTML = printHTML;

    updatePaperSizeButtons(currentPrintPaperSize);
    updatePrintModeButtons(currentPrintMode);
}

function updatePrintModeButtons(mode) {
    document.querySelectorAll('.btn-print-mode').forEach(btn => {
        const btnMode = btn.getAttribute('data-mode');
        if (btnMode === mode) {
            btn.classList.add('btn-primary');
            btn.classList.remove('btn-outline');
        } else {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-outline');
        }
    });
}

function updatePaperSizeButtons(selectedSize) {
    document.body.className = `print-size-${selectedSize}`;
    document.querySelectorAll('.print-prescription-doc').forEach(docWrapper => {
        docWrapper.className = `print-prescription-doc paper-size-${selectedSize}`;
    });

    document.querySelectorAll('.btn-paper-size').forEach(btn => {
        const sz = btn.getAttribute('data-size');
        if (sz === selectedSize) {
            btn.classList.add('btn-primary');
            btn.classList.remove('btn-outline');
        } else {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-outline');
        }
    });
}

function wirePrintModalControls() {
    // Print mode toggle buttons
    document.querySelectorAll('.btn-print-mode').forEach(btn => {
        btn.addEventListener('click', () => {
            currentPrintMode = btn.getAttribute('data-mode');
            refreshPrintView();
        });
    });

    // Paper size toggle buttons
    document.querySelectorAll('.btn-paper-size').forEach(btn => {
        btn.addEventListener('click', () => {
            const size = btn.getAttribute('data-size');
            updatePaperSizeButtons(size);
        });
    });

    // Browser / Electron print trigger
    document.getElementById('btn-trigger-print')?.addEventListener('click', () => {
        if (window.electronAPI && typeof window.electronAPI.silentPrint === 'function') {
            const printHTML = document.getElementById('print-area').innerHTML;
            window.electronAPI.silentPrint({
                html: printHTML,
                paperSize: currentPrintPaperSize
            });

            // Auto-close print preview modal after silent printing
            setTimeout(() => {
                document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
                const searchInput = document.getElementById('patient-search-input');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }, 400);
        } else {
            window.print();
        }
    });

    // Handle manual close of print preview modal
    document.querySelectorAll('#modal-print-preview .modal-close, #modal-print-preview .btn-modal-close').forEach(btn => {
        btn.addEventListener('click', () => {
            setTimeout(() => {
                const searchInput = document.getElementById('patient-search-input');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }, 50);
        });
    });

    // WhatsApp share
    document.getElementById('btn-share-whatsapp')?.addEventListener('click', () => {
        if (!activeRxData) return;
        const rx = activeRxData;
        const s = rx.settings || {};

        let msg = `🏥 *${s.clinic_name || 'Clinic'}*\n`;
        msg += `👨‍⚕️ ${s.doctor_name || 'Doctor'} (${s.qualifications || ''})\n`;
        msg += `--------------------------------\n`;
        msg += `📄 *Rx No:* ${rx.rx_number}\n`;
        msg += `👤 *Patient:* ${rx.patient_name} (${rx.patient_age}y / ${rx.patient_gender})\n`;
        msg += `📅 *Date:* ${new Date(rx.created_at || Date.now()).toLocaleDateString()}\n`;

        if (rx.diagnosis) {
            msg += `🩺 *Diagnosis:* ${rx.diagnosis}\n`;
        }

        const clinicItems = (rx.items || []).filter(i => (i.item_source || 'clinic') !== 'pharmacy');
        const pharmacyItems = (rx.items || []).filter(i => (i.item_source || 'clinic') === 'pharmacy');

        if (clinicItems.length > 0) {
            msg += `\n💊 *Issued from Clinic:*\n`;
            clinicItems.forEach((item, idx) => {
                msg += `${idx + 1}. *${item.medicine_name} ${item.strength || ''}* - ${item.dosage || ''} (${item.instruction || ''}) [Days: ${item.duration || '-'}]\n`;
            });
        }

        if (pharmacyItems.length > 0) {
            msg += `\n🏪 *To Buy from Outside Pharmacy:*\n`;
            pharmacyItems.forEach((item, idx) => {
                msg += `${idx + 1}. *${item.medicine_name} ${item.strength || ''}* - ${item.dosage || ''} (${item.instruction || ''}) [Days: ${item.duration || '-'}]\n`;
            });
        }

        if (currentPrintMode === 'rx_bill') {
            msg += `\n💰 *Total Net Bill:* LKR ${parseFloat(rx.grand_total || 0).toFixed(2)}\n`;
        }

        if (rx.advice) {
            msg += `\n📋 *Doctor's Advice:* ${rx.advice}\n`;
        }

        if (rx.follow_up_date) {
            msg += `🗓️ *Follow-up Date:* ${rx.follow_up_date}\n`;
        }

        msg += `--------------------------------\nGet Well Soon! 🌸`;

        let rawPhone = (rx.patient_phone || '').replace(/[^0-9]/g, '');
        if (rawPhone.startsWith('0')) {
            rawPhone = '94' + rawPhone.substring(1);
        }

        const whatsappUrl = `https://wa.me/${rawPhone}?text=${encodeURIComponent(msg)}`;
        window.open(whatsappUrl, '_blank');
    });
}

// ─── Clear Prescription Form ───────────────────────────────────────────────────

function clearPrescriptionForm() {
    state.set('selectedPatient', null);
    document.getElementById('selected-patient-id').value = '';
    const badge = document.getElementById('selected-patient-badge');
    if (badge) {
        badge.innerHTML = `
          <div style="text-align:center; color:var(--text-muted); font-size:13.5px; padding:10px 0;">
            <i class="fa-solid fa-user-injured" style="margin-right:6px;"></i> Search and select a patient to start prescribing.
          </div>
        `;
    }
    document.getElementById('patient-search-input').value = '';

    // Clear vitals
    document.getElementById('vitals-bp').value = '';
    document.getElementById('vitals-pulse').value = '';
    document.getElementById('vitals-weight').value = '';
    document.getElementById('vitals-temp').value = '';

    // Clear notes & advice
    document.getElementById('rx-complaint').value = '';
    document.getElementById('rx-diagnosis').value = '';
    document.getElementById('rx-advice').value = '';
    document.getElementById('rx-followup').value = '';

    // Reset items to single clean row
    const tbody = document.getElementById('rx-items-tbody');
    if (tbody) {
        tbody.innerHTML = '';
        addRxRow();
    }

    // Reset billing inputs to default
    const consultFee = document.getElementById('bill-consultation-fee');
    if (consultFee) consultFee.value = '1000';

    const discount = document.getElementById('bill-discount');
    if (discount) discount.value = '0';

    recalculateGrandTotal();
}
