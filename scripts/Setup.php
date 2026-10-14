<?php

use App\Support\SetupProcess;
use App\Support\SetupRuntime;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Question\Question;

// Standalone entry point: works before vendor/ or .env exist.
chdir(dirname(__DIR__));
require_once __DIR__.'/../app/Support/SetupRuntime.php';
require_once __DIR__.'/../app/Support/SetupProcess.php';

function setupConfirm(string $question): bool
{
    echo $question.' [j/N]: ';
    $answer = fgets(STDIN);
    if ($answer === false) {
        throw new RuntimeException('Interaktive Eingabe fehlt. Setup abgebrochen.');
    }

    return in_array(strtolower(trim($answer)), ['j', 'ja', 'y', 'yes'], true);
}

function setupRun(array $arguments): void
{
    $label = $arguments[2] ?? 'Einrichtung';
    $label = in_array($label, ['install', 'config:clear', 'route:clear', 'migrate', 'package:discover', 'filament:upgrade', 'permission:cache-reset', 'setup:admin', 'storage:link', 'brain:scan'], true) ? $label : 'Einrichtung';
    $start = microtime(true);
    echo '[Start] '.$label.PHP_EOL;
    $process = proc_open($arguments, [STDIN, STDOUT, STDERR], $pipes);
    if (! is_resource($process)) {
        throw new RuntimeException('Einrichtungsprozess konnte nicht gestartet werden.');
    }
    $exit = SetupProcess::wait($process, function () use ($label, $start): void {
        if ($label !== 'setup:admin') {
            echo '[Aktiv seit '.(int) (microtime(true) - $start).' s] '.$label.' läuft; auf Prozessabschluss warten.'.PHP_EOL;
        }
    }, 5);
    if ($exit !== 0) {
        throw new RuntimeException('Ein Einrichtungsschritt ist fehlgeschlagen. Keine weiteren Schritte ausgeführt.');
    }
    echo '[Fertig nach '.(int) (microtime(true) - $start).' s] '.$label.PHP_EOL;
}

