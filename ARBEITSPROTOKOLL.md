# Arbeitsprotokoll

## Prüfung des Contributor-Leitfadens

- Auftrag: `AGENTS.md` erstellen, sofern die Datei im Arbeitsverzeichnis noch nicht existiert.
- Arbeitsverzeichnis: `C:\Entwicklung\Projekte\Boilerplates\LaravelApiPlatform`.
- CLI-Prüfung (PowerShell):

  ```powershell
  Test-Path -LiteralPath AGENTS.md
  Get-Location
  ```

- Ergebnis: `AGENTS.md` existiert (`True`). Gemäß Vorgabe wurde die Datei weder überschrieben noch verändert.

## Einrichtung des Arbeitsprotokolls

- Vor dem Anlegen wurden vorhandene Markdown-Dateien und der Git-Status geprüft:

  ```powershell
  Get-ChildItem -Name -File *.md
  git status --short
  ```

- Ergebnis: `AGENTS.md`, `CLAUDE.md` und `README.md` waren vorhanden; der Git-Status zeigte keine Änderungen.
- `ARBEITSPROTOKOLL.md` wurde über das Werkzeug `apply_patch` angelegt, um wichtige Schritte, erforderliche CLI-Befehle und gegebenenfalls aufgerufene URLs knapp festzuhalten.
- Für diese Arbeiten waren keine URL-Aufrufe und keine Tests erforderlich, da ausschließlich Dokumentation angelegt wurde.

## Erweiterungsvergleich und Übernahme (14.10.2026)

- Referenz `../LaravelAPI` ausschließlich gelesen: `Get-Content composer.json`, `rg --files app config routes scripts tests docs database`, `git log -12 --oneline`, `git status --short`. Dort bereits vorhandene Änderung: `PROJEKTDOKUMENTATION.md`; bleibt unberührt.
- Zielbestand: `Get-Content composer.json`, `Get-Content config/api-platform.php`, `php --version`, `composer --version`, `php artisan route:list --json`, `php vendor/phpunit/phpunit/phpunit --no-progress`.
- Tatsächlich installiert: Laravel 13 und API Platform 5 (`^5.0`), obwohl AGENTS.md noch 4.x nennt. AGENTS.md bleibt unverändert. API Platform Admin `/admin`, API-Präfix `/api` und JSON:API/Problem-JSON bleiben erhalten.
- Zwölf fehlende direkte Erweiterungen: Permission, Sanctum, Filament, Artisan Runner UI, Media Library, Socialite, Fortify, Telescope, Brain, Sluggable, Migrations Generator, IDE Helper. Gemeinsame Basis-/Testpakete sind bereits vorhanden.
- Setup-Vorbild: CLI `composer setup` / `php scripts/Setup.php`, Browser `/setup.php`, Datenbankauswahl, CORS, Administrator, optionale Integrationen und Abschlusssperre. Übernahme erfolgt angepasst an API Platform; keine pauschale Übernahme der Standard-API.
- Dokumentation aufgerufen: https://api-platform.com/docs/laravel/, https://api-platform.com/docs/core/operations/, https://filamentphp.com/docs/5.x/introduction/installation, https://laravel.com/docs/13.x/sanctum, https://laravel.com/docs/13.x/fortify, https://laravel.com/docs/13.x/telescope, https://laravel.com/docs/13.x/socialite. Spatie-/Runner-Webseiten teilweise nicht abrufbar; zusätzlich Paketquellen und README im Referenz-vendor geprüft.
- Composer wird pro Erweiterung ausgeführt und anschließend separat committet. Erster Versuch `composer require spatie/laravel-permission:~8.3 --no-interaction --no-scripts` scheiterte am Sandbox-Netzwerk; derselbe Befehl wurde mit freigegebenem Netzwerkzugriff wiederholt. `--no-scripts` ermöglicht kontrollierte Registrierung nach der Konfiguration.

### Spatie Permission

- Installiert mit obigem Composer-Befehl. Konfiguration, Migration und Integrationstest aus der Referenz übernommen; `HasRoles` am User und Middleware-Aliase eingerichtet.
- Prüfung: `php artisan package:discover --ansi`, `php vendor/phpunit/phpunit/phpunit --filter PermissionsTest --no-progress`. Tests verwenden SQLite `:memory:`; keine Migration auf der lokalen Anwendungsdatenbank.
- Ausgangstest vor Änderungen scheiterte am Sandbox-Schreibzugriff auf Blade-Cache; Prüfung außerhalb der Sandbox wiederholt.
- Commit: `Add Spatie Permission with roles and authorization middleware`.

### Sanctum und CORS

- `composer require laravel/sanctum:~4.0 --no-interaction --no-scripts`; Sanctum-/CORS-Konfiguration übernommen, Token-Migration ergänzt, `HasApiTokens` und Stateful-Middleware aktiviert. Keine Standard-API-Routen erzeugt.
- Prüfung: Paketregistrierung und Sanctum-Integrationstest (Token mit `auth:sanctum`, CSRF-Cookie und CORS).
- Commit: `Add Sanctum token authentication and credentialed CORS`.

