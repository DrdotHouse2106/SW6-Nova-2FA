import template from './nova-two-factor-enforce.html.twig';

/**
 * Pflicht-Modus: Benutzer ohne 2FA sehen nach der Anmeldung ein Fenster, das sich erst nach der
 * Einrichtung schließt. (Der Server sperrt Änderungen zusätzlich, das Fenster ist nur die Führung.)
 */
Shopware.Component.register('nova-two-factor-enforce', {
    template,

    inject: ['loginService'],

    data() {
        return { show: false };
    },

    created() {
        this.check();
        this.onSetupRequired = () => { this.show = true; };
        window.addEventListener('nova-two-factor:setup-required', this.onSetupRequired);
    },

    beforeUnmount() {
        window.removeEventListener('nova-two-factor:setup-required', this.onSetupRequired);
    },

    methods: {
        async check() {
            try {
                const { data } = await Shopware.Application.getContainer('init').httpClient.get('_action/nova-2fa/me', {
                    headers: { Accept: 'application/json', Authorization: `Bearer ${this.loginService.getToken()}` },
                });
                this.show = Boolean(data.required && !data.enabled);
            } catch {
                this.show = false;
            }
        },

        onEnabled() {
            this.show = false;
        },
    },
});
