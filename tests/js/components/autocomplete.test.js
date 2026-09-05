import { describe, test, expect, beforeEach, afterEach } from 'bun:test';
import { createAutocomplete } from '../../../assets/js/components/autocomplete.js';

let input, dropdown;

beforeEach(() => {
    document.body.innerHTML = `
        <input id="ac-input" type="text" />
        <div id="ac-dropdown"></div>
    `;
    input    = document.getElementById('ac-input');
    dropdown = document.getElementById('ac-dropdown');
});

afterEach(() => {
    document.body.innerHTML = '';
});

describe('createAutocomplete', () => {
    test('returns without error when elements exist', () => {
        expect(() => createAutocomplete({
            inputId:   'ac-input',
            dropdownId: 'ac-dropdown',
            fetchFn:   async () => [],
            onSelect:  () => {},
        })).not.toThrow();
    });

    test('does nothing when inputId element is missing', () => {
        expect(() => createAutocomplete({
            inputId:   'nonexistent',
            dropdownId: 'ac-dropdown',
            fetchFn:   async () => [],
            onSelect:  () => {},
        })).not.toThrow();
    });

    test('shows dropdown items from fetchFn after debounce', async () => {
        createAutocomplete({
            inputId:   'ac-input',
            dropdownId: 'ac-dropdown',
            fetchFn:   async () => [{ id: 1, name: 'Alice' }, { id: 2, name: 'Bob' }],
            onSelect:  () => {},
            minChars:  1,
        });

        input.value = 'A';
        input.dispatchEvent(new Event('input'));
        await new Promise(r => setTimeout(r, 400)); // debounce=250ms + fetch time

        expect(dropdown.children.length).toBe(2);
        expect(dropdown.children[0].textContent).toBe('Alice');
        expect(dropdown.children[1].textContent).toBe('Bob');
    });

    test('hides dropdown when value is below minChars', async () => {
        createAutocomplete({
            inputId:   'ac-input',
            dropdownId: 'ac-dropdown',
            fetchFn:   async () => [{ id: 1, name: 'Alice' }],
            onSelect:  () => {},
            minChars:  3,
        });

        input.value = 'Al'; // 2 chars < minChars=3
        input.dispatchEvent(new Event('input'));
        await new Promise(r => setTimeout(r, 400));

        expect(dropdown.hidden).toBe(true);
    });

    test('calls onSelect and fills input when item clicked', async () => {
        let selected = null;
        createAutocomplete({
            inputId:   'ac-input',
            dropdownId: 'ac-dropdown',
            fetchFn:   async () => [{ id: 1, name: 'Alice' }],
            onSelect:  (item) => { selected = item; },
            minChars:  1,
        });

        input.value = 'A';
        input.dispatchEvent(new Event('input'));
        await new Promise(r => setTimeout(r, 400));

        const li = dropdown.querySelector('li');
        li.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));

        expect(selected).toEqual({ id: 1, name: 'Alice' });
        expect(input.value).toBe('Alice');
        expect(dropdown.hidden).toBe(true);
    });

    test('uses label property over name when both present', async () => {
        createAutocomplete({
            inputId:   'ac-input',
            dropdownId: 'ac-dropdown',
            fetchFn:   async () => [{ id: 1, name: 'Alice Perera', label: 'Alice Perera - P001' }],
            onSelect:  () => {},
            minChars:  1,
        });

        input.value = 'A';
        input.dispatchEvent(new Event('input'));
        await new Promise(r => setTimeout(r, 400));

        expect(dropdown.children[0].textContent).toBe('Alice Perera - P001');
    });
});
