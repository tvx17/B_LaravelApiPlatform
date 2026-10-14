<?php

final class SetupEnvironment
{
    public static function write(string $path, array $values): void
    {
        $content = file_get_contents($path);
        foreach ($values as $name => $value) {
            if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $name) || strpbrk((string) $value, "\r\n\0") !== false) {
                throw new InvalidArgumentException('Ungültige Umgebungsvariable.');
            }
            $quoted = '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], (string) $value).'"';
            $line = $name.'='.$quoted;
            $pattern = '/^'.preg_quote($name, '/').'=.*$/m';
            $content = preg_match($pattern, $content)
                ? preg_replace_callback($pattern, fn () => $line, $content)
                : rtrim($content).PHP_EOL.$line.PHP_EOL;
        }
        $temporary = $path.'.setup-tmp';
        if (file_put_contents($temporary, $content, LOCK_EX) === false) {
            throw new RuntimeException('Konfiguration konnte nicht geschrieben werden.');
        }
        chmod($temporary, 0600);
        if (! rename($temporary, $path)) {
            unlink($temporary);
            throw new RuntimeException('Konfiguration konnte nicht ersetzt werden.');
        }
    }
}
