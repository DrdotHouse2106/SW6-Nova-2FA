# 1.2.0
- Neu: **Pflicht für die Administration** (Schalter „Zwei-Faktor-Anmeldung für die Administration verpflichtend“): Benutzer ohne 2FA müssen sie nach der Anmeldung einrichten und können vorher nichts ändern; Integrationen (API-Zugänge) dürfen dann keine Benutzer, Rollen oder Zugänge anlegen
- Neu: Benutzer, Rollen, Integrationen und Zugangsschlüssel anlegen, ändern oder löschen nur mit frisch bestätigtem App-Code (Bestätigungsfenster, danach 5 Minuten gültig)
- Neu: Wiederherstellungscodes drucken bzw. als PDF speichern – im Profil der Administration und im Kundenkonto

# 1.1.0
- Neu: **Zwei-Faktor-Anmeldung für die Administration** – jeder Benutzer richtet sie unter *Profil* ein (QR-Code, Wiederherstellungscodes). Bei der Anmeldung erscheint nach dem Passwort ein Code-Feld. Nach dem Passwort geprüft: ohne richtiges Passwort lässt sich weder erkennen, ob 2FA aktiv ist, noch das Konto sperren
- *Einstellungen → Benutzer*: Status und „2FA zurücksetzen“ für andere Benutzer
- Notfall per Konsole: `bin/console nova-2fa:reset-user <benutzername>`
- Kunden: Zwei-Faktor-Anmeldung jetzt pro Verkaufskanal einschaltbar („Für Kunden anbieten“, Standard aus). Konten mit Zugriff auf den Nova-Upload-Download-Bereich haben sie immer

# 1.0.0
- Erste Version: Zwei-Faktor-Anmeldung per Authenticator-App für Kundenkonten – Einrichtung mit QR-Code, Wiederherstellungscodes, Code nach dem Passwort (12 Stunden gültig), Sperre nach Fehlversuchen, Zurücksetzen in der Administration, Schnittstelle für Nova Upload
