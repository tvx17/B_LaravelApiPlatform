# Entwicklungsprotokoll

Jeder weitere Arbeitsschritt erhält einen eigenen Abschnitt mit Prompt, Entscheidungen, Änderungen, ausgeführten Befehlen und Prüfergebnis. Vorschläge werden von tatsächlich ausgeführten Aktionen unterschieden.

## Schritt 1 — Contributor Guide

**Prompt:** Eine vorhandene `AGENTS.md` bewahren; andernfalls einen kurzen Contributor Guide erstellen.

**Ausgangslage und Ergebnis:** Das Verzeichnis war leer, ohne Git-Repository. `AGENTS.md` wurde mit 319 Wörtern erstellt. Die Hinweise beschreiben den Ausgangszustand und Konventionen für zukünftige Ergänzungen.

**Ausgeführte PowerShell-Befehle:**

```powershell
Test-Path -LiteralPath AGENTS.md
Get-ChildItem -Force
git log -6 --pretty=format:'%s'
Get-Location
Get-Item -LiteralPath AGENTS.md
(Get-Content -LiteralPath AGENTS.md -Raw | Measure-Object -Word).Words
```

Die Git-Abfrage scheiterte erwartbar, weil kein Git-Repository existiert. Die Datei wurde über das Patch-Werkzeug erstellt.

## Schritt 2 — Technische Basis für eine Konfigurations-GUI

**Prompt:** Eine plattformübergreifende GUI vorbereiten, mit der später PHP-, MariaDB-/MySQL- und Apache-Konfigurationsdateien sichtbar und editierbar werden. Zunächst die allgemeine Basis entwickeln, Details schrittweise ergänzen und alle Schritte dokumentieren.

**Bestandsaufnahme:** Nur `AGENTS.md` vorhanden. Node.js `v24.18.0` und npm `11.16.0` sind verfügbar. Noch keine Anwendung oder Abhängigkeiten eingerichtet.

**Ausgeführte PowerShell-Befehle:**

```powershell
Get-Content -LiteralPath AGENTS.md
Get-ChildItem -Force
node --version
npm --version
```

**Technologievorschlag, noch nicht entschieden:** Quasar mit Vue 3, TypeScript und Electron. Quasar liefert GUI-Komponenten und einen Electron-Entwicklungsmodus. Electron ermöglicht die spätere lokale Dateiverarbeitung in einem separaten Hauptprozess. Tauri ist eine Alternative mit System-WebView und zusätzlicher Rust-Toolchain.

**Vorgeschlagene Architektur:**

- Oberfläche: Navigation, gemeinsame Layouts und später Formulare für Einstellungen.
- Desktop-Prozess: Dateidialoge sowie kontrolliertes Lesen und Schreiben.
- Schmale Preload-Schnittstelle: explizite Operationen zwischen Oberfläche und Desktop-Prozess; keine allgemeinen Shell-Befehle aus der Oberfläche.
- Konfigurationsmodule: später getrennte Parser für PHP, MariaDB/MySQL und Apache; Kommentare, Abschnitte und Include-Dateien berücksichtigen.
- Speicherung: später Änderungsvorschau, Sicherung und Erkennung zwischenzeitlich geänderter Dateien vor dem Schreiben.

**Erster Umsetzungsschritt zur Abstimmung:** Startfähige Desktop-Oberfläche mit Navigation und Platzhalterseiten. Dateiverarbeitung folgt in einem eigenen Schritt. Die Entscheidungen über Technologie, Umfang und lokale beziehungsweise spätere SSH-Verbindungen sind noch offen.

