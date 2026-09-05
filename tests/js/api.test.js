import { describe, test, expect, mock, beforeEach, afterEach } from 'bun:test';
import { get, post, put, del, escapeHtml, debounce } from '../../assets/js/api.js';

// Default happy-path fetch mock
const okResponse = (data = {}) => ({
    ok: true,
    json: async () => data,
});

let fetchMock;

beforeEach(() => {
    fetchMock = mock(async () => okResponse({ status: 'success' }));
    globalThis.fetch = fetchMock;
});

afterEach(() => {
    fetchMock.mockRestore?.();
});

describe('get()', () => {
    test('calls fetch with correct base URL', async () => {
        await get('/patients.php');
        const url = fetchMock.mock.calls[0][0];
        expect(url).toBe('http://127.0.0.1:8989/api/patients.php');
    });

    test('injects X-CureRx-Token header', async () => {
        await get('/patients.php');
        const opts = fetchMock.mock.calls[0][1];
        expect(opts.headers['X-CureRx-Token']).toBe('test-token-for-tests');
    });

    test('appends query params when provided', async () => {
        await get('/patients.php', { q: 'Alice', limit: '10' });
        const url = fetchMock.mock.calls[0][0];
        expect(url).toContain('q=Alice');
        expect(url).toContain('limit=10');
    });

    test('throws Error on non-ok response', async () => {
        globalThis.fetch = mock(async () => ({
            ok: false,
            statusText: 'Not Found',
            json: async () => ({ message: 'Patient not found' }),
        }));
        await expect(get('/patients.php?id=999')).rejects.toThrow('Patient not found');
    });
});

describe('post()', () => {
    test('uses POST method with JSON body', async () => {
        await post('/patients.php', { name: 'Alice', age: 30 });
        const opts = fetchMock.mock.calls[0][1];
        expect(opts.method).toBe('POST');
        expect(JSON.parse(opts.body)).toEqual({ name: 'Alice', age: 30 });
    });
});

describe('put()', () => {
    test('uses PUT method', async () => {
        await put('/queue.php', { id: 1, status: 'Serving' });
        expect(fetchMock.mock.calls[0][1].method).toBe('PUT');
    });
});

describe('del()', () => {
    test('uses DELETE method', async () => {
        await del('/queue.php?id=1');
        expect(fetchMock.mock.calls[0][1].method).toBe('DELETE');
    });
});

describe('escapeHtml()', () => {
    test('escapes < > & " \'', () => {
        expect(escapeHtml('<b>"it\'s" & fun</b>')).toBe(
            '&lt;b&gt;&quot;it&#39;s&quot; &amp; fun&lt;/b&gt;'
        );
    });

    test('returns empty string for null/undefined', () => {
        expect(escapeHtml(null)).toBe('');
        expect(escapeHtml(undefined)).toBe('');
    });

    test('returns string unchanged when no special chars', () => {
        expect(escapeHtml('Hello World')).toBe('Hello World');
    });
});

describe('debounce()', () => {
    test('only fires once after rapid successive calls', async () => {
        let count = 0;
        const fn = debounce(() => count++, 50);
        fn(); fn(); fn(); fn();
        await new Promise(r => setTimeout(r, 150));
        expect(count).toBe(1);
    });

    test('fires again after the wait period resets', async () => {
        let count = 0;
        const fn = debounce(() => count++, 50);
        fn();
        await new Promise(r => setTimeout(r, 150));
        fn();
        await new Promise(r => setTimeout(r, 150));
        expect(count).toBe(2);
    });
});
