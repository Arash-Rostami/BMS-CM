(function () {
    const STORAGE_KEY = 'recent_records';
    const MAX_ENTRIES = 20;
    const RECORD_URL = /^\/dashboard\/([a-z0-9-]+)\/(\d+)(?:\/edit)?\/?$/;
    const EVENT_UPDATED = 'recents-updated';

    const readEntries = () => {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return [];
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    };

    const capture = () => {
        const path = location.pathname;
        const match = path.match(RECORD_URL);

        if (!match) return;

        const map = window.BMS_RESOURCE_MAP;
        if (!map || !map[match[1]]) return;

        const entries = readEntries();
        const out = [{ label: map[match[1]].label, number: match[2], url: path, slug: match[1] }];
        const len = entries.length;

        for (let i = 0; i < len; i++) {
            if (out.length >= MAX_ENTRIES) break;
            if (entries[i].url !== path) {
                out.push(entries[i]);
            }
        }

        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(out));
        } catch (e) {}

        window.dispatchEvent(new CustomEvent(EVENT_UPDATED));
    };

    document.addEventListener('livewire:navigated', capture);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', capture);
    } else {
        capture();
    }
})();
