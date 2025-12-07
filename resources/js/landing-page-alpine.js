export default function landingPage() {
    return {
        darkMode: false,
        showSubmodules: false,
        hoveredStep: null,
        loading: true,
        open: false,

        init() {
            const KEY = 'theme';
            let raw = null;
            try {
                raw = localStorage.getItem(KEY);
            } catch (e) {
            }
            if (raw !== null) {
                const v = String(raw).toLowerCase();
                this.darkMode = (v === '1' || v === 'true' || v === 'dark' || v === 'on');
            }

            document.documentElement.classList.toggle('dark', !!this.darkMode);

            window.addEventListener('dark-mode-toggled', e => this.darkMode = !!e.detail);

            this.$watch('darkMode', val => {
                try {
                    localStorage.setItem(KEY, val ? 'dark' : 'light');
                } catch (e) {
                }
                document.documentElement.classList.toggle('dark', !!val);
                if (window.torusMaterial) {
                    window.torusMaterial.opacity = val ? 0.1 : 0.3;
                    window.ringMaterial.opacity = val ? 0.2 : 0.1;
                }
            });
        },

        toggleDark() {
            this.darkMode = !this.darkMode;
        }
    };
}
