// assets/js/modules/settings.js
import * as api from '../api.js';
import * as state from '../state.js';
import { showToast } from '../components/toast.js';

export function init() {
    loadSettings();
    wireCheckUpdates();
    wireLicenseActivation();
    wireLockLicenseActivation();
    wireSettingsForm();
    wireBackupRestore();
    wireRenewBanner();
    wireBillingToggle();
}

export async function loadSettings() {
    try {
        const result = await api.get('/settings.php');
        if (result.status === 'success' && result.data) {
            state.set('settings', result.data);
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
    if (document.getElementById('set-clinic-logo')) {
        document.getElementById('set-clinic-logo').value = s.clinic_logo || '';
    }

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

    const expiryEl = document.getElementById('sub-expiry-display');
    if (expiryEl) expiryEl.textContent = s.subscription_expires_at || '2026-12-31';

    const daysRemaining = (s.days_remaining !== undefined) ? s.days_remaining : 30;
    const daysDisp = document.getElementById('sub-days-remaining-display');
    const topDaysCount = document.getElementById('stat-license-days-count');

    if (daysDisp) daysDisp.textContent = `${daysRemaining} Days`;
    if (topDaysCount) {
        topDaysCount.textContent = `${daysRemaining} Days`;
        topDaysCount.style.color = daysRemaining <= 7 ? '#e11d48' : 'var(--teal-600)';
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
        lockOverlay.style.display = (s.subscription_status === 'expired' || daysRemaining <= 0) ? 'flex' : 'none';
    }

    const warningBanner = document.getElementById('expiry-warning-banner');
    const warningText = document.getElementById('expiry-warning-text');
    if (warningBanner) {
        if (s.subscription_status === 'expired' || daysRemaining <= 7) {
            const msg = window.i18n?.t('reminder_7days_msg')?.replace('{days}', daysRemaining) ?? `Your subscription expires in ${daysRemaining} days.`;
            if (warningText) warningText.textContent = msg;
            warningBanner.style.display = 'flex';
        } else {
            warningBanner.style.display = 'none';
        }
    }
}

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

function wireBillingToggle() {
    const chkIncludeBill = document.getElementById('chk-include-bill');
    if (chkIncludeBill) {
        chkIncludeBill.addEventListener('change', (e) => {
            toggleBillingUI(e.target.checked);
        });
    }
}

function wireRenewBanner() {
    const btnRenewBanner = document.getElementById('btn-renew-banner-action');
    if (btnRenewBanner) {
        btnRenewBanner.addEventListener('click', () => {
            document.querySelector('[data-tab="tab-settings"]')?.click();
            document.getElementById('license-key-input')?.focus();
        });
    }
}

function wireCheckUpdates() {
    const btnCheckUpdates = document.getElementById('btn-check-updates');
    if (!btnCheckUpdates) return;

    btnCheckUpdates.addEventListener('click', async () => {
        btnCheckUpdates.disabled = true;
        btnCheckUpdates.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking...';

        const currentVersion = '2.5.0';
        const msgEl = document.getElementById('update-status-msg');
        if (msgEl) msgEl.style.display = 'none';

        try {
            const res = await fetch(`https://smarthub.web.lk/licensing/update.php?cb=${Date.now()}`);
            if (!res.ok) throw new Error('Network response was not ok');
            const info = await res.json();

            if (info && info.latest_version && info.latest_version !== currentVersion) {
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
                btnCheckUpdates.innerHTML = '<i class="fa-solid fa-rotate"></i> ' + (window.i18n?.t('btn_check_updates') ?? 'Check for Updates');

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
                            window.electronAPI.startAutoUpdate('v' + info.latest_version);
                        } else {
                            window.open(downloadUrl, '_blank');
                        }
                    };
                }
            } else {
                btnCheckUpdates.disabled = false;
                btnCheckUpdates.innerHTML = '<i class="fa-solid fa-rotate"></i> ' + (window.i18n?.t('btn_check_updates') ?? 'Check for Updates');
                if (msgEl) {
                    msgEl.textContent = `✓ You are running the latest version of CureRx Pro (v${currentVersion})`;
                    msgEl.style.color = 'var(--emerald-700)';
                    msgEl.style.display = 'block';
                }
            }
        } catch (err) {
            console.error('Update check failed:', err);
            btnCheckUpdates.disabled = false;
            btnCheckUpdates.innerHTML = '<i class="fa-solid fa-rotate"></i> ' + (window.i18n?.t('btn_check_updates') ?? 'Check for Updates');
            if (msgEl) {
                msgEl.textContent = '✗ Update check failed. Please check your internet connection.';
                msgEl.style.color = 'var(--rose-700)';
                msgEl.style.display = 'block';
            }
        }
    });
}

