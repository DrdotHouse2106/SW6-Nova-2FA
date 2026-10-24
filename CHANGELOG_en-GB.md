# 1.1.0
- New: two-factor login for the administration (set up per user in the profile, code field at login, checked only after a correct password)
- Settings → Users: status and reset for other users; emergency console command `nova-2fa:reset-user`
- Customers: two-factor login can now be enabled per sales channel (default off); accounts with Nova Upload download access always have it

# 1.0.0
- First version: two-factor login with an authenticator app for customer accounts – QR setup, recovery codes, code after the password (valid 12 hours), lockout after failed attempts, reset in the administration, interface for Nova Upload
