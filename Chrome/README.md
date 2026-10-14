# Leseklar

Eine Manifest-V3-Erweiterung für Chromium-Browser, die Texte lokal nach persönlichen Leseregeln verändert. Sie löst Schreibweisen wie `Leser*innen`, `Leser:innen` und `LeserInnen` auf und wandelt unterstützte substantivierte Partizipien kontextabhängig um:

| Original                     | Ergebnis                  |
| ---------------------------- | ------------------------- |
| eine engagierte Studierende  | eine engagierte Studentin |
| ein engagierter Studierender | ein engagierter Student   |
| Studierende gesucht          | Studenten gesucht         |
| Ärzt*innen und Kolleg:innen  | Ärzte und Kollegen        |

## Installation

Der fertige Ordner `dist/` kann direkt geladen werden; zur Benutzung sind weder Node.js noch ein Server erforderlich.

1. Öffne [Chrome-Erweiterungen](chrome://extensions) oder [Edge-Erweiterungen](edge://extensions).
2. Aktiviere den Entwicklermodus und wähle **Entpackte Erweiterung laden**.
3. Wähle `C:\Entwicklung\Projekte\Boilerplates\Chrome\dist`.
4. Lade bereits geöffnete Webseiten neu. Öffne über das Erweiterungssymbol die **Einstellungen**.

Andere Chromium-Browser bieten eine entsprechende Erweiterungsverwaltung. Die Installation und Screenreader-Ausgabe müssen dort noch manuell geprüft werden.

## Einstellungen

- Erweiterung und Sprachregeln unabhängig aktivieren; Domains ausnehmen (einschließlich Unterdomains).
- Zugängliche Beschriftungen (`aria-label`, `aria-description`, `aria-valuetext`, `alt`, `title`) mitbearbeiten. ARIA-Referenzen werden nicht verändert.
- Nachgeladene Inhalte werden automatisch geprüft. Ein zusätzlicher Scanabstand von 1 bis 86400 Sekunden ist optional; `0` deaktiviert ihn. Hintergrund-Tabs können Timer verzögern.
- Eigene Regex-Regeln mit Flags, Ersetzung, Domain und Reihenfolge definieren. `$1` und benannte Capture-Gruppen sind unterstützt. Die Vorschau verwendet noch nicht gespeicherte Einstellungen.
- Wortfamilien als JSON ergänzen oder die mitgelieferten Familien überschreiben:

```json
[
  {
    "source": "Studierend",
    "male": "Student",
    "female": "Studentin",
    "plural": "Studenten",
    "weak": true
  }
]
```

Importierte Einstellungen werden zunächst geprüft und erst durch **Speichern** übernommen. Exportiert werden die aktuellen Formulareinstellungen. Beim Ausschalten werden gespeicherte Ursprungstexte wiederhergestellt, soweit die Website sie nicht zwischenzeitlich verändert hat.

## Verhalten und Grenzen

Die Erweiterung startet bei `document_start`; frühes CSS hält die Seite während der ersten Verarbeitung unsichtbar. Nach spätestens 1,5 Sekunden wird sie unabhängig vom Skript wieder sichtbar. Bei langsamen Seiten oder Einstellungszugriff kann deshalb eine spätere sichtbare Korrektur auftreten. Es wird der DOM bearbeitet, nicht die HTTP-Antwort. Siehe [Chromium-Content-Scripts](https://developer.chrome.com/docs/extensions/develop/concepts/content-scripts).

Text über Inline-Elemente hinweg wird gemeinsam analysiert; die Elemente und Ereignisbindungen bleiben erhalten. Code, Formulareingaben, editierbare Bereiche, `translate="no"` und `data-leseklar-ignore` sind ausgenommen. Seiten können Erweiterungsänderungen zurücksetzen. Shadow-DOM, browserinterne Seiten und der Chrome Web Store sind nicht abgedeckt. Frames mit eigener HTTP(S)-Adresse werden separat verarbeitet.

Allgemeine Schreibmuster werden erkannt, auch bei zusammengesetzten Wörtern. Unterstützte produktive Endungen und unregelmäßige Wortstämme werden umgeformt; unbekannte Stämme bleiben erhalten. Substantivierte Partizipien benötigen eine bekannte Wortfamilie: Eine pauschale Umformung jedes Partizips wäre semantisch falsch. Die Kontextanalyse ist eine begrenzte Heuristik, keine vollständige deutsche Grammatik. Unklare Fälle können falsche Formen ergeben; Domain-Ausnahmen und eigene Regeln ermöglichen Korrekturen. Die Eignung der Ersetzungen und tatsächliche Screenreader-Aussprache ist mit den jeweiligen Nutzern zu prüfen.

Regex laufen auf begrenzten Textabschnitten (bis 30000 Zeichen) und werden aus Ursprungstexten berechnet, damit wiederholte Scans nicht etwa `A → AA → AAAA` erzeugen. Sehr große einzelne Textknoten bleiben unverändert. Komplexe Regex können trotzdem den Seitenthread blockieren; die Vorschau wird nach einer Sekunde abgebrochen. Nur vertrauenswürdige, einfache Regeln importieren.

## Datenschutz und Berechtigungen

Alle Ersetzungen und Einstellungen bleiben lokal. Es gibt keine Telemetrie, externen Skripte oder Netzwerkdienste. `storage` speichert Einstellungen im lokalen Browserprofil. Content Scripts benötigen Zugriff auf HTTP- und HTTPS-Webseiten, damit sie automatisch früh starten können; der Browser zeigt diesen Seitenzugriff bei der Installation an. Ausgenommene Domains werden nicht sprachlich verarbeitet, das frühe Skript und CSS starten dort dennoch kurz.

## Entwicklung und Prüfungen

Arbeitsverzeichnis: `C:\Entwicklung\Projekte\Boilerplates\Chrome`. Voraussetzung: Node.js 24 und npm. Reihenfolge für ein frisches Checkout:

```powershell
npm ci
npm run lint
npm test
npm run build
```

`npm run dev` beobachtet `src/` und aktualisiert `dist/`; Änderungen anschließend in der Erweiterungsverwaltung neu laden. `npm run format` formatiert Quellen, `npm run package` erzeugt `release/leseklar.zip`. Die ZIP-Datei ist ein Paket für Verteilung/Upload, keine signierte Store-Veröffentlichung.

Automatische Integrationstests laden die echte Erweiterung in Playwright-Chromium. Die Browserinstallation ist einmalig erforderlich:

```powershell
$env:PLAYWRIGHT_BROWSERS_PATH = Join-Path $PWD 'node_modules/.cache/playwright'
npx playwright install chromium
npm run test:browser
```

Für einen vorhandenen kompatiblen Testbrowser kann alternativ `LESEKLAR_CHROMIUM_EXECUTABLE` auf dessen vollständigen Pfad gesetzt werden. `LESEKLAR_CDP_URL` erlaubt die Verbindung zu einem bereits gestarteten lokalen Testbrowser mit geladener Erweiterung. Verwende hierfür ein separates Testprofil, weil die Tests Einstellungen verändern.

Manuelle Prüfung nach Installation:

```powershell
npm run demo
```

Öffne [die lokale Testseite](http://127.0.0.1:4173). Prüfe erste Anzeige, dynamisches Hinzufügen, bestehende Ereignisbindung, ausgeschlossene Bereiche, Abschalten und Bild-/Button-Beschriftungen mit deinem Screenreader. Mit `Ctrl+C` den Testserver beenden.

Ausgeführte Prüfungen und offene Browserprüfungen sind in [CHANGES.md](CHANGES.md) dokumentiert.
