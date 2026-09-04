// assets/js/api.js
const BASE = 'http://127.0.0.1:8989/api';

async function request(endpoint, options = {}) {
    const res = await fetch(`${BASE}${endpoint}`, {
        headers: {
            'Content-Type': 'application/json',
            'X-CureRx-Token': window.__crxToken ?? '',
        },
        ...options,
    });
    if (!res.ok) {
        const err = await res.json().catch(() => ({ message: res.statusText }));
        throw new Error(err.message ?? 'Request failed');
    }
    return res.json();
}

export const get  = (url, params) =>
    request(url + (params ? '?' + new URLSearchParams(params) : ''));
export const post = (url, body) =>
    request(url, { method: 'POST', body: JSON.stringify(body) });
export const put  = (url, body) =>
    request(url, { method: 'PUT',  body: JSON.stringify(body) });
export const del  = (url) =>
    request(url, { method: 'DELETE' });

export function escapeHtml(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

export function debounce(fn, wait) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
}
