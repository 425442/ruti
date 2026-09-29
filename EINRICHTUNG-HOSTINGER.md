# Ruti auf Hostinger einrichten (bollmer.click)

Ziel: Ruti läuft unter **https://bollmer.click/ruti/** mit Benutzerkonten und Speicherung in einer MySQL-Datenbank.
Dauer: etwa 15 Minuten, alles im Hostinger **hPanel**.

---

## 1. MySQL-Datenbank anlegen

hPanel → **Websites** → bollmer.click → **Datenbanken** → **MySQL-Datenbanken**

- Datenbankname: `ruti`
- Benutzername: `ruti`
- Passwort: ein neues, starkes Passwort (Hostinger bietet einen Generator an) – **notieren**
- **Erstellen**

Hostinger stellt eine Kennung davor. Notiere die vollständigen Namen aus der Liste darunter,
z. B. `u123456789_ruti` (Datenbank) und `u123456789_ruti` (Benutzer).

Die Tabellen legt Ruti beim ersten Aufruf selbst an.

## 2. E-Mail-Absender anlegen (für „Passwort vergessen“)

hPanel → **E-Mails** → bollmer.click → **E-Mail-Konto erstellen**

- Adresse: `noreply@bollmer.click` (Passwort beliebig, wird für Ruti nicht gebraucht)

## 3. App-Dateien auf den Server bringen (Git-Deployment)

hPanel → **Websites** → bollmer.click → **Erweitert** → **GIT**

- Repository: `https://github.com/425442/ruti.git`
- Branch: `main`
- Verzeichnis: `ruti`   (landet dann in `public_html/ruti`)
- **Erstellen**, danach bei dem Eintrag auf **Deploy** klicken.

Bei späteren Updates genügt ein Klick auf **Deploy** (oder „Automatische Bereitstellung“ einschalten).

*Alternative ohne Git:* Im **Dateimanager** in `public_html` einen Ordner `ruti` anlegen und die Ordner
`app/`, `api/` sowie die Datei `index.html` aus dem Repository hineinladen.

## 4. Konfigurationsdatei anlegen

hPanel → **Dateien** → **Dateimanager**. Gehe **eine Ebene über** `public_html`, also in den Ordner
`domains/bollmer.click/` (dort, wo `public_html` liegt).

1. Neue Datei **`ruti-config.php`** anlegen.
2. Den Inhalt von `api/config.sample.php` hineinkopieren.
3. Die Werte aus Schritt 1 eintragen: `dbname`, `db_user`, `db_pass`.
4. Speichern.

Die Datei liegt damit außerhalb von `public_html` – sie ist aus dem Internet nicht abrufbar und wird bei
einem erneuten Deployment nicht überschrieben. Das Datenbank-Passwort gehört nur in diese Datei, nie ins Repository.

## 5. SSL und PHP prüfen

- hPanel → **Sicherheit** → **SSL**: für bollmer.click aktiv (sonst aktivieren).
- hPanel → **Erweitert** → **PHP-Konfiguration**: Version 8.1 oder neuer.

## 6. Testen

1. https://bollmer.click/ruti/ öffnen → die Anmeldeseite erscheint.
   - Steht dort „Der Server ist noch nicht eingerichtet“: Die Konfigurationsdatei wird nicht gefunden (Schritt 4 prüfen).
   - Steht dort „Der Server ist gerade nicht erreichbar“ oder die Datenbank meldet sich nicht: Zugangsdaten in `ruti-config.php` prüfen.
2. **Registrieren** → Konto anlegen. Eine Bestätigungs-Mail kommt an (auch im Spam-Ordner nachsehen).
3. Auf dem Handy dieselbe Adresse öffnen und anmelden → deine Daten sind da.
4. Tipp am Handy: im Browser-Menü **„Zum Startbildschirm hinzufügen“**.

## Bisherige Daten übernehmen

Wenn du Ruti bisher auf einem Gerät ohne Konto genutzt hast:
- Alte Version öffnen (z. B. GitHub-Adresse oder Ruti.html) → **Routinen → Datensicherung → Sicherung speichern**.
- In der neuen Version anmelden → **Routinen → Datensicherung → Sicherung laden**.

(Öffnest du die neue Adresse im selben Browser, in dem schon Daten ohne Konto liegen, bietet Ruti die Übernahme direkt an.)

## Sicherheit – was eingebaut ist

- Passwörter werden nur als bcrypt-Hash gespeichert.
- Anmelde-Token werden nur gehasht gespeichert, laufen nach 180 Tagen ohne Nutzung ab.
- Begrenzung von Fehlversuchen (8 pro E-Mail-Adresse / 15 Min., 30 pro Anschluss).
- „Passwort vergessen“: Einmal-Link, 1 Stunde gültig; verrät nicht, ob eine Adresse registriert ist.
- Nach Passwort-Zurücksetzen werden alle Geräte abgemeldet; nach Passwortänderung alle anderen Geräte.
- Jeder Nutzer sieht ausschließlich seine eigenen Daten. Konto samt Daten lässt sich selbst löschen.
