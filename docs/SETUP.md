# Einrichtung von Laravel API Platform

## Start und Voraussetzungen

Im Projektverzeichnis `composer setup` oder direkt `php scripts/Setup.php` ausführen. Der Assistent funktioniert auch ohne `vendor/` und `.env`. Benötigt werden PHP ab 8.3, Composer, PDO SQLite oder PDO MySQL und ein interaktives Terminal. Für den API-Platform-Admin zusätzlich Node.js mit npm; alternativ fertig gebaute Assets hochladen.

Jeder CLI-Schritt fragt nach Zustimmung; Enter lehnt ab. Fehler stoppen den Ablauf. Bestehende Schlüssel, SQLite-Dateien und Benutzerpasswörter bleiben erhalten. Migrationen verwenden weder `fresh` noch `reset`. Vor Zustimmung das angezeigte Datenbankziel prüfen; eine eigene Datenbank verwenden, insbesondere nicht die Datenbank von `LaravelAPI`.

## Ablauf

1. Abhängigkeiten aus `composer.lock` installieren (`--no-scripts`), `.env` aus `.env.example` anlegen und Caches leeren.
2. Fehlenden Anwendungsschlüssel erzeugen; vorhandenen Schlüssel behalten.
3. SQLite, MariaDB oder MySQL wählen, neue oder vorhandene Datenbank verbinden und Einstellungen speichern.
4. API-URL und Frontend-Origin einstellen. Für Session-Cookies denselben Host und dasselbe Schema verwenden; unterschiedliche Ports sind möglich. Frontend muss Cookies, Credentials und CSRF mitsenden.
5. Mailkonfiguration prüfen. Dieses Projekt veröffentlicht noch keine E-Mail-Verifizierungsendpunkte. `MAIL_MAILER=log` versendet keine echte Mail.
6. Migrationen ausführen, Pakete registrieren, Filament-Assets veröffentlichen und Permission-Cache zurücksetzen.
7. Optional Administrator anlegen oder bestehendem Konto `access-admin-panel` und `run-artisan-runner-ui` geben. Separat: `php artisan setup:admin`. Neue Passwörter werden verdeckt eingegeben und benötigen mindestens zwölf Zeichen.
8. Runner aktivieren, optional `storage:link` erstellen, mit `npm ci --ignore-scripts` und `npm run build` die Admin-Assets bauen und lokal `brain:scan` ausführen.

Server separat starten: `php artisan serve --host=localhost --port=8000`. HTTPS benötigt einen entsprechend konfigurierten Webserver. Der Assistent startet weder Server noch Queue.

## Adressen

| Pfad | Zweck |
| --- | --- |
| `/api` | API Platform Einstieg |
| `/api/docs` | API-Platform-Dokumentation / OpenAPI |
| `/admin` | API Platform Admin (Vite-Build erforderlich) |
| `/filament/login` | Filament-Anmeldung |
| `/artisan/login` | Runner-Anmeldung |
| `/artisan` | Runner, nach expliziter Aktivierung und Rechtevergabe |
| `/sanctum/csrf-cookie` | Sanctum-CSRF-Cookie |
| `/up` | Health-Endpunkt |
| `/telescope`, `/_laravel-brain` | Nur lokale Entwicklungsumgebung |

Runner ist standardmäßig deaktiviert (`ARTISAN_RUNNER_UI_ENABLED=false`) und in Produktion gesperrt. Anmeldung allein verleiht keine Befehlsrechte. Filament verwendet eigene Routen; `/admin` bleibt API Platform. Fortifys automatische Routen sind wie in der Referenz deaktiviert. Standard-API-Endpunkte wie `/api/user` werden nicht hinzugefügt.

## Browser-Setup ohne Terminalzugang

Document Root auf `public/` setzen. Im Projektverzeichnis `.setup-access` mit einem eigenen zufälligen Schlüssel von mindestens 32 Zeichen anlegen und `/setup.php` öffnen, beispielsweise `http://localhost:8000/setup.php`. Externer Zugriff erfordert HTTPS. Der Assistent benötigt kein Laravel-Bootstrap und funktioniert vor der Installation. Englisch, Deutsch, Französisch, Italienisch und Spanisch stehen zur Auswahl.

Jeden Schritt ausdrücklich ausführen oder überspringen. „Abschließen und sperren“ legt `.setup-complete` an und entfernt `.setup-access`. Zugangsschlüssel, Formularschutz, einstündige Sitzung, Login-Drosselung und Prozesssperre schützen den Ablauf. Passwörter werden nicht als CLI-Argumente übergeben, rohe Prozessausgaben nicht im Browser angezeigt. Fortschrittsmeldungen zeigen Laufzeit und Aktivität; nach Verbindungsabbruch keinen zweiten Vorgang starten, solange der erste läuft.

Server benötigt `proc_open`, PHP-CLI, Composer, Schreibrechte und für den optionalen Asset-Build Node.js/npm im PATH. Bei Bedarf serverseitig `SETUP_PHP_BINARY` und `COMPOSER_BINARY` auf vorhandene Programme setzen. Hosting-Zeitlimits können Installationen abbrechen. Browser-SQLite-Dateien sind auf `database/` begrenzt. Ohne Node.js den Build separat ausführen und `public/build` bereitstellen. Für eine bewusst gewollte erneute Einrichtung Abschlusssperre entfernen und neuen Zugangsschlüssel anlegen.

## Weitere Pakete und Prüfungen

Socialite benötigt einen OAuth-Anbieter. Media Library benötigt ein geeignetes Modell und Upload-Endpunkte. Sluggable braucht Trait, Quellattribut und Slug-Spalte je Fachmodell. IDE Helper und Migrations Generator werden nicht automatisch ausgeführt:

```powershell
php artisan ide-helper:generate --no-interaction
php artisan ide-helper:models --nowrite --no-interaction
php -d memory_limit=512M artisan ide-helper:meta --no-interaction
php artisan migrate:generate --path=database/schema-export --skip-log --no-interaction
php vendor/phpunit/phpunit/phpunit
php vendor/bin/pint --test
```

Modellmetadaten und Schemaexporte benötigen eine erreichbare Datenbank; exportierte Migrationen vor Verwendung prüfen. Entwicklungspakete fehlen bei `composer install --no-dev`. `.env`, SQLite-Dateien, Setup-Schlüssel und generierte IDE-/Brain-Dateien bleiben außerhalb von Git. Kopia ist keine Composer-Erweiterung und wird nicht aus der Referenz übernommen. Pail benötigt PCNTL und läuft nicht nativ unter Windows.