### Filament

- `composer require filament/filament:~5.0 --no-interaction --no-scripts`; Panel-Provider aus Referenz unter `/filament` registriert. User-Zugang erfordert `access-admin-panel`; `admin:grant-access` vergibt das Recht gezielt an bestehende Konten.
- `/admin/{path?}` bleibt API Platform Admin. Assets werden mit `php artisan filament:upgrade` veröffentlicht; Composer-Hook und Git-Ausschlüsse ergänzt.
- Prüfung: `php vendor/phpunit/phpunit/phpunit --filter FilamentTest --no-progress` (Gast, verweigerter/erlaubter Zugang, unbekannter Benutzer).
- Commit: `Add Filament panel at /filament with explicit access permission`.

### Artisan GUI Runner

- `composer require codesquirrel/artisan-runner-ui:~1.0 --no-interaction --no-scripts`.
- Runner-Konfiguration, Session-Actions, Controller, Login-/Kontoseiten und `runner:create-user` übernommen. Routen: `/artisan`, `/artisan/login`, `/artisan/account`, `/artisan/logout`; Login gedrosselt, Session-ID bei Anmeldung erneuert.
- Runner standardmäßig aus, nur ausgewählte Umgebungen; Authentifizierung, Permission und CSRF erforderlich. Destruktive/berechtigungsändernde Befehle gesperrt. Test aus Referenz angepasst: `/admin` darf hier weiterhin existieren.
- Prüfung: PHPUnit `ArtisanRunnerUiTest|CreateRunnerUserTest`; anschließend separater Commit `Add authenticated Artisan GUI runner at /artisan`.

### Media Library

- `composer require spatie/laravel-medialibrary:~11.0 --no-interaction --no-scripts`; Konfiguration und Media-Migration aus Referenz übernommen. Wie dort noch kein Fachmodell und kein Upload-Endpunkt.
- Prüfung: Paketregistrierung und bestehende Featuretests mit allen Migrationen auf SQLite `:memory:`.
- Commit: `Add Spatie Media Library configuration and media migration`.

### Socialite

- `composer require laravel/socialite:~5.31 --no-interaction --no-scripts`; wie Referenz ohne vorgegebenen OAuth-Anbieter oder Zugangsdaten.
- Prüfung: `php artisan package:discover --ansi`; Composer prüft Laravel-/PHP-Kompatibilität und Sicherheitsmeldungen.
- Commit: `Add Laravel Socialite OAuth integration foundation`.

### Fortify

- `composer require laravel/fortify:~1.0 --no-interaction --no-scripts`; Referenz-Konfiguration mit deaktivierten Views/Features übernommen. Eigener Provider ruft wie Referenz `Fortify::ignoreRoutes()` auf, damit API-Platform- und Runner-Routen nicht kollidieren.
- Referenz-spezifische Standard-API-Response-Klassen nicht übernommen; keine neuen `/api/login`-/`/api/user`-Versprechen. Bestehende Runner-Anmeldung bleibt Anwendungscode, Filament verwaltet seine Anmeldung selbst.
- Prüfung: Paketregistrierung und Sessiontests; Commit `Add Fortify foundation without conflicting automatic routes`.

### Telescope

- `composer require --dev laravel/telescope:~5.25 --no-interaction --no-scripts`; automatische Registrierung deaktiviert, ausschließlich in `local` registriert. Konfiguration, Migration und Maskierung sensibler Requestdaten übernommen.
- Test an vorhandenen Health-Endpunkt `/up` angepasst; `TelescopeTest` prüft lokale Oberfläche, Ausschluss in Testing/Produktion und Maskierung.
- Commit: `Add local-only Telescope with sensitive request masking`.

### Laravel Brain

- `composer require --dev laramint/laravel-brain:~2.4 --no-interaction --no-scripts`; erzeugte Architekturgraphen Git-ignoriert.
- Prüfung: `php artisan brain:scan --no-interaction`; lokaler Graph unter `/_laravel-brain`. Produktionssperre des Pakets geprüft. Kein zusätzlicher optionaler MCP-/Security-Scanner installiert.
- Commit: `Add Laravel Brain development architecture explorer`.

### Eloquent Sluggable

- `composer require cviebrock/eloquent-sluggable:~13.0 --no-interaction --no-scripts`; Referenz-Konfiguration übernommen, keine ungefragte Slug-Spalte am User.
- `SluggableIntegrationTest` prüft Modellereignisse, eindeutige Slugs und stabile URLs bei Titeländerung; Tabelle nur in Testdatenbank.
- Commit: `Add Eloquent Sluggable with unique slug integration coverage`.

### Migrations Generator

- `composer require --dev kitloong/laravel-migrations-generator:~7.4 --no-interaction --no-scripts`.
- `MigrationsGeneratorIntegrationTest` exportiert ausschließlich eine isolierte Testtabelle in ein temporäres Verzeichnis mit `--skip-log`, spielt die Migration wieder ein und prüft, dass der Migrationseintrag unverändert bleibt. Kein Export der Anwendungsdatenbank.
- Commit: `Add Kitloong Migrations Generator with schema replay test`.

