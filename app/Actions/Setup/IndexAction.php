<?php

namespace App\Actions\Setup;

use App\Support\SetupAccess;
use App\Support\SetupLocale;
use App\Support\SetupOrigin;
use PDOException;
use RuntimeException;
use Throwable;

final class IndexAction
{
    public function __construct(private readonly string $root) {}

    public function handle(): void
    {
        ini_set('display_errors', '0');
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'none'; script-src 'self'; connect-src 'self'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
        $access = new SetupAccess($this->root);
        $language = (string) ($_GET['lang'] ?? $_COOKIE['setup_language'] ?? 'en');
        $locale = new SetupLocale($this->root, $language);
        $t = $locale->text(...);
        $base = '';
        $runner = new RunAction($this->root);
        $steps = RunAction::STEPS;
        $environment = [];
        $authenticated = false;
        $message = '';
        $error = '';
        $locked = false;
        $finished = false;
        $streaming = false;
        $streamRequested = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/x-ndjson');
        $ready = false;
        $step = (string) ($_GET['step'] ?? 'install');
        $step = array_key_exists($step, $steps) ? $step : 'install';
        $lock = null;
        try {
            if (PHP_VERSION_ID < 80300) {
                throw new RuntimeException('PHP 8.3 oder neuer erforderlich.');
            }
            $base = SetupOrigin::fromServer($_SERVER);
            if (! $access->allowedTransport($_SERVER)) {
                throw new RuntimeException('Browser-Einrichtung außerhalb von localhost nur über HTTPS aufrufen.');
            }
            $access->key();
            $ready = true;
            session_name('laravel_api_platform_setup');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => ! empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off', 'path' => '/setup.php']);
            if (! session_start()) {
                throw new RuntimeException('PHP-Sitzung konnte nicht gestartet werden. Hosting-Konfiguration prüfen.');
            }
            $_SESSION['setup_csrf'] ??= bin2hex(random_bytes(32));
            if (isset($_GET['lang'])) {
                setcookie('setup_language', $locale->language, ['expires' => time() + 31536000, 'path' => '/setup.php', 'httponly' => true, 'samesite' => 'Strict', 'secure' => str_starts_with($base, 'https://')]);
            } elseif (isset($_SESSION['setup_locale'])) {
                $locale = new SetupLocale($this->root, $_SESSION['setup_locale']);
                $t = $locale->text(...);
            }
            $_SESSION['setup_locale'] = $locale->language;
            $authenticated = $access->authenticated($_SESSION);
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                foreach ($_POST as $value) {
                    if (! is_string($value)) {
                        throw new RuntimeException('Ungültiges Formular.');
                    }
                }
                $lock = fopen($this->root.'/.setup.lock', 'c');
                if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
                    throw new RuntimeException('Ein Einrichtungsschritt läuft bereits. Bitte warten.');
                }
                $access->key();
                if (! hash_equals($_SESSION['setup_csrf'], (string) ($_POST['csrf'] ?? ''))) {
                    throw new RuntimeException('Formularschutz ungültig. Seite neu laden.');
                }
                if (($_POST['operation'] ?? '') === 'login') {
                    if (! $access->login((string) ($_POST['access_key'] ?? ''), $_SERVER['REMOTE_ADDR'] ?? '')) {
                        throw new RuntimeException('Zugangsschlüssel ungültig.');
                    }
                    session_regenerate_id(true);
                    $_SESSION = ['setup_locale' => $locale->language, 'setup_csrf' => bin2hex(random_bytes(32)), 'setup_access' => hash('sha256', $access->key()), 'setup_expires' => time() + 3600];
                    $message = 'Angemeldet. Schritte ausführen oder überspringen.';
                } elseif (($_POST['operation'] ?? '') === 'logout') {
                    $_SESSION = ['setup_locale' => $locale->language, 'setup_csrf' => bin2hex(random_bytes(32))];
                    session_regenerate_id(true);
                    $message = 'Abgemeldet.';
                } else {
                    if (! $access->authenticated($_SESSION)) {
                        throw new RuntimeException('Bitte erneut anmelden.');
                    }
                    $step = (string) ($_POST['step'] ?? '');
                    if (! array_key_exists($step, $steps)) {
                        throw new RuntimeException('Unbekannter Einrichtungsschritt.');
                    }
                    $operation = $_POST['operation'] ?? '';
                    if ($operation === 'skip' && $step !== 'finish') {
                        $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
                        $_SESSION['setup_steps'][$step] = 'übersprungen';
                        $message = 'Schritt übersprungen. Einstellungen bleiben erhalten.';
                    } elseif ($operation === 'run') {
                        $access->verifyPost($_POST, $_SESSION);
                        // A browser refresh or a second queued submission must not repeat a step.
                        $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
                        @set_time_limit(0);
                        if ($streamRequested) {
                            $streaming = true;
                            $started = microtime(true);
                            header('Content-Type: application/x-ndjson; charset=utf-8');
                            header('X-Accel-Buffering: no');
                            header('Cache-Control: no-store, no-transform');
                            ini_set('zlib.output_compression', '0');
                            while (ob_get_level() > 0) {
                                ob_end_flush();
                            }
                            $emit = function (array $progress) use ($started, $t): void {
                                $progress['message'] = $t($progress['message']);
                                echo json_encode(['type' => 'progress', 'elapsed' => (int) (microtime(true) - $started)] + $progress, JSON_THROW_ON_ERROR)."\n";
                                flush();
                            };
                            $emit(['message' => 'Schritt wird gestartet.', 'silent_seconds' => null]);
                            $runner = new RunAction($this->root, $emit);
                        }
                        // Clicking Execute is the explicit authorization; account creation has its own choice.
                        $message = $runner->handle($step, array_replace($_POST, ['confirm' => 'yes']));
                        $_SESSION['setup_steps'][$step] = 'erledigt';
                        if ($step === 'finish') {
                            $finished = true;
                        }
                    } else {
                        throw new RuntimeException('Unbekannte Formularaktion.');
                    }
                    if (! $finished) {
                        $keys = array_keys($steps);
                        $step = $keys[min(array_search($step, $keys, true) + 1, count($keys) - 1)];
                        $_SESSION['setup_expires'] = time() + 3600;
                    }
                }
            }
            $authenticated = $finished || $access->authenticated($_SESSION);
            if ($authenticated) {
                $environment = $runner->environment();
            }
        } catch (PDOException) {
            $error = 'Datenbankverbindung oder Anlage fehlgeschlagen. Zugangsdaten, Server und Rechte prüfen. Eine bereits angelegte Datenbank bleibt erhalten.';
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
            // Re-evaluate login after a failed operation, without exposing configuration to guests.
            try {
                if (! $ready) {
                    throw new RuntimeException;
                }
                $authenticated = $access->authenticated($_SESSION ?? []);
                if ($authenticated) {
                    $environment = $runner->environment();
                }
            } catch (Throwable) {
                $locked = true;
            }
        } catch (Throwable) {
            $error = 'Einrichtung fehlgeschlagen. PHP-Erweiterungen, Schreibrechte und Hosting-Konfiguration prüfen. Zugangsdaten werden nicht ausgegeben.';
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
        if ($authenticated && $environment === []) {
            try {
                $environment = $runner->environment();
            } catch (Throwable) {
                // Keep the form available without rendering parser errors containing secrets.
            }
        }
        if ($streaming) {
            ob_start();
            require $this->root.'/resources/views/setup/wizard.php';
            $html = ob_get_clean();
            echo json_encode(['type' => 'result', 'ok' => $error === '', 'html' => $html], JSON_THROW_ON_ERROR)."\n";
            flush();
        } else {
            require $this->root.'/resources/views/setup/wizard.php';
        }
    }
}
