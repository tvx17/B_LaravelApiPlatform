<?php

namespace Tests\Unit;

use App\Support\SetupRuntime;
use PHPUnit\Framework\TestCase;

class SetupRuntimeTest extends TestCase
{
    public function test_available_npm_runs_directly_through_node(): void
    {
        try {
            $arguments = SetupRuntime::npm('--version');
        } catch (\RuntimeException) {
            $this->markTestSkipped('Optional Node.js/npm installation is unavailable.');
        }
        $output = tmpfile();
        try {
            $process = proc_open($arguments, [$output, $output, $output], $pipes);
            $this->assertIsResource($process);
            $this->assertSame(0, proc_close($process));
            rewind($output);
            $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+\s*$/', stream_get_contents($output));
        } finally {
            fclose($output);
        }
    }

    public function test_diagnostics_return_fixed_explanations_without_subprocess_secrets(): void
    {
        $this->assertStringContainsString('PDO', SetupRuntime::failure('could not find driver; private-test-value'));
        $this->assertStringContainsString('Migrationen', SetupRuntime::failure('no such table: users; private-test-value'));
        $this->assertStringContainsString('Administratordaten', SetupRuntime::failure('SETUP_ADMIN_INVALID'));
        $this->assertStringNotContainsString('private-test-value', SetupRuntime::failure('unrecognized error private-test-value'));
        $this->assertStringNotContainsString('private-test-value', SetupRuntime::failure('Access denied for user private-test-value'));
        $this->assertFileExists(SetupRuntime::php());
    }

    public function test_composer_callback_preserves_stdin_before_vendor_exists(): void
    {
        $root = sys_get_temp_dir().'/composer-setup-'.bin2hex(random_bytes(8));
        mkdir($root);
        mkdir($root.'/app/Support', 0777, true);
        mkdir($root.'/scripts');
        try {
            copy(dirname(__DIR__, 2).'/app/Support/ComposerSetup.php', $root.'/app/Support/ComposerSetup.php');
            file_put_contents($root.'/scripts/Setup.php', '<?php $answer = fgets(STDIN); echo $answer === false ? "NO_STDIN" : "INPUT_RECEIVED"; exit($answer === false ? 1 : 0);');
            file_put_contents($root.'/composer.json', json_encode(['name' => 'test/setup', 'autoload' => ['psr-4' => ['App\\' => 'app/']], 'scripts' => ['setup' => 'App\\Support\\ComposerSetup::run']], JSON_THROW_ON_ERROR));
            $command = array_slice(SetupRuntime::composer(), 0, 2);
            $command[] = 'setup';
            $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $root);
            $this->assertIsResource($process);
            fwrite($pipes[0], "yes\n");
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), $output.$error);
            $this->assertStringContainsString('INPUT_RECEIVED', $output);
            $this->assertFileDoesNotExist($root.'/vendor');
        } finally {
            foreach (['scripts/Setup.php', 'app/Support/ComposerSetup.php', 'composer.json'] as $file) {
                unlink($root.'/'.$file);
            }
            foreach (['scripts', 'app/Support', 'app', ''] as $directory) {
                rmdir($root.'/'.$directory);
            }
        }
    }
}
