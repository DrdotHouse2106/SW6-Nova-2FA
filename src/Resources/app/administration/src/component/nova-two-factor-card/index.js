import template from './nova-two-factor-card.html.twig';

const { Component, Mixin } = Shopware;

Component.register('nova-two-factor-card', {
    template,

    inject: ['acl', 'loginService'],

    mixins: [Mixin.getByName('notification')],

    props: {
        customerId: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            isLoading: true,
            enabled: false,
            status: null,
            showConfirm: false,
        };
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
                const response = await this.http().get(`_action/nova-2fa/customer/${this.customerId}`, { headers: this.headers() });
                this.enabled = response.data.enabled;
                this.status = response.data.status;
            } catch {
                this.enabled = false;
            } finally {
                this.isLoading = false;
            }
        },

        async reset() {
            this.showConfirm = false;
            try {
                await this.http().delete(`_action/nova-2fa/customer/${this.customerId}`, { headers: this.headers() });
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
