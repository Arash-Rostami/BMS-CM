export default function landingPage() {
    return {
        darkMode: false,
        activeTab: 'workflow',
        loading: true,
        widgetOpen: false,

        init() {
            const get = k => { try { return localStorage.getItem(k); } catch { return null; } };
            const set = (k, v) => { try { localStorage.setItem(k, v); } catch {} };

            const theme = get('theme');
            if (theme !== null) {
                const v = theme.toLowerCase();
                this.darkMode = v === '1' || v === 'true' || v === 'dark' || v === 'on';
            }

            const tab = get('lp_tab');
            if (tab) this.activeTab = tab;

            document.documentElement.classList.toggle('dark', this.darkMode);

            window.addEventListener('dark-mode-toggled', e => { this.darkMode = !!e.detail; });

            this.$watch('darkMode', val => {
                set('theme', val ? 'dark' : 'light');
                document.documentElement.classList.toggle('dark', val);
                if (window.torusMaterial) {
                    window.torusMaterial.opacity = val ? 0.1 : 0.3;
                    window.ringMaterial.opacity  = val ? 0.2 : 0.1;
                }
            });

            this.$watch('activeTab', val => set('lp_tab', val));
        },
    };
}
