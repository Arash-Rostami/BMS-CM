const THEME_KEY = 'theme';
const TAB_KEY = 'lp_tab';
const WIDGET_MIN_KEY = 'lp_widget_min';
const WIDGET_OPEN_KEY = 'lp_widget_open';
const THEME_DARK = 'dark';
const THEME_LIGHT = 'light';
const STATE_ON = '1';
const STATE_OFF = '0';
const EVENT_TOGGLE = 'dark-mode-toggled';

const TRUE_SET = new Set([STATE_ON, 'true', THEME_DARK, 'on']);

const getItem = k => { try { return localStorage.getItem(k); } catch { return null; } };
const setItem = (k, v) => { try { localStorage.setItem(k, v); } catch {} };

export default function landingPage() {
    return {
        darkMode: false,
        activeTab: 'workflow',
        widgetOpen: false,
        widgetMinimized: false,
        panelOpen: false,
        isWide: window.innerWidth >= 1024,

        init() {
            const theme = getItem(THEME_KEY);
            if (theme !== null) this.darkMode = TRUE_SET.has(theme.toLowerCase());

            const tab = getItem(TAB_KEY);
            if (tab) this.activeTab = tab;

            const widgetMin = getItem(WIDGET_MIN_KEY);
            if (widgetMin !== null) this.widgetMinimized = widgetMin === STATE_ON;

            const widgetOpenSaved = getItem(WIDGET_OPEN_KEY);
            if (widgetOpenSaved !== null) this.widgetOpen = widgetOpenSaved === STATE_ON;

            document.documentElement.classList.toggle(THEME_DARK, this.darkMode);

            window.addEventListener(EVENT_TOGGLE, e => {
                this.darkMode = Boolean(e.detail);
            });

            this.$watch('darkMode', val => {
                setItem(THEME_KEY, val ? THEME_DARK : THEME_LIGHT);
                document.documentElement.classList.toggle(THEME_DARK, val);
            });

            this.$watch('activeTab', val => setItem(TAB_KEY, val));
            this.$watch('widgetMinimized', val => setItem(WIDGET_MIN_KEY, val ? STATE_ON : STATE_OFF));
            this.$watch('widgetOpen', val => setItem(WIDGET_OPEN_KEY, val ? STATE_ON : STATE_OFF));

            window.addEventListener('resize', () => {
                this.isWide = window.innerWidth >= 1024;
            });
        },
    };
}
