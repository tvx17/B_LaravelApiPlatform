<?php

namespace App\Actions\Setup;

use App\Support\SetupProcess;
use App\Support\SetupRuntime;
use Closure;
use Dotenv\Dotenv;
use PDO;
use RuntimeException;
use SetupEnvironment;

final class RunAction
{
    public const STEPS = [
        'install' => 'Abhängigkeiten installieren',
        'environment' => '.env vorbereiten',
        'cache' => 'Konfigurations- und Routencache leeren',
        'key' => 'Anwendungsschlüssel vorbereiten',
        'database' => 'Datenbank konfigurieren',
        'cors' => 'API und CORS konfigurieren',
        'verification' => 'E-Mail-Verifizierung prüfen',
        'migrate' => 'Migrationen ausführen',
        'packages' => 'Erweiterungen vorbereiten',
        'admin' => 'Administratorkonto einrichten',
        'runner' => 'Artisan Runner aktivieren',
        'storage' => 'Storage-Link erstellen',
        'assets' => 'Frontend-Assets bauen',
        'brain' => 'Brain-Graph erzeugen',
        'finish' => 'Adressen und Abschluss',
    ];

    public function __construct(private readonly string $root, private readonly ?Closure $progress = null) {}

    public function environment(): array
    {
        if (! is_file($this->root.'/vendor/autoload.php')) {
            return [];
        }
        require_once $this->root.'/vendor/autoload.php';

        return is_file($this->root.'/.env') ? Dotenv::parse(file_get_contents($this->root.'/.env')) : [];
    }

    public function handle(string $step, array $data): string
    {
        if (! array_key_exists($step, self::STEPS)) {
            throw new RuntimeException('Unbekannter Einrichtungsschritt.');
        }
        $this->report(self::STEPS[$step]);
        if ($step === 'install') {
            $this->process(SetupRuntime::composer());

            return 'Composer-Abhängigkeiten installiert. Weiter mit .env.';
        }
        $this->dependencies();
        if ($step === 'environment') {
            if (is_file($this->root.'/.env')) {
                return 'Vorhandene .env bleibt erhalten.';
            }
            // Exclusive creation prevents an existing environment from being overwritten.
            $file = fopen($this->root.'/.env', 'x');
            if ($file === false) {
                throw new RuntimeException('.env konnte nicht erstellt werden.');
            }
            $template = file_get_contents($this->root.'/.env.example');
            $written = fwrite($file, $template);
            fclose($file);
            chmod($this->root.'/.env', 0600);
            if ($written !== strlen($template)) {
                throw new RuntimeException('.env wurde nicht vollständig geschrieben. Datei prüfen.');
            }

            return '.env aus der sicheren Projektvorlage erstellt.';
        }
        if (! is_file($this->root.'/.env')) {
            throw new RuntimeException('Bitte zuerst .env vorbereiten.');
        }
        if ($step === 'cache') {
            $this->artisan('config:clear');
            $this->artisan('route:clear');

            return 'Konfigurations- und Routencache geleert.';
        }
        if (is_file($this->root.'/bootstrap/cache/config.php')) {
            throw new RuntimeException('Bitte zuerst den Konfigurationscache leeren.');
        }
        $env = $this->environment();

        return match ($step) {
            'key' => $this->key($env),
            'database' => $this->database($data),
            'cors' => $this->cors($data),
            'verification' => 'Mailzustellung und öffentliche API-Adresse prüfen; keine Testmail versandt. Verifizierungsendpunkte sind noch nicht eingerichtet.',
            'migrate' => $this->commands(['migrate'], 'Migrationen abgeschlossen.'),
            'packages' => $this->commands(['package:discover', 'filament:upgrade', 'permission:cache-reset'], 'Pakete, Filament-Assets und Permission-Cache vorbereitet.'),
            'admin' => $this->admin($data),
            'runner' => $this->enableRunner(),
            'storage' => $this->commands(['storage:link'], 'Öffentlicher Storage-Link erstellt.'),
            'assets' => $this->assets(),
            'brain' => $this->brain($env),
            'finish' => $this->finish(),
        };
    }

    private function dependencies(): void
    {
        if (! is_file($this->root.'/vendor/autoload.php')) {
            throw new RuntimeException('Bitte zuerst Composer-Abhängigkeiten installieren oder vendor/ vollständig hochladen.');
        }
        require_once $this->root.'/vendor/autoload.php';
        require_once $this->root.'/scripts/SetupEnvironment.php';
    }

    private function save(array $values): void
    {
        SetupEnvironment::write($this->root.'/.env', $values);
        $this->artisan('config:clear');
    }

