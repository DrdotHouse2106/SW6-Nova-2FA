# Nova 2FA for Shopware 6

🇩🇪 [Deutsche Version](README.md)

Two-factor login with an authenticator app (TOTP, RFC 6238) for the **administration** (Shopware has none built in) and optionally for **customer accounts** in Shopware 6.6/6.7.

- Administration: set up per user under *Profile*; a code field appears at login after a correct password. Settings → Users: status and reset. Emergency: `bin/console nova-2fa:reset-user <username>`.
- Optional: make 2FA mandatory for the administration (users must set it up first; integrations cannot create users/roles/access keys).
- Creating/changing users, roles, integrations and access keys always requires a fresh code (confirmation dialog, valid 5 minutes).
- Recovery codes can be printed or saved as PDF.
- Customers: enable per sales channel ("Offer two-factor login to customers", default off); accounts with Nova Upload download access always have it.

- Setup in *My account → Two-factor login*: scan the QR code (generated in the browser), confirm a code, 8 one-time recovery codes.
- After the password, the shop asks for the 6-digit code; valid for 12 hours in the same session (configurable).
- Lockout after 5 failed attempts for 15 minutes, every code usable once, secrets encrypted (AES-256-GCM with the app secret).
- Administration: customer detail card with status and "Reset 2FA".
- Service for other plugins: `Nova\TwoFactor\Service\TwoFactorGuard` (`isEnabled`, `isVerified`) – used by Nova Upload for its download area.
- Store API logins and API integrations are not challenged.

Troubleshooting: codes always invalid → check phone/server time sync; lost phone → reset in the administration after verifying identity; no QR code → `theme:compile`.

License: MIT. Includes qrcode-generator (MIT).
