// assets/js/modules/billing.js
import * as api   from '../api.js';
import * as state from '../state.js';
import { showToast } from '../components/toast.js';
import { escapeHtml } from '../api.js';
import { createAutocomplete } from '../components/autocomplete.js';

export function init() {
    initChannelingBilling();
    loadChannelingBillsToday();
}

// Toggle billing price columns in the Rx builder
function toggleBillingUI(show) {
    const billInputsRow = document.getElementById('billing-inputs-row');
    if (billInputsRow) {
        billInputsRow.style.display = show ? 'flex' : 'none';
    }
    document.querySelectorAll('.rx-uprice, .rx-itotal').forEach(el => {
        const td = el.closest('td');
        if (td) td.style.display = show ? '' : 'none';
    });
    const thPrice = document.querySelector('th[data-i18n="th_unit_price"]');
    const thTotal = document.querySelector('th[data-i18n="th_item_total"]');
    if (thPrice) thPrice.style.display = show ? '' : 'none';
    if (thTotal) thTotal.style.display = show ? '' : 'none';
}

function initChannelingBilling() {
    const selectedBadge   = document.getElementById('chl-selected-patient-badge');
    const selectedIdInput = document.getElementById('chl-selected-patient-id');
    const selectedNameDiv = document.getElementById('chl-selected-patient-name');
    const selectedMetaSmall = document.getElementById('chl-selected-patient-meta');

    if (!document.getElementById('chl-patient-search')) return;

    // Patient autocomplete — replaces the inline debounced typeahead from app.js
    createAutocomplete({
        inputId:    'chl-patient-search',
        dropdownId: 'chl-patient-dropdown',
        fetchFn: async (q) => {
            const r = await api.get('/patients.php', { q });
            if (r.status === 'success') {
                // Attach a label property so createAutocomplete can display it
                return r.data.map(p => ({
                    ...p,
                    label: `${p.name} (${p.age}y / ${p.gender}) — ${p.patient_code}`,
                }));
            }
            return [];
        },
        onSelect: (p) => {
            selectedIdInput.value = p.id;
            selectedNameDiv.textContent = p.name;
            selectedMetaSmall.textContent =
                `Patient ID: ${p.patient_code} | Age: ${p.age}y / ${p.gender} | Phone: ${p.phone || 'N/A'}`;
            selectedBadge.style.display = 'block';
        },
        minChars: 1,
    });

    // Auto-calculate total on fee input
    document.querySelectorAll('.chl-calc-input').forEach(inp => {
        inp.addEventListener('input', () => {
            const docFee  = parseFloat(document.getElementById('chl-doctor-fee').value) || 0;
            const hospFee = parseFloat(document.getElementById('chl-hospital-fee').value) || 0;
            document.getElementById('chl-grand-total').value = (docFee + hospFee).toFixed(2);
        });
    });

    // Issue & Print button
    const btnPrint = document.getElementById('btn-print-channeling-bill');
    if (btnPrint) {
        btnPrint.addEventListener('click', async () => {
            const patientId = selectedIdInput.value;
            if (!patientId) {
                showToast('Please search & select a patient first!', 'error');
                return;
            }

            const payload = {
                patient_id:   parseInt(patientId),
                doctor_id:    parseInt(document.getElementById('chl-doctor-select').value || 1),
                doctor_fee:   parseFloat(document.getElementById('chl-doctor-fee').value) || 0,
                hospital_fee: parseFloat(document.getElementById('chl-hospital-fee').value) || 0,
                payment_status: 'Paid',
            };

            try {
                btnPrint.disabled = true;
                btnPrint.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving & Printing...';

                const json = await api.post('/queue_billing.php', payload);
                btnPrint.disabled = false;
                btnPrint.innerHTML = '<i class="fa-solid fa-print"></i> Issue & Print';

                if (json.status === 'success' && json.data) {
                    document.dispatchEvent(new CustomEvent('crx:reloadQueue'));
                    loadChannelingBillsToday();
                    printChannelingReceipt(json.data);

                    selectedIdInput.value = '';
                    selectedBadge.style.display = 'none';
                    selectedNameDiv.textContent = '--';
                    selectedMetaSmall.textContent = '--';
                } else {
                    showToast(json.message || 'Failed to issue bill', 'error');
                }
            } catch (err) {
                btnPrint.disabled = false;
                btnPrint.innerHTML = '<i class="fa-solid fa-print"></i> Issue & Print';
                console.error(err);
            }
        });
    }

    // Phone Booking button (no print, shows token info)
    const btnPhoneBook = document.getElementById('btn-phone-book-channeling');
    if (btnPhoneBook) {
        btnPhoneBook.addEventListener('click', async () => {
            const patientId = selectedIdInput.value;
            if (!patientId) {
                showToast('Please search & select a patient first!', 'error');
                return;
            }

            const payload = {
                patient_id:   parseInt(patientId),
                doctor_id:    parseInt(document.getElementById('chl-doctor-select').value || 1),
                doctor_fee:   parseFloat(document.getElementById('chl-doctor-fee').value) || 0,
                hospital_fee: parseFloat(document.getElementById('chl-hospital-fee').value) || 0,
                payment_status: 'Unpaid',
            };

            try {
                btnPhoneBook.disabled = true;
                btnPhoneBook.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Booking...';

                const json = await api.post('/queue_billing.php', payload);
                btnPhoneBook.disabled = false;
                btnPhoneBook.innerHTML = '<i class="fa-solid fa-phone"></i> Phone Booking';

                if (json.status === 'success' && json.data) {
                    document.dispatchEvent(new CustomEvent('crx:reloadQueue'));
                    loadChannelingBillsToday();

                    showToast(
                        `Phone Booking Successful! Token ${json.data.token_number} — ${json.data.patient_name}`,
                        'success',
                        6000
                    );

                    selectedIdInput.value = '';
                    selectedBadge.style.display = 'none';
                    selectedNameDiv.textContent = '--';
                    selectedMetaSmall.textContent = '--';
                } else {
                    showToast(json.message || 'Booking failed', 'error');
                }
            } catch (err) {
                btnPhoneBook.disabled = false;
                btnPhoneBook.innerHTML = '<i class="fa-solid fa-phone"></i> Phone Booking';
                console.error(err);
            }
        });
    }

    // Refresh bills list button
    const btnRefresh = document.getElementById('btn-refresh-chl-bills-list');
    if (btnRefresh) {
        btnRefresh.addEventListener('click', loadChannelingBillsToday);
    }

    // Billing UI toggle checkbox (Rx builder)
    const chkIncludeBill = document.getElementById('chk-include-bill');
    if (chkIncludeBill) {
        chkIncludeBill.addEventListener('change', (e) => {
            toggleBillingUI(e.target.checked);
        });
    }
}

