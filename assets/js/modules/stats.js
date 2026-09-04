// assets/js/modules/stats.js
import * as api from '../api.js';

export function init() {
    loadStats();
    document.addEventListener('crx:reloadStats', () => loadStats());
}

async function loadStats() {
    try {
        const json = await api.get('/stats.php');
        if (json.status === 'success' && json.data) {
            document.getElementById('stat-today-rx').textContent = json.data.today_prescriptions;
            document.getElementById('stat-total-patients').textContent = json.data.total_patients;
            document.getElementById('stat-total-meds').textContent = json.data.total_medicines;
            const revEl = document.getElementById('stat-today-revenue');
            if (revEl && json.data.today_revenue !== undefined) {
                revEl.textContent = `LKR ${parseFloat(json.data.today_revenue).toFixed(2)}`;
            }
        }
    } catch (err) {
        console.error('Failed to load stats', err);
    }
}
