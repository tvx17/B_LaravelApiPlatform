# WebAdmin

Lokale Desktop-App mit Quasar, Vue, TypeScript und Electron. Die deutsche Oberfläche enthält Übersicht, Arbeitsbereich und App-Informationen. Im Arbeitsbereich lässt sich ein Apache-Verzeichnis auswählen und dessen Konfigurationsdateien auflisten. Gefundene Dateien lassen sich im Formular- und Quelltexteditor bearbeiten; Virtual Hosts können angelegt, geändert und entfernt werden.

## Voraussetzungen und Start

Node.js 24 LTS und npm. Nach einem frischen Checkout beide Abhängigkeitsgruppen installieren:

```powershell
npm install
npm install --prefix src-electron
npm run dev
```

`npm run dev` öffnet das Desktop-Fenster. `npm run dev:web` startet eine Browser-Vorschau ohne Desktop-Schnittstelle.

## Befehle

| Befehl               | Funktion                                                     |
| -------------------- | ------------------------------------------------------------ |
| `npm run build`      | Desktop-Code kompilieren, ohne ausführbare App zu paketieren |
| `npm run package`    | Desktop-App für das aktuelle Betriebssystem paketieren       |
| `npm run typecheck`  | Vue- und TypeScript-Typen prüfen                             |
| `npm run lint`       | ESLint-Prüfung                                               |
| `npm run lint:check` | Formatierung und ESLint prüfen                               |
| `npm run format`     | Mit Prettier formatieren; AGENTS.md bleibt ausgeschlossen    |

Desktop-Ausgaben liegen in `dist/electron/`. Pakete für Windows, macOS und Linux sollten auf dem jeweiligen Zielsystem gebaut und geprüft werden. Aktuell erfolgt die Prüfung unter Windows.

## Struktur

- `src/layouts/`: gemeinsames Fensterlayout und Navigation.
- `src/pages/`: Seiten für Übersicht, Arbeitsbereich, Einstellungen und 404.
- `src/router/`: Navigation mit Hash-Routing für lokale Desktop-Seiten.
- `src/css/`: gemeinsame Gestaltung.
- `src/types/`: Typen der Desktop-Schnittstelle.
- `src-electron/`: Hauptprozess, Preload und eigene npm-Abhängigkeiten.
- `quasar.config.ts`: gemeinsame Quasar- und Electron-Konfiguration.
- `ENTWICKLUNG.md`: Prompt-bezogenes Entwicklungsprotokoll mit Befehlen und Ergebnissen.

## Architektur und Prüfungen

Der Renderer nutzt Vue und Quasar. Im Electron-Fenster sind Context Isolation und Sandbox aktiviert, Node Integration ist deaktiviert. Die Preload-Schnittstelle stellt Plattform, Electron-Version sowie gezielte Operationen zur Apache-Verzeichnisauswahl und Dateisuche bereit. Der Hauptprozess prüft Aufrufer und Eingaben. Lesen und Speichern erfolgen über Editorsitzungen mit Sicherung und Prüfung auf externe Änderungen.

Der Electron-Hauptprozess unterstützt `--smoke-test`: Das versteckte Fenster prüft die gerenderte Übersicht, Navigation zu Arbeitsbereich und Einstellungen, die Fehlerseite sowie die Preload-Schnittstelle. Erfolg wird als `SMOKE_OK` ausgegeben, Fehler liefern Exitcode 1. Für die Ausführung `npm test` verwenden: Das Testskript erzeugt die benötigten Beispieldateien und ein isoliertes Profil.

