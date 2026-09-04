// assets/js/components/autocomplete.js

export function createAutocomplete({ inputId, dropdownId, fetchFn, onSelect, minChars = 2 }) {
    const input    = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    if (!input || !dropdown) return;

    let timer;

    input.addEventListener('input', () => {
        clearTimeout(timer);
        if (input.value.length < minChars) { dropdown.hidden = true; return; }
        timer = setTimeout(async () => {
            try {
                const results = await fetchFn(input.value);
                renderDropdown(dropdown, results, onSelect, input);
            } catch {
                dropdown.hidden = true;
            }
        }, 250);
    });

    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target) && e.target !== input) {
            dropdown.hidden = true;
        }
    });
}

function renderDropdown(dropdown, results, onSelect, input) {
    dropdown.innerHTML = '';
    if (!results.length) { dropdown.hidden = true; return; }
    results.forEach((item) => {
        const li = document.createElement('li');
        li.textContent = item.label ?? item.name ?? JSON.stringify(item);
        li.addEventListener('mousedown', (e) => {
            e.preventDefault();
            onSelect(item);
            input.value = item.label ?? item.name ?? '';
            dropdown.hidden = true;
        });
        dropdown.appendChild(li);
    });
    dropdown.hidden = false;
}
