import template from './nova-two-factor-user-card.html.twig';

const { Component, Mixin } = Shopware;

/**
 * Einstellungen → Benutzer → Benutzer: Status der 2FA und Zurücksetzen.
 */
Component.register('nova-two-factor-user-card', {
    template,

    inject: ['acl', 'loginService'],

    mixins: [Mixin.getByName('notification')],

    props: {
        userId: {
            type: String,
            required: true,
        },
    },

    data() {
        return { isLoading: true, enabled: false, status: null, showConfirm: false };
    },

    created() {
        this.load();
    },

    methods: {
        headers() {
            return { Accept: 'application/json', Authorization: `Bearer ${this.loginService.getToken()}` };
        },

        async load() {
            this.isLoading = true;
            try {
                const { data } = await Shopware.Application.getContainer('init').httpClient.get(`_action/nova-2fa/user/${this.userId}`, { headers: this.headers() });
                this.enabled = data.enabled;
                this.status = data.status;
            } catch {
                this.enabled = false;
            } finally {
                this.isLoading = false;
            }
        },

        async reset() {
            this.showConfirm = false;
            try {
                await Shopware.Application.getContainer('init').httpClient.delete(`_action/nova-2fa/user/${this.userId}`, { headers: this.headers() });
                this.createNotificationSuccess({ message: this.$tc('nova-two-factor.resetDone') });
                await this.load();
            } catch (error) {
                this.createNotificationError({ message: error?.response?.data?.message || this.$tc('nova-two-factor.resetError') });
            }
        },

        date(value) {
            return value ? Shopware.Utils.format.date(value, { hour: '2-digit', minute: '2-digit' }) : '–';
        },
    },
});