**Recherche:** Offizielle Dokumentation zu [Quasar und Electron](https://quasar.dev/quasar-cli-vite/developing-electron-apps/introduction/), [Electron-Vorbereitung](https://quasar.dev/quasar-cli-vite/developing-electron-apps/preparation/) und [Tauri-Voraussetzungen](https://tauri.app/start/prerequisites/).

**Prüfergebnis:** Ausgangszustand und installierte Laufzeitversionen geprüft. Noch keine Anwendung installiert, gestartet oder gebaut.

## Schritt 3 — Lokales Quasar-/Electron-Grundgerüst

**Prompt:** „Quasar und Electron, erst einmal ein Grundgerüst, zuerst nur lokal“.

**Entscheidungen:** Quasar CLI mit Vite, Vue, TypeScript und Electron; npm als Paketmanager. Eine allgemeine Oberfläche bildet die Basis für spätere lokale Konfigurationsmodule.

**Änderungen:** Deutsche Übersicht, Arbeitsbereich, App-Informationen, Fehlerseite und responsive Navigation. Electron-Hauptprozess mit Context Isolation, Sandbox und deaktivierter Node Integration. Die schmale Preload-Schnittstelle liefert Plattform und Electron-Version. README mit Startanleitung, npm-Lockfiles, ESLint, Prettier, TypeScript-Prüfung und versteckter Desktop-Smoke-Test. AGENTS.md wurde unverändert bewahrt und von der Formatierung ausgeschlossen.

**Ausgeführte Shell-Befehle:**

```powershell
Get-Content AGENTS.md
Get-Content ENTWICKLUNG.md
npm view create-quasar version
npm view @quasar/app-vite version
npm config get registry
npm config get cache
npm install --no-save --package-lock=false --offline=false --cache .npm-cache create-quasar
node node_modules/create-quasar/index.js scaffold --template app --engine vite-2 --preset typescript --preset eslint --name webadmin --product WebAdmin --no-git --defaults
node node_modules/create-quasar/bin/create-quasar.js scaffold --template app --engine vite-3 --preset typescript --preset eslint --name webadmin --product WebAdmin --no-git --defaults
node node_modules/create-quasar/bin/create-quasar.js scaffold --template app --preset typescript --preset eslint --name webadmin --product WebAdmin --no-git --defaults --no-color
npm install --offline=false --cache .npm-cache
node node_modules/@quasar/app-vite/bin/quasar.js mode add electron
npm run setup:electron
npm install --offline=false --cache ../.npm-cache --prefix src-electron
npm install --save-dev --offline=false --cache .npm-cache @electron/packager
npm run format
npm run lint
npm run typecheck
npm audit --offline=false --cache .npm-cache --json
npm run build
npm test
npm install --package-lock-only --offline=false --cache .npm-cache
```

Die ersten npm-Abfragen scheiterten am Offline-Modus. Der erste Generatoraufruf hatte einen falschen Einstiegspfad, der zweite den inzwischen entfernten Parameter `--engine`. Der dritte erzeugte die offizielle Vorlage, deren automatische pnpm-Installation mangels pnpm scheiterte. Anschließend wurde npm verwendet.

Die Vorlage wurde mit `Move-Item` ins Projektverzeichnis verschoben; zuvor wurden der absolute Quellpfad und freie Zielpfade geprüft. Das leere Verzeichnis `scaffold` und die pnpm-Workspace-Datei wurden entfernt. Eigene Dateien wurden mit Patch-Werkzeug und PowerShell-Here-Strings angelegt; Paketmetadaten mit Node-Skripten angepasst. Die optionale generierte MCP-Konfiguration wurde entfernt, da die Anwendung sie nicht benötigt. Zur Bestandsaufnahme wurden `Get-Content`, `Get-ChildItem` und `rg` verwendet. Eine optionale Prozessabfrage mit `Get-CimInstance` war mangels Berechtigung nicht möglich.

Das automatische Hinzufügen des Electron-Modus erkannte im eingeschränkten Kontext keinen Paketmanager. Daher wurden die Dateien aus der installierten offiziellen Quasar-Electron-Vorlage übernommen und angepasst. Installationsversuche in zusammengesetzten Befehlen scheiterten am Netzwerk beziehungsweise wurden abgebrochen. Erfolgreich waren separate Installationen im Electron-Unterverzeichnis:

```powershell
# Arbeitsverzeichnis: WebAdmin/src-electron
npm install --offline=false --cache C:/Entwicklung/Projekte/Boilerplates/WebAdmin/.npm-cache
npm install --package-lock-only --offline=false --cache C:/Entwicklung/Projekte/Boilerplates/WebAdmin/.npm-cache
```

Der Build ergänzte den Packager automatisch unter `src-electron`. Electron wurde auf `^44.6.0` festgelegt; seine Binärdateien wurden beim ersten Smoke-Test heruntergeladen. Das temporäre Skript `setup:electron` wurde anschließend entfernt.

**Prüfergebnis:** ESLint und TypeScript erfolgreich. Desktop-Build erfolgreich. Der echte Electron-Smoke-Test meldete `SMOKE_OK {"platform":"win32","electron":"44.6.0","pages":4}`. Er prüfte Übersicht, Arbeitsbereich, Einstellungen, Fehlerseite, Preload-Informationen und das Fehlen von Node-`require` im Renderer. Auch `npm run lint:check` ist erfolgreich: Formatierung und ESLint ohne Fehler. macOS, Linux und eine paketierte Distribution sind noch nicht geprüft.

**Abhängigkeiten:** npm meldete für das Hauptprojekt vier Befunde mit hoher Schwere. Die Detailabfrage mit `npm audit` scheiterte am gesperrten Netzwerkendpunkt; die Befunde sind noch nicht bewertet oder behoben. Für die Electron-Abhängigkeiten meldete npm keine Befunde. Kein automatisches Update mit `--force` durchgeführt.

**Nächster möglicher Schritt:** Ein lokaler Dateidialog mit reiner Inhaltsansicht, anschließend der erste Konfigurationsparser.

## Schritt 4 — Apache-Verzeichnis speichern und Konfigurationen auflisten

**Prompt:** Ein Apache-Verzeichnis auswählen, lokal speichern und die wichtigsten Konfigurationsdateien darin suchen und auflisten.

**Ableitung und Umsetzung:** Der native Electron-Verzeichnisdialog liefert den Pfad. Die Oberfläche speichert ihn unter `webadmin.apache.directory` im localStorage und lädt beim nächsten Besuch eine frische Dateiliste. Gezielte IPC-Operationen verbinden Oberfläche und Hauptprozess; Aufrufer und absolute Pfade werden geprüft. Auswahl ändern, erneut suchen, Auswahl entfernen und Filtern sind verfügbar. Ein Dialogabbruch erhält den bisherigen Zustand. Fehlende Verzeichnisse, Speicherfehler und nicht lesbare Einträge werden angezeigt.

Die Suche berücksichtigt Hauptkonfigurationen, Dateitypen, Umgebung sowie `.conf`/`.load` in üblichen Konfigurationsordnern inklusive Unterverzeichnissen und symbolischen Links. Sie begrenzt Tiefe und Eintragszahl und erkennt Verzeichniszyklen. Die Anzeige enthält relative Pfade, grobe Bereiche und Dateigrößen. Kategorien werden aus Dateinamen und Pfaden abgeleitet, nicht aus Konfigurationsinhalten. Include-Anweisungen und externe individuelle Pfade werden noch nicht ausgewertet; die Liste behauptet keine Aktivierung der gefundenen Dateien.

**Geänderte Dateien:** `src/pages/WorkspacePage.vue`, `src/pages/IndexPage.vue`, gemeinsame API-Typen unter `src/types/`, Electron-Hauptprozess und Preload, neues Suchmodul `src-electron/apache-discovery.ts`, Tests unter `tests/`, Smoke-Test, npm-Testskript und README. Keine zusätzlichen Abhängigkeiten. AGENTS.md bleibt unverändert.

**Ausgeführte Befehle:**

```powershell
Get-Content AGENTS.md
Get-Content src-electron/electron-main.ts, src-electron/electron-preload.ts, src/pages/WorkspacePage.vue, src/types/desktop.d.ts, package.json, quasar.config.ts
Get-Content ENTWICKLUNG.md -Tail 35
Get-Content src/pages/IndexPage.vue
Get-Content README.md -TotalCount 8
npm run format
npm run typecheck
npm run lint:check
npm run build
npm test
```

Änderungen wurden mit dem Patch-Werkzeug und PowerShell-Here-Strings geschrieben. Der erste Electron-Test scheiterte an der Testsuche nach Button-Text: CSS stellt Buttons großgeschrieben dar. Der Test wurde auf den unveränderten Textinhalt umgestellt; Zusätzlich wartet der Test gezielt auf den leeren Arbeitsbereich, da der aktualisierte Übersichtstext ebenfalls Apache erwähnt. Danach wurden Build und Tests erfolgreich wiederholt.

**Prüfergebnis:** Fünf Suchtests erfolgreich: Apache-Installationsstruktur, direkt gewählter Konfigurationsordner mit Debian-artiger Struktur, ungültige/fehlende Pfade, fehlende Hauptkonfiguration und verschachtelte `conf.d`-Dateien. Electron-Smoke-Test unter Windows erfolgreich: Dateiliste, localStorage-Persistenz einschließlich Neuladen, Abbruch und Entfernen; zusätzlich bisherige Seiten- und Preload-Prüfungen. Der native Auswahldialog wird im Smoke-Test simuliert; Testdateien und Profil sind temporär. TypeScript, ESLint, Formatierung und Electron-Build erfolgreich. Linux-Strukturen wurden als Dateibaum getestet; kein Test auf einem Linux- oder macOS-Rechner.

**Quellen und Grenzen:** [Apache-Konfigurationsdateien](https://httpd.apache.org/docs/2.4/configuring.html) und [Include / ServerRoot](https://httpd.apache.org/docs/2.4/mod/core.html#include). Apache kann Konfigurationen über individuelle Includes und Startparameter laden; deren vollständige Auflösung folgt später.

**Nächster möglicher Schritt:** Eine gefundene Datei auswählen und ihren Inhalt anzeigen.

## Schritt 5 — Apache-Editor mit Formularen und Virtual Hosts

**Prompt:** Gewählte Dateien editieren; bekannte Attribute mit auswählbaren Werten verwalten; besonders Virtual Hosts verwalten.

**Ableitung:** Ein Datei-Editor mit Formular-, Virtual-Host-, Quelltext- und Vorschauansicht. Änderungen bleiben zunächst im Entwurf. Speichern erhält eine Sicherung und erkennt externe Änderungen. Die Arbeit betrifft weiterhin lokale Dateien; kein Neustart oder automatisches Aktivieren von Apache-Konfigurationen.

**Umsetzung:**

- Dateiliste verlinkt über „Bearbeiten“ in einen eigenen Editor.
- Gemeinsamer Direktivenkatalog mit Textfeldern und Dropdowns, unter anderem für Servernamen, Dokumentverzeichnis, Includes, Listen, Logs, KeepAlive, Signatur, TLS und Verzeichnisregeln. Eigene Werte bleiben möglich. Wiederholte Direktiven werden einzeln dargestellt.
- Globaler Geltungsbereich und vorhandene `IfModule`-/`IfDefine`-Abschnitte auswählbar; Bedingungen werden nicht ausgeführt.
- Virtual Hosts auswählen, bearbeiten, anlegen und mit Bestätigung entfernen. Neue Hosts ergänzen einen passenden Directory-Abschnitt. Vorhandene Reihenfolge und nicht bearbeitete Inhalte bleiben bestehen.
- Direkt zugeordnete Directory-Abschnitte mit Pfad, AllowOverride, Options und Require bearbeiten. Komplexe oder weitere Abschnitte sind im Quelltext erreichbar.
- Zeilenbasierter Parser für Abschnitte und gezielte Änderungen. Unbekannte Direktiven, Kommentare und nicht bearbeitete Werte bleiben erhalten. Mehrzeilige Direktiven werden ausschließlich im Quelltext bearbeitet.
- Hauptprozess liest nur Dateien aus einer erneuten Suche im gewählten Verzeichnis. Speichern erfolgt über einen Sitzungsschlüssel, nicht über beliebige Schreibpfade.
- UTF-8, BOM und LF/CRLF unterstützt; Limit 2 MB. Andere Kodierungen werden vor dem Bearbeiten abgelehnt. Gemischte Zeilenenden werden bei Änderungen vereinheitlicht.
- Sicherung neben der Datei, temporäre Datei im gleichen Verzeichnis, anschließend Ersetzen des Originals. Inhalts- und Link-Zielprüfung vor dem Schreiben; gleichzeitiges Speichern desselben Ziels innerhalb des Prozesses wird verhindert. Dateirechte werden beim Anlegen übernommen; spezielle Eigentümer-/ACL-Metadaten werden nicht ausdrücklich kopiert. Hardlinks werden nicht ersetzt.
- Bestätigung beim Verlassen, Neuladen oder Schließen mit offenen Änderungen. Vorschau vergleicht Zeilenpositionen und ist kein vollständiger Diff-Algorithmus.

**Dateien:** Neue Module `src/lib/apache-config.ts`, `src-electron/apache-files.ts`, `src/components/ApacheDirectiveFields.vue`, `src/pages/ApacheEditorPage.vue` und `tests/apache-editor.test.mjs`. Erweiterte API-Typen, Preload, IPC, Routing, Dateiliste, Übersicht, Smoke-Test, Quasar-Dialogplugin und TypeScript-Konfiguration. README aktualisiert; keine neuen Abhängigkeiten. AGENTS.md unverändert.

**Ausgeführte Shell-Befehle:**

```powershell
Get-Content AGENTS.md
Get-Content src-electron/apache-discovery.ts, src-electron/electron-main.ts, src/types/apache.ts, src-electron/electron-preload.ts, src/pages/WorkspacePage.vue, src/router/routes.ts, tests/apache-discovery.test.mjs
Get-Content tsconfig.json
Get-Content scripts/smoke-electron.mjs
Get-Content README.md
rg -n 'onBlur|emitValue|onInput' node_modules/quasar/src/components/input/QInput.js
rg -n 'onControlFocusout|onControlFocusin' node_modules/quasar/src/composables/private.use-field/use-field.js
npm run format
npm run typecheck
npm run lint
npm run lint:check
npm run build
node --test tests/*.test.mjs
npm test
```

Dateien wurden über Patch-Werkzeug und PowerShell geschrieben; `Get-Content` und `rg` wurden außerdem gezielt zur Kontrolle der geänderten Stellen verwendet. Tests und Builds wurden nach Korrekturen wiederholt. Der direkte Node-Testaufruf scheiterte an eingeschränktem Prozessstart (`spawn EPERM`); der normale npm-Testaufruf funktioniert. TypeScript benötigte `allowImportingTsExtensions` für die durch Node direkt getesteten TS-Module und eine explizite Typisierung der Tabellen-Spalten. ESLint meldete drei unnötige Regex-Escapes, die entfernt wurden.

**Testkorrekturen:** Ein neues leeres Konfigurationsdokument erhält beim Einfügen eine abschließende Leerzeile; die entsprechende Erwartung wurde korrigiert. Im Electron-Test wurden echte Fokus-/Eingabeereignisse, unveränderte Texte statt CSS-Großschreibung und die gezielte Suche im offenen Dialog verwendet. So unterscheidet der Test die gleichnamigen Felder im Host und im Anlegen-Dialog.

**Prüfergebnis:** Elf Datei-/Parser-/Speichertests erfolgreich. Der Electron-Test meldete `SMOKE_OK {"platform":"win32","electron":"44.6.0","pages":5,"editor":true}` nach Dropdownwechsel, Host-Bearbeitung, Anlegen, Speichern, Neuladen und Entfernen eines Hosts. Die bisherigen Such-, Persistenz- und Seitenprüfungen bleiben enthalten. Tests schreiben ausschließlich temporäre Beispieldateien und verwenden ein temporäres App-Profil. Abschließende Prüfungen: TypeScript, ESLint, Formatierung und Desktop-Build.

**Grenzen:** Kein vollständiger Apache-Parser und kein integriertes `httpd -t`. Keine Auswertung von Includes, Bedingungen, Modulverfügbarkeit oder Zertifikaten. Anlegen und Entfernen betreffen die aktuell geöffnete Datei. Die Sicherung erlaubt Wiederherstellung über Dateisystemwerkzeuge; ein Wiederherstellungsdialog ist noch nicht enthalten. Kein Test einer echten laufenden Apache-Installation oder von macOS/Linux.

**Recherche:** Offizielle [Core-Direktiven](https://httpd.apache.org/docs/2.4/mod/core.html), [Virtual-Host-Beispiele](https://httpd.apache.org/docs/2.4/vhosts/examples.html) und [mod_ssl](https://httpd.apache.org/docs/2.4/mod/mod_ssl.html). Neue Hosts werden am Ende eingefügt, damit bestehende Standard-Host-Reihenfolgen erhalten bleiben.

**Nächster möglicher Schritt:** Apache-Syntaxprüfung vor dem Speichern integrieren und Include-Verknüpfungen sichtbar machen.
