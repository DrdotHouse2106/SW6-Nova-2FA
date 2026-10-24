/**
 * Hängt beim Login der Administration den Code aus der Authenticator-App an die Token-Anfrage an
 * (Feld „nova_otp“), sobald die Anmeldemaske ihn abgefragt hat.
 */
let pendingOtp = '';

export function setPendingOtp(code) {
    pendingOtp = (code || '').replace(/\s+/g, '');
}

const httpClient = Shopware.Application.getContainer('init').httpClient;
httpClient.interceptors.request.use((config) => {
    if (pendingOtp && typeof config.url === 'string' && config.url.endsWith('/oauth/token')) {
        const data = typeof config.data === 'string' ? JSON.parse(config.data) : (config.data || {});
        if (data.grant_type === 'password') {
            config.data = { ...data, nova_otp: pendingOtp };
        }
    }

    return config;
});
