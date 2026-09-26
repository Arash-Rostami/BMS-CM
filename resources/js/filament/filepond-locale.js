(function () {
    document.addEventListener('alpine:init', () => {
        if (window.__filepondLocaleFixBound) return;
        window.__filepondLocaleFixBound = true;

        const applyLabel = () => {
            const label = window.FILEPOND_MAX_SIZE_LABEL;

            if (!label || !window.FilePond) return false;

            const originalSetOptions = window.FilePond.setOptions.bind(window.FilePond);

            window.FilePond.setOptions = (options) => originalSetOptions({
                ...options,
                labelMaxFileSizeExceeded: label,
                labelMaxFileSize: label,
            });

            window.FilePond.setOptions({});

            return true;
        };

        if (applyLabel()) return;

        const interval = setInterval(() => {
            if (applyLabel()) clearInterval(interval);
        }, 200);

        setTimeout(() => clearInterval(interval), 10000);
    });
})();
