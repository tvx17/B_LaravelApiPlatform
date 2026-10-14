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
