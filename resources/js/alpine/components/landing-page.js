const getItem = k => { try { return localStorage.getItem(k); } catch { return null; } };
const setItem = (k, v) => { try { localStorage.setItem(k, v); } catch {} };
const TRUE_VALUES = ['1', 'true', 'dark', 'on'];

export default function landingPage() {
    return {
        darkMode: false,
        activeTab: 'workflow',
        widgetOpen: false,
        widgetMinimized: false,

        init() {
            const theme = getItem('theme');
            if (theme !== null) this.darkMode = TRUE_VALUES.includes(theme.toLowerCase());

            const tab = getItem('lp_tab');
            if (tab) this.activeTab = tab;

            const widgetMin = getItem('lp_widget_min');
            if (widgetMin !== null) this.widgetMinimized = widgetMin === '1';

            const widgetOpenSaved = getItem('lp_widget_open');
            if (widgetOpenSaved !== null) this.widgetOpen = widgetOpenSaved === '1';

            document.documentElement.classList.toggle('dark', this.darkMode);

            window.addEventListener('dark-mode-toggled', e => { this.darkMode = !!e.detail; });

            this.$watch('darkMode', val => {
                setItem('theme', val ? 'dark' : 'light');
                document.documentElement.classList.toggle('dark', val);
            });
            this.$watch('activeTab', val => setItem('lp_tab', val));
            this.$watch('widgetMinimized', val => setItem('lp_widget_min', val ? '1' : '0'));
            this.$watch('widgetOpen', val => setItem('lp_widget_open', val ? '1' : '0'));
        },
    };
}
