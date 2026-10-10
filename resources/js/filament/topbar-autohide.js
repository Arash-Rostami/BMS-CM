(function () {
    const STORAGE_KEY = 'topbar_pinned';
    const PINNED_STATE = '1';
    const CLASS_PINNED = 'topbar-pinned';
    const CLASS_HIDDEN = 'topbar-force-hidden';

    document.addEventListener('alpine:init', () => {
        if (window.__topbarPinBound) return;
        window.__topbarPinBound = true;

        const Alpine = window.Alpine;
        let hideTimer = null;

        Alpine.store('topbarPinned', localStorage.getItem(STORAGE_KEY) === PINNED_STATE);

        const applyTopbarPin = () => {
            const pinned = Alpine.store('topbarPinned');
            const rootClasses = document.documentElement.classList;

            rootClasses.toggle(CLASS_PINNED, pinned);
            clearTimeout(hideTimer);

            if (!pinned) {
                rootClasses.add(CLASS_HIDDEN);
                hideTimer = setTimeout(() => {
                    document.documentElement.classList.remove(CLASS_HIDDEN);
                }, 400);
            } else {
                rootClasses.remove(CLASS_HIDDEN);
            }
        };

        Alpine.effect(applyTopbarPin);
        document.addEventListener('livewire:navigated', applyTopbarPin);
    });
})();
