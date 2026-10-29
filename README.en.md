# Nova 2FA for Shopware 6

🇩🇪 [Deutsche Version](README.md)

Two-factor login with an authenticator app (TOTP, RFC 6238) for the **administration** (Shopware has none built in) and optionally for **customer accounts** in Shopware 6.6/6.7.

- Administration: set up per user under *Profile*; at login a separate window asks for the 6-digit code after username and password (fallback: password followed by the code in the password field, e.g. `MyPassword123456`). Settings → Users: status and reset. Emergency: `bin/console nova-2fa:reset-user <username>`.
- Optional: make 2FA mandatory for the administration (users must set it up first; integrations cannot create users/roles/access keys).
- Creating/changing users, roles, integrations and access keys – and resetting the 2FA of other users or customers – always requires a fresh code (confirmation dialog, valid 5 minutes), also for nested API writes (sync). Resets are logged; your own 2FA can only be disabled in the profile.
- Recovery codes can be printed or saved as PDF.
- Customers: enable per sales channel ("Offer two-factor login to customers", default off); accounts with Nova Upload download access always have it.

- Setup in *My account → Two-factor login*: scan the QR code (generated in the browser), confirm a code, 8 one-time recovery codes.
- After the password, the shop asks for the 6-digit code; valid for 12 hours in the same session (configurable). Until then only small widgets without account data load via XHR (cart counter, off-canvas cart/menu, search suggestions, cookies).
- Lockout after 5 failed attempts for 15 minutes, every code usable once, secrets encrypted (AES-256-GCM with the app secret). Shopware's login rate limit also applies to the admin code prompt.
- Do not change `APP_SECRET`: the 2FA secrets are encrypted with it. After a change they become unreadable – log in with a recovery code or run `bin/console nova-2fa:reset-user <username>`.
- Administration: customer detail card with status and "Reset 2FA".
- Service for other plugins: `Nova\TwoFactor\Service\TwoFactorGuard` (`isEnabled`, `isVerified`) – used by Nova Upload for its download area.
- Store API logins and API integrations are not challenged. For accounts with 2FA, e-mail, password and profile cannot be changed via the Store API (only in the shop's customer account); orders and addresses stay readable there.

Troubleshooting: codes always invalid → check phone/server time sync; lost phone → reset in the administration after verifying identity; no QR code → `theme:compile`.

License: MIT. Includes qrcode-generator (MIT).
### ERP (e.g. weclapp) reports "NOVA_2FA_REQUIRED" during sync

Create a dedicated user for the ERP (long random password, no 2FA), select it under *Interface users* in the plugin settings and enter its credentials in the ERP. Never use your own admin account. An integration (access key) is the better choice if the ERP supports it.