// Fetch and display today's channeling bills
async function loadChannelingBillsToday() {
    const tbody = document.getElementById('tbody-channeling-bills');
    if (!tbody) return;

    try {
        const json = await api.get('/queue_billing.php');

        if (json.status === 'success' && json.data) {
            const bills = json.data;
            if (bills.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No receipts issued today.</td></tr>`;
                return;
            }

            tbody.innerHTML = bills.map(b => {
                const isPaid = b.payment_status === 'Paid';
                return `
                    <tr>
                      <td><strong>${escapeHtml(b.bill_number)}</strong></td>
                      <td>${escapeHtml(b.patient_name)}</td>
                      <td>${escapeHtml(b.doctor_name)}</td>
                      <td><span class="badge" style="background:var(--teal-600); color:#ffffff; font-weight:800; border-radius:4px; padding:2px 6px;">Token ${escapeHtml(b.token_number)}</span></td>
                      <td>
                        <div style="display:flex; flex-direction:column; gap:2px;">
                          <strong>LKR ${parseFloat(b.total_amount).toFixed(2)}</strong>
                          <span class="badge" style="background:${isPaid ? '#dcfce7' : '#fef08a'}; color:${isPaid ? '#166534' : '#854d0e'}; font-weight:800; font-size:10px; padding:1px 4px; border-radius:3px; align-self:flex-start;">
                            ${isPaid ? 'Paid' : 'Unpaid'}
                          </span>
                        </div>
                      </td>
                      <td>
                        ${isPaid ? `
                          <button class="btn btn-outline btn-sm btn-reprint-chl" data-id="${b.id}" style="color:var(--teal-600); border-color:var(--teal-600); font-size:11px; padding:3px 6px;"><i class="fa-solid fa-print"></i> Re-print</button>
                        ` : `
                          <button class="btn btn-success btn-sm btn-collect-chl" data-id="${b.id}" style="background:var(--emerald-600); border-color:var(--emerald-600); font-size:11px; padding:3px 6px;"><i class="fa-solid fa-cash-register"></i> Collect & Print</button>
                        `}
                      </td>
                    </tr>
                `;
            }).join('');

            // Re-print listeners
            tbody.querySelectorAll('.btn-reprint-chl').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const billId = btn.getAttribute('data-id');
                    try {
                        const detailJson = await api.get(`/queue_billing.php`, { id: billId });
                        if (detailJson.status === 'success' && detailJson.data) {
                            printChannelingReceipt(detailJson.data);
                        }
                    } catch (err) {
                        console.error(err);
                    }
                });
            });

            // Collect & Print listeners (unpaid phone bookings)
            tbody.querySelectorAll('.btn-collect-chl').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const billId = btn.getAttribute('data-id');
                    if (window.confirm('Collect payment and print channeling ticket?')) {
                        try {
                            const putJson = await api.put('/queue_billing.php', { id: parseInt(billId) });
                            if (putJson.status === 'success') {
                                const detailJson = await api.get('/queue_billing.php', { id: billId });
                                if (detailJson.status === 'success' && detailJson.data) {
                                    printChannelingReceipt(detailJson.data);
                                }
                                loadChannelingBillsToday();
                            } else {
                                showToast(putJson.message || 'Failed to collect payment', 'error');
                            }
                        } catch (err) {
                            console.error(err);
                        }
                    }
                });
            });
        }
    } catch (err) {
        console.error(err);
    }
}

// Render and trigger print of a channeling receipt
function printChannelingReceipt(bill) {
    const printArea = document.getElementById('print-area');
    if (!printArea) return;

    const settings = state.get('settings');
    const formattedDate = new Date(bill.created_at).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
    });

    printArea.innerHTML = `
      <div class="thermal-print-wrapper" style="box-sizing: border-box; width: 74mm; font-family: 'Courier New', Courier, monospace; font-size: 10pt; color: #000000; padding: 5px 3mm;">
        <div style="text-align: center; margin-bottom: 10px;">
          <h2 style="font-size: 14pt; margin: 0; font-weight: 800;">${escapeHtml(settings?.clinic_name || 'Medical Center')}</h2>
          <p style="margin: 3px 0; font-size: 9pt;">${escapeHtml(settings?.clinic_address || 'Clinic Address')}</p>
          <p style="margin: 3px 0; font-size: 9pt;">Tel: ${escapeHtml(settings?.clinic_phone || 'N/A')}</p>
        </div>

        <hr style="border: 0; border-top: 1px dashed #000; margin: 8px 0;">

        <div style="text-align: center; margin: 12px 0;">
          <div style="font-size: 11pt; font-weight: 700; text-transform: uppercase;">CHANNELING RECEIPT</div>
          <div style="font-size: 26pt; font-weight: 900; margin: 10px 0; border: 2px solid #000; display: inline-block; padding: 4px 18px; border-radius: 6px;">
            TOKEN ${escapeHtml(bill.token_number)}
          </div>
        </div>

        <hr style="border: 0; border-top: 1px dashed #000; margin: 8px 0;">

        <table style="width: 100%; border-collapse: collapse; font-size: 9.5pt;">
          <tr>
            <td style="padding: 2px 0; font-weight: 700; width: 35%;">Bill No:</td>
            <td style="padding: 2px 0; text-align: right;">${escapeHtml(bill.bill_number)}</td>
          </tr>
          <tr>
            <td style="padding: 2px 0; font-weight: 700;">Date/Time:</td>
            <td style="padding: 2px 0; text-align: right;">${formattedDate}</td>
          </tr>
          <tr>
            <td style="padding: 2px 0; font-weight: 700;">Doctor:</td>
            <td style="padding: 2px 0; text-align: right; font-weight: bold;">${escapeHtml(bill.doctor_name)}</td>
          </tr>
          <tr>
            <td style="padding: 2px 0; font-weight: 700;">Patient:</td>
            <td style="padding: 2px 0; text-align: right;">${escapeHtml(bill.patient_name)} (${escapeHtml(bill.patient_age)}y / ${escapeHtml(bill.patient_gender)})</td>
          </tr>
          <tr>
            <td style="padding: 2px 0; font-weight: 700;">Patient ID:</td>
            <td style="padding: 2px 0; text-align: right;">${escapeHtml(bill.patient_code)}</td>
          </tr>
        </table>

        <hr style="border: 0; border-top: 1px dashed #000; margin: 8px 0;">

        <table style="width: 100%; border-collapse: collapse; font-size: 10pt; margin-top: 5px;">
          <tr>
            <td style="padding: 3px 0; font-weight: 700;">Doctor Fee:</td>
            <td style="padding: 3px 0; text-align: right;">LKR ${parseFloat(bill.doctor_fee).toFixed(2)}</td>
          </tr>
          <tr>
            <td style="padding: 3px 0; font-weight: 700;">Center Fee:</td>
            <td style="padding: 3px 0; text-align: right;">LKR ${parseFloat(bill.hospital_fee).toFixed(2)}</td>
          </tr>
          <tr style="border-top: 1px solid #000; font-weight: 900; font-size: 11pt;">
            <td style="padding: 6px 0;">TOTAL PAID:</td>
            <td style="padding: 6px 0; text-align: right;">LKR ${parseFloat(bill.total_amount).toFixed(2)}</td>
          </tr>
        </table>

        <hr style="border: 0; border-top: 1px dashed #000; margin: 10px 0;">

        <div style="text-align: center; font-size: 9pt; font-style: italic; margin-top: 5px;">
          Please wait for your turn.<br>
          Thank You!
        </div>
      </div>
    `;

    document.body.className = 'print-size-Thermal';

    if (window.electronAPI && typeof window.electronAPI.silentPrint === 'function') {
        window.electronAPI.silentPrint({
            html: document.getElementById('print-area').innerHTML,
            paperSize: 'Thermal',
        });
    } else {
        window.print();
    }
}