Quellen: [Quasar Electron](https://quasar.dev/quasar-cli-vite/developing-electron-apps/introduction/) und [Electron-Vorbereitung](https://quasar.dev/quasar-cli-vite/developing-electron-apps/preparation/).

## Desktop-Smoke-Test

Nach `npm run build` führt `npm test` die oben beschriebenen Prüfungen im versteckten Electron-Fenster aus. Beim ersten Start lädt Electron bei Bedarf seine Binärdateien herunter.

## Apache-Verzeichnis und Dateiliste

Im Arbeitsbereich **Verzeichnis auswählen** anklicken und den Installationsordner (z. B. `C:/Apps/Apache24`) oder direkt den Konfigurationsordner (z. B. `/etc/apache2`) wählen. Der Pfad liegt unter `webadmin.apache.directory` im localStorage des Electron-Renderers. Beim erneuten Öffnen wird die Liste frisch ermittelt. **Erneut suchen**, **Verzeichnis ändern**, **Auswahl entfernen** und ein Dateifilter sind verfügbar.

Die Suche berücksichtigt Hauptdateien (`httpd.conf`, `apache2.conf`, `ports.conf`), `mime.types`, `magic`, `envvars` sowie `.conf`- und `.load`-Dateien in üblichen Ordnern: `conf` einschließlich `extra`, `conf.d`, `conf.modules.d`, `extra`, `sites-available`, `sites-enabled`, `mods-available`, `mods-enabled`, `conf-available` und `conf-enabled`. Verzeichnissuche rekursiv, maximal zwölf Unterebenen und 10.000 Einträge. Verzeichniszyklen werden erkannt; nicht zugängliche Einträge erzeugen Hinweise. Webinhalte und Backup-Dateien werden nicht durchsucht. Verlinkte Dateien und Verzeichnisse werden innerhalb der üblichen Konfigurationsordner berücksichtigt.

Die Liste beschreibt vorhandene Dateien mit relativem Pfad, Bereich und Größe. Die Bereiche sind anhand des Pfads zugeordnet. Include-Anweisungen, benutzerdefinierte externe Konfigurationspfade und der tatsächliche Apache-Ladezustand werden noch nicht ausgewertet. Die Suche verändert keine Konfigurationen. Apache-Prozesse werden nicht gestartet.

`npm test` führt nun elf Datei-, Parser- und Speichertests mit Nodes Test-Runner aus und anschließend den Electron-Smoke-Test. Der Smoke-Test verwendet ein temporäres Profil und einen simulierten Auswahldialog mit Testdateien; die echte Dateisuche läuft im Hauptprozess. Er prüft Auswahl, Persistenz über Seitenwechsel und Neuladen, Dialogabbruch und Entfernen des Pfads. Das normale App-Profil bleibt unberührt.

## Apache-Dateien bearbeiten und Virtual Hosts verwalten

In der Dateiliste **Bearbeiten** wählen. Der Editor bietet:

- **Allgemeine Direktiven:** bekannte Einstellungen im globalen Bereich oder vorhandenen `IfModule`-/`IfDefine`-Abschnitten. Direktiven hinzufügen, einzelne Vorkommen bearbeiten oder entfernen. Texteingaben mit Enter oder beim Verlassen des Feldes übernehmen.
- **Virtual Hosts:** bestehenden Host auswählen, Adresse/Port, `ServerName`, `ServerAlias`, `DocumentRoot`, Protokolle und TLS-Direktiven bearbeiten. Neue Hosts erhalten einen passenden `Directory`-Abschnitt mit auswählbarem `AllowOverride` und `Require`; Hosts lassen sich nach Bestätigung entfernen.
- **Verzeichnisregeln:** direkt zugeordnete `Directory`-Abschnitte auswählen und Pfad, `Options`, `AllowOverride`, `Require` sowie weitere bekannte Felder bearbeiten.
- **Quelltext:** vollständiger Text für unbekannte Direktiven, komplexe Abschnitte und mehrzeilige Werte. Dateien wie `mime.types` oder `envvars` werden ausschließlich hier bearbeitet.
- **Änderungsvorschau:** Vergleich nach Zeilenposition vor dem Speichern; eingefügte Zeilen können nachfolgende Positionen verschieben.

Dropdowns enthalten übliche Werte, erhalten vorhandene individuelle Werte und erlauben eigene Eingaben. Beispiele sind `KeepAlive`, `LogLevel`, `ServerTokens`, `ServerSignature`, `SSLEngine`, `Options`, `AllowOverride` und `Require`. `Include` und `IncludeOptional` können ebenfalls bearbeitet werden. Das Bearbeiten einer VHost-Datei aktiviert sie nicht automatisch in anderen Dateien: nötige Includes, Ports, Module, Zertifikate und Verzeichnisse müssen zur Installation passen.

**Datei speichern** schreibt den Entwurf. Vorher entsteht eine bytegetreue Sicherung `<Datei>.webadmin-<UUID>.bak`. Eine temporäre Datei im gleichen Verzeichnis wird anschließend an die Stelle des Originals verschoben. Externe Inhaltsänderungen und geänderte Link-Ziele führen zu einer Fehlermeldung statt zum Überschreiben. Symlink-Dateien bleiben erhalten; geschrieben wird das beim Öffnen aufgelöste Ziel. Bei Konflikten den Entwurf kopieren und die Datei neu laden. Verlassen, Neuladen oder Schließen mit offenen Änderungen verlangt eine Bestätigung.

Unterstützt werden UTF-8-Dateien bis 2 MB, einschließlich BOM und üblicher LF-/CRLF-Zeilenenden. Andere Kodierungen und Binärdaten werden nicht bearbeitet. Formulare lassen unbekannte Zeilen stehen; gemischte Zeilenenden werden bei einer Änderung vereinheitlicht. Mehrzeilige Direktiven bearbeitet man im Quelltext. Die Abschnittsprüfung ersetzt keinen Apache-Konfigurationstest: vor dem Anwenden mit der passenden Apache-Installation prüfen, etwa `httpd -t` beziehungsweise `apachectl configtest`. Die App startet Apache nicht neu und interpretiert Includes oder Bedingungen nicht als Laufzeitzustand.

Die elf automatisierten Tests prüfen zusätzlich Formularänderungen, verschachtelte Abschnitte, Sicherungen, wiederholtes Speichern, Konflikte und Kodierung. Der Electron-Test prüft einen Dropdownwechsel, das Bearbeiten/Anlegen/Löschen von Virtual Hosts sowie Speichern und Neuladen mit temporären Dateien.

Referenzen: [Apache-Direktiven](https://httpd.apache.org/docs/2.4/mod/core.html), [Virtual Hosts](https://httpd.apache.org/docs/2.4/vhosts/examples.html), [TLS-Direktiven](https://httpd.apache.org/docs/2.4/mod/mod_ssl.html).
