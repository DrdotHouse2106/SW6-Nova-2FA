# 1.2.2
- Neu: **Eigenes Fenster für den Code bei der Admin-Anmeldung** – nach Benutzername und Passwort öffnet sich ein Fenster für den 6-stelligen Code (oder einen Wiederherstellungscode). Bei falschem Code erscheint ein Hinweis
- Fehler behoben: „Passwort und Code im Passwortfeld“ wurde abgelehnt, weil Shopware die Anmeldedaten bei JSON-Anfragen aus dem Rohtext neu einliest. Jetzt wird auch dort das reine Passwort übergeben (funktioniert weiter als Ausweichweg)

# 1.2.1
- **Fehler behoben: Anmeldung in der Administration mit 2FA war nicht möglich.** Shopware lädt Plugin-Erweiterungen erst nach der Anmeldung, das Code-Feld erschien daher nie. Jetzt: ins Passwortfeld das Passwort und direkt dahinter den 6-stelligen Code eingeben („MeinPasswort123456“, Leerzeichen erlaubt; Wiederherstellungscode: „MeinPasswort ABCDE-12345“)
- Hinweis zur Anmeldung im Profil und im Einrichtungsfenster

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
