(function () {
    const CLOSE_ON_METHODS = new Set(['applyTableFilters', 'applyTableColumnManager']);
    const SELECTOR_DROPDOWN = '[x-data="filamentDropdown"]';
    const HOOK_COMMIT = 'commit';

    document.addEventListener('alpine:init', () => {
        if (window.__tablePanelAutocloseBound) return;
        window.__tablePanelAutocloseBound = true;

        Livewire.hook(HOOK_COMMIT, ({ commit, succeed }) => {
            if (!commit.calls?.some(call => CLOSE_ON_METHODS.has(call.method))) return;

            succeed(() => {
                const dropdowns = document.querySelectorAll(SELECTOR_DROPDOWN);
                const Alpine = window.Alpine;

                for (const el of dropdowns) {
                    Alpine?.$data(el)?.close?.();
                }
            });
        });
    });
})();
