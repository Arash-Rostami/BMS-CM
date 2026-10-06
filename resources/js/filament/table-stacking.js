(function () {
    const STORAGE_KEY = 'table_stacking';
    const STACKING_CLASSIC = 'classic';
    const STACKING_STACKED = 'stacked';
    const CLASS_CLASSIC = 'table-stacking-classic';

    document.addEventListener('alpine:init', () => {
        if (window.__tableStackingBound) return;
        window.__tableStackingBound = true;

        const Alpine = window.Alpine;

        Alpine.store('tableStackingClassic', localStorage.getItem(STORAGE_KEY) === STACKING_CLASSIC);

        window.setTableStacking = (mode) => {
            const classic = mode === STACKING_CLASSIC;

            try {
                localStorage.setItem(STORAGE_KEY, classic ? STACKING_CLASSIC : STACKING_STACKED);
            } catch (e) {}

            Alpine.store('tableStackingClassic', classic);
        };

        const applyTableStacking = () => {
            document.documentElement.classList.toggle(CLASS_CLASSIC, Alpine.store('tableStackingClassic'));
        };

        Alpine.effect(applyTableStacking);
        document.addEventListener('livewire:navigated', applyTableStacking);
    });
})();
