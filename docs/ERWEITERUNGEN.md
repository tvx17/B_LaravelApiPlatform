# Erweiterungen und Vergleich mit LaravelAPI

Referenz: `../LaravelAPI`, geprüft am 14.10.2026. Verglichen werden direkte Composer-Abhängigkeiten; transitive Bibliotheken installiert Composer automatisch. Dieses Projekt behält zusätzlich `api-platform/laravel` 5.x, dessen API-Routen, JSON:API und Problem-JSON.

| Erweiterung | Vor Übernahme | Jetzt installiert | Verwendung |
| --- | --- | --- | --- |
| spatie/laravel-permission | fehlte | 8.3.0 | Rollen, Rechte, User-Trait, Middleware, Migration |
| laravel/sanctum | fehlte | 4.3.3 | API-Tokens und Stateful-Authentifizierung |
| filament/filament | fehlte | 5.10.2 | Panel `/filament`, Recht `access-admin-panel` |
| codesquirrel/artisan-runner-ui | fehlte | 1.0.1 | `/artisan`, Recht `run-artisan-runner-ui`, explizite Aktivierung |
| spatie/laravel-medialibrary | fehlte | 11.23.9 | Konfiguration und Media-Migration; Modelle/Uploads später ergänzen |
| laravel/socialite | fehlte | 5.31.0 | OAuth-Grundlage; Anbieter und Zugangsdaten separat konfigurieren |
| laravel/fortify | fehlte | 1.41.0 | Authentifizierungsbausteine; automatische Routen wie in Referenz deaktiviert |
| laravel/telescope | fehlte | 5.26.0 | Entwicklungsabhängigkeit, ausschließlich lokal registriert |
| laramint/laravel-brain | fehlte | 2.7.1 | Entwicklungsabhängigkeit, lokaler Architekturgraph |
| cviebrock/eloquent-sluggable | fehlte | 13.0.1 | Konfiguration; Trait und Slug-Spalte je Fachmodell ergänzen |
| kitloong/laravel-migrations-generator | fehlte | 7.4.0 | Entwicklungsabhängigkeit; bewusster Schemaexport per CLI |
| barryvdh/laravel-ide-helper | fehlte | 3.7.0 | Entwicklungsabhängigkeit; generierte IDE-Dateien bleiben lokal |

Bereits vorhanden: Laravel Framework, Tinker, Faker, Pail, Pao, Pint, Mockery, Collision und PHPUnit. Pail benötigt PCNTL und läuft nicht nativ unter Windows. Es wird durch das Setup nicht gestartet.

Abschlussvergleich gegen `vendor/composer/installed.json`: alle 21 direkten PHP-Pakete der Referenz sind hier installiert und im gleichen Laufzeit-/Entwicklungsabschnitt deklariert. API Platform bleibt zusätzlich vorhanden. Composer löste kompatible Versionen für das bestehende Lockfile auf; Filament/Telescope sind hier etwas neuer als in der Referenz. Keine gemeinsamen Basisabhängigkeiten wurden dafür gezielt aktualisiert. Jede der zwölf Erweiterungen besitzt einen eigenen Git-Commit, die Setup-Übernahme einen zusätzlichen.

## API-Platform-spezifische Abgrenzung

`/admin` bleibt API Platform Admin; Filament verwendet `/filament`. Keine Standard-API-Controller, Response-Envelopes oder globalen Exception-Umschreibungen aus der Referenz übernehmen. Neue Ressourcen benötigen API-Platform-Metadaten, Provider/Processor und eine bewusste Zugriffspolitik. Die Installation von Sanctum veröffentlicht keine Benutzer automatisch als API-Ressourcen.

## Quellen

- https://api-platform.com/docs/laravel/
- https://filamentphp.com/docs/5.x/introduction/installation
- https://laravel.com/docs/13.x/sanctum
- https://laravel.com/docs/13.x/fortify
- https://laravel.com/docs/13.x/telescope
- https://laravel.com/docs/13.x/socialite
- https://github.com/reyesorlan/artisan-runner-ui
- https://spatie.be/docs/laravel-permission/
- https://spatie.be/docs/laravel-medialibrary/v11/installation-setup
- https://github.com/laramint/laravel-brain
- https://github.com/cviebrock/eloquent-sluggable
- https://github.com/kitloong/laravel-migrations-generator
- https://github.com/barryvdh/laravel-ide-helper

Bei nicht abrufbaren Webseiten zusätzlich README, Composer-Anforderungen und Service Provider der lokal installierten Pakete prüfen.
