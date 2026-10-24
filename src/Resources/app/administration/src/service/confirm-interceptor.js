/**
 * Fordert der Server beim Anlegen/Ändern von Benutzern, Rollen oder API-Zugängen den App-Code an
 * (NOVA_2FA_CONFIRM_REQUIRED), erscheint ein kleines Bestätigungsfenster; danach wird die Anfrage
 * mit dem Code wiederholt. Fehlt die eingerichtete 2FA im Pflicht-Modus, öffnet sich die Einrichtung.
 */
const HEADER = 'x-nova-2fa-code';
const t = (key) => {
    const id = `nova-two-factor.confirm.${key}`;
    const tc = Shopware.Snippet?.tc || Shopware.Application?.view?.i18n?.global?.tc;

    return tc ? tc(id) : id;
};

function askForCode(invalid) {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'nova-two-factor-confirm';
        overlay.innerHTML = `
            <form class="nova-two-factor-confirm__box" role="dialog" aria-modal="true">
                <h3></h3><p class="nova-two-factor-confirm__text"></p>
                <p class="nova-two-factor-confirm__error"></p>
                <input name="code" inputmode="numeric" autocomplete="one-time-code" required>
                <div class="nova-two-factor-confirm__actions">
                    <button type="button" class="nova-two-factor-confirm__cancel"></button>
                    <button type="submit" class="nova-two-factor-confirm__ok"></button>
                </div>
            </form>`;
        overlay.querySelector('h3').textContent = t('title');
        overlay.querySelector('.nova-two-factor-confirm__text').textContent = t('text');
        overlay.querySelector('.nova-two-factor-confirm__error').textContent = invalid ? t('invalid') : '';
        overlay.querySelector('input').placeholder = '123456';
        overlay.querySelector('.nova-two-factor-confirm__cancel').textContent = t('cancel');
        overlay.querySelector('.nova-two-factor-confirm__ok').textContent = t('submit');

        const close = (value) => { overlay.remove(); resolve(value); };
        overlay.querySelector('.nova-two-factor-confirm__cancel').addEventListener('click', () => close(null));
        overlay.querySelector('form').addEventListener('submit', (event) => {
            event.preventDefault();
            close(overlay.querySelector('input').value.replace(/\s+/g, ''));
        });
        document.body.appendChild(overlay);
        overlay.querySelector('input').focus();
    });
}

function errorCode(error) {
    let errors = error?.response?.data?.errors;
    errors = Array.isArray(errors) ? errors[0] : errors;

    return errors?.code;
}

const httpClient = Shopware.Application.getContainer('init').httpClient;
httpClient.interceptors.response.use(undefined, async (error) => {
    const code = errorCode(error);

    if (code === 'NOVA_2FA_SETUP_REQUIRED') {
        window.dispatchEvent(new CustomEvent('nova-two-factor:setup-required'));
        return Promise.reject(error);
    }

    if ((code !== 'NOVA_2FA_CONFIRM_REQUIRED' && code !== 'NOVA_2FA_CONFIRM_INVALID') || !error.config) {
        return Promise.reject(error);
    }

    const otp = await askForCode(code === 'NOVA_2FA_CONFIRM_INVALID');
    if (!otp) {
        return Promise.reject(error);
    }

    const config = { ...error.config, headers: { ...(error.config.headers || {}), [HEADER]: otp } };

    return httpClient.request(config);
});
