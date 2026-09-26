(function () {
    const STORE_KEY = 'isFullscreen';
    const EVENT_CHANGE = 'fullscreenchange';

    document.addEventListener('alpine:init', () => {
        if (window.__fullscreenBound) return;
        window.__fullscreenBound = true;

        const Alpine = window.Alpine;
        const doc = document;

        Alpine.store(STORE_KEY, doc.fullscreenElement !== null);

        window.toggleFullscreen = () => {
            if (doc.fullscreenElement !== null) {
                doc.exitFullscreen().catch(() => {});
            } else {
                doc.documentElement.requestFullscreen().catch(() => {});
            }
        };

        doc.addEventListener(EVENT_CHANGE, () => {
            Alpine.store(STORE_KEY, doc.fullscreenElement !== null);
        });
    });
})();