### IDE Helper

- `composer require --dev barryvdh/laravel-ide-helper:~3.7 --no-interaction --no-scripts`; Konfiguration aus Referenz übernommen, generierte IDE-Dateien Git-ignoriert.
- Prüfung: `php artisan ide-helper:generate --no-interaction`; keine Modelländerungen oder Datenbankabfragen durch `ide-helper:models` veranlasst.
- Commit: `Add Laravel IDE Helper and ignore generated IDE metadata`.

## Setup-Übernahme und Gesamtprüfung

- Setup-Dateien gezielt aus `../LaravelAPI` in dieses Repository kopiert (`Copy-Item -LiteralPath`); das Referenzprojekt blieb unverändert. Übernommen: CLI-/Browser-Assistent, Administratoranlage, Prozessüberwachung, Sprachkatalog, Zugriffsschutz und zugehörige Tests.
- `composer setup` nutzt denselben STDIN-erhaltenden Callback wie die Referenz. `/setup.php` funktioniert vor `vendor/`/`.env`, benötigt expliziten Zugangsschlüssel und wird beim Abschluss gesperrt.
- API-Platform-Anpassungen: eigene Setup-Session, tatsächliche Abschlusslinks `/api`, `/api/docs`, `/admin`, `/up`, keine Referenz-Standard-API-Routen. Mailprüfung behauptet keine vorhandenen Verifizierungsendpunkte.
- Optionalen Asset-Schritt ergänzt: `npm ci --ignore-scripts`, `npm run build`; Node/npm werden ohne frei zusammengesetzte Shell-Befehle gestartet. Der API-Platform-React-Admin benötigt diesen Build.
- Prüfungen: `php vendor/phpunit/phpunit/phpunit --no-progress` (inklusive isoliertem CLI-Setup, Browser-Setup ohne Dependencies, Zugangsschutz, Formularschutz, SQLite, Administratorrechten, Sprachwahl und Fortschrittsstream), `npm run build`, Pint, Composer-Validierung und Routenprüfung.
- Tests migrieren ausschließlich die Testdatenbank (`:memory:`) oder isolierte temporäre Datenbanken; kein Setup auf der vorhandenen lokalen Anwendung ausgeführt und keine Zugangsdaten vergeben.
- Zusätzlicher Schutz gegen Verwechslung mit der Referenz: vorgeschlagener MySQL-/MariaDB-Datenbankname ist `laravel_api_platform`. Vorhandene explizite Konfiguration bleibt maßgeblich.
- Erster vollständiger Lauf: **59 Tests, 769 Assertions erfolgreich**; Diagnose-Wiederholung ebenfalls Exit-Code 0. `npm run build` erfolgreich (bestehende React/MUI-Directive-/Chunk- und optionale Fontaine-Hinweise). Pint korrigierte Imports/Klassennamen in fünf Dateien; anschließendes `php vendor/bin/pint --test` erfolgreich.
- `composer validate --strict`, `composer check-platform-reqs`, `git diff --check` erfolgreich. Referenz-Git-Status weiterhin ausschließlich die bereits vorher geänderte `PROJEKTDOKUMENTATION.md`.
- HTTP-Prüfungen im isolierten Browser-Test: `http://127.0.0.1:<freier-Port>/setup.php` (GET/POST und Fortschrittsstream). API-/Admin-/Runner-Routen zusätzlich über Laravels HTTP-Testclient geprüft.
- Abschließender Paketabgleich per PowerShell (`ConvertFrom-Json` für beide `composer.json` und Ziel-`vendor/composer/installed.json`): alle **21 direkten Referenzpakete** im richtigen Abschnitt deklariert und installiert; zwölf fehlende Erweiterungen jeweils separat committet. Versionen in `docs/ERWEITERUNGEN.md`.
- Routenbefehle: temporär nur für den Prüfprozess `$env:ARTISAN_RUNNER_UI_ENABLED='true'`, dann `php artisan route:list --path=artisan`, `--path=filament` und `--path=admin --except-vendor`. Ergebnis: sechs Runner-Routen, fünf Filament-Routen, unveränderter API-Platform-Admin. `--path=api --except-vendor` findet erwartungsgemäß keine eigenen Standard-API-Routen; die API Platform registriert ihre Routen über das Paket.
- Letzte Prüfung mit `$env:PAO_DISABLE='true'; composer test`: **60 Tests, 774 Assertions erfolgreich, ohne Warnungen**. Erwartete Verbindungsverweigerung beim Start des isolierten Testservers wird nur während der Bereitschaftsprüfung behandelt. Auch direkter npm-Aufruf über Node erfolgreich getestet. `php vendor/bin/pint --test` und `git diff --check` erfolgreich.
- Setup-Abschlusscommit: `Add API Platform aware CLI and browser setup with integration checks`. Git-Staging umfasst ausschließlich die genannten Projektänderungen; lokale Geheimnisse und generierte Assets bleiben ausgeschlossen. Kein Push ausgeführt.
