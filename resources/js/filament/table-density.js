(function () {
    const STORAGE_KEY = 'table_density';
    const DENSITY_COMPACT = 'compact';
    const DENSITY_COMFORTABLE = 'comfortable';
    const CLASS_COMPACT = 'table-density-compact';

    document.addEventListener('alpine:init', () => {
        if (window.__tableDensityBound) return;
        window.__tableDensityBound = true;

        const Alpine = window.Alpine;

        Alpine.store('tableDensityCompact', localStorage.getItem(STORAGE_KEY) === DENSITY_COMPACT);

        window.setTableDensity = (mode) => {
            const compact = mode === DENSITY_COMPACT;

            try {
                localStorage.setItem(STORAGE_KEY, compact ? DENSITY_COMPACT : DENSITY_COMFORTABLE);
            } catch (e) {}

            Alpine.store('tableDensityCompact', compact);
        };

        const applyTableDensity = () => {
            document.documentElement.classList.toggle(CLASS_COMPACT, Alpine.store('tableDensityCompact'));
        };

        Alpine.effect(applyTableDensity);
        document.addEventListener('livewire:navigated', applyTableDensity);
    });
})();
