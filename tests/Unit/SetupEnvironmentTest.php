<?php

namespace Tests\Unit;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/scripts/SetupEnvironment.php';

class SetupEnvironmentTest extends TestCase
{
    public function test_environment_updates_preserve_other_settings_and_literal_credentials(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'laravel-setup-');
        try {
            file_put_contents($path, "# Keep this comment\nAPP_KEY=existing-key\nDB_PASSWORD=old\n");
            $password = 'a $literal ${APP_KEY} "quoted" \\path #value';
            \SetupEnvironment::write($path, ['DB_PASSWORD' => $password, 'DB_CONNECTION' => 'mysql']);
            \SetupEnvironment::write($path, ['DB_CONNECTION' => 'mariadb']);
            $content = file_get_contents($path);
            $values = Dotenv::parse($content);

            $this->assertSame($password, $values['DB_PASSWORD']);
            $this->assertSame('existing-key', $values['APP_KEY']);
            $this->assertSame('mariadb', $values['DB_CONNECTION']);
            $this->assertSame(1, substr_count($content, 'DB_CONNECTION='));
            $this->assertStringContainsString('# Keep this comment', $content);
            $this->assertFileDoesNotExist($path.'.setup-tmp');
        } finally {
            unlink($path);
        }
    }

    public function test_multiline_environment_injection_is_rejected_without_changing_the_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'laravel-setup-');
        file_put_contents($path, 'APP_KEY=existing-key');
        try {
            try {
                \SetupEnvironment::write($path, ['DB_PASSWORD' => "value\nAPP_KEY=changed"]);
                $this->fail('Multiline value accepted.');
            } catch (\InvalidArgumentException) {
                $this->assertSame('APP_KEY=existing-key', file_get_contents($path));
            }
        } finally {
            unlink($path);
        }
    }
}
