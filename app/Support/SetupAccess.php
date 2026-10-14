<?php

namespace App\Support;

use RuntimeException;

final class SetupAccess
{
    public function __construct(private readonly string $root) {}

    public function key(): string
    {
        if (is_file($this->root.'/.setup-complete')) {
            throw new RuntimeException('Die Browser-Einrichtung ist abgeschlossen und gesperrt.');
        }
        $key = is_file($this->root.'/.setup-access') ? trim(file_get_contents($this->root.'/.setup-access')) : '';
        if (strlen($key) < 32) {
            throw new RuntimeException('Browser-Einrichtung gesperrt. Bitte zuerst eine Datei .setup-access im Projektverzeichnis mit einem eigenen zufälligen Zugangsschlüssel (mindestens 32 Zeichen) anlegen. Anleitung: docs/SETUP.md.');
        }

        return $key;
    }

    public function allowedTransport(array $server): bool
    {
        $localHost = preg_match('/^(?:localhost|127\.0\.0\.1|\[::1\])(?::\d+)?$/i', $server['HTTP_HOST'] ?? '');

        return (in_array($server['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) && $localHost)
            || (! empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off');
    }

    public function authenticated(array $session): bool
    {
        return hash_equals(hash('sha256', $this->key()), $session['setup_access'] ?? '')
            && ($session['setup_expires'] ?? 0) > time();
    }

    public function verifyPost(array $post, array $session): void
    {
        if (! $this->authenticated($session)
            || empty($session['setup_csrf'])
            || ! hash_equals($session['setup_csrf'], (string) ($post['csrf'] ?? ''))
            || ($post['operation'] ?? '') !== 'run') {
            throw new RuntimeException('Zugang oder Formularschutz fehlt. Bitte erneut anmelden.');
        }
    }

    public function login(string $candidate, string $address): bool
    {
        $path = $this->root.'/.setup-login-attempts.json';
        $attempts = is_file($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
        $id = hash('sha256', $address);
        $record = $attempts[$id] ?? ['time' => time(), 'count' => 0];
        if ($record['time'] < time() - 900) {
            $record = ['time' => time(), 'count' => 0];
        }
        if ($record['count'] >= 10) {
            throw new RuntimeException('Zu viele Anmeldeversuche. Bitte nach 15 Minuten erneut versuchen.');
        }
        $valid = hash_equals($this->key(), $candidate);
        $attempts = array_filter($attempts, fn (array $entry) => $entry['time'] >= time() - 900);
        $attempts[$id] = ['time' => $record['time'], 'count' => $valid ? 0 : $record['count'] + 1];
        if (file_put_contents($path, json_encode($attempts, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException('Anmeldeschutz konnte nicht gespeichert werden. Schreibrechte prüfen.');
        }
        chmod($path, 0600);

        return $valid;
    }
}
