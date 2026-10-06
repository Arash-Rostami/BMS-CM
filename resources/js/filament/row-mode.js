(function () {
    const COOKIE_NAME = 'row_click_mode';
    const MODE_EDIT = 'edit';
    const MODE_VIEW = 'view';

    function readCookie() {
        const match = document.cookie.match(new RegExp('(?:^|; )' + COOKIE_NAME + '=([^;]*)'));

        return match ? decodeURIComponent(match[1]) : MODE_VIEW;
    }

    function writeCookie(value) {
        document.cookie = COOKIE_NAME + '=' + value + ';path=/;max-age=31536000;samesite=lax';
    }

    document.addEventListener('alpine:init', () => {
        if (window.__rowClickModeBound) return;
        window.__rowClickModeBound = true;

        const Alpine = window.Alpine;

        Alpine.store('rowClickEdit', readCookie() === MODE_EDIT);

        window.setRowClickMode = (mode) => {
            writeCookie(mode === MODE_EDIT ? MODE_EDIT : MODE_VIEW);
            window.location.reload();
        };
    });
})();
