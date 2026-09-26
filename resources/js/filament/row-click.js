const CLICK_DELAY = 300;
let pendingClick = null;

function closestRow(el) {
    return el.closest('tr.fi-ta-row[wire\\:key]');
}

function isInteractive(el) {
    return !!el.closest('input, button, a, [wire\\:click], .fi-right-click-menu, .fi-dropdown-panel');
}

function recordKeyFromRow(row) {
    const key = row.getAttribute('wire:key') || '';
    const marker = '.table.records.';
    const idx = key.indexOf(marker);
    return idx === -1 ? null : key.slice(idx + marker.length);
}

function mountTableAction(el, name, recordKey) {
    const root = el.closest('[wire\\:id]');
    const component = root && window.Livewire?.find(root.getAttribute('wire:id'));
    component?.call('mountTableAction', name, recordKey);
}

document.addEventListener('click', (event) => {
    const row = closestRow(event.target);
    if (!row || isInteractive(event.target)) return;

    const recordKey = recordKeyFromRow(row);
    if (!recordKey) return;

    clearTimeout(pendingClick);
    pendingClick = null;

    if (event.detail > 1) return;

    pendingClick = setTimeout(() => {
        pendingClick = null;
        mountTableAction(row, 'view', recordKey);
    }, CLICK_DELAY);
});

document.addEventListener('dblclick', (event) => {
    const row = closestRow(event.target);
    if (!row || isInteractive(event.target)) return;

    clearTimeout(pendingClick);
    pendingClick = null;

    const recordKey = recordKeyFromRow(row);
    if (!recordKey) return;

    mountTableAction(row, 'edit', recordKey);
});
