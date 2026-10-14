<?php

namespace App\Support;

use RuntimeException;

final class SetupRuntime
{
    public static function php(): string
    {
        $configured = getenv('SETUP_PHP_BINARY');
        if ($configured) {
            if (! is_file($configured)) {
                throw new RuntimeException('SETUP_PHP_BINARY verweist auf keine vorhandene PHP-CLI-Datei.');
            }

            return $configured;
        }
        $name = PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php';
        $candidates = [];
        if (in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
            $candidates[] = PHP_BINARY;
        }
        if (php_ini_loaded_file()) {
            $candidates[] = dirname(php_ini_loaded_file()).'/'.$name;
        }
        $candidates[] = PHP_BINDIR.'/'.$name;
        foreach (explode(PATH_SEPARATOR, getenv('PATH') ?: getenv('Path') ?: '') as $directory) {
            $candidates[] = trim($directory, '"').'/'.$name;
        }
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('PHP-CLI nicht gefunden. SETUP_PHP_BINARY als Servervariable auf die vorhandene php.exe beziehungsweise php setzen.');
    }

    public static function composer(): array
    {
        $php = self::php();
        $configured = getenv('COMPOSER_BINARY');
        if ($configured) {
            if (! is_file($configured)) {
                throw new RuntimeException('COMPOSER_BINARY verweist auf keine vorhandene Composer-Datei.');
            }

            return [$php, $configured, 'install', '--no-interaction', '--no-scripts'];
        }
        $directories = [dirname($php), ...explode(PATH_SEPARATOR, getenv('PATH') ?: getenv('Path') ?: '')];
        foreach ($directories as $directory) {
            $directory = trim($directory, '"');
            foreach (['composer.phar', 'composer'] as $name) {
                $candidate = $directory.'/'.$name;
                if (is_file($candidate)) {
                    return [$php, $candidate, 'install', '--no-interaction', '--no-scripts'];
                }
            }
        }

        throw new RuntimeException('Composer nicht gefunden. composer.phar neben PHP ablegen oder COMPOSER_BINARY als Servervariable auf die vorhandene Composer-PHP-Datei setzen.');
    }

    public static function npm(string ...$arguments): array
    {
        $nodeName = PHP_OS_FAMILY === 'Windows' ? 'node.exe' : 'node';
        foreach (explode(PATH_SEPARATOR, getenv('PATH') ?: getenv('Path') ?: '') as $directory) {
            $directory = trim($directory, '"');
            $node = $directory.'/'.$nodeName;
            if (! is_file($node)) {
                continue;
            }
            foreach ([$directory.'/node_modules/npm/bin/npm-cli.js', dirname(realpath($node)).'/../lib/node_modules/npm/bin/npm-cli.js', $directory.'/../share/nodejs/npm/bin/npm-cli.js'] as $npm) {
                if (is_file($npm)) {
                    return [$node, $npm, ...$arguments];
                }
            }
        }

        throw new RuntimeException('Node.js/npm nicht gefunden. Node.js mit npm installieren oder Frontend-Assets separat bauen und public/build hochladen.');
    }

    public static function failure(string $output): string
    {
        // Only return fixed explanations. Never echo raw output, URLs or submitted secrets.
        $patterns = [
            '/could not find driver/i' => 'PDO-Datenbanktreiber fehlt in der CLI-PHP-Konfiguration.',
            '/no such table|table .* doesn.t exist|base table or view not found/i' => 'Datenbanktabellen fehlen. Zuerst die Migrationen ausführen.',
            '/database file .* does not exist|unable to open database file/i' => 'SQLite-Datei fehlt oder ist für den Server nicht zugänglich.',
            '/access denied for user|connection refused|SQLSTATE\[HY000\] \[2002\]/i' => 'Datenbankzugriff fehlgeschlagen. Server, Benutzer, Passwort und Rechte prüfen.',
            '/requires .*ext-|missing from your system|requires a php version|platform requirements/i' => 'PHP-Version oder benötigte PHP-Erweiterungen passen nicht zum Lockfile.',
            '/permission denied|access is denied|zugriff verweigert/i' => 'Der Serverprozess hat nicht die benötigten Datei-/Verzeichnisrechte.',
            '/allowed memory size/i' => 'Das PHP-Speicherlimit ist zu niedrig.',
            '/certificate|SSL|curl error|could not resolve host|failed to download/i' => 'Composer-Netzwerkzugriff fehlgeschlagen. DNS, Proxy und TLS-Zertifikate prüfen.',
            '/SETUP_ADMIN_INVALID/i' => 'Administratordaten ungültig. E-Mail, Anlagebestätigung, Name und übereinstimmende Passwörter mit mindestens zwölf Zeichen prüfen.',
        ];
        foreach ($patterns as $pattern => $message) {
            if (preg_match($pattern, $output)) {
                return $message;
            }
        }

        return 'PHP-CLI-Konfiguration, Datenbank, Schreibrechte und Server-Zeitlimits prüfen. Prozessausgaben bleiben zum Schutz von Zugangsdaten verborgen.';
    }
}
