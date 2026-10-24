# Nova 2FA for Shopware 6

🇩🇪 [Deutsche Version](README.md)

Two-factor login with an authenticator app (TOTP, RFC 6238) for customer accounts in Shopware 6.6/6.7.

- Setup in *My account → Two-factor login*: scan the QR code (generated in the browser), confirm a code, 8 one-time recovery codes.
- After the password, the shop asks for the 6-digit code; valid for 12 hours in the same session (configurable).
- Lockout after 5 failed attempts for 15 minutes, every code usable once, secrets encrypted (AES-256-GCM with the app secret).
- Administration: customer detail card with status and "Reset 2FA".
- Service for other plugins: `Nova\TwoFactor\Service\TwoFactorGuard` (`isEnabled`, `isVerified`) – used by Nova Upload for its download area.
- Storefront only; Store API logins are not challenged. Admin users use Shopware's built-in admin 2FA.

Troubleshooting: codes always invalid → check phone/server time sync; lost phone → reset in the administration after verifying identity; no QR code → `theme:compile`.

License: MIT. Includes qrcode-generator (MIT).
