# 1.5.0
- Sicherheit: Die Einstellungen der Zwei-Faktor-Anmeldung (u. a. Schnittstellen-Benutzer, Pflicht für die Administration) lassen sich über die API (`/api/_action/system-config` und `/api/_action/system-config/batch`) nicht mehr ohne Code ändern, auch nicht mit kodierten Schlüsseln (JSON-Escapes, Formular-Kodierung, andere Groß-/Kleinschreibung, übergeordnete Schlüssel). Geprüft werden jetzt die Schlüssel, die Shopware tatsächlich speichert
- Sicherheit: Passwort, E-Mail-Adresse und Benutzername im eigenen Profil der Administration ändern verlangt jetzt den App-Code (Bestätigungsfenster wie bei Zugangs-Änderungen). Name, Sprache und Zeitzone bleiben ohne Code änderbar
- Sicherheit: Zugangsschlüssel und Rollen-Zuordnungen eines Benutzers über Shopwares eigene Lösch-Routen entfernen verlangt ebenfalls den Code
- Sicherheit: Die 2FA anderer Admins kann nur noch zurücksetzen, wer selbst 2FA eingerichtet hat (Notfall weiterhin per `bin/console nova-2fa:reset-user`)
- Sicherheit: Vor der Code-Eingabe lädt die Storefront auch den Off-Canvas-Warenkorb und CMS-Bausteine nicht mehr nach (das Kontaktformular war dort mit Name und Telefon des Kontos vorbelegt). Der Warenkorb-Zähler bleibt
- Geprüft: Der Schlüssel für Shopwares Anmelde-Limit entspricht genau dem von Shopware 6.7 (Benutzername kleingeschrieben + IP), Plugin und Shopware zählen also gemeinsam

# 1.4.0
- Sicherheit: Vor der Code-Eingabe liefert die Storefront auch per Nachlade-Anfrage (XHR) keine Konto-Inhalte mehr (Bestellungen, Adressen, Merkzettel, Checkout). Erlaubt bleiben nur kleine Bausteine wie Warenkorb-Zähler, Off-Canvas-Warenkorb und -Menü, Suchvorschläge und Cookie-Einstellungen. Nach dem Code und für Konten ohne 2FA ändert sich nichts
- Sicherheit: „2FA zurücksetzen“ für andere Benutzer und für Kunden verlangt jetzt den eigenen App-Code (wie beim Anlegen von Zugängen) und wird protokolliert. Die eigene 2FA lässt sich nur noch im Profil mit gültigem Code abschalten. Schnittstellen-Benutzer dürfen nichts zurücksetzen
- Sicherheit: Shopwares Anmelde-Limit gilt jetzt auch, wenn das Plugin bei der Admin-Anmeldung selbst antwortet (Code fehlt oder falsch). Passwörter von Konten mit 2FA lassen sich so nicht mehr unbegrenzt durchprobieren
- Sicherheit: Fehlversuche werden auch bei gleichzeitigen Anfragen sicher gezählt (Sperre nach Fehlversuchen nicht mehr umgehbar)
- Sicherheit: Benutzer, Rollen, Integrationen und die 2FA-Einstellungen sind jetzt auch über verschachtelte Schreibvorgänge der API (z. B. Sync) und als Einstellungs-Datensatz nur mit Code änderbar
- Sicherheit: Store-API (z. B. App): Für Konten mit 2FA sind E-Mail-Adresse, Passwort und Profil dort nicht mehr änderbar – nur noch im Kundenkonto im Shop
- Der Bestätigungscode aus der Einrichtung gilt danach nicht noch einmal
- Hinweis in der Anleitung: Wird das App-Secret (`APP_SECRET`) geändert, sind alle eingerichteten 2FA-Schlüssel unlesbar

# 1.3.0
- Neu: **Schnittstellen-Benutzer (ohne Zwei-Faktor-Anmeldung)** – für Programme wie ein ERP (z. B. weclapp), die sich mit Benutzername und Passwort anmelden. Sie brauchen keinen Code, dürfen aber nie Benutzer, Rollen, API-Zugänge oder die 2FA-Einstellungen ändern
- Änderungen an den Einstellungen der Zwei-Faktor-Anmeldung verlangen jetzt einen frischen Code

# 1.2.3
- Kunden, die in Nova Upload (ab 1.2.0) unter „Kunden mit Zugriff auf den Download-Bereich“ ausgewählt sind, können die Zwei-Faktor-Anmeldung einrichten – auch ohne Zusatzfeld

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
