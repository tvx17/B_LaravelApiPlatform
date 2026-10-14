<?php

namespace Tests\Unit;

use App\Actions\Setup\RunAction;
use App\Support\SetupLocale;
use App\Support\SetupOrigin;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SetupLocaleTest extends TestCase
{
    public function test_english_is_default_and_all_languages_cover_the_catalog(): void
    {
        $root = dirname(__DIR__, 2);
        $this->assertSame('Browser setup', (new SetupLocale($root))->text('Einrichtung im Browser'));
        $this->assertSame('en', (new SetupLocale($root, '../../invalid'))->language);
        $catalog = json_decode(file_get_contents($root.'/resources/setup/translations.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($catalog as $original => $translations) {
            $this->assertSame(['en', 'fr', 'it', 'es'], array_keys($translations), $original);
            $this->assertNotContains('', $translations, $original);
        }
        foreach (['en', 'fr', 'it', 'es'] as $language) {
            $locale = new SetupLocale($root, $language);
            foreach (RunAction::STEPS as $title) {
                $this->assertNotSame($title, $locale->text($title));
            }
            $this->assertStringNotContainsString('läuft', $locale->text('config:clear läuft'));
            $this->assertStringNotContainsString('fehlgeschlagen', $locale->text('migrate fehlgeschlagen (Exit-Code 17). Datenbanktabellen fehlen. Zuerst die Migrationen ausführen.'));
        }
    }

    public function test_request_origin_preserves_host_port_and_https_without_trusting_forwarded_headers(): void
    {
        $this->assertSame('http://localhost:8090', SetupOrigin::fromServer(['HTTP_HOST' => 'localhost:8090']));
        $this->assertSame('https://api.example.org:9443', SetupOrigin::fromServer(['HTTP_HOST' => 'api.example.org:9443', 'HTTPS' => 'on', 'HTTP_X_FORWARDED_HOST' => 'evil.example']));
        $this->assertSame('http://[::1]:8090', SetupOrigin::fromServer(['HTTP_HOST' => '[::1]:8090']));
        foreach (['evil.example/path', 'user@evil.example', "localhost\r\nX-Injected: true", 'localhost:99999', ''] as $host) {
            try {
                SetupOrigin::fromServer(['HTTP_HOST' => $host]);
                $this->fail('Invalid host accepted.');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
