# Nova 2FA für Shopware 6

🇬🇧 [English version](README.en.md)

**Zwei-Faktor-Anmeldung per Authenticator-App** für Shopware 6.6 und 6.7 – für die **Administration** (Shopware bringt dort keine mit) und optional für **Kundenkonten**. Nach dem Passwort wird der 6-stellige Code aus Google Authenticator, Microsoft Authenticator, 1Password, Authy o. Ä. abgefragt.

Für Kunden ist 2FA pro Verkaufskanal einschaltbar und dann freiwillig. Konten mit Zugriff auf den Download-Bereich von **Nova Upload** (Fertigung, Partner) haben sie immer – dort ist sie Pflicht.

## Funktionen

| Funktion | Beschreibung |
|---|---|
| **Administration** | Jeder Benutzer richtet 2FA unter *Profil* ein (QR-Code, Wiederherstellungscodes). **Anmeldung:** Benutzername und Passwort eingeben – danach öffnet sich ein Fenster für den 6-stelligen Code (oder einen Wiederherstellungscode). Ausweichweg: ins Passwortfeld das Passwort und direkt dahinter den Code, z. B. `MeinPasswort123456` (Leerzeichen erlaubt). Geprüft wird erst nach richtigem Passwort – ohne Passwort lässt sich weder erkennen, ob 2FA aktiv ist, noch das Konto sperren. *Einstellungen → Benutzer*: Status und Zurücksetzen für andere Benutzer |
| **Pflicht für die Administration** | Schalter „Zwei-Faktor-Anmeldung für die Administration verpflichtend“: Benutzer ohne 2FA sehen nach der Anmeldung ein Einrichtungsfenster und können vorher nichts ändern (Lesen geht). Integrationen (API-Zugänge) dürfen dann keine Benutzer, Rollen oder Zugänge anlegen |
| **Zugänge nur mit Code** | Benutzer, Rollen, Integrationen (API-Zugänge) und Zugangsschlüssel anlegen, ändern oder löschen sowie die 2FA anderer Benutzer oder von Kunden zurücksetzen verlangt immer den aktuellen App-Code – ein Bestätigungsfenster fragt ihn ab, danach 5 Minuten gültig. Gilt auch für verschachtelte API-Schreibvorgänge (Sync). Schützt vor heimlich angelegten Hintertür-Zugängen, auch wenn jemand eine offene Sitzung erwischt. Zurücksetzungen werden im Shopware-Log protokolliert |
| **Codes drucken** | Wiederherstellungscodes lassen sich direkt drucken oder als PDF speichern (Druckdialog → „Als PDF speichern“) – im Profil und im Kundenkonto |
| **Notfall** | `bin/console nova-2fa:reset-user <benutzername>` entfernt die 2FA eines Admin-Benutzers (z. B. einziger Admin, Handy und Codes verloren) |
| **Einrichtung im Kundenkonto** | *Mein Konto → Zwei-Faktor-Anmeldung*: QR-Code scannen (wird im Browser erzeugt, kein externer Dienst), Code bestätigen – fertig. Schlüssel zusätzlich zum Abtippen |
| **Wiederherstellungscodes** | 8 Einmal-Codes bei der Einrichtung (nur einmal angezeigt), neu erzeugbar |
| **Code nach dem Passwort** | Wer 2FA eingerichtet hat, sieht nach dem Login die Code-Abfrage. Bis dahin leiten alle Seiten dorthin, Änderungen werden abgelehnt. Nachladen per JavaScript ist nur für kleine Bausteine ohne Kontodaten erlaubt (Warenkorb-Zähler, Off-Canvas-Warenkorb und -Menü, Suchvorschläge, Cookie-Einstellungen) |
| **Gültigkeit** | Nach der Code-Eingabe gilt die Anmeldung in derselben Sitzung 12 Stunden (einstellbar). Neuer Login = neuer Code |
| **Schutz** | Sperre nach 5 Fehlversuchen für 15 Minuten (einstellbar), jeder Code nur einmal verwendbar, Schlüssel verschlüsselt gespeichert (AES-256-GCM mit dem App-Secret). Bei der Admin-Anmeldung gilt zusätzlich Shopwares Anmelde-Limit |
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

**Empfehlung:** Zuerst die eigene 2FA im Profil einrichten und testen, dann unter „Alle Verkaufskanäle“ **„Zwei-Faktor-Anmeldung für die Administration verpflichtend“** einschalten. Alle anderen Benutzer werden bei der nächsten Anmeldung zur Einrichtung geführt.

