document.addEventListener('alpine:init', () => {
    if (window.__topbarPinBound) return;
    window.__topbarPinBound = true;

    const Alpine = window.Alpine;
    Alpine.store('topbarPinned', localStorage.getItem('topbar_pinned') === '1');

    let hideTimer = null;

    const applyTopbarPin = () => {
        const pinned = Alpine.store('topbarPinned');
        document.documentElement.classList.toggle('topbar-pinned', pinned);

        clearTimeout(hideTimer);
        if (!pinned) {
            document.documentElement.classList.add('topbar-force-hidden');
            hideTimer = setTimeout(() => document.documentElement.classList.remove('topbar-force-hidden'), 400);
        }
    };

    Alpine.effect(applyTopbarPin);
    document.addEventListener('livewire:navigated', applyTopbarPin);
});
