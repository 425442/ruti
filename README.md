# Ruti

Persönliche App zum Festhalten und Bewerten täglicher Routinen – nutzbar am PC und am Handy (die Ansicht passt sich dem Gerät an).

## Bereiche

| Tab | Stand |
|---|---|
| **Routinen** | fertig |
| **Ausgaben** | fertig |
| **Rauchen** | fertig |

## Routinen

- Routinen anlegen, beschreiben, bearbeiten und löschen (Papierkorb direkt auf der Karte oder unter „Routinen verwalten“).
- Pro Tag je Routine „Erledigt“ oder „Nicht erledigt“ markieren; vergangene Tage lassen sich über die Pfeile oder den 30-Tage-Balken nachtragen.
- **Wertigkeit mit Sternen:** 1 ★ = 10 Punkte, 2 ★ = 12,5, 3 ★ = 15, 4 ★ = 17,5, 5 ★ = 20 Punkte je erledigtem Tag. Sterne im Formular oder direkt auf der Karte antippen.
- **Zuverlässigkeit** = erledigte ÷ fällige Aufgaben, inklusive heute. Offene Routinen zählen sofort als nicht erledigt (4 Routinen, 1 erledigt → 25 %).
- Serie (Tage am Stück) und die letzten 14 Tage je Routine.
- Reihenfolge per Drag and Drop: Karte ziehen, am Handy kurz gedrückt halten und dann ziehen.

## Ausgaben

- Ausgaben pro Tag erfassen: Betrag, Datum, Kategorie, Notiz. Jede Ausgabe lässt sich bearbeiten und löschen.
- Kategorien selbst anlegen, umbenennen und löschen (Ausgaben einer gelöschten Kategorie landen in „Ohne Kategorie“).
- Kategorie **Rauchen** wird automatisch aus dem Tab Rauchen befüllt (Anzahl × Preis je Tag).
- Übersicht in € und % nach Kategorie für **Tag, Woche (KW), Monat und gesamt**, mit Blättern in die Vergangenheit.
- **Tagesbudget** mit Startdatum und **Übertrag**: Nicht genutztes Budget steht an den folgenden Tagen zusätzlich zur Verfügung, Überziehungen werden abgezogen.
- „Heute noch verfügbar“ = Tagesbudget + Übertrag − heutige Ausgaben.
- Vergleich Ausgaben / Verfügbar / Rest für Tag, Woche, Monat und gesamt – gezählt werden die Tage bis heute.

## Rauchen

- Preis pro Zigarette festlegen (wird mit jeder Erfassung gespeichert, spätere Preisänderungen verfälschen alte Kosten nicht).
- Ein Knopf erfasst eine Zigarette mit Datum und Uhrzeit; jede weitere Erfassung ist sofort möglich.
- Großer Zähler: **Minuten seit der letzten Zigarette**, aktualisiert sich laufend.
- Ziel: Abstände vergrößern. Der Balken vergleicht den laufenden Abstand mit dem Ø-Abstand der letzten 7 Tage und markiert den Rekord.
- Kennzahlen: Anzahl heute (und gestern), Kosten heute / 7 Tage, Ø-Abstand heute / 7 Tage, Rekord-Abstand.
- Verlauf der letzten 14 Tage (Ø-Abstand je Tag und Anzahl).
- Letzte Erfassung 15 Minuten lang rückgängig machen, Einträge löschen, vergessene Zigaretten nachtragen.
- Abstände zählen nur innerhalb eines Tages – die Nacht fließt nicht in die Durchschnitte ein.

## Nutzung

**Online:** https://425442.github.io/ruti/ – am PC und am Handy im Browser öffnen.

**Offline:** `app/index.html` im Browser öffnen – eine einzige Datei, funktioniert ohne Internet.

Die Daten liegen im Browser des jeweiligen Geräts (localStorage). Mit **Sicherung speichern / Sicherung laden** (unter „Routinen verwalten“) überträgst du sie als JSON-Datei zwischen PC und Handy.

## Aufbau

```
app/index.html          fertige App (wird gebaut, nicht direkt bearbeiten)
src/ruti.html           Quelltext der App (HTML, CSS, JavaScript)
vendor/Sortable.min.js  SortableJS 1.15.6 (MIT) für Drag and Drop
build.py                baut app/index.html:  python build.py
```
