# Änderungsprotokoll

## 2026-10-08 – Leseklar als Chromium-Erweiterung umgesetzt

- Manifest-V3-Erweiterung mit früher Seitenverarbeitung, lokaler sprachlicher Umformung und zusätzlichen Regex-Regeln eingerichtet. Die Seite wird bis zur ersten Verarbeitung, höchstens etwa 1,5 Sekunden, ausgeblendet.
- Allgemeine Schreibmuster, unregelmäßige Wortstämme und erweiterbare Wortfamilien berücksichtigen unter anderem Stern, Doppelpunkt, Binnen-I und weiblichen Kontext. Die Sprachverarbeitung ist heuristisch und deckt nicht alle deutschen Wörter und Grammatikfälle ab.
- Einstellungsseite und Popup ermöglichen Aktivierung, Domain-Ausnahmen, Scanintervall, Regelreihenfolge, Vorschau sowie Import und Export. Nachgeladene Inhalte werden automatisch geprüft; eigene Ersetzungen wachsen bei wiederholten Scans nicht weiter.
- Textstruktur und vorhandene Ereignisbindungen bleiben erhalten; zugängliche Beschriftungen werden optional angepasst. Code und editierbare Inhalte sind standardmäßig ausgenommen. Die tatsächliche Screenreader-Verständlichkeit ist noch manuell zu prüfen.
- Projektstruktur eingeführt: `src/` enthält Manifest und Erweiterungsquellen, `scripts/` Build- und Paketwerkzeuge, `tests/` Sprach-, DOM-, Ablauf-, Oberflächen- und Browserprüfungen. `dist/` enthält die ladbare Erweiterung; `release/leseklar.zip` das Verteilungspaket. Die tatsächliche Struktur und Befehle sind in `README.md` dokumentiert; `AGENTS.md` bleibt gemäß der ursprünglichen Anweisung unverändert.
- Node.js-Tooling mit ESLint, Prettier, jsdom, Playwright und fflate ergänzt. Laufzeitcode benötigt keine externen Bibliotheken oder Netzwerkdienste. Die Erweiterung verwendet `storage` und automatischen Seitenzugriff auf HTTP(S)-Webseiten.

### Bereits ausgeführte Schritte

Arbeitsverzeichnis: `C:\Entwicklung\Projekte\Boilerplates\Chrome`; vorhanden: Node.js 24.18.0 und npm 11.16.0.

```powershell
npm install
npm install --save-dev @playwright/test
npm install --save-dev fflate
npm run format
npm run lint
npm test
npm run package
```

Ergebnis: Abhängigkeiten installiert, keine gemeldeten Sicherheitslücken; Formatierung und Linting erfolgreich; alle 42 automatisierten Sprach-, DOM-, Ablauf- und Oberflächetests erfolgreich. Build und ZIP-Paket erfolgreich erzeugt. Der Paketinhalt wurde anschließend auf Manifest und referenzierte Erweiterungsdateien geprüft.

### Browserprüfung noch offen

Folgende Schritte wurden versucht:

```powershell
$env:PLAYWRIGHT_BROWSERS_PATH = Join-Path $PWD 'node_modules/.cache/playwright'
npx playwright install chromium
```

Der Downloadprozess konnte wegen `spawn EPERM` nicht starten. Anschließend wurde ein vorhandener Testbrowser verwendet:

```powershell
$env:LESEKLAR_CHROMIUM_EXECUTABLE = Join-Path $env:LOCALAPPDATA 'ms-playwright/chromium-1243/chrome-win64/chrome.exe'
npm run test:browser
```

Die Browserprüfung scheiterte ebenfalls beim Prozessstart (`spawn EPERM`), bevor die fünf Browsertests ausgeführt werden konnten. Ein separater Browserstart wurde von der Ausführungsrichtlinie abgelehnt. Die Browsertests sind vorhanden, aber nicht erfolgreich validiert; Installation, erste sichtbare Darstellung und Screenreader-Ausgabe sind noch manuell zu prüfen.

### Vom Benutzer auszuführen

Für die direkte Nutzung ist kein Build-Befehl erforderlich: In [Chrome-Erweiterungen](chrome://extensions) oder [Edge-Erweiterungen](edge://extensions) den Entwicklermodus aktivieren, **Entpackte Erweiterung laden** wählen und den Projektordner `dist/` laden. Bereits geöffnete Webseiten danach neu laden.

Zur manuellen Prüfung im Projektverzeichnis:

```powershell
npm run demo
```

Danach [die lokale Testseite](http://127.0.0.1:4173) öffnen. Dynamische Inhalte, Ereignisbindungen, Ausnahmen, Einstellungen und Screenreader-Beschriftungen prüfen. Den Testserver mit `Ctrl+C` beenden. Wiederholbare Browser-Testinstallation und weitere Entwicklungsbefehle stehen in `README.md`.

## 2026-10-08 – Codex-Sitzung wieder aufnehmen

- `Resume-Session.ps1` nimmt gezielt diese Sitzung anhand ihrer festen ID im Projektverzeichnis wieder auf, auch beim Aufruf aus einem anderen Verzeichnis.
- Voraussetzung: installierte Codex-CLI im PATH, gültige Anmeldung und erhaltener Sitzungsverlauf im ursprünglichen Codex-Profil. Eine Wiederaufnahme nach Löschung des Verlaufs kann das Skript nicht gewährleisten.
- `AGENTS.md` bleibt unverändert.

### Bereits ausgeführte Prüfung

Im Arbeitsverzeichnis `C:\Entwicklung\Projekte\Boilerplates\Chrome`:

```powershell
codex resume --help
.\Resume-Session.ps1 -CheckOnly
```

Die CLI-Hilfe bestätigt die Wiederaufnahme anhand einer Sitzungs-ID und die Verzeichnisoption. Die Skriptprüfung ermittelt CLI, Projektpfad und Sitzungs-ID, ohne eine zweite Sitzung zu starten.

### Vom Benutzer auszuführen

In PowerShell im Projektverzeichnis, nach Beendigung dieser Sitzung:

```powershell
.\Resume-Session.ps1
```

Aus einem anderen Verzeichnis ist der vollständige Skriptpfad verwendbar. Falls die lokale Ausführungsrichtlinie das Skript blockiert, kann es einmalig so gestartet werden:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\Entwicklung\Projekte\Boilerplates\Chrome\Resume-Session.ps1"
```

Keine Browser-Schritte erforderlich. Befehlsreferenz: [Offizielle Codex-CLI-Dokumentation](https://developers.openai.com/codex/cli/reference).

## 2026-10-08 – Änderungsdokumentation eingerichtet

- `AGENTS.md` als Contributor-Guide erstellt und um eine verbindliche Regel zur Dokumentation aller künftigen Änderungen ergänzt.
- `CHANGES.md` als zentrales Änderungsprotokoll angelegt. Einträge beschreiben Zweck und Ergebnis auf hoher Ebene.
- Relevante CLI-Befehle und Browser-URLs müssen sichtbar dokumentiert werden, einschließlich der Unterscheidung zwischen bereits ausgeführten und noch erforderlichen Schritten.

### Ausführbare Schritte

Keine CLI-Befehle oder Browser-Aufrufe zur Nutzung dieser Dokumentationsänderung erforderlich.

### Prüfung

Vorhandene Repository-Dateien gelesen; die Dokumentationsregel und diesen Eintrag auf Übereinstimmung mit den gewünschten Vorgaben geprüft.
