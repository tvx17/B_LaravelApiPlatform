<?php

namespace App\Support;

final class SetupLocale
{
    public const LANGUAGES = ['en' => 'English', 'de' => 'Deutsch', 'fr' => 'Français', 'it' => 'Italiano', 'es' => 'Español'];

    public readonly string $language;

    private array $catalog;

    public function __construct(string $root, string $language = 'en')
    {
        $this->language = array_key_exists($language, self::LANGUAGES) ? $language : 'en';
        $this->catalog = json_decode(file_get_contents($root.'/resources/setup/translations.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function text(string $message): string
    {
        if ($this->language === 'de') {
            return $message;
        }
        if (isset($this->catalog[$message][$this->language])) {
            return $this->catalog[$message][$this->language];
        }
        if (preg_match('/^(.*) fehlgeschlagen \(Exit-Code (-?\d+)\)\. (.*)$/s', $message, $match)) {
            return strtr($this->text('{command} fehlgeschlagen (Exit-Code {code}). {reason}'), ['{command}' => $this->text($match[1]), '{code}' => $match[2], '{reason}' => $this->text($match[3])]);
        }
        foreach ([' läuft' => '{command} läuft', ' abgeschlossen' => '{command} abgeschlossen'] as $suffix => $template) {
            if (str_ends_with($message, $suffix)) {
                return str_replace('{command}', $this->text(substr($message, 0, -strlen($suffix))), $this->text($template));
            }
        }

        return $message;
    }

    public function client(): array
    {
        $keys = ['Schritt wird ausgeführt', 'Verbindung zum Server wird aufgebaut …', 'Bitte die Seite geöffnet lassen.', 'Laufzeit: {elapsed} s · letzte Statusmeldung vor {silence} s', 'Zurzeit keine neue Statusmeldung. Server oder Verbindung könnten warten; der Prozess kann weiterlaufen. Bitte nicht erneut starten.', 'Der Server bearbeitet diesen Schritt.', 'Prozess aktiv · keine neue Prozessausgabe seit {seconds} s. Das kann bei längeren Aufgaben normal sein.', 'Statusverbindung unterbrochen', 'Der Schritt kann auf dem Server noch laufen. Nicht erneut absenden. Seite später neu laden; die Serversperre verhindert parallele Ausführung.'];

        return array_combine($keys, array_map($this->text(...), $keys));
    }
}
