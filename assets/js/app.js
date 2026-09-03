// assets/js/app.js - Main Application Logic & Interactive Handler

document.addEventListener('DOMContentLoaded', () => {
  // Override native alert/confirm to prevent Electron OS-level focus freezing
  const originalAlert = window.alert;
  window.alert = function(msg) {
    originalAlert(msg);
    if (window.electronAPI && typeof window.electronAPI.refocusWindow === 'function') {
      window.electronAPI.refocusWindow();
    }
  };

  const originalConfirm = window.confirm;
  window.confirm = function(msg) {
    const res = originalConfirm(msg);
    if (window.electronAPI && typeof window.electronAPI.refocusWindow === 'function') {
      window.electronAPI.refocusWindow();
    }
    return res;
  };

  // Global App State
  const state = {
    selectedPatient: null,
    settings: null,
    medicinesMaster: [],
    rxItems: [],
    templates: [],
    queueMonitorInterval: null
  };

  // Initialize App
  initNavigation();
  initLanguageSwitcher();
  initDiseaseChips();
  loadSettings();
  loadStats();
  loadTemplates();
  initPatientSearch();
  initRxTable();
  initKeyboardShortcuts();
  initPediatricCalculator();
  initModals();
  loadPrescriptionsHistory();
  loadPatientsList();
  loadMedicinesList();
  loadDoctorsList();
  initDoctorManagement();
  loadWaitingQueue();
  initChannelingBilling();

  const btnRefreshMonitor = document.getElementById('btn-refresh-queue-monitor');
  if (btnRefreshMonitor) {
    btnRefreshMonitor.addEventListener('click', () => {
      loadQueueMonitorDashboard();
    });
  }

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

  function initLanguageSwitcher() {
    const select = document.getElementById('app-language-select');
    if (!select) return;

    select.value = i18n.currentLang;
    select.addEventListener('change', () => {
      i18n.setLanguage(select.value);
    });

    document.addEventListener('languageChanged', () => {
      // Re-render instructions on rows
      updateRowInstructionOptions();
    });
  }

  function updateRowInstructionOptions() {
    document.querySelectorAll('.rx-instruction').forEach(sel => {
      const curVal = sel.value;
      sel.innerHTML = `
        <option value="${i18n.t('ins_after_meals')}">${i18n.t('ins_after_meals')}</option>
        <option value="${i18n.t('ins_before_meals')}">${i18n.t('ins_before_meals')}</option>
        <option value="${i18n.t('ins_at_night')}">${i18n.t('ins_at_night')}</option>
        <option value="${i18n.t('ins_morning')}">${i18n.t('ins_morning')}</option>
        <option value="${i18n.t('ins_with_meals')}">${i18n.t('ins_with_meals')}</option>
        <option value="${i18n.t('ins_prn')}">${i18n.t('ins_prn')}</option>
        <option value="${i18n.t('ins_as_directed')}">${i18n.t('ins_as_directed')}</option>
      `;
      if (curVal) sel.value = curVal;
    });
  }

  // Speed Prescribing & Keyboard Shortcuts System (Issue #5)
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

  // Pediatric Body-Weight Dosage Calculator (Issue #7)
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
      let instruction = i18n.t('ins_after_meals') || 'කෑමට පසු (After meals)';

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
        instruction = i18n.t('ins_at_night') || 'රාත්‍රියට කෑමට පසු (Night, after meals)';
      } else if (drug === 'domperidone_5') {
        medName = 'Domperidone Syrup';
        strength = '5mg/5ml';
        mgPerKg = 0.25;
        mgPer5ml = 5;
        freq = '3 times daily before meals';
        dosageCode = 'tds (1-1-1)';
        instruction = i18n.t('ins_before_meals') || 'කෑමට පැය භාගයකට පෙර (Before meals)';
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

  // Navigation Logic
  function initNavigation() {
    const navItems = document.querySelectorAll('.nav-item');
    const tabContents = document.querySelectorAll('.tab-content');

    navItems.forEach(item => {
      item.addEventListener('click', (e) => {
        e.preventDefault();
        const targetTab = item.getAttribute('data-tab');

        navItems.forEach(i => i.classList.remove('active'));
        tabContents.forEach(c => c.classList.remove('active'));

        item.classList.add('active');
        const activeTabEl = document.getElementById(targetTab);
        if (activeTabEl) activeTabEl.classList.add('active');

        // Dynamic title refresh
        const pageTitle = item.textContent.trim();
        document.getElementById('page-headline').textContent = pageTitle;

        // Clear active queue interval if navigating away
        if (state.queueMonitorInterval) {
          clearInterval(state.queueMonitorInterval);
          state.queueMonitorInterval = null;
        }

        // Specific tab refresh triggers
        if (targetTab === 'tab-history') loadPrescriptionsHistory();
        if (targetTab === 'tab-patients') loadPatientsList();
        if (targetTab === 'tab-queue-monitor') {
          loadQueueMonitorDashboard();
          state.queueMonitorInterval = setInterval(loadQueueMonitorDashboard, 5000); // Live poll every 5 seconds
        }
        if (targetTab === 'tab-channeling-billing') loadChannelingBillsToday();
        if (targetTab === 'tab-medicines') loadMedicinesList();
        if (targetTab === 'tab-templates') loadTemplates();
      });
    });
  }

  // Fetch Settings
  async function loadSettings() {
    try {
      const res = await fetch('api/settings.php');
      const result = await res.json();
      if (result.status === 'success' && result.data) {
        state.settings = result.data;
        populateSettingsForm(result.data);
        syncOnlineSubscription();
      }
    } catch (err) {
      console.error('Failed to load settings', err);
    }
  }

  function populateSettingsForm(s) {
    document.getElementById('set-clinic-name').value = s.clinic_name || '';
    document.getElementById('set-clinic-address').value = s.clinic_address || '';
    document.getElementById('set-clinic-phone').value = s.clinic_phone || '';
    document.getElementById('set-paper-size').value = s.print_paper_size || 'A5';
    if (document.getElementById('set-practice-mode')) {
      document.getElementById('set-practice-mode').value = s.practice_mode || 'dispensary';
    }

    // Apply practice mode to Rx Builder toggle
    const chkBill = document.getElementById('chk-include-bill');
    if (chkBill) {
      if (s.practice_mode === 'channeling') {
        chkBill.checked = false;
        toggleBillingUI(false);
      } else {
        chkBill.checked = true;
        toggleBillingUI(true);
      }
    }

    // Subscription & Expiry Countdown
    const expiryEl = document.getElementById('sub-expiry-display');
    if (expiryEl) expiryEl.textContent = s.subscription_expires_at || '2026-12-31';

    const daysRemaining = (s.days_remaining !== undefined) ? s.days_remaining : 30;
    const daysDisp = document.getElementById('sub-days-remaining-display');
    const topDaysCount = document.getElementById('stat-license-days-count');
    
    if (daysDisp) daysDisp.textContent = `${daysRemaining} Days`;
    if (topDaysCount) {
      topDaysCount.textContent = `${daysRemaining} Days`;
      if (daysRemaining <= 7) {
        topDaysCount.style.color = '#e11d48';
      } else {
        topDaysCount.style.color = 'var(--teal-600)';
      }
    }

    const statusPill = document.getElementById('sub-status-pill');
    if (statusPill) {
      if (s.subscription_status === 'expired' || daysRemaining <= 0) {
        statusPill.style.background = '#fff1f2';
        statusPill.style.borderColor = '#fecdd3';
        statusPill.style.color = '#e11d48';
        statusPill.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> Expired`;
      } else {
        statusPill.style.background = '#f0fdf4';
        statusPill.style.borderColor = '#bbf7d0';
        statusPill.style.color = '#166534';
        statusPill.innerHTML = `<i class="fa-solid fa-circle-check"></i> Active`;
      }
    }

    const lockOverlay = document.getElementById('subscription-lock-overlay');
    if (lockOverlay) {
      if (s.subscription_status === 'expired' || daysRemaining <= 0) {
        lockOverlay.style.display = 'flex';
      } else {
        lockOverlay.style.display = 'none';
      }
    }

    // 7-Day Automatic Expiry Warning Banner
    const warningBanner = document.getElementById('expiry-warning-banner');
    const warningText = document.getElementById('expiry-warning-text');

    if (warningBanner) {
      if (s.subscription_status === 'expired' || daysRemaining <= 7) {
        const msg = i18n.t('reminder_7days_msg').replace('{days}', daysRemaining);
        if (warningText) warningText.textContent = msg;
        warningBanner.style.display = 'flex';
      } else {
        warningBanner.style.display = 'none';
      }
    }
  }

  // Toggle Billing UI helper
  function toggleBillingUI(show) {
    const billInputsRow = document.getElementById('billing-inputs-row');
    if (billInputsRow) {
      billInputsRow.style.display = show ? 'flex' : 'none';
    }
    // Also toggle price columns in Rx table
    document.querySelectorAll('.rx-uprice, .rx-itotal').forEach(el => {
      const td = el.closest('td');
      if (td) td.style.display = show ? '' : 'none';
    });
    const thPrice = document.querySelector('th[data-i18n="th_unit_price"]');
    const thTotal = document.querySelector('th[data-i18n="th_item_total"]');
    if (thPrice) thPrice.style.display = show ? '' : 'none';
    if (thTotal) thTotal.style.display = show ? '' : 'none';
  }

  // Checkbox toggle listener in Rx Builder
  const chkIncludeBill = document.getElementById('chk-include-bill');
  if (chkIncludeBill) {
    chkIncludeBill.addEventListener('change', (e) => {
      toggleBillingUI(e.target.checked);
    });
  }

  // 7-Day Expiry Banner Renew Button Listener
  const btnRenewBanner = document.getElementById('btn-renew-banner-action');
  if (btnRenewBanner) {
    btnRenewBanner.addEventListener('click', () => {
      document.querySelector('[data-tab="tab-settings"]')?.click();
      document.getElementById('license-key-input')?.focus();
    });
  }

  // Software Updates Check Button Listener
  const btnCheckUpdates = document.getElementById('btn-check-updates');
  if (btnCheckUpdates) {
    btnCheckUpdates.addEventListener('click', async () => {
      btnCheckUpdates.disabled = true;
      btnCheckUpdates.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking...';
      
      const currentVersion = '2.5.0'; // Current app version matching package.json
      const msgEl = document.getElementById('update-status-msg');
      if (msgEl) msgEl.style.display = 'none';

      try {
        // Fetch latest version info from online server with a cache-buster
        const res = await fetch(`https://smarthub.web.lk/licensing/update.php?cb=${Date.now()}`);
        if (!res.ok) throw new Error('Network response was not ok');
        
        const info = await res.json();

        if (info && info.latest_version && info.latest_version !== currentVersion) {
          // Open Modal Auto-Update Status Dialog (Issue #14)
          const modalUpdate = document.getElementById('modal-auto-update');
          const verPill = document.getElementById('update-modal-ver');
          const notesText = document.getElementById('update-modal-notes');
          const btnStartDL = document.getElementById('btn-start-download-update');
          const progContainer = document.getElementById('update-progress-container');
          const statusText = document.getElementById('update-progress-status-text');
          const percentText = document.getElementById('update-progress-percent');
          const barFill = document.getElementById('update-progress-bar-fill');

          if (verPill) verPill.textContent = `v${info.latest_version}`;
          if (notesText) notesText.textContent = info.release_notes || 'Includes critical security enhancements and performance fixes.';
          if (modalUpdate) modalUpdate.classList.add('active');

          btnCheckUpdates.disabled = false;
          btnCheckUpdates.innerHTML = '<i class="fa-solid fa-rotate"></i> ' + i18n.t('btn_check_updates');

          if (btnStartDL) {
            btnStartDL.onclick = () => {
              const downloadUrl = info.download_url || 'https://smarthub.web.lk/curerx.php';
              btnStartDL.disabled = true;
              btnStartDL.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Downloading...';
              if (progContainer) progContainer.style.display = 'block';

              if (window.electronAPI && typeof window.electronAPI.startAutoUpdate === 'function') {
                window.electronAPI.onUpdateProgress((percent) => {
                  if (percentText) percentText.textContent = `${percent}%`;
                  if (barFill) barFill.style.width = `${percent}%`;
                  if (percent === 100) {
                    if (statusText) statusText.textContent = 'Installing update... Restarting app...';
                    btnStartDL.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Restarting...';
                  }
                });

                window.electronAPI.onUpdateError((errMsg) => {
                  btnStartDL.disabled = false;
                  btnStartDL.innerHTML = '<i class="fa-solid fa-download"></i> Retry Download';
                  if (statusText) statusText.textContent = `Download failed: ${errMsg}`;
                });

                window.electronAPI.startAutoUpdate(downloadUrl);
              } else {
                window.open(downloadUrl, '_blank');
              }
            };
          }
        } else {
          // Already on latest version
          btnCheckUpdates.disabled = false;
          btnCheckUpdates.innerHTML = '<i class="fa-solid fa-rotate"></i> ' + i18n.t('btn_check_updates');
          if (msgEl) {
            msgEl.textContent = `✓ You are running the latest version of CureRx Pro (v${currentVersion})`;
            msgEl.style.color = 'var(--emerald-700)';
            msgEl.style.display = 'block';
          }
        }
      } catch (err) {
        console.error('Update check failed:', err);
        btnCheckUpdates.disabled = false;
        btnCheckUpdates.innerHTML = '<i class="fa-solid fa-rotate"></i> ' + i18n.t('btn_check_updates');
        if (msgEl) {
          msgEl.textContent = '✗ Update check failed. Please check your internet connection.';
          msgEl.style.color = 'var(--rose-700)';
          msgEl.style.display = 'block';
        }
      }
    });
  }

  // License Key Activation Event
  const btnActivateLicense = document.getElementById('btn-activate-license');
  if (btnActivateLicense) {
    btnActivateLicense.addEventListener('click', async () => {
      const key = document.getElementById('license-key-input').value.trim();
      if (!key) {
        alert('Please enter a valid License Key.');
        return;
      }

      btnActivateLicense.disabled = true;
      btnActivateLicense.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Activating...';

      const res = await fetch('api/settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'activate_license', license_key: key })
      });

      const json = await res.json();
      btnActivateLicense.disabled = false;
      btnActivateLicense.innerHTML = '<i class="fa-solid fa-unlock"></i> Activate / Extend Subscription';

      if (json.status === 'success') {
        alert(json.message);
        if (json.data) {
          state.settings = json.data;
          populateSettingsForm(json.data);
        } else {
          loadSettings();
        }
        document.getElementById('license-key-input').value = '';
      } else {
        alert('Error: ' + json.message);
      }
    });
  }

  // Lock Screen Activation Event
  const btnActivateLockLicense = document.getElementById('btn-activate-lock-license');
  if (btnActivateLockLicense) {
    btnActivateLockLicense.addEventListener('click', async () => {
      const key = document.getElementById('lock-license-key-input').value.trim();
      if (!key) {
        alert('Please enter a valid License Key.');
        return;
      }

      btnActivateLockLicense.disabled = true;
      btnActivateLockLicense.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Activating...';

      try {
        const res = await fetch('api/settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'activate_license', license_key: key })
        });

        const json = await res.json();
        btnActivateLockLicense.disabled = false;
        btnActivateLockLicense.innerHTML = '<i class="fa-solid fa-unlock"></i> Activate Subscription';

        if (json.status === 'success') {
          alert(json.message);
          if (json.data) {
            state.settings = json.data;
            populateSettingsForm(json.data);
          } else {
            loadSettings();
          }
          document.getElementById('lock-license-key-input').value = '';
        } else {
          alert('Error: ' + json.message);
        }
      } catch (err) {
        btnActivateLockLicense.disabled = false;
        btnActivateLockLicense.innerHTML = '<i class="fa-solid fa-unlock"></i> Activate Subscription';
        alert('An unexpected error occurred: ' + err.message);
      }
    });
  }

  // Save Settings Event
  const settingsForm = document.getElementById('form-settings');
  if (settingsForm) {
    settingsForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const payload = {
        clinic_name: document.getElementById('set-clinic-name').value,
        clinic_address: document.getElementById('set-clinic-address').value,
        clinic_phone: document.getElementById('set-clinic-phone').value,
        print_paper_size: document.getElementById('set-paper-size').value,
        practice_mode: document.getElementById('set-practice-mode')?.value || 'dispensary'
      };

      const res = await fetch('api/settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      alert(data.message || 'Settings saved');
      loadSettings();
    });
  }

  // Fetch Stats
  async function loadStats() {
    try {
      const res = await fetch('api/stats.php');
      const json = await res.json();
      if (json.status === 'success' && json.data) {
        document.getElementById('stat-today-rx').textContent = json.data.today_prescriptions;
        document.getElementById('stat-total-patients').textContent = json.data.total_patients;
        document.getElementById('stat-total-meds').textContent = json.data.total_medicines;
      }
    } catch (err) {
      console.error('Failed to load stats', err);
    }
  }

  // Patient Search & Select
  function initPatientSearch() {
    const input = document.getElementById('patient-search-input');
    const dropdown = document.getElementById('patient-dropdown');
    const patientBadge = document.getElementById('selected-patient-badge');

    if (!input) return;

    input.addEventListener('input', debounce(async () => {
      const query = input.value.trim();
      if (query.length < 1) {
        dropdown.style.display = 'none';
        return;
      }

      const res = await fetch(`api/patients.php?q=${encodeURIComponent(query)}`);
      const json = await res.json();

      if (json.status === 'success' && json.data.length > 0) {
        dropdown.innerHTML = json.data.map(p => `
          <div class="autocomplete-item" data-id="${p.id}">
            <strong>${escapeHtml(p.name)}</strong> (${p.age}y / ${p.gender})
            <small>Code: ${p.patient_code} | Phone: ${p.phone || 'N/A'}</small>
          </div>
        `).join('');
        dropdown.style.display = 'block';

        dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
          item.addEventListener('click', () => {
            const pid = item.getAttribute('data-id');
            const found = json.data.find(x => x.id == pid);
            selectPatient(found);
            dropdown.style.display = 'none';
            input.value = '';
          });
        });
      } else {
        dropdown.innerHTML = `<div class="autocomplete-item"><small>No patients found. Click "+ New Patient" to register.</small></div>`;
        dropdown.style.display = 'block';
      }
    }, 300));

    // Hide dropdown on click outside
    document.addEventListener('click', (e) => {
      if (!input.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.style.display = 'none';
      }
    });
  }

  function selectPatient(p) {
    state.selectedPatient = p;
    document.getElementById('selected-patient-id').value = p.id;
    const badge = document.getElementById('selected-patient-badge');
    badge.innerHTML = `
      <div style="flex:1;">
        <h4>${escapeHtml(p.name)} <small>(${p.patient_code})</small></h4>
        <p>Age: ${p.age} Yrs | Gender: ${p.gender} | Phone: ${p.phone || 'N/A'} ${p.allergies ? `| <strong style="color:var(--rose)">Allergies: ${escapeHtml(p.allergies)}</strong>` : ''}</p>
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
      openPatientHistoryModal(p.id);
    });

    document.getElementById('btn-change-patient')?.addEventListener('click', () => {
      state.selectedPatient = null;
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

  async function loadRepeatLastRxButton(patientId) {
    const repContainer = document.getElementById('repeat-rx-container');
    if (!repContainer) return;
    repContainer.innerHTML = ''; // Clear initially

    try {
      const res = await fetch(`api/prescriptions.php?patient_id=${patientId}`);
      const json = await res.json();
      if (json.status === 'success' && json.data && json.data.items && json.data.items.length > 0) {
        const lastRx = json.data;
        repContainer.innerHTML = `
          <button type="button" class="btn btn-primary btn-sm" id="btn-repeat-last-rx" style="background:var(--teal-600); border-color:var(--teal-600);">
            <i class="fa-solid fa-rotate-left"></i> ${i18n.t('btn_repeat_last_rx')}
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

            // Clear table and populate items
            const tbody = document.getElementById('rx-items-tbody');
            if (tbody) {
              tbody.innerHTML = '';
              lastRx.items.forEach(item => {
                addRxRow(item);
              });
              recalculateGrandTotal();
            }
          }
        });
      }
    } catch (e) {
      console.error('Failed to load last prescription for repeat', e);
    }
  }

  // Quick Prescription Templates Loader
  async function loadTemplates() {
    try {
      const res = await fetch('api/templates.php');
      const json = await res.json();
      if (json.status === 'success') {
        state.templates = json.data;
        renderTemplateChips(json.data);
        renderTemplatesList(json.data);
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
        const tpl = state.templates.find(x => x.id == id);
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

  // Rx Table Rows Builder
  function initRxTable() {
    const tbody = document.getElementById('rx-items-tbody');
    const btnAddRow = document.getElementById('btn-add-rx-row');

    if (btnAddRow) {
      btnAddRow.addEventListener('click', () => {
        addRxRow();
      });
    }

    // Add initial blank row
    addRxRow();
  }

  function addRxRow(data = {}) {
    const tbody = document.getElementById('rx-items-tbody');
    const rowId = 'rx-row-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
    const itemSource = data.item_source || 'clinic';

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
          <option value="${i18n.t('ins_after_meals')}">${i18n.t('ins_after_meals')}</option>
          <option value="${i18n.t('ins_before_meals')}">${i18n.t('ins_before_meals')}</option>
          <option value="${i18n.t('ins_at_night')}">${i18n.t('ins_at_night')}</option>
          <option value="${i18n.t('ins_morning')}">${i18n.t('ins_morning')}</option>
          <option value="${i18n.t('ins_with_meals')}">${i18n.t('ins_with_meals')}</option>
          <option value="${i18n.t('ins_prn')}">${i18n.t('ins_prn')}</option>
          <option value="${i18n.t('ins_as_directed')}">${i18n.t('ins_as_directed')}</option>
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
          <option value="clinic" ${itemSource === 'clinic' ? 'selected' : ''}>${i18n.t('opt_source_clinic')}</option>
          <option value="pharmacy" ${itemSource === 'pharmacy' ? 'selected' : ''}>${i18n.t('opt_source_pharmacy')}</option>
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

      const res = await fetch(`api/medicines.php?q=${encodeURIComponent(q)}`);
      const json = await res.json();

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
        alert('At least one medicine row is required.');
      }
    });
  }

  // Recalculate Grand Total Bill
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

  // Attach listeners to consultation fee and discount inputs
  document.querySelectorAll('.bill-calc-input').forEach(inp => {
    inp.addEventListener('input', recalculateGrandTotal);
  });

  // Prescription Submission (Save & Print)
  const btnSavePrint = document.getElementById('btn-save-print-rx');
  if (btnSavePrint) {
    btnSavePrint.addEventListener('click', async () => {
      const patientId = document.getElementById('selected-patient-id').value;
      if (!patientId) {
        alert('Please search & select a patient first!');
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
        alert('Please enter at least one drug in the prescription.');
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

        const res = await fetch('api/prescriptions.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        const json = await res.json();
        btnSavePrint.disabled = false;
        btnSavePrint.innerHTML = '<i class="fa-solid fa-print"></i> Save & Print Prescription';

        if (json.status === 'success' && json.prescription_id) {
          loadStats();
          openPrintPreviewModal(json.prescription_id);
          clearPrescriptionForm();
        } else {
          alert('Error: ' + json.message);
        }
      } catch (err) {
        btnSavePrint.disabled = false;
        btnSavePrint.innerHTML = '<i class="fa-solid fa-print"></i> Save & Print Prescription';
        alert('An unexpected error occurred: ' + err.message);
      }
    });
  }

  // Print Preview Engine & Trigger
  let activeRxData = null;
  let currentPrintPaperSize = 'A5';
  let currentPrintMode = 'rx_bill'; // 'rx_bill' or 'rx_only'

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
            <h3>${escapeHtml(s.clinic_name || 'Clinic Name')}</h3>
            <p>${escapeHtml(s.clinic_address || '')}</p>
            <p>Tel: ${escapeHtml(s.clinic_phone || '')}</p>
          </div>
        </div>

        <!-- Patient Info Bar -->
        <div class="print-patient-bar">
          <div><label>Rx No:</label><span>${escapeHtml(rx.rx_number)}</span></div>
          <div><label>Date & Time:</label><span>${formattedDate}</span></div>
          <div><label>Patient Name:</label><span>${escapeHtml(rx.patient_name)} (${rx.patient_age}y / ${rx.patient_gender})</span></div>
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
      const res = await fetch(`api/prescriptions.php?id=${rxId}`);
      const json = await res.json();

      if (json.status === 'success' && json.data) {
        activeRxData = json.data;
        const s = activeRxData.settings || {};
        currentPrintPaperSize = s.print_paper_size || 'A5';

        // Check initial print mode
        const chkBill = document.getElementById('chk-include-bill');
        if (s.practice_mode === 'channeling' || (chkBill && !chkBill.checked)) {
          currentPrintMode = 'rx_only';
        } else {
          currentPrintMode = 'rx_bill';
        }

        refreshPrintView();

        // Open Modal
        const modal = document.getElementById('modal-print-preview');
        modal.classList.add('active');
      }
    } catch (err) {
      alert('Failed to load print preview: ' + err.message);
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

  // Handle Print Mode button clicks inside preview modal
  document.querySelectorAll('.btn-print-mode').forEach(btn => {
    btn.addEventListener('click', () => {
      currentPrintMode = btn.getAttribute('data-mode');
      refreshPrintView();
    });
  });

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

  // Handle Paper Size toolbar button clicks inside preview modal
  document.querySelectorAll('.btn-paper-size').forEach(btn => {
    btn.addEventListener('click', () => {
      const size = btn.getAttribute('data-size');
      updatePaperSizeButtons(size);
    });
  });

  // Trigger browser print dialog (Silent in Electron, fallback to System Print in Browser)
  document.getElementById('btn-trigger-print')?.addEventListener('click', () => {
    if (window.electronAPI && typeof window.electronAPI.silentPrint === 'function') {
      const printHTML = document.getElementById('print-area').innerHTML;
      window.electronAPI.silentPrint({
        html: printHTML,
        paperSize: currentPrintPaperSize
      });
      
      // Auto-close print preview modal after silent printing to save them a click
      setTimeout(() => {
        document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
        // Focus the patient search input field so they can immediately type the next patient's name
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

  // Handle manual close of print preview modal to refocus patient search input
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

  // Trigger WhatsApp share
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

  // Database Backup Restore File Input Listener
  const backupInput = document.getElementById('backup-file-input');
  if (backupInput) {
    backupInput.addEventListener('change', async (e) => {
      const file = e.target.files[0];
      if (!file) return;

      if (!confirm(i18n.t('msg_restore_confirm'))) {
        backupInput.value = '';
        return;
      }

      const formData = new FormData();
      formData.append('backup_file', file);
      formData.append('action', 'import');

      try {
        const res = await fetch('api/backup.php', {
          method: 'POST',
          body: formData
        });
        const json = await res.json();
        if (json.status === 'success') {
          alert(i18n.t('msg_restore_success'));
          loadStats();
          loadMedicinesList();
          loadPatientsList();
          loadPrescriptionsHistory();
        } else {
          alert('Restore Failed: ' + json.message);
        }
      } catch (err) {
        alert('Restore Error: ' + err.message);
      } finally {
        backupInput.value = '';
      }
    });
  }

  // Modal Handlers
  function initModals() {
    document.querySelectorAll('.modal-close, .btn-modal-close').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
      });
    });

    // Quick Add Patient Modal
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
          blood_group: document.getElementById('patient-blood-in').value
        };

        const res = await fetch('api/patients.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        const json = await res.json();
        if (json.status === 'success' && json.data) {
          selectPatient(json.data);
          document.getElementById('modal-patient').classList.remove('active');
          loadPatientsList();
          loadStats();
        } else {
          alert('Error: ' + json.message);
        }
      });
    }

    // New Medicine Master Modal
    const btnNewMed = document.getElementById('btn-new-medicine-modal');
    if (btnNewMed) {
      btnNewMed.addEventListener('click', () => {
        document.getElementById('form-medicine-modal').reset();
        document.getElementById('modal-medicine').classList.add('active');
      });
    }

    const formMed = document.getElementById('form-medicine-modal');
    if (formMed) {
      formMed.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
          name: document.getElementById('med-name-in').value,
          generic_name: document.getElementById('med-generic-in').value,
          type: document.getElementById('med-type-in').value,
          default_strength: document.getElementById('med-strength-in').value,
          unit_price: parseFloat(document.getElementById('med-price-in')?.value) || 10.00
        };

        const res = await fetch('api/medicines.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        const json = await res.json();
        if (json.status === 'success') {
          document.getElementById('modal-medicine').classList.remove('active');
          loadMedicinesList();
          loadStats();
        } else {
          alert(json.message);
        }
      });
    }
  }

  // Load Prescriptions History Table
  async function loadPrescriptionsHistory() {
    const tbody = document.getElementById('tbody-history');
    if (!tbody) return;

    const filterVal = document.getElementById('history-doctor-filter')?.value || 'all';

    try {
      const res = await fetch(`api/prescriptions.php?doctor_id=${filterVal}`);
      const json = await res.json();

      if (json.status === 'success') {
        if (json.data.length === 0) {
          tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">No prescriptions created yet.</td></tr>`;
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

        tbody.querySelectorAll('.btn-print-history').forEach(btn => {
          btn.addEventListener('click', () => {
            const rxId = btn.getAttribute('data-id');
            openPrintPreviewModal(rxId);
          });
        });

        tbody.querySelectorAll('.btn-delete-rx').forEach(btn => {
          btn.addEventListener('click', async () => {
            if (confirm('Are you sure you want to delete this prescription record?')) {
              const rxId = btn.getAttribute('data-id');
              await fetch(`api/prescriptions.php?id=${rxId}`, { method: 'DELETE' });
              loadPrescriptionsHistory();
              loadStats();
            }
          });
        });
      }
    } catch (err) {
      console.error(err);
    }
  }

  // Open Patient Medical History Modal
  async function openPatientHistoryModal(patientId) {
    const modal = document.getElementById('modal-patient-history');
    if (!modal) return;

    try {
      const res = await fetch(`api/prescriptions.php?patient_id=${patientId}&all=true`);
      const json = await res.json();

      if (json.status === 'success' && json.data) {
        const rxs = json.data;

        // 1. Populate Patient info on top
        if (rxs.length > 0) {
          const first = rxs[0];
          document.getElementById('hist-pt-name').textContent = first.patient_name || '--';
          document.getElementById('hist-pt-code-gender').textContent = `Patient ID: ${first.patient_code} | Age: ${first.patient_age}y / ${first.patient_gender} | Phone: ${first.patient_phone || 'N/A'}`;
          document.getElementById('hist-pt-blood').textContent = first.patient_blood_group || 'N/A';
          document.getElementById('hist-pt-allergies').textContent = first.patient_allergies || 'None';
        } else {
          // If no prescriptions exist, fetch patient details from patients list
          const pRes = await fetch(`api/patients.php`);
          const pJson = await pRes.json();
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

        // 2. Render timeline visits
        const container = document.getElementById('history-timeline-container');
        if (rxs.length === 0) {
          container.innerHTML = `<div style="padding: 20px 0; text-align: center; color: var(--text-muted); font-weight:700;"><i class="fa-solid fa-circle-info"></i> No past visits found for this patient.</div>`;
        } else {
          container.innerHTML = rxs.map(rx => {
            const formattedDate = new Date(rx.created_at).toLocaleDateString('en-US', {
              year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
            });

            // Vitals
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

            // Medicines Table
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
                <!-- Timeline Dot -->
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

          // Attach click listeners to timeline prints
          container.querySelectorAll('.btn-print-hist-rx').forEach(btn => {
            btn.addEventListener('click', () => {
              const rxId = btn.getAttribute('data-id');
              openPrintPreviewModal(rxId);
            });
          });
        }

        // Show Modal
        modal.classList.add('active');
      }
    } catch (err) {
      console.error('Failed to load patient history timeline', err);
    }
  }

  // Load Patients List Table
  async function loadPatientsList() {
    const tbody = document.getElementById('tbody-patients');
    if (!tbody) return;

    try {
      const res = await fetch('api/patients.php');
      const json = await res.json();

      if (json.status === 'success') {
        if (json.data.length === 0) {
          tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--text-muted);">No registered patients found.</td></tr>`;
          return;
        }

        tbody.innerHTML = json.data.map(p => `
          <tr>
            <td><strong>${escapeHtml(p.patient_code)}</strong></td>
            <td>${escapeHtml(p.name)}</td>
            <td>${p.age} Yrs / ${p.gender}</td>
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
            addPatientToQueue(pid);
          });
        });

        tbody.querySelectorAll('.btn-view-history').forEach(btn => {
          btn.addEventListener('click', () => {
            const pid = btn.getAttribute('data-id');
            openPatientHistoryModal(pid);
          });
        });

        tbody.querySelectorAll('.btn-del-patient').forEach(btn => {
          btn.addEventListener('click', async () => {
            if (confirm('Delete patient record? This will also remove their prescription history.')) {
              const pid = btn.getAttribute('data-id');
              await fetch(`api/patients.php?id=${pid}`, { method: 'DELETE' });
              loadPatientsList();
              loadStats();
            }
          });
        });
      }
    } catch (err) {
      console.error(err);
    }
  }

  // Load Medicines Master List
  async function loadMedicinesList() {
    const tbody = document.getElementById('tbody-medicines');
    if (!tbody) return;

    try {
      const res = await fetch('api/medicines.php');
      const json = await res.json();

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
              await fetch(`api/medicines.php?id=${mid}`, { method: 'DELETE' });
              loadMedicinesList();
              loadStats();
            }
          });
        });
      }
    } catch (err) {
      console.error(err);
    }
  }

  // Fetch/Load Doctors List
  async function loadDoctorsList() {
    const dropdown = document.getElementById('active-doctor-select');
    const tbody = document.getElementById('tbody-profile-doctors');
    if (!dropdown) return;

    try {
      const res = await fetch('api/doctors.php');
      const json = await res.json();

      if (json.status === 'success' && json.data) {
        const doctors = json.data;

        // 1. Populate top bar switcher dropdown
        const activeId = state.settings ? state.settings.active_doctor_id : 1;
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
          // Re-apply filter value if exists, fallback to all if deleted
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
                const delRes = await fetch(`api/doctors.php?id=${docId}`, { method: 'DELETE' });
                const delJson = await delRes.json();
                if (delJson.status === 'success') {
                  loadDoctorsList();
                } else {
                  alert(delJson.message);
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

  // Initialize Doctor profile form and dropdown change event
  function initDoctorManagement() {
    // 1. Top bar select change
    const select = document.getElementById('active-doctor-select');
    if (select) {
      select.addEventListener('change', async () => {
        const docId = select.value;
        const res = await fetch('api/settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'set_active_doctor', active_doctor_id: docId })
        });
        const json = await res.json();
        if (json.status === 'success') {
          if (state.settings) {
            state.settings.active_doctor_id = docId;
          }
          loadWaitingQueue();
        } else {
          alert('Failed to update active doctor profile.');
        }
      });
    }

    // 1.1 History filter change listener
    const historyFilter = document.getElementById('history-doctor-filter');
    if (historyFilter) {
      historyFilter.addEventListener('change', () => {
        loadPrescriptionsHistory();
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
          slmc_reg: document.getElementById('doc-profile-slmc').value.trim()
        };

        const docId = document.getElementById('doc-profile-id').value;
        if (docId) {
          payload.id = docId;
        }

        const res = await fetch('api/doctors.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        const json = await res.json();
        if (json.status === 'success') {
          docForm.reset();
          document.getElementById('doc-profile-id').value = '';
          document.getElementById('btn-save-doctor-profile').innerHTML = '<i class="fa-solid fa-plus"></i> Add Doctor Profile';
          if (btnClear) btnClear.style.display = 'none';
          loadDoctorsList();
        } else {
          alert(json.message);
        }
      });
    }

  }

  // Add Patient to Waiting Queue (Selects patient and switches to Channeling tab)
  async function addPatientToQueue(patientId) {
    try {
      const res = await fetch(`api/patients.php`);
      const json = await res.json();
      if (json.status === 'success' && json.data) {
        const p = json.data.find(x => x.id == patientId);
        if (p) {
          // Pre-select patient in Channeling Billing tab
          document.getElementById('chl-selected-patient-id').value = p.id;
          document.getElementById('chl-selected-patient-name').textContent = p.name;
          document.getElementById('chl-selected-patient-meta').textContent = `Patient ID: ${p.patient_code} | Age: ${p.age}y / ${p.gender} | Phone: ${p.phone || 'N/A'}`;
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
      const res = await fetch(`api/queue.php?doctor_id=${docId}`);
      const json = await res.json();

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
                <span class="badge" style="background:${item.status === 'Serving' ? 'var(--emerald-600)' : 'var(--teal-600)'}; color:#ffffff; font-weight:800; font-size:11px; padding:2px 6px; border-radius:4px;">Token ${item.token_number}</span>
                <strong style="font-size:13px; color:var(--text-heading); text-overflow:ellipsis; overflow:hidden; white-space:nowrap;">${escapeHtml(item.patient_name)}</strong>
              </div>
              <small style="color:var(--text-muted); font-size:11px;">ID: ${item.patient_code} | ${item.patient_age}y / ${item.patient_gender}</small>
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
            const ptId = btn.getAttribute('data-pid');
            
            // Set status to Serving
            await fetch('api/queue.php', {
              method: 'PUT',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ id: queueId, status: 'Serving' })
            });

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
            if (confirm('Cancel this patient\'s queue token?')) {
              await fetch(`api/queue.php?id=${queueId}`, { method: 'DELETE' });
              loadWaitingQueue();
            }
          });
        });
      }
    } catch (err) {
      console.error(err);
    }
  }

  // Load and Render all Doctor Queues on Live Dashboard
  async function loadQueueMonitorDashboard() {
    const grid = document.getElementById('queue-monitor-grid');
    if (!grid) return;

    try {
      const res = await fetch('api/queue.php?all_doctors=true');
      const json = await res.json();

      if (json.status === 'success' && json.data) {
        const data = json.data;
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
                    <small style="color:var(--text-muted); font-size:10.5px;">${pt.patient_age}y / ${pt.patient_gender}</small>
                  </div>
                `).join('')}
              </div>

              <!-- Action button for receptionist -->
              <button type="button" class="btn btn-outline btn-sm btn-monitor-add-queue" data-docid="${doc.id}" style="width:100%; font-size:11.5px; border-color:var(--teal-600); color:var(--teal-600); margin-top: auto;">
                <i class="fa-solid fa-plus-circle"></i> Add Patient to Queue
              </button>
            </div>
          `;
        }).join('');

        // Attach event listeners to Add Patient buttons in cards
        grid.querySelectorAll('.btn-monitor-add-queue').forEach(btn => {
          btn.addEventListener('click', () => {
            document.querySelector('[data-tab="tab-patients"]').click();
            alert('Please select a patient from the directory list and click the "Queue" button.');
          });
        });
      }
    } catch (err) {
      console.error(err);
    }
  }

  // Initialize Front Desk Channeling Billing Form & Methods
  function initChannelingBilling() {
    const searchInput = document.getElementById('chl-patient-search');
    const dropdown = document.getElementById('chl-patient-dropdown');
    const selectedBadge = document.getElementById('chl-selected-patient-badge');
    const selectedIdInput = document.getElementById('chl-selected-patient-id');
    const selectedNameDiv = document.getElementById('chl-selected-patient-name');
    const selectedMetaSmall = document.getElementById('chl-selected-patient-meta');

    if (!searchInput) return;

    searchInput.addEventListener('input', debounce(async () => {
      const query = searchInput.value.trim();
      if (query.length < 1) {
        dropdown.style.display = 'none';
        return;
      }

      const res = await fetch(`api/patients.php?q=${encodeURIComponent(query)}`);
      const json = await res.json();

      if (json.status === 'success' && json.data.length > 0) {
        dropdown.innerHTML = json.data.map(p => `
          <div class="autocomplete-item" data-id="${p.id}">
            <strong>${escapeHtml(p.name)}</strong> (${p.age}y / ${p.gender})
            <small>Code: ${p.patient_code} | Phone: ${p.phone || 'N/A'}</small>
          </div>
        `).join('');
        dropdown.style.display = 'block';

        dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
          item.addEventListener('click', () => {
            const pid = item.getAttribute('data-id');
            const p = json.data.find(x => x.id == pid);
            if (p) {
              selectedIdInput.value = p.id;
              selectedNameDiv.textContent = p.name;
              selectedMetaSmall.textContent = `Patient ID: ${p.patient_code} | Age: ${p.age}y / ${p.gender} | Phone: ${p.phone || 'N/A'}`;
              selectedBadge.style.display = 'block';
              searchInput.value = '';
              dropdown.style.display = 'none';
            }
          });
        });
      } else {
        dropdown.style.display = 'none';
      }
    }, 250));

    document.addEventListener('click', (e) => {
      if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.style.display = 'none';
      }
    });

    // Auto total calculate on doctor/center fee change
    const inputs = document.querySelectorAll('.chl-calc-input');
    inputs.forEach(inp => {
      inp.addEventListener('input', () => {
        const docFee = parseFloat(document.getElementById('chl-doctor-fee').value) || 0;
        const hospFee = parseFloat(document.getElementById('chl-hospital-fee').value) || 0;
        document.getElementById('chl-grand-total').value = (docFee + hospFee).toFixed(2);
      });
    });

    // Submit billing and print
    const btnPrint = document.getElementById('btn-print-channeling-bill');
    if (btnPrint) {
      btnPrint.addEventListener('click', async () => {
        const patientId = selectedIdInput.value;
        if (!patientId) {
          alert('Please search & select a patient first!');
          return;
        }

        const payload = {
          patient_id: parseInt(patientId),
          doctor_id: parseInt(document.getElementById('chl-doctor-select').value || 1),
          doctor_fee: parseFloat(document.getElementById('chl-doctor-fee').value) || 0,
          hospital_fee: parseFloat(document.getElementById('chl-hospital-fee').value) || 0,
          payment_status: 'Paid'
        };

        try {
          btnPrint.disabled = true;
          btnPrint.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving & Printing...';

          const res = await fetch('api/queue_billing.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });
          const json = await res.json();
          btnPrint.disabled = false;
          btnPrint.innerHTML = '<i class="fa-solid fa-print"></i> Issue & Print';

          if (json.status === 'success' && json.data) {
            loadWaitingQueue();
            loadChannelingBillsToday();
            printChannelingReceipt(json.data);

            // Reset form fields
            selectedIdInput.value = '';
            selectedBadge.style.display = 'none';
            selectedNameDiv.textContent = '--';
            selectedMetaSmall.textContent = '--';
          } else {
            alert(json.message);
          }
        } catch (err) {
          btnPrint.disabled = false;
          btnPrint.innerHTML = '<i class="fa-solid fa-print"></i> Issue & Print';
          console.error(err);
        }
      });
    }

    // Phone booking action (No Print, displays Token Number dialog)
    const btnPhoneBook = document.getElementById('btn-phone-book-channeling');
    if (btnPhoneBook) {
      btnPhoneBook.addEventListener('click', async () => {
        const patientId = selectedIdInput.value;
        if (!patientId) {
          alert('Please search & select a patient first!');
          return;
        }

        const payload = {
          patient_id: parseInt(patientId),
          doctor_id: parseInt(document.getElementById('chl-doctor-select').value || 1),
          doctor_fee: parseFloat(document.getElementById('chl-doctor-fee').value) || 0,
          hospital_fee: parseFloat(document.getElementById('chl-hospital-fee').value) || 0,
          payment_status: 'Unpaid'
        };

        try {
          btnPhoneBook.disabled = true;
          btnPhoneBook.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Booking...';

          const res = await fetch('api/queue_billing.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });
          const json = await res.json();
          btnPhoneBook.disabled = false;
          btnPhoneBook.innerHTML = '<i class="fa-solid fa-phone"></i> Phone Booking';

          if (json.status === 'success' && json.data) {
            loadWaitingQueue();
            loadChannelingBillsToday();

            // Display token number to tell the patient
            alert(`📞 Phone Booking Successful!\n-----------------------------------------\nPatient: ${json.data.patient_name}\nDoctor: ${json.data.doctor_name}\nTOKEN NUMBER: ${json.data.token_number}\n-----------------------------------------\nPlease inform the patient that their Token is ${json.data.token_number}.`);

            // Reset form fields
            selectedIdInput.value = '';
            selectedBadge.style.display = 'none';
            selectedNameDiv.textContent = '--';
            selectedMetaSmall.textContent = '--';
          } else {
            alert(json.message);
          }
        } catch (err) {
          btnPhoneBook.disabled = false;
          btnPhoneBook.innerHTML = '<i class="fa-solid fa-phone"></i> Phone Booking';
          console.error(err);
        }
      });
    }

    // Refresh button trigger
    const btnRefresh = document.getElementById('btn-refresh-chl-bills-list');
    if (btnRefresh) {
      btnRefresh.addEventListener('click', loadChannelingBillsToday);
    }
  }

  // Fetch and display today's issued channeling bills
  async function loadChannelingBillsToday() {
    const tbody = document.getElementById('tbody-channeling-bills');
    if (!tbody) return;

    try {
      const res = await fetch('api/queue_billing.php');
      const json = await res.json();

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
              <td><span class="badge" style="background:var(--teal-600); color:#ffffff; font-weight:800; border-radius:4px; padding:2px 6px;">Token ${b.token_number}</span></td>
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

        // Attach reprint click listeners
        tbody.querySelectorAll('.btn-reprint-chl').forEach(btn => {
          btn.addEventListener('click', async () => {
            const billId = btn.getAttribute('data-id');
            const detailRes = await fetch(`api/queue_billing.php?id=${billId}`);
            const detailJson = await detailRes.json();
            if (detailJson.status === 'success' && detailJson.data) {
              printChannelingReceipt(detailJson.data);
            }
          });
        });

        // Attach collect and print click listeners for unpaid phone bookings
        tbody.querySelectorAll('.btn-collect-chl').forEach(btn => {
          btn.addEventListener('click', async () => {
            const billId = btn.getAttribute('data-id');
            if (confirm('Collect payment and print channeling ticket?')) {
              // 1. Mark as Paid in DB
              const putRes = await fetch('api/queue_billing.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: parseInt(billId) })
              });
              const putJson = await putRes.json();

              if (putJson.status === 'success') {
                // 2. Fetch bill details and print ticket
                const detailRes = await fetch(`api/queue_billing.php?id=${billId}`);
                const detailJson = await detailRes.json();
                if (detailJson.status === 'success' && detailJson.data) {
                  printChannelingReceipt(detailJson.data);
                }
                loadChannelingBillsToday();
              } else {
                alert(putJson.message);
              }
            }
          });
        });
      }
    } catch (err) {
      console.error(err);
    }
  }

  // Print Channeling Receipt layout
  function printChannelingReceipt(bill) {
    const printArea = document.getElementById('print-area');
    if (!printArea) return;

    const formattedDate = new Date(bill.created_at).toLocaleDateString('en-US', {
      year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
    });

    printArea.innerHTML = `
      <div class="thermal-print-wrapper" style="box-sizing: border-box; width: 74mm; font-family: 'Courier New', Courier, monospace; font-size: 10pt; color: #000000; padding: 5px 3mm;">
        <div style="text-align: center; margin-bottom: 10px;">
          <h2 style="font-size: 14pt; margin: 0; font-weight: 800;">${escapeHtml(state.settings?.clinic_name || 'Medical Center')}</h2>
          <p style="margin: 3px 0; font-size: 9pt;">${escapeHtml(state.settings?.clinic_address || 'Clinic Address')}</p>
          <p style="margin: 3px 0; font-size: 9pt;">Tel: ${escapeHtml(state.settings?.clinic_phone || 'N/A')}</p>
        </div>
        
        <hr style="border: 0; border-top: 1px dashed #000; margin: 8px 0;">

        <div style="text-align: center; margin: 12px 0;">
          <div style="font-size: 11pt; font-weight: 700; text-transform: uppercase;">CHANNELING RECEIPT</div>
          <div style="font-size: 26pt; font-weight: 900; margin: 10px 0; border: 2px solid #000; display: inline-block; padding: 4px 18px; border-radius: 6px;">
            TOKEN ${bill.token_number}
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
            <td style="padding: 2px 0; text-align: right;">${escapeHtml(bill.patient_name)} (${bill.patient_age}y / ${bill.patient_gender})</td>
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
      const printHTML = document.getElementById('print-area').innerHTML;
      window.electronAPI.silentPrint({
        html: printHTML,
        paperSize: 'Thermal'
      });
    } else {
      window.print();
    }
  }

  // Clear Prescription form inputs and reset to a clean state
  function clearPrescriptionForm() {
    state.selectedPatient = null;
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

  // Periodically check and synchronize subscription status with smarthub.web.lk
  async function syncOnlineSubscription() {
    const s = state.settings;
    if (!s || !s.license_key) return;

    try {
      // Fetch status from online smarthub licensing endpoint
      const res = await fetch(`https://smarthub.web.lk/licensing/check.php?key=${encodeURIComponent(s.license_key)}`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
      });
      const online = await res.json();
      
      if (online.status === 'success' && online.expires_at) {
        // Sync retrieved details to local settings database
        const syncRes = await fetch('api/settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'sync_subscription',
            expires_at: online.expires_at,
            status: online.subscription_status || 'active',
            plan: online.subscription_plan || 'Monthly Pro (LKR 2,500/mo)'
          })
        });
        const syncJson = await syncRes.json();
        if (syncJson.status === 'success') {
          console.log('Subscription auto-synced with smarthub.web.lk. New expiry: ' + online.expires_at);
          // Apply changes to local UI state and form
          state.settings.subscription_expires_at = online.expires_at;
          state.settings.subscription_status = online.subscription_status || 'active';
          state.settings.subscription_plan = online.subscription_plan || 'Monthly Pro (LKR 2,500/mo)';
          populateSettingsForm(state.settings);
        }
      }
    } catch (err) {
      console.log('Online subscription sync skipped (offline or server unreachable)');
    }
  }

  // Helper Utilities
  function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }
});
