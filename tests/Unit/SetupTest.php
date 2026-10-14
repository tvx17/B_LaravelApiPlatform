<?php

namespace Tests\Unit;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

class SetupTest extends TestCase
{
    public function test_new_checkout_configuration_with_sqlite_in_an_isolated_directory(): void
    {
        $root = dirname(__DIR__, 2);
        $fixture = sys_get_temp_dir().'/laravel-setup-'.bin2hex(random_bytes(8));
        mkdir($fixture);
        foreach (['scripts', 'vendor', 'database', 'app', 'app/Support'] as $directory) {
            mkdir($fixture.'/'.$directory);
        }
        try {
            copy($root.'/app/Support/SetupRuntime.php', $fixture.'/app/Support/SetupRuntime.php');
            copy($root.'/app/Support/SetupProcess.php', $fixture.'/app/Support/SetupProcess.php');
            foreach (['Setup.php', 'SetupEnvironment.php'] as $script) {
                copy($root.'/scripts/'.$script, $fixture.'/scripts/'.$script);
            }
            copy($root.'/.env.example', $fixture.'/.env.example');
            file_put_contents($fixture.'/vendor/autoload.php', '<?php require '.var_export($root.'/vendor/autoload.php', true).';');
            // Configuration-clearing commands are isolated from the actual application.
            file_put_contents($fixture.'/artisan', '<?php exit(0);');
            $process = proc_open([PHP_BINARY, 'scripts/Setup.php'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $fixture);
            $this->assertIsResource($process);
            fwrite($pipes[0], implode("\n", ['n', 'j', 'n', 'j', 'j', 'sqlite', 'j', $fixture.'/database/database.sqlite', 'j', 'j', 'http://localhost:8000', 'http://localhost:9200', 'n', 'n', 'n', 'n', 'n', 'n', 'n', 'n'])."\n");
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), $output.$errors);
            $environment = Dotenv::parse(file_get_contents($fixture.'/.env'));
            $this->assertStringStartsWith('base64:', $environment['APP_KEY']);
            $this->assertSame('sqlite', $environment['DB_CONNECTION']);
            $this->assertFileExists($fixture.'/database/database.sqlite');
            $this->assertSame('http://localhost:9200', $environment['CORS_ALLOWED_ORIGINS']);
            $this->assertSame('localhost:9200', $environment['SANCTUM_STATEFUL_DOMAINS']);
            $this->assertStringContainsString('/filament/login', $output);
        } finally {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($fixture, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($fixture);
        }
    }
}
