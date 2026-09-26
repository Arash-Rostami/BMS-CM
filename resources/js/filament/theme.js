(function () {
    const KEY_NEW = 'theme_palette';
    const KEY_OLD = 'theme';
    const THEME_DEFAULT = 'slate';
    const EVENT_PALETTE = 'palette-changed';

    const isValidTheme = (key) => {
        const themes = window.BMS_THEMES;
        return key !== null && Array.isArray(themes) && themes.includes(key);
    };

    const applySavedTheme = () => {
        try {
            const key = localStorage.getItem(KEY_NEW);
            if (isValidTheme(key)) {
                document.documentElement.dataset.theme = key;
            }
        } catch (e) {}
    };

    window.setTheme = (key) => {
        const safeKey = isValidTheme(key) ? key : THEME_DEFAULT;

        try {
            localStorage.setItem(KEY_NEW, safeKey);
        } catch (e) {}

        document.documentElement.dataset.theme = safeKey;
        window.dispatchEvent(new CustomEvent(EVENT_PALETTE, { detail: { palette: safeKey } }));
    };

    document.addEventListener('livewire:navigated', applySavedTheme);

    try {
        const oldTheme = localStorage.getItem(KEY_OLD);
        if (isValidTheme(oldTheme)) {
            localStorage.removeItem(KEY_OLD);
        }
    } catch (e) {}
})();
