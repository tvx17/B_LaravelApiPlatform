<?php

namespace Tests\Unit;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

class BrowserSetupTest extends TestCase
{
    public function test_browser_setup_before_dependencies_guards_changes_and_configures_an_isolated_checkout(): void
    {
        $source = dirname(__DIR__, 2);
        $root = sys_get_temp_dir().'/browser-setup-'.bin2hex(random_bytes(8));
        mkdir($root);
        $files = ['public/setup.php', 'public/setup-progress.js', 'app/Support/SetupAccess.php', 'app/Support/SetupRuntime.php', 'app/Support/SetupProcess.php', 'app/Support/SetupLocale.php', 'app/Support/SetupOrigin.php', 'app/Actions/Setup/IndexAction.php', 'app/Actions/Setup/RunAction.php', 'resources/views/setup/wizard.php', 'resources/setup/translations.json', 'scripts/SetupEnvironment.php', '.env.example'];
        foreach ($files as $file) {
            if (! is_dir(dirname($root.'/'.$file))) {
                mkdir(dirname($root.'/'.$file), 0777, true);
            }
            copy($source.'/'.$file, $root.'/'.$file);
        }
        mkdir($root.'/database');
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $this->assertIsResource($socket, $error);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $log = tmpfile();
        $process = proc_open([PHP_BINARY, '-S', $address, '-t', $root.'/public'], [['pipe', 'r'], $log, $log], $pipes, $root);
        $this->assertIsResource($process);
        fclose($pipes[0]);
        $cookie = '';
        $cookies = [];
        $token = '';
        $request = function (array $post = [], string $query = '') use ($address, &$cookie, &$cookies, &$token): string {
            $context = stream_context_create(['http' => ['method' => $post ? 'POST' : 'GET', 'header' => "Content-Type: application/x-www-form-urlencoded\r\nCookie: $cookie\r\n", 'content' => $post ? http_build_query($post) : '', 'ignore_errors' => true, 'timeout' => 10]]);
            $body = file_get_contents('http://'.$address.'/setup.php'.$query, false, $context);
            $headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : ($http_response_header ?? []);
            foreach ($headers as $header) {
                if (preg_match('/^Set-Cookie: ([^;]+)/i', $header, $match)) {
                    [$name, $value] = explode('=', $match[1], 2);
                    $cookies[$name] = $value;
                    $cookie = implode('; ', array_map(fn ($key, $value) => $key.'='.$value, array_keys($cookies), $cookies));
                }
            }
            if (preg_match('/name="csrf" value="([a-f0-9]+)"/', $body, $match)) {
                $token = $match[1];
            }

            return $body;
        };
        $csrf = function (string $html): string {
            $this->assertSame(1, preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $match));

            return $match[1];
        };
        try {
            $ready = false;
            for ($i = 0; $i < 50; $i++) {
                // Refused connections are expected while the isolated server starts.
                set_error_handler(static fn (): bool => true);
                try {
                    $connection = stream_socket_client('tcp://'.$address, $errno, $error, 0.1);
                } finally {
                    restore_error_handler();
                }
                if ($connection) {
                    fclose($connection);
                    $ready = true;
                    break;
                }
                usleep(100000);
            }
            $this->assertTrue($ready, 'PHP web server did not start.');
            $html = $request();
            $this->assertStringContainsString('Setup locked', $html);
            $this->assertStringContainsString('<html lang="en">', $html);
            foreach (['English', 'Deutsch', 'Français', 'Italiano', 'Español'] as $language) {
                $this->assertStringContainsString($language, $html);
            }
            $this->assertFileDoesNotExist($root.'/.env');
            $key = bin2hex(random_bytes(32));
            file_put_contents($root.'/.setup-access', $key);
            foreach (['en' => ['Browser setup', 'Invalid access key.'], 'fr' => ['Configuration dans le navigateur', 'Clé d’accès invalide.'], 'it' => ['Configurazione nel browser', 'Chiave di accesso non valida.'], 'es' => ['Configuración en el navegador', 'Clave de acceso no válida.']] as $language => [$title, $loginError]) {
                $html = $request([], '?lang='.$language);
                $this->assertStringContainsString('<html lang="'.$language.'">', $html);
                $this->assertStringContainsString($title, $html);
                $html = $request(['csrf' => $token, 'operation' => 'login', 'access_key' => 'incorrect']);
                $this->assertStringContainsString($loginError, $html);
            }
            $html = $request([], '?lang=de');
            $html = $request(['csrf' => $csrf($html), 'operation' => 'login', 'access_key' => $key]);
            $this->assertStringContainsString('Angemeldet.', $html);
            $token = $csrf($html);
            $html = $request(['csrf' => $token, 'operation' => 'run', 'step' => 'environment', 'confirm' => 'yes']);
            $this->assertStringContainsString('zuerst Composer', $html);
            $this->assertFileDoesNotExist($root.'/.env');
            mkdir($root.'/vendor');
            file_put_contents($root.'/vendor/autoload.php', '<?php require '.var_export($source.'/vendor/autoload.php', true).';');
            file_put_contents($root.'/artisan', '<?php exit(0);');
            $html = $request(['csrf' => 'wrong', 'operation' => 'run', 'step' => 'environment', 'confirm' => 'yes']);
            $this->assertStringContainsString('Formularschutz ungültig', $html);
            $this->assertFileDoesNotExist($root.'/.env');
            $oldToken = $token;
            $html = $request(['csrf' => $token, 'operation' => 'run', 'step' => 'environment']);
            $this->assertFileExists($root.'/.env');
            $this->assertStringNotContainsString('name="confirm"', $html);
            $this->assertStringContainsString('<html lang="de">', $html);
            $html = $request(['csrf' => $oldToken, 'operation' => 'run', 'step' => 'key', 'confirm' => 'yes']);
            $this->assertStringContainsString('Formularschutz ungültig', $html);
            $this->assertEmpty(Dotenv::parse(file_get_contents($root.'/.env'))['APP_KEY']);
            $html = $request(['csrf' => $token, 'operation' => 'run', 'step' => 'key', 'confirm' => 'yes']);
            $this->assertStringContainsString('Anwendungsschlüssel gespeichert', $html);
            $before = file_get_contents($root.'/.env');
            $html = $request(['csrf' => $token, 'operation' => 'run', 'step' => 'database', 'confirm' => 'yes', 'driver' => 'sqlite', 'mode' => 'new', 'path' => '../outside.sqlite']);
            $this->assertStringContainsString('innerhalb von database/', $html);
            $this->assertSame($before, file_get_contents($root.'/.env'));
            $request(['csrf' => $token, 'operation' => 'run', 'step' => 'database', 'confirm' => 'yes', 'driver' => 'sqlite', 'mode' => 'new', 'path' => 'database/database.sqlite']);
            $this->assertFileExists($root.'/database/database.sqlite');
            $request(['csrf' => $token, 'operation' => 'run', 'step' => 'cors', 'confirm' => 'yes', 'api' => 'http://localhost:8000', 'frontend' => 'http://localhost:9200']);
            $environment = Dotenv::parse(file_get_contents($root.'/.env'));
            $this->assertSame('http://localhost:9200', $environment['CORS_ALLOWED_ORIGINS']);
            $this->assertSame('localhost:9200', $environment['SANCTUM_STATEFUL_DOMAINS']);
            $beforeReview = file_get_contents($root.'/.env');
            $html = $request(['csrf' => $token, 'operation' => 'run', 'step' => 'verification']);
            $this->assertStringContainsString('Verifizierungsendpunkte sind noch nicht eingerichtet', $html);
            $this->assertStringContainsString('keine Testmail versandt', $html);
            $this->assertSame($beforeReview, file_get_contents($root.'/.env'));
            // Exercise actual streaming, including a deliberately quiet long-running child.
            file_put_contents($root.'/artisan', '<?php echo "private-stream-value"; usleep(2200000); exit(0);');
            $started = microtime(true);
            $context = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/x-ndjson\r\nCookie: $cookie\r\n", 'content' => http_build_query(['csrf' => $token, 'operation' => 'run', 'step' => 'cache', 'confirm' => 'yes']), 'timeout' => 15]]);
            $stream = fopen('http://'.$address.'/setup.php', 'r', false, $context);
            $this->assertIsResource($stream);
            $first = fgets($stream);
            $this->assertLessThan(2, microtime(true) - $started, 'First status must arrive before the child finishes.');
            $events = [json_decode($first, true, 512, JSON_THROW_ON_ERROR)];
            $raw = $first;
            while (($line = fgets($stream)) !== false) {
                $raw .= $line;
                $events[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            }
            fclose($stream);
            $this->assertSame('progress', $events[0]['type']);
            $this->assertGreaterThanOrEqual(4, count(array_filter($events, fn ($event) => $event['type'] === 'progress')));
            $this->assertStringNotContainsString('private-stream-value', $raw);
            $last = $events[array_key_last($events)];
            $this->assertSame('result', $last['type']);
            $this->assertTrue($last['ok']);
            $token = $csrf($last['html']);
            $this->assertStringContainsString('Konfigurations- und Routencache geleert', $last['html']);
            $html = $request(['csrf' => $token, 'operation' => 'run', 'step' => 'finish', 'confirm' => 'yes']);
            $this->assertStringContainsString('abgeschlossen und gesperrt', $html);
            $this->assertStringContainsString('/filament/login', $html);
            $this->assertStringContainsString('http://'.$address.'/filament/login', $html);
            $this->assertStringNotContainsString('http://localhost:8000/filament/login', $html);
            $this->assertFileExists($root.'/.setup-complete');
            $this->assertFileDoesNotExist($root.'/.setup-access');
            $this->assertStringContainsString('Einrichtung gesperrt', $request());
        } finally {
            proc_terminate($process);
            proc_close($process);
            fclose($log);
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($root);
        }
    }
}
