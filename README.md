# Ruti

Persönliche App zum Festhalten und Bewerten täglicher Routinen – nutzbar am PC und am Handy (die Ansicht passt sich dem Gerät an).

## Bereiche

| Tab | Stand |
|---|---|
| **Routinen** | fertig |
| Ausgaben | Platzhalter, folgt |
| Rauchen | Platzhalter, folgt |

## Routinen

- Routinen anlegen, beschreiben, bearbeiten und löschen (Papierkorb direkt auf der Karte oder unter „Routinen verwalten“).
- Pro Tag je Routine „Erledigt“ oder „Nicht erledigt“ markieren; vergangene Tage lassen sich über die Pfeile oder den 30-Tage-Balken nachtragen.
- **10 Punkte** je erledigter Routine und Tag.
- **Zuverlässigkeit** = erledigte ÷ fällige Aufgaben, inklusive heute. Offene Routinen zählen sofort als nicht erledigt (4 Routinen, 1 erledigt → 25 %).
- Serie (Tage am Stück) und die letzten 14 Tage je Routine.
- Reihenfolge per Drag and Drop: Karte ziehen, am Handy kurz gedrückt halten und dann ziehen.

## Nutzung

`app/index.html` im Browser öffnen – eine einzige Datei, funktioniert ohne Internet.

Die Daten liegen im Browser des jeweiligen Geräts (localStorage). Mit **Sicherung speichern / Sicherung laden** (unter „Routinen verwalten“) überträgst du sie als JSON-Datei zwischen PC und Handy.

## Aufbau

```
app/index.html          fertige App (wird gebaut, nicht direkt bearbeiten)
src/ruti.html           Quelltext der App (HTML, CSS, JavaScript)
vendor/Sortable.min.js  SortableJS 1.15.6 (MIT) für Drag and Drop
build.py                baut app/index.html:  python build.py
```