    private function key(array $environment): string
    {
        if (! empty($environment['APP_KEY'])) {
            return 'Vorhandener Anwendungsschlüssel bleibt erhalten.';
        }
        $this->save(['APP_KEY' => 'base64:'.base64_encode(random_bytes(32))]);

        return 'Neuer Anwendungsschlüssel gespeichert.';
    }

    private function database(array $data): string
    {
        $driver = $data['driver'] ?? '';
        if (! in_array($driver, ['sqlite', 'mariadb', 'mysql'], true) || ! in_array($data['mode'] ?? '', ['new', 'existing'], true)) {
            throw new RuntimeException('Datenbanktyp und vorhandene/neue Datenbank auswählen.');
        }
        $new = $data['mode'] === 'new';
        if ($driver === 'sqlite') {
            if (! extension_loaded('pdo_sqlite')) {
                throw new RuntimeException('PHP-Erweiterung pdo_sqlite fehlt.');
            }
            $path = trim($data['path'] ?? '');
            if ($path === '' || strpbrk($path, "\r\n\0") !== false || str_contains($path, '://')) {
                throw new RuntimeException('Eine SQLite-Datei angeben.');
            }
            if (! preg_match('~^(?:[A-Za-z]:[/\\\\]|/)~', $path)) {
                $path = $this->root.'/'.$path;
            }
            // Browser requests may only create SQLite files inside database/.
            $parent = realpath(dirname($path));
            $database = realpath($this->root.'/database');
            $normalized = str_replace('\\', '/', $parent ?: '');
            $allowed = str_replace('\\', '/', $database ?: '');
            if (PHP_OS_FAMILY === 'Windows') {
                $normalized = strtolower($normalized);
                $allowed = strtolower($allowed);
            }
            if (! $parent || ! $database || ($normalized !== $allowed && ! str_starts_with($normalized, $allowed.'/')) || ! str_ends_with(strtolower($path), '.sqlite') || is_link($path)) {
                throw new RuntimeException('Im Browser sind nur .sqlite-Dateien innerhalb von database/ erlaubt; Unterverzeichnisse müssen existieren.');
            }
            if ($new) {
                if (file_exists($path)) {
                    throw new RuntimeException('SQLite-Datei existiert bereits. Vorhandene Datenbank auswählen.');
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
            $values = ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $path, 'DB_URL' => ''];
        } else {
            if (! extension_loaded('pdo_mysql')) {
                throw new RuntimeException('PHP-Erweiterung pdo_mysql fehlt.');
            }
            $host = trim($data['host'] ?? '');
            $port = $data['port'] ?? '';
            $name = trim($data['database'] ?? '');
            if (! preg_match('/^[A-Za-z0-9_.:\-]+$/', $host) || ! ctype_digit($port) || (int) $port < 1 || (int) $port > 65535 || ! preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                throw new RuntimeException('Datenbankhost, Port oder Name ungültig.');
            }
            $username = $data['username'] ?? '';
            $password = $data['password'] ?? '';
            $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
            if ($new) {
                $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
            $pdo = new PDO($dsn.";dbname=$name", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $values = ['DB_CONNECTION' => $driver, 'DB_HOST' => $host, 'DB_PORT' => $port, 'DB_DATABASE' => $name, 'DB_USERNAME' => $username, 'DB_PASSWORD' => $password, 'DB_URL' => '', 'DB_SOCKET' => ''];
        }
        $pdo->query('SELECT 1');
        $this->save($values);

        return 'Datenbankverbindung geprüft und gespeichert. Bereits angelegte Datenbanken werden bei späteren Fehlern nicht gelöscht.';
    }

    private function cors(array $data): string
    {
        $api = rtrim(trim($data['api'] ?? ''), '/');
        $frontend = rtrim(trim($data['frontend'] ?? ''), '/');
        foreach ([$api, $frontend] as $url) {
            $parts = parse_url($url);
            if (! $parts || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host']) || isset($parts['pass']) || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment']) || ! empty($parts['path'])) {
                throw new RuntimeException('HTTP(S)-Origin ohne Pfad oder Zugangsdaten angeben.');
            }
        }
        $apiParts = parse_url($api);
        $frontendParts = parse_url($frontend);
        if ($apiParts['host'] !== $frontendParts['host'] || $apiParts['scheme'] !== $frontendParts['scheme']) {
            throw new RuntimeException('API und Frontend benötigen für diesen Session-Ablauf denselben Host und dasselbe Schema.');
        }
        $this->save(['APP_URL' => $api, 'CORS_ALLOWED_ORIGINS' => $frontend, 'SANCTUM_STATEFUL_DOMAINS' => $frontendParts['host'].(isset($frontendParts['port']) ? ':'.$frontendParts['port'] : ''), 'SESSION_SECURE_COOKIE' => $apiParts['scheme'] === 'https' ? 'true' : 'false']);

        return 'API-Adresse, CORS und Session-Cookies konfiguriert. Frontend muss Credentials und CSRF mitsenden.';
    }

    private function admin(array $data): string
    {
        // A fresh CLI process loads the newly selected database and key.
        $this->process([$this->php(), $this->root.'/scripts/SetupWebAdmin.php'], json_encode($data, JSON_THROW_ON_ERROR));

        return 'Administratorkonto eingerichtet; vorhandener Name und Passwort wurden erhalten.';
    }

    private function enableRunner(): string
    {
        $this->save(['ARTISAN_RUNNER_UI_ENABLED' => 'true']);

        return 'Artisan Runner aktiviert; Anmeldung und Runner-Recht sind erforderlich.';
    }

    private function assets(): string
    {
        $this->process(SetupRuntime::npm('ci', '--ignore-scripts'));
        $this->process(SetupRuntime::npm('run', 'build'));

        return 'Frontend-Assets für API Platform Admin gebaut.';
    }

    private function brain(array $environment): string
    {
        if (($environment['APP_ENV'] ?? 'local') !== 'local') {
            throw new RuntimeException('Brain-Scan ist nur in APP_ENV=local verfügbar.');
        }

        return $this->commands(['brain:scan'], 'Brain-Graph erzeugt.');
    }

    private function finish(): string
    {
        $file = fopen($this->root.'/.setup-complete', 'x');
        if ($file === false) {
            throw new RuntimeException('Abschlusssperre konnte nicht angelegt werden.');
        }
        fwrite($file, date(DATE_ATOM));
        fclose($file);
        if (is_file($this->root.'/.setup-access')) {
            unlink($this->root.'/.setup-access');
        }

        return 'Browser-Einrichtung abgeschlossen und gesperrt. Anwendung über die angezeigten Adressen öffnen.';
    }

    private function commands(array $commands, string $message): string
    {
        foreach ($commands as $command) {
            $this->artisan($command);
        }

        return $message;
    }

    private function artisan(string $command): void
    {
        $arguments = [$this->php(), $this->root.'/artisan', $command, '--no-interaction'];
        if ($command === 'migrate') {
            $arguments[] = '--force';
        }
        $this->process($arguments);
    }

    private function php(): string
    {
        return SetupRuntime::php();
    }

    private function process(array $arguments, string $input = ''): void
    {
        if (! function_exists('proc_open')) {
            throw new RuntimeException('Das Hosting deaktiviert proc_open. Installation und Artisan-Schritte benötigen diese PHP-Funktion.');
        }
        // Temporary streams avoid pipe deadlocks and never expose subprocess output or passwords.
        $stdin = tmpfile();
        $output = tmpfile();
        $errors = tmpfile();
        if (! $stdin || ! $output || ! $errors) {
            throw new RuntimeException('Temporäre Prozessdateien konnten nicht erstellt werden.');
        }
        try {
            fwrite($stdin, $input);
            rewind($stdin);
            $process = proc_open($arguments, [$stdin, $output, $errors], $pipes, $this->root);
            if (! is_resource($process)) {
                throw new RuntimeException('Einrichtungsprozess konnte nicht gestartet werden. Prozessrechte prüfen.');
            }
            $label = str_ends_with($arguments[1] ?? '', 'npm-cli.js') ? 'Frontend-Assets bauen' : (in_array($arguments[2] ?? '', ['install', 'config:clear', 'route:clear', 'migrate', 'package:discover', 'filament:upgrade', 'permission:cache-reset', 'storage:link', 'brain:scan'], true) ? $arguments[2] : 'Administratorkonto');
            $bytes = 0;
            $lastOutput = microtime(true);
            $exit = SetupProcess::wait($process, function () use ($label, $output, $errors, &$bytes, &$lastOutput): void {
                $size = (fstat($output)['size'] ?? 0) + (fstat($errors)['size'] ?? 0);
                if ($size > $bytes) {
                    $bytes = $size;
                    $lastOutput = microtime(true);
                }
                $this->report($label.' läuft', (int) (microtime(true) - $lastOutput));
            });
            if ($exit !== 0) {
                rewind($output);
                rewind($errors);
                $diagnosis = SetupRuntime::failure(stream_get_contents($output).stream_get_contents($errors));
                throw new RuntimeException($label.' fehlgeschlagen (Exit-Code '.$exit.'). '.$diagnosis);
            }
            $this->report($label.' abgeschlossen');
        } finally {
            fclose($stdin);
            fclose($output);
            fclose($errors);
        }
    }

    private function report(string $message, ?int $silentSeconds = null): void
    {
        if ($this->progress) {
            ($this->progress)(['message' => $message, 'silent_seconds' => $silentSeconds]);
        }
    }
}
