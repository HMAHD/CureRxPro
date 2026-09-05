import { describe, test, expect, afterEach } from 'bun:test';
import { showToast } from '../../../assets/js/components/toast.js';

afterEach(() => {
    // Clear toasts without removing the container (module holds a reference to it)
    const container = document.getElementById('toast-container');
    if (container) container.innerHTML = '';
});

describe('showToast', () => {
    test('creates toast-container on first call', () => {
        showToast('Hello', 'info', 99999);
        const container = document.getElementById('toast-container');
        expect(container).not.toBeNull();
    });

    test('appends a div with the message text', () => {
        showToast('Saved successfully', 'success', 99999);
        const container = document.getElementById('toast-container');
        expect(container.children.length).toBe(1);
        expect(container.children[0].textContent).toBe('Saved successfully');
    });

    test('sets background to error color for error type', () => {
        showToast('Something went wrong', 'error', 99999);
        const container = document.getElementById('toast-container');
        expect(container.children[0].style.background).toBe('#ef4444');
    });

    test('sets background to success color for success type', () => {
        showToast('Done!', 'success', 99999);
        const container = document.getElementById('toast-container');
        expect(container.children[0].style.background).toBe('#22c55e');
    });

    test('appends multiple toasts on multiple calls', () => {
        showToast('First', 'info', 99999);
        showToast('Second', 'info', 99999);
        const container = document.getElementById('toast-container');
        expect(container.children.length).toBe(2);
    });

    test('removes toast element after duration elapses', async () => {
        showToast('Bye', 'info', 0);
        const container = document.getElementById('toast-container');
        expect(container.children.length).toBe(1);
        // duration=0 fires the fade-out immediately; +300ms for the remove timeout
        await new Promise(r => setTimeout(r, 400));
        expect(container.children.length).toBe(0);
    });
});
