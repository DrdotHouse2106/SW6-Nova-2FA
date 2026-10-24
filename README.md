# Nova 2FA für Shopware 6

🇬🇧 [English version](README.en.md)

**Zwei-Faktor-Anmeldung per Authenticator-App** für Shopware 6.6 und 6.7 – für die **Administration** (Shopware bringt dort keine mit) und optional für **Kundenkonten**. Nach dem Passwort wird der 6-stellige Code aus Google Authenticator, Microsoft Authenticator, 1Password, Authy o. Ä. abgefragt.

Für Kunden ist 2FA pro Verkaufskanal einschaltbar und dann freiwillig. Konten mit Zugriff auf den Download-Bereich von **Nova Upload** (Fertigung, Partner) haben sie immer – dort ist sie Pflicht.

## Funktionen

| Funktion | Beschreibung |
|---|---|
| **Administration** | Jeder Benutzer richtet 2FA unter *Profil* ein (QR-Code, Wiederherstellungscodes). Bei der Anmeldung erscheint nach dem Passwort ein Code-Feld. Geprüft wird erst nach richtigem Passwort – ohne Passwort lässt sich weder erkennen, ob 2FA aktiv ist, noch das Konto sperren. *Einstellungen → Benutzer*: Status und Zurücksetzen für andere Benutzer |
| **Notfall** | `bin/console nova-2fa:reset-user <benutzername>` entfernt die 2FA eines Admin-Benutzers (z. B. einziger Admin, Handy und Codes verloren) |
| **Einrichtung im Kundenkonto** | *Mein Konto → Zwei-Faktor-Anmeldung*: QR-Code scannen (wird im Browser erzeugt, kein externer Dienst), Code bestätigen – fertig. Schlüssel zusätzlich zum Abtippen |
| **Wiederherstellungscodes** | 8 Einmal-Codes bei der Einrichtung (nur einmal angezeigt), neu erzeugbar |
| **Code nach dem Passwort** | Wer 2FA eingerichtet hat, sieht nach dem Login die Code-Abfrage. Bis dahin leiten alle Seiten dorthin, Änderungen werden abgelehnt |
| **Gültigkeit** | Nach der Code-Eingabe gilt die Anmeldung in derselben Sitzung 12 Stunden (einstellbar). Neuer Login = neuer Code |
| **Schutz** | Sperre nach 5 Fehlversuchen für 15 Minuten (einstellbar), jeder Code nur einmal verwendbar, Schlüssel verschlüsselt gespeichert (AES-256-GCM mit dem App-Secret) |
| **Administration** | *Kunden → Kunde → Allgemein*: Karte „Zwei-Faktor-Anmeldung“ mit Status und **„2FA zurücksetzen“** (z. B. Handy verloren) |
| **Für andere Plugins** | Dienst `Nova\TwoFactor\Service\TwoFactorGuard` – `isEnabled($customerId)`, `isVerified($request, $customerId)` |

## Installation

Über den Plugin-Katalog des Nova Updaters oder:

```bash
cd <shopware-root>/custom/plugins
git clone https://github.com/DrdotHouse2106/SW6-Nova-2FA.git NovaTwoFactor
cd <shopware-root>
bin/console plugin:refresh
bin/console plugin:install --activate NovaTwoFactor
bin/console theme:compile
bin/console cache:clear
```

## Einstellungen

*Erweiterungen → Meine Erweiterungen → Nova 2FA → Konfigurieren* (pro Verkaufskanal): **Zwei-Faktor-Anmeldung für Kunden anbieten** (aus), Gültigkeit in Stunden (12), Fehlversuche bis zur Sperre (5), Sperre in Minuten (15), Name in der App (leer = Name des Verkaufskanals). Fehlversuche und Sperre gelten auch für die Administration (Einstellung „Alle Verkaufskanäle“).

**Empfehlung:** Für alle Benutzer der Administration 2FA im Profil einrichten.

## Grenzen

- Gilt für die Storefront. Anmeldungen über die Store-API (z. B. eine App) fragen keinen Code ab.
- Integrationen (API-Zugangsschlüssel) und Single Sign-on der Administration sind nicht betroffen.
- „Angemeldet bleiben“ in der Administration verlängert die Sitzung ohne neuen Code.

## Häufige Probleme und Lösungen

### Code wird immer als ungültig abgelehnt

**Ursache:** Die Uhrzeit auf dem Handy oder dem Server weicht mehr als ca. 30 Sekunden ab

**Lösung:** Auf dem Handy „Uhrzeit automatisch einstellen“ aktivieren. Auf dem Server die Zeitsynchronisierung (NTP) prüfen – beim Hosting meist automatisch

### Kunde hat das Handy verloren und keine Wiederherstellungscodes

**Ursache:** Ohne App und ohne Codes ist kein zweiter Faktor mehr vorhanden

**Lösung:** In der Administration beim Kunden „2FA zurücksetzen“ – vorher die Identität prüfen (z. B. Rückruf). Danach meldet sich der Kunde nur mit Passwort an und richtet 2FA neu ein

### Admin hat Handy und Wiederherstellungscodes verloren

**Ursache:** Ohne zweiten Faktor ist keine Anmeldung in der Administration möglich

**Lösung:** Ein anderer Admin setzt unter *Einstellungen → Benutzer → Benutzer → Zwei-Faktor-Anmeldung* zurück. Gibt es keinen: per SSH `bin/console nova-2fa:reset-user <benutzername>` ausführen (z. B. in Plesk unter *SSH-Terminal*), danach mit Passwort anmelden und 2FA im Profil neu einrichten

### Admin-Anmeldung zeigt kein Code-Feld, obwohl 2FA eingerichtet ist

**Ursache:** Die Administration wurde nach dem Update noch nicht neu geladen (alter Browser-Cache)

**Lösung:** Seite mit Strg+F5 neu laden. Das Code-Feld erscheint, sobald Benutzername und Passwort stimmen

### „Zwei-Faktor-Anmeldung“ fehlt im Kundenkonto

**Ursache:** Für Kunden ist 2FA im Verkaufskanal nicht eingeschaltet

**Lösung:** Nova 2FA → Konfigurieren → Verkaufskanal → „Zwei-Faktor-Anmeldung für Kunden anbieten“. Konten mit Zugriff auf den Nova-Upload-Download-Bereich sehen den Punkt immer

### QR-Code erscheint nicht

**Ursache:** Das Storefront-JavaScript ist noch nicht neu gebaut bzw. der Theme-Cache ist alt

**Lösung:** `bin/console theme:compile` und Cache leeren. Bis dahin funktioniert die Einrichtung über den angezeigten Schlüssel (in der App „Schlüssel eingeben“)

### Nach dem Login landet man immer wieder auf der Code-Abfrage

**Ursache:** Die Sitzung geht verloren (z. B. Cookies blockiert oder mehrere Domains ohne gemeinsame Sitzung)

**Lösung:** Cookies für den Shop erlauben und immer dieselbe Shop-Adresse verwenden

## Lizenz

MIT. Enthält [qrcode-generator](https://github.com/kazuhikoarase/qrcode-generator) (MIT) für den QR-Code.