function wireLicenseActivation() {
    const btnActivateLicense = document.getElementById('btn-activate-license');
    if (!btnActivateLicense) return;

    btnActivateLicense.addEventListener('click', async () => {
        const key = document.getElementById('license-key-input').value.trim();
        if (!key) {
            showToast('Please enter a valid License Key.', 'error');
            return;
        }

        btnActivateLicense.disabled = true;
        btnActivateLicense.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Activating...';

        try {
            const json = await api.post('/settings.php', { action: 'activate_license', license_key: key });
            btnActivateLicense.disabled = false;
            btnActivateLicense.innerHTML = '<i class="fa-solid fa-unlock"></i> Activate / Extend Subscription';

            if (json.status === 'success') {
                showToast(json.message, 'success');
                if (json.data) {
                    state.set('settings', json.data);
                    populateSettingsForm(json.data);
                } else {
                    loadSettings();
                }
                document.getElementById('license-key-input').value = '';
            } else {
                showToast('Error: ' + json.message, 'error');
            }
        } catch (err) {
            btnActivateLicense.disabled = false;
            btnActivateLicense.innerHTML = '<i class="fa-solid fa-unlock"></i> Activate / Extend Subscription';
            showToast('An unexpected error occurred: ' + err.message, 'error');
        }
    });
}

function wireLockLicenseActivation() {
    const btnActivateLockLicense = document.getElementById('btn-activate-lock-license');
    if (!btnActivateLockLicense) return;

    btnActivateLockLicense.addEventListener('click', async () => {
        const key = document.getElementById('lock-license-key-input').value.trim();
        if (!key) {
            showToast('Please enter a valid License Key.', 'error');
            return;
        }

        btnActivateLockLicense.disabled = true;
        btnActivateLockLicense.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Activating...';

        try {
            const json = await api.post('/settings.php', { action: 'activate_license', license_key: key });
            btnActivateLockLicense.disabled = false;
            btnActivateLockLicense.innerHTML = '<i class="fa-solid fa-unlock"></i> Activate Subscription';

            if (json.status === 'success') {
                showToast(json.message, 'success');
                if (json.data) {
                    state.set('settings', json.data);
                    populateSettingsForm(json.data);
                } else {
                    loadSettings();
                }
                document.getElementById('lock-license-key-input').value = '';
            } else {
                showToast('Error: ' + json.message, 'error');
            }
        } catch (err) {
            btnActivateLockLicense.disabled = false;
            btnActivateLockLicense.innerHTML = '<i class="fa-solid fa-unlock"></i> Activate Subscription';
            showToast('An unexpected error occurred: ' + err.message, 'error');
        }
    });
}

function wireSettingsForm() {
    const settingsForm = document.getElementById('form-settings');
    if (!settingsForm) return;

    settingsForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            clinic_name: document.getElementById('set-clinic-name').value,
            clinic_address: document.getElementById('set-clinic-address').value,
            clinic_phone: document.getElementById('set-clinic-phone').value,
            clinic_logo: document.getElementById('set-clinic-logo')?.value || '',
            print_paper_size: document.getElementById('set-paper-size').value,
            practice_mode: document.getElementById('set-practice-mode')?.value || 'dispensary'
        };

        try {
            const data = await api.post('/settings.php', payload);
            showToast(data.message || 'Settings saved', 'success');
            loadSettings();
        } catch (err) {
            showToast('Failed to save settings: ' + err.message, 'error');
        }
    });
}

function wireBackupRestore() {
    const backupInput = document.getElementById('backup-file-input');
    if (!backupInput) return;

    backupInput.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        const confirmMsg = window.i18n?.t('msg_restore_confirm') ?? 'Restore database from this backup file? This will overwrite all current data.';
        if (!confirm(confirmMsg)) {
            backupInput.value = '';
            return;
        }

        const formData = new FormData();
        formData.append('backup_file', file);
        formData.append('action', 'import');

        try {
            const res = await fetch('http://127.0.0.1:8989/api/backup.php', {
                method: 'POST',
                headers: { 'X-CureRx-Token': window.__crxToken ?? '' },
                body: formData
            });
            const json = await res.json();
            if (json.status === 'success') {
                const successMsg = window.i18n?.t('msg_restore_success') ?? 'Database restored successfully!';
                showToast(successMsg, 'success');
                document.dispatchEvent(new CustomEvent('crx:reloadStats'));
            } else {
                showToast('Restore Failed: ' + json.message, 'error');
            }
        } catch (err) {
            showToast('Restore Error: ' + err.message, 'error');
        } finally {
            backupInput.value = '';
        }
    });
}

async function syncOnlineSubscription() {
    const s = state.get('settings');
    if (!s || !s.license_key) return;

    try {
        const res = await fetch(`https://smarthub.web.lk/licensing/check.php?key=${encodeURIComponent(s.license_key)}`, {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        });
        const online = await res.json();

        if (online.status === 'success' && online.expires_at) {
            const syncJson = await api.post('/settings.php', {
                action: 'sync_subscription',
                expires_at: online.expires_at,
                status: online.subscription_status || 'active',
                plan: online.subscription_plan || 'Monthly Pro (LKR 2,500/mo)'
            });
            if (syncJson.status === 'success') {
                console.log('Subscription auto-synced with smarthub.web.lk. New expiry: ' + online.expires_at);
                const settings = state.get('settings');
                settings.subscription_expires_at = online.expires_at;
                settings.subscription_status = online.subscription_status || 'active';
                settings.subscription_plan = online.subscription_plan || 'Monthly Pro (LKR 2,500/mo)';
                state.set('settings', settings);
                populateSettingsForm(settings);
            }
        }
    } catch (err) {
        console.log('Online subscription sync skipped (offline or server unreachable)');
    }
}