**Schnittstellen-Benutzer (ohne Zwei-Faktor-Anmeldung):** Benutzer, mit denen sich Programme wie ein ERP anmelden. Sie brauchen keinen Code, dürfen aber keine Benutzer, Rollen, API-Zugänge oder 2FA-Einstellungen ändern.

## Grenzen

- Gilt für die Storefront. Anmeldungen über die Store-API (z. B. eine App) fragen keinen Code ab. Für Konten mit 2FA sind dort aber E-Mail-Adresse, Passwort und Profil nicht änderbar (nur im Kundenkonto im Shop); Bestellungen und Adressen bleiben über die Store-API lesbar.
- **App-Secret nicht ändern:** Die 2FA-Schlüssel sind mit dem `APP_SECRET` aus der `.env` verschlüsselt. Wird es geändert (z. B. Umzug ohne die alte `.env`), lassen sie sich nicht mehr lesen – Anmeldung dann nur noch mit Wiederherstellungscode bzw. nach `bin/console nova-2fa:reset-user <benutzername>`; Kunden-2FA in der Administration zurücksetzen.
- Integrationen (API-Zugangsschlüssel) und Single Sign-on der Administration sind nicht betroffen.
- „Angemeldet bleiben“ in der Administration verlängert die Sitzung ohne neuen Code.

## Häufige Probleme und Lösungen

### Code wird immer als ungültig abgelehnt

**Ursache:** Die Uhrzeit auf dem Handy oder dem Server weicht mehr als ca. 30 Sekunden ab

**Lösung:** Auf dem Handy „Uhrzeit automatisch einstellen“ aktivieren. Auf dem Server die Zeitsynchronisierung (NTP) prüfen – beim Hosting meist automatisch

### Kunde hat das Handy verloren und keine Wiederherstellungscodes

**Ursache:** Ohne App und ohne Codes ist kein zweiter Faktor mehr vorhanden

**Lösung:** In der Administration beim Kunden „2FA zurücksetzen“ – vorher die Identität prüfen (z. B. Rückruf). Danach meldet sich der Kunde nur mit Passwort an und richtet 2FA neu ein

### Admin-Anmeldung: „Die Anfrage an "/oauth/token" ist fehlgeschlagen“

**Ursache:** Für den Benutzer ist 2FA eingerichtet, und es wurde nur das Passwort eingegeben (bis Version 1.2.0 gab es dafür keinen Weg ohne eigenes Code-Feld)

**Lösung:** Ab 1.2.2 öffnet sich nach dem Passwort ein eigenes Fenster für den Code. Bleibt es aus (z. B. Browser-Cache), Seite mit Strg+F5 neu laden oder Passwort und direkt dahinter den Code ins Passwortfeld eingeben. Kommt man gar nicht mehr hinein (z. B. noch Version 1.2.0 installiert): in Plesk → *Datenbanken* → *phpMyAdmin* → *SQL* `DELETE FROM nova_two_factor_user;` ausführen – das entfernt nur die Admin-2FA, danach Anmeldung mit Passwort. Oder per SSH `bin/console nova-2fa:reset-user <benutzername>`

### Passwort + Code im Passwortfeld wird abgelehnt (Version 1.2.1)

**Ursache:** Die Administration schickt die Anmeldung als JSON. Shopware liest diesen Rohtext für die Passwortprüfung neu ein, daher kam das Passwort samt Code dort an

**Lösung:** Auf 1.2.2 aktualisieren – das Plugin ersetzt das Passwort jetzt auch im Rohtext. Zusätzlich gibt es das eigene Code-Fenster

### Admin hat Handy und Wiederherstellungscodes verloren

**Ursache:** Ohne zweiten Faktor ist keine Anmeldung in der Administration möglich

**Lösung:** Ein anderer Admin setzt unter *Einstellungen → Benutzer → Benutzer → Zwei-Faktor-Anmeldung* zurück. Gibt es keinen: per SSH `bin/console nova-2fa:reset-user <benutzername>` ausführen (z. B. in Plesk unter *SSH-Terminal*), danach mit Passwort anmelden und 2FA im Profil neu einrichten

### „Zwei-Faktor-Anmeldung“ fehlt im Kundenkonto

**Ursache:** Für Kunden ist 2FA im Verkaufskanal nicht eingeschaltet

