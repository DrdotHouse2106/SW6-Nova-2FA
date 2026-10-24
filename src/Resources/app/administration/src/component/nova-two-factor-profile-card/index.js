import template from './nova-two-factor-profile-card.html.twig';
import qrcode from '../../vendor/qrcode';
import printRecoveryCodes from '../../service/print-codes';

const { Component, Mixin } = Shopware;

/**
 * Profil → Zwei-Faktor-Anmeldung für den angemeldeten Admin-Benutzer.
 */
Component.register('nova-two-factor-profile-card', {
    template,

    inject: ['loginService'],

    emits: ['enabled'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: true,
            enabled: false,
            status: null,
            setup: null,
            code: '',
            recoveryCodes: null,
            isBusy: false,
        };
    },

    computed: {
        qrSvg() {
            if (!this.setup?.uri) {
                return '';
            }
            const qr = qrcode(0, 'M');
            qr.addData(this.setup.uri);
            qr.make();

            return qr.createSvgTag({ cellSize: 5, margin: 4, scalable: true });
        },

        secretGroups() {
            return (this.setup?.secret || '').replace(/(.{4})/g, '$1 ').trim();
        },
    },

    created() {
        this.load();
    },

    methods: {
        http() {
            return Shopware.Application.getContainer('init').httpClient;
        },

        headers() {
            return { Accept: 'application/json', Authorization: `Bearer ${this.loginService.getToken()}` };
        },

        async load() {
            this.isLoading = true;
            try {
                const { data } = await this.http().get('_action/nova-2fa/me', { headers: this.headers() });
                this.enabled = data.enabled;
                this.status = data.status;
            } finally {
                this.isLoading = false;
            }
        },

        async post(path, body = {}) {
            this.isBusy = true;
            try {
                return (await this.http().post(`_action/nova-2fa/me${path}`, body, { headers: this.headers() })).data;
            } catch (error) {
                this.createNotificationError({ message: error?.response?.data?.message || this.$tc('nova-two-factor.profile.error') });
                return null;
            } finally {
                this.isBusy = false;
                this.code = '';
            }
        },

        async startSetup() {
            this.setup = await this.post('/setup');
        },

        async confirmSetup() {
            const result = await this.post('/confirm', { code: this.code });
            if (result) {
                this.recoveryCodes = result.recoveryCodes;
                this.setup = null;
                this.createNotificationSuccess({ message: this.$tc('nova-two-factor.profile.enabled') });
                await this.load();
                this.$emit('enabled');
            }
        },

        async regenerate() {
            const result = await this.post('/recovery', { code: this.code });
            if (result) {
                this.recoveryCodes = result.recoveryCodes;
                await this.load();
            }
        },

        async disable() {
            const result = await this.post('/disable', { code: this.code });
            if (result) {
                this.recoveryCodes = null;
                this.createNotificationSuccess({ message: this.$tc('nova-two-factor.profile.disabled') });
                await this.load();
            }
        },

        printCodes() {
            const user = Shopware.Store?.get?.('session')?.currentUser;
            const ok = printRecoveryCodes(this.recoveryCodes || [], {
                title: this.$tc('nova-two-factor.profile.printTitle'),
                account: `${window.location.host} – ${user?.username || 'Administration'}`,
                note: this.$tc('nova-two-factor.profile.printNote'),
                date: new Date().toLocaleString(),
            });
            if (!ok) {
                this.createNotificationError({ message: this.$tc('nova-two-factor.profile.printBlocked') });
            }
        },

        formatCode(code) {
            return `${code.slice(0, 5)}-${code.slice(5)}`;
        },

        date(value) {
            return value ? Shopware.Utils.format.date(value, { hour: '2-digit', minute: '2-digit' }) : '–';
        },
    },
});
