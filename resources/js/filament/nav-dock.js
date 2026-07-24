document.addEventListener('alpine:init', () => {
    if (window.__navDockBound) return;
    window.__navDockBound = true;

    const Alpine = window.Alpine;
    Alpine.store('navDock', localStorage.getItem('nav_dock') === 'bottom' ? 'bottom' : 'side');

    let raf = null;

    const applyNavDock = () => {
        const bottom = Alpine.store('navDock') === 'bottom';
        document.documentElement.classList.toggle('nav-dock-bottom', bottom);
        bottom ? Alpine.store('sidebar')?.close() : Alpine.store('sidebar')?.open();

        cancelAnimationFrame(raf);
        raf = requestAnimationFrame(() => {
            const placement = bottom ? 'top' : (document.dir === 'rtl' ? 'left' : 'right');
            document.querySelectorAll('.fi-sidebar-item-btn, .fi-sidebar-group-dropdown-trigger-btn')
                .forEach((el) => el._tippy?.setProps({ placement }));
        });
    };

    Alpine.effect(applyNavDock);
    document.addEventListener('livewire:navigated', applyNavDock);
});
