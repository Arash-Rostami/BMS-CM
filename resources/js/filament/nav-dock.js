(function () {
    const STORAGE_KEY = 'nav_dock';
    const MODE_SIDE = 'side';
    const MODE_BOTTOM = 'bottom';
    const MODE_PEEK = 'peek';
    const CLASS_DOCK_BOTTOM = 'nav-dock-bottom';
    const CLASS_DOCK_PEEK = 'nav-dock-peek';
    const NAV_MODES = new Set([MODE_SIDE, MODE_BOTTOM, MODE_PEEK]);
    const TIPPY_SELECTORS = '.fi-sidebar-item-btn, .fi-sidebar-group-dropdown-trigger-btn';

    document.addEventListener('alpine:init', () => {
        if (window.__navDockBound) return;
        window.__navDockBound = true;

        const Alpine = window.Alpine;
        let raf = null;

        const saved = localStorage.getItem(STORAGE_KEY);
        Alpine.store('navDock', NAV_MODES.has(saved) ? saved : MODE_SIDE);

        window.setNavDock = (mode) => {
            const safeMode = NAV_MODES.has(mode) ? mode : MODE_SIDE;

            try {
                localStorage.setItem(STORAGE_KEY, safeMode);
            } catch (e) {}

            Alpine.store('navDock', safeMode);
        };

        const applyNavDock = () => {
            const mode = Alpine.store('navDock');
            const docked = mode !== MODE_SIDE;
            const rootClasses = document.documentElement.classList;

            rootClasses.toggle(CLASS_DOCK_BOTTOM, docked);
            rootClasses.toggle(CLASS_DOCK_PEEK, mode === MODE_PEEK);

            const sidebar = Alpine.store('sidebar');

            if (docked) {
                sidebar?.close?.();
            } else {
                sidebar?.open?.();
            }

            cancelAnimationFrame(raf);
            raf = requestAnimationFrame(() => {
                const placement = docked ? 'top' : (document.dir === 'rtl' ? 'left' : 'right');
                const elements = document.querySelectorAll(TIPPY_SELECTORS);

                for (const el of elements) {
                    if (el._tippy && typeof el._tippy.setProps === 'function') {
                        el._tippy.setProps({ placement });
                    }
                }
            });
        };

        Alpine.effect(applyNavDock);
        document.addEventListener('livewire:navigated', applyNavDock);

        document.addEventListener('click', (e) => {
            const mode = Alpine.store('navDock');

            if (mode === MODE_SIDE) return;

            const target = e.target;

            if (mode === MODE_BOTTOM && target.closest('.dock-min')) {
                window.setNavDock(MODE_PEEK);
                return;
            }

            if (mode === MODE_PEEK && target.closest('.fi-sidebar')) {
                window.setNavDock(MODE_BOTTOM);
            }
        });
    });
})();
