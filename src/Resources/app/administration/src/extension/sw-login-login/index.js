import template from './sw-login-login.html.twig';
import { setPendingOtp } from '../../service/otp-interceptor';

/**
 * Anmeldung: Fordert der Server den App-Code an (NOVA_2FA_REQUIRED), erscheint ein Code-Feld;
 * das Passwort bleibt stehen, damit nur noch der Code eingegeben werden muss.
 */
Shopware.Component.override('sw-login-login', {
    template,

    data() {
        return {
            novaOtpRequired: false,
            novaOtp: '',
            novaPassword: '',
        };
    },

    methods: {
        loginUserWithPassword() {
            this.novaPassword = this.password;
            setPendingOtp(this.novaOtpRequired ? this.novaOtp : '');

            return this.$super('loginUserWithPassword');
        },

        handleLoginError(response) {
            setPendingOtp('');
            let error = response?.response?.data?.errors;
            error = Array.isArray(error) ? error[0] : error;
            const code = error?.code;

            if (['NOVA_2FA_REQUIRED', 'NOVA_2FA_INVALID', 'NOVA_2FA_LOCKED'].includes(code)) {
                this.novaOtpRequired = true;
                this.novaOtp = '';
                this.password = this.novaPassword;
                this.loginErrorMessage = code === 'NOVA_2FA_REQUIRED' ? '' : this.$tc(`nova-two-factor.login.${code === 'NOVA_2FA_LOCKED' ? 'locked' : 'invalid'}`);
                this.$emit('login-error');

                return;
            }

            this.$super('handleLoginError', response);
        },
    },
});