**Lösung:** Nova 2FA → Konfigurieren → Verkaufskanal → „Zwei-Faktor-Anmeldung für Kunden anbieten“. Konten mit Zugriff auf den Nova-Upload-Download-Bereich sehen den Punkt immer

### Beim Speichern eines Benutzers oder einer Integration erscheint „Mit 2FA bestätigen“

**Ursache:** Gewollt – Zugänge zur Administration und API lassen sich nur mit dem aktuellen App-Code anlegen oder ändern. Dasselbe gilt für „2FA zurücksetzen“ bei anderen Benutzern und bei Kunden

**Lösung:** Code aus der App eingeben. Danach gilt die Bestätigung 5 Minuten, weitere Änderungen in dieser Zeit fragen nicht erneut

### Eine Integration (z. B. ERP-Anbindung) kann keine Benutzer oder Zugänge mehr anlegen

**Ursache:** Im Pflicht-Modus dürfen Integrationen keine Benutzer, Rollen oder Zugänge anlegen – sie können keinen zweiten Faktor nachweisen

**Lösung:** Zugänge von Hand in der Administration anlegen. Normale Daten (Produkte, Bestellungen …) ändern Integrationen weiterhin

### ERP (z. B. weclapp) meldet „NOVA_2FA_REQUIRED“ bei der Synchronisation

**Ursache:** Das ERP meldet sich mit Benutzername und Passwort eines Admin-Benutzers an, der 2FA eingerichtet hat – einen Code kann das Programm nicht eingeben

**Lösung:** Eigenen Benutzer für das ERP anlegen (z. B. „weclapp“, langes Zufallspasswort, keine 2FA einrichten), in den Plugin-Einstellungen unter *Schnittstellen-Benutzer* auswählen und die Zugangsdaten im ERP eintragen. Den eigenen Admin-Zugang nie dafür verwenden. Bietet das ERP die Anbindung per Integration (Zugangs-ID + Sicherheitsschlüssel), ist das die bessere Wahl – Integrationen sind von der Code-Abfrage nicht betroffen

### „2FA zurücksetzen“ beim eigenen Benutzer wird abgelehnt

**Ursache:** Die eigene Zwei-Faktor-Anmeldung lässt sich nicht über die Benutzerverwaltung entfernen – sonst könnte eine erwischte Sitzung sie ohne Code abschalten

**Lösung:** Im eigenen *Profil* mit gültigem Code (oder Wiederherstellungscode) abschalten. Ohne Handy und Codes: ein anderer Admin setzt zurück, oder per SSH `bin/console nova-2fa:reset-user <benutzername>`

### Admin-Anmeldung: „Too many requests“ bzw. „Notification throttled“

**Ursache:** Shopwares Anmelde-Limit – nach mehreren falschen Passwörtern oder Codes vom selben Rechner muss zwischen den Versuchen gewartet werden

**Lösung:** Die angezeigte Zeit abwarten und erneut anmelden. Nach einer erfolgreichen Anmeldung ist der Zähler wieder auf null

### Vor der Code-Eingabe funktioniert ein Baustein eines anderen Plugins nicht

**Ursache:** Bis zur Code-Eingabe beantwortet der Shop Nachlade-Anfragen nur für wenige Standard-Bausteine (Warenkorb-Zähler, Menü, Suche, Cookies); alle anderen bekommen „403“

**Lösung:** Code eingeben – danach funktioniert alles wie gewohnt. Konten ohne 2FA sind nicht betroffen

### Druckfenster für die Wiederherstellungscodes öffnet sich nicht

**Ursache:** Der Browser blockiert Pop-ups

**Lösung:** Pop-ups für die Shop- bzw. Admin-Adresse erlauben oder die Codes abschreiben

### QR-Code erscheint nicht

**Ursache:** Das Storefront-JavaScript ist noch nicht neu gebaut bzw. der Theme-Cache ist alt

**Lösung:** `bin/console theme:compile` und Cache leeren. Bis dahin funktioniert die Einrichtung über den angezeigten Schlüssel (in der App „Schlüssel eingeben“)

### Nach dem Login landet man immer wieder auf der Code-Abfrage

**Ursache:** Die Sitzung geht verloren (z. B. Cookies blockiert oder mehrere Domains ohne gemeinsame Sitzung)

**Lösung:** Cookies für den Shop erlauben und immer dieselbe Shop-Adresse verwenden

## Lizenz

MIT. Enthält [qrcode-generator](https://github.com/kazuhikoarase/qrcode-generator) (MIT) für den QR-Code.
