// assets/js/main.js
import { init as initPatients }      from './modules/patients.js';
import { init as initQueue, startQueueMonitorPolling, stopQueueMonitorPolling } from './modules/queue.js';
import { init as initBilling }       from './modules/billing.js';
import { init as initMedicines }     from './modules/medicines.js';
import { init as initPrescriptions } from './modules/prescriptions.js';
import { init as initHistory }       from './modules/history.js';
import { init as initTemplates }     from './modules/templates.js';
import { init as initSettings }      from './modules/settings.js';
import { init as initStats }         from './modules/stats.js';
import { closeAllModals }            from './components/modal.js';

document.addEventListener('DOMContentLoaded', () => {
    initNavigation();
    initStats();
    initSettings();
    initPatients();
    initPrescriptions();
    initQueue();
    initBilling();
    initMedicines();
    initHistory();
    initTemplates();
});

function initNavigation() {
    let activeTabId = null;

    document.querySelectorAll('.nav-item[data-tab]').forEach(link => {
        link.addEventListener('click', () => {
            const nextTabId = link.dataset.tab;

            // Stop queue monitor polling when leaving queue-monitor tab
            if (activeTabId === 'tab-queue-monitor' && nextTabId !== 'tab-queue-monitor') {
                stopQueueMonitorPolling();
            }

            document.querySelectorAll('.nav-item').forEach(l => l.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            link.classList.add('active');
            const tab = document.getElementById(nextTabId);
            if (tab) tab.classList.add('active');
            closeAllModals();

            activeTabId = nextTabId;

            // Start queue monitor polling when entering queue-monitor tab
            if (nextTabId === 'tab-queue-monitor') {
                startQueueMonitorPolling();
            }
        });
    });
}
