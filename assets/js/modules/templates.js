// assets/js/modules/templates.js
import * as api from '../api.js';
import * as state from '../state.js';
import { escapeHtml } from '../api.js';

export function init() {
    loadTemplates();
}

export async function loadTemplates() {
    try {
        const json = await api.get('/templates.php');
        if (json.status === 'success') {
            state.set('templates', json.data);
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
            const tpl = state.get('templates').find(x => x.id == id);
            if (tpl && tpl.items) {
                applyTemplate(tpl.items);
            }
        });
    });
}

function applyTemplate(items) {
    const tbody = document.getElementById('rx-items-tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    items.forEach(item => {
        document.dispatchEvent(new CustomEvent('crx:addRxRow', { detail: item }));
    });
}

function renderTemplatesList(templates) {
    const container = document.getElementById('templates-manage-list');
    if (!container) return;

    if (!templates || templates.length === 0) {
        container.innerHTML = `<p style="color:var(--text-muted);text-align:center;padding:20px;">No templates saved yet.</p>`;
        return;
    }

    container.innerHTML = templates.map(t => `
      <div class="glass-card" style="padding:12px 16px; margin-bottom:10px; display:flex; align-items:center; justify-content:space-between; gap:12px;">
        <div>
          <strong style="font-size:14px;">${escapeHtml(t.name)}</strong>
          <small style="color:var(--text-muted); margin-left:10px;">${t.items ? t.items.length : 0} medicine(s)</small>
        </div>
      </div>
    `).join('');
}
