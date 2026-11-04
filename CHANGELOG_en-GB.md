# 1.5.0
- Security: the two-factor settings (e.g. interface users, mandatory 2FA for the administration) can no longer be changed through the API (`/api/_action/system-config` and `/api/_action/system-config/batch`) without a code, including encoded keys (JSON escapes, form encoding, different letter case, parent keys). The check now uses the keys Shopware actually stores
- Security: changing password, e-mail address or username in your own administration profile now requires the app code (same confirmation dialog as for access changes). Name, language and time zone can still be changed without a code
- Security: deleting a user's access keys or role assignments through Shopware's dedicated delete routes also requires the code
- Security: only users who have 2FA set up themselves can reset the 2FA of other admins (emergency: `bin/console nova-2fa:reset-user`)
- Security: before the code is entered, the storefront no longer loads the off-canvas cart and CMS blocks via XHR (the contact form was prefilled with the account's name and phone). The cart counter remains
- Verified: the key for Shopware's login rate limit matches Shopware 6.7 exactly (lower-case username + IP), so plugin and Shopware count together

# 1.4.0
- Security: before the code is entered, the storefront no longer returns account content via XHR requests (orders, addresses, wishlist, checkout). Only small widgets such as the cart counter, off-canvas cart and menu, search suggestions and cookie settings remain available. Nothing changes after the code and for accounts without 2FA
- Security: "Reset 2FA" for other users and for customers now requires your own app code (like creating access) and is logged. Your own 2FA can only be disabled in the profile with a valid code. Interface users cannot reset anything
- Security: Shopware's login rate limit now also applies when the plugin answers the admin login itself (code missing or wrong), so passwords of 2FA accounts can no longer be guessed without limit
- Security: failed attempts are counted reliably even for parallel requests (lockout can no longer be bypassed)
- Security: users, roles, integrations and the 2FA settings now also require a code when written through nested API writes (e.g. sync) or as a system config record
- Security: Store API (e.g. app): for accounts with 2FA, e-mail, password and profile can no longer be changed there – only in the shop's customer account
- The confirmation code used during setup cannot be reused afterwards
- Docs: changing the app secret (`APP_SECRET`) makes all stored 2FA secrets unreadable

# 1.3.0
- New: interface users without two-factor login (for ERP systems logging in with username and password); they can never change users, roles, access keys or the 2FA settings
- Changing the 2FA settings now requires a fresh code

# 1.2.3
- Customers selected in Nova Upload's download-area access list can set up two-factor login

# 1.2.2
- New: separate window for the code at administration login (opens after username and password; recovery codes accepted)
- Fixed: "password followed by code" in the password field was rejected because Shopware re-reads JSON login data from the raw body

# 1.2.1
- Fixed: administration login with 2FA was impossible because Shopware loads plugin extensions only after login. Now enter password followed by the 6-digit code in the password field.

# 1.2.0
- New: two-factor login can be made mandatory for the administration (users must set it up first; integrations cannot create users, roles or access keys)
- New: creating/changing/deleting users, roles, integrations and access keys requires a fresh code (confirmation dialog, valid 5 minutes)
- New: print / save recovery codes as PDF (administration profile and customer account)

# 1.1.0
- New: two-factor login for the administration (set up per user in the profile, code field at login, checked only after a correct password)
- Settings → Users: status and reset for other users; emergency console command `nova-2fa:reset-user`
- Customers: two-factor login can now be enabled per sales channel (default off); accounts with Nova Upload download access always have it

# 1.0.0
- First version: two-factor login with an authenticator app for customer accounts – QR setup, recovery codes, code after the password (valid 12 hours), lockout after failed attempts, reset in the administration, interface for Nova Upload