try {
    if (PHP_VERSION_ID < 80300) {
        throw new RuntimeException('PHP 8.3 oder neuer erforderlich.');
    }
    echo "Laravel API Platform-Einrichtung (Windows/Linux/macOS). Alle Schritte sind optional und werden bestätigt.\n";
    if (setupConfirm('Composer-Abhängigkeiten aus composer.lock installieren (inklusive Entwicklungspakete, ohne Setup-Hooks)?')) {
        setupRun(SetupRuntime::composer());
    }
    if (! is_file('vendor/autoload.php')) {
        throw new RuntimeException('Abhängigkeiten fehlen. Composer installieren bzw. Installationsschritt bestätigen.');
    }
    require 'vendor/autoload.php';
    require __DIR__.'/SetupEnvironment.php';
    $input = new ArgvInput;
    $output = new ConsoleOutput;
    $helper = new QuestionHelper;
    $ask = function (string $label, ?string $default = null, bool $hidden = false) use ($input, $output, $helper): string {
        $question = new Question($label.($default !== null ? " [$default]" : '').': ', $default);
        $question->setHidden($hidden);
        $question->setHiddenFallback(false);
        $value = $helper->ask($input, $output, $question);

        return (string) $value;
    };
    $environment = is_file('.env') ? Dotenv\Dotenv::parse(file_get_contents('.env')) : [];
    if (! is_file('.env') && setupConfirm('.env aus der sicheren Projektvorlage erstellen?')) {
        if (! copy('.env.example', '.env')) {
            throw new RuntimeException('.env konnte nicht erstellt werden.');
        }
        chmod('.env', 0600);
        $environment = Dotenv\Dotenv::parse(file_get_contents('.env'));
    }
    if (! is_file('.env')) {
        throw new RuntimeException('Ohne .env keine weitere Einrichtung möglich.');
    }
    $save = function (array $values) use (&$environment): void {
        SetupEnvironment::write('.env', $values);
        $environment = array_replace($environment, $values);
        setupRun([PHP_BINARY, 'artisan', 'config:clear', '--no-interaction']);
    };
    if (setupConfirm('Konfigurations-/Routencache leeren, damit lokale Einstellungen verwendet werden?')) {
        setupRun([PHP_BINARY, 'artisan', 'config:clear', '--no-interaction']);
        setupRun([PHP_BINARY, 'artisan', 'route:clear', '--no-interaction']);
    } elseif (is_file('bootstrap/cache/config.php')) {
        throw new RuntimeException('Konfigurationscache aktiv. Erst Cache-Freigabe bestätigen.');
    }
    if (empty($environment['APP_KEY'])) {
        if (setupConfirm('Neuen Anwendungsschlüssel erzeugen und lokal speichern?')) {
            $save(['APP_KEY' => 'base64:'.base64_encode(random_bytes(32))]);
        } else {
            throw new RuntimeException('Ein Anwendungsschlüssel wird für Sessions benötigt.');
        }
    } else {
        echo "Vorhandener Anwendungsschlüssel bleibt erhalten.\n";
    }
    if (setupConfirm('Datenbank konfigurieren (bestehende Einstellungen werden gezielt ersetzt)?')) {
        $driver = strtolower(trim($ask('Datenbank: sqlite, mariadb oder mysql', $environment['DB_CONNECTION'] ?? 'sqlite')));
        if (! in_array($driver, ['sqlite', 'mariadb', 'mysql'], true)) {
            throw new RuntimeException('Unbekannte Datenbankauswahl.');
        }
        $new = setupConfirm('Neue Datenbank anlegen? Nein = vorhandene Datenbank verwenden');
        if ($driver === 'sqlite') {
            if (! extension_loaded('pdo_sqlite')) {
                throw new RuntimeException('PHP-Erweiterung pdo_sqlite fehlt.');
            }
            $path = $ask('SQLite-Datei', ($environment['DB_CONNECTION'] ?? 'sqlite') === 'sqlite' ? ($environment['DB_DATABASE'] ?? getcwd().'/database/database.sqlite') : getcwd().'/database/database.sqlite');
            if ($new) {
                if (file_exists($path)) {
                    throw new RuntimeException('Datei existiert bereits; sie wird nicht überschrieben. Vorhandene Datenbank wählen.');
                }
                if (! is_dir(dirname($path))) {
                    throw new RuntimeException('Zielverzeichnis fehlt; bitte zuerst anlegen.');
                }
                $file = fopen($path, 'x');
                if ($file === false) {
                    throw new RuntimeException('SQLite-Datei konnte nicht angelegt werden.');
                }
                fclose($file);
            } elseif (! is_file($path)) {
                throw new RuntimeException('Vorhandene SQLite-Datei nicht gefunden.');
            }
            $path = str_replace('\\', '/', realpath($path));
            $pdo = new PDO('sqlite:'.$path);
            $values = ['DB_CONNECTION' => $driver, 'DB_DATABASE' => $path, 'DB_URL' => ''];
        } else {
            if (! extension_loaded('pdo_mysql')) {
                throw new RuntimeException('PHP-Erweiterung pdo_mysql fehlt.');
            }
            $host = $ask('Datenbankhost', $environment['DB_HOST'] ?? '127.0.0.1');
            $port = $ask('Port', $environment['DB_PORT'] ?? '3306');
            $name = $ask('Datenbankname', ($environment['DB_CONNECTION'] ?? 'sqlite') === 'sqlite' ? 'laravel_api_platform' : ($environment['DB_DATABASE'] ?? 'laravel_api_platform'));
            if (! preg_match('/^[A-Za-z0-9_]+$/', $name) || ! ctype_digit($port) || strpbrk($host, ';') !== false) {
                throw new RuntimeException('Ungültiger Datenbankname, Host oder Port.');
            }
            $username = $ask('Benutzer', $environment['DB_USERNAME'] ?? 'root');
            $password = $ask('Passwort (verdeckt; leer ist erlaubt)', null, true);
            $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
            if ($new) {
                $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
            $pdo = new PDO($dsn.";dbname=$name", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $values = ['DB_CONNECTION' => $driver, 'DB_HOST' => $host, 'DB_PORT' => $port, 'DB_DATABASE' => $name, 'DB_USERNAME' => $username, 'DB_PASSWORD' => $password, 'DB_URL' => '', 'DB_SOCKET' => ''];
        }
        $pdo->query('SELECT 1');
        if (setupConfirm('Geprüfte Datenbankverbindung in .env übernehmen und Konfigurationscache leeren?')) {
            $save($values);
        }
    }
    echo "CORS: Frontend-Origin inklusive Schema/Port; API und SPA für Cookies auf demselben Host betreiben.\n";
    if (setupConfirm('API-Adresse und CORS/Stateful-Frontend konfigurieren?')) {
        $api = rtrim($ask('API-URL', $environment['APP_URL'] ?? 'http://localhost:8000'), '/');
        $frontend = rtrim($ask('Frontend-Origin', $environment['CORS_ALLOWED_ORIGINS'] ?? 'http://localhost:9200'), '/');
        foreach ([$api, $frontend] as $url) {
            $parts = parse_url($url);
            if (! $parts || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || ! empty($parts['path'])) {
                throw new RuntimeException('Eine HTTP(S)-Origin ohne Pfad oder Zugangsdaten angeben.');
            }
        }
        if (parse_url($api, PHP_URL_HOST) !== parse_url($frontend, PHP_URL_HOST) || parse_url($api, PHP_URL_SCHEME) !== parse_url($frontend, PHP_URL_SCHEME)) {
            throw new RuntimeException('Dieses Session-Setup benötigt für API und Frontend denselben Host und dasselbe Schema.');
        }
        $frontendParts = parse_url($frontend);
        $save(['APP_URL' => $api, 'CORS_ALLOWED_ORIGINS' => $frontend, 'SANCTUM_STATEFUL_DOMAINS' => $frontendParts['host'].(isset($frontendParts['port']) ? ':'.$frontendParts['port'] : ''), 'SESSION_SECURE_COOKIE' => parse_url($api, PHP_URL_SCHEME) === 'https' ? 'true' : 'false']);
    }
    if (setupConfirm('Hinweise zur nativen E-Mail-Verifizierung und Mailzustellung anzeigen?')) {
        echo "Laravel unterstützt E-Mail-Verifizierung; dieses Boilerplate veröffentlicht noch keine Verifizierungsendpunkte.\n";
        echo "MAIL_MAILER=log schreibt nur ins Laravel-Log. Für echte Zustellung SMTP/Maildienst in .env konfigurieren.\n";
        echo "APP_URL auf die öffentliche API-Adresse setzen; AUTH_VERIFICATION_EXPIRE bestimmt Linkdauer (Standard 60 Minuten).\n";
        echo "Bestätigungslinks benötigen Anmeldung desselben Benutzers. Konten bleiben unverändert; keine Testmail wird versandt.\n";
    }
    echo 'Migration-Ziel laut .env: '.($environment['DB_CONNECTION'] ?? 'sqlite').' / '.($environment['DB_DATABASE'] ?? 'database/database.sqlite').PHP_EOL;
    if (setupConfirm('Migrationen auf der aktuell konfigurierten Datenbank ausführen (bestehende Daten behalten)?')) {
        setupRun([PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction']);
    }
    if (setupConfirm('Pakete registrieren, Filament-Assets veröffentlichen und Permission-Cache zurücksetzen?')) {
        setupRun([PHP_BINARY, 'artisan', 'package:discover', '--no-interaction']);
        setupRun([PHP_BINARY, 'artisan', 'filament:upgrade', '--no-interaction']);
        setupRun([PHP_BINARY, 'artisan', 'permission:cache-reset', '--no-interaction']);
    }
    if (setupConfirm('Optionales Administratorkonto für Filament und Runner einrichten?')) {
        setupRun([PHP_BINARY, 'artisan', 'setup:admin']);
    }
    if (setupConfirm('Artisan Runner UI lokal aktivieren?')) {
        $save(['ARTISAN_RUNNER_UI_ENABLED' => 'true']);
    }
    if (setupConfirm('Öffentlichen Storage-Link für zukünftige Media-Dateien erstellen?')) {
        setupRun([PHP_BINARY, 'artisan', 'storage:link', '--no-interaction']);
    }
    if (setupConfirm('Frontend-Abhängigkeiten aus package-lock.json installieren und API-Platform-Admin-Assets bauen (Node.js/npm erforderlich)?')) {
        setupRun(SetupRuntime::npm('ci', '--ignore-scripts'));
        setupRun(SetupRuntime::npm('run', 'build'));
    }
    if (($environment['APP_ENV'] ?? 'local') === 'local' && setupConfirm('Laravel Brain scannen und Architekturgraph erzeugen?')) {
        setupRun([PHP_BINARY, 'artisan', 'brain:scan', '--no-interaction']);
    }
    $base = rtrim($environment['APP_URL'] ?? 'http://localhost:8000', '/');
    echo "\nAusgewählte Schritte abgeschlossen; ausgelassene Schritte wurden nicht ausgeführt.\n";
    if (parse_url($base, PHP_URL_SCHEME) === 'https') {
        echo "HTTPS erfordert einen konfigurierten Webserver/Proxy mit TLS; artisan serve liefert selbst kein HTTPS.\n";
    } else {
        echo 'Server separat starten: php artisan serve --host='.parse_url($base, PHP_URL_HOST).' --port='.(parse_url($base, PHP_URL_PORT) ?? 80)."\n";
    }
    echo "Adressübersicht laut Konfiguration; Verfügbarkeit hängt von den bestätigten Schritten ab:\n";
    foreach (['/api', '/api/docs', '/admin', '/up', '/sanctum/csrf-cookie', '/filament/login', '/artisan/login', '/artisan', '/telescope (nur local)', '/_laravel-brain (nur local)'] as $path) {
        echo $base.$path.PHP_EOL;
    }
    echo 'Frontend: '.($environment['CORS_ALLOWED_ORIGINS'] ?? 'http://localhost:9200')."\nCookies/CSRF mitsenden; Details: README.md. Server/Queue/Backup nicht automatisch gestartet.\n";
} catch (PDOException $exception) {
    fwrite(STDERR, 'Datenbankverbindung/-anlage fehlgeschlagen (SQLSTATE '.$exception->getCode()."). Zugang/Rechte prüfen.\n");
    exit(1);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}
