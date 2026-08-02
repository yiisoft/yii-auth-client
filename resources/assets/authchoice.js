/**
 * Yii auth choice widget
 */
const DEFAULTS = {
    triggerSelector: 'a.auth-link',
    popup: {
        resizable: 'yes',
        scrollbars: 'no',
        toolbar: 'no',
        menubar: 'no',
        location: 'no',
        directories: 'no',
        status: 'yes',
        width: 450,
        height: 380,
    },
};

function authchoice(container, options = {}) {
    const settings = {
        ...DEFAULTS,
        ...options,
        popup: {
            ...DEFAULTS.popup,
            ...(options.popup ?? {}),
        },
    };

    let popup = null;

    container.addEventListener('click', event => {
        const link = event.target.closest(settings.triggerSelector);

        if (!link || !container.contains(link)) {
            return;
        }

        event.preventDefault();

        popup?.close();

        const width = Number(link.dataset.popupWidth || settings.popup.width);
        const height = Number(link.dataset.popupHeight || settings.popup.height);

        popup = window.open(
            link.href,
            'auth_choice',
            Object.entries({
                ...settings.popup,
                width,
                height,
                left: Math.round(window.screenX + (window.outerWidth - width) / 2),
                top: Math.round(window.screenY + (window.outerHeight - height) / 2),
            }).map(([k, v]) => `${k}=${v}`).join(',')
        );

        popup?.focus();
    });
}
