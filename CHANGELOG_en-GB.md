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
