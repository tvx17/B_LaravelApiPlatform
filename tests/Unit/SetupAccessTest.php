<?php

namespace Tests\Unit;

use App\Support\SetupAccess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SetupAccessTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/setup-access-'.bin2hex(random_bytes(8));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root.'/.*') as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->root);
    }

    public function test_browser_setup_is_disabled_without_an_access_file(): void
    {
        $this->expectException(RuntimeException::class);
        (new SetupAccess($this->root))->key();
    }

    public function test_setup_requires_key_csrf_execute_operation_and_an_unexpired_session(): void
    {
        $key = bin2hex(random_bytes(32));
        file_put_contents($this->root.'/.setup-access', $key);
        $access = new SetupAccess($this->root);
        $this->assertFalse($access->login('wrong-key', '127.0.0.1'));
        $this->assertTrue($access->login($key, '127.0.0.1'));
        $session = ['setup_access' => hash('sha256', $key), 'setup_expires' => time() + 60, 'setup_csrf' => 'test-csrf'];
        $this->assertTrue($access->authenticated($session));
        $access->verifyPost(['csrf' => 'test-csrf', 'operation' => 'run'], $session);
        $this->assertFalse($access->authenticated(array_replace($session, ['setup_expires' => time() - 1])));
        foreach ([['csrf' => 'wrong', 'operation' => 'run'], ['csrf' => 'test-csrf'], ['csrf' => 'test-csrf', 'operation' => 'skip']] as $post) {
            try {
                $access->verifyPost($post, $session);
                $this->fail('Unauthorized setup mutation accepted.');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
        file_put_contents($this->root.'/.setup-access', bin2hex(random_bytes(32)));
        $this->assertFalse($access->authenticated($session));
    }

    public function test_completed_installation_remains_locked_even_with_a_key(): void
    {
        file_put_contents($this->root.'/.setup-access', bin2hex(random_bytes(32)));
        file_put_contents($this->root.'/.setup-complete', 'complete');
        $this->expectException(RuntimeException::class);
        (new SetupAccess($this->root))->key();
    }

    public function test_transport_requires_local_host_and_peer_or_https(): void
    {
        $access = new SetupAccess($this->root);
        $this->assertTrue($access->allowedTransport(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost:8000']));
        $this->assertFalse($access->allowedTransport(['REMOTE_ADDR' => '192.0.2.1', 'HTTP_HOST' => 'localhost:8000']));
        $this->assertFalse($access->allowedTransport(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'example.org']));
        $this->assertFalse($access->allowedTransport(['REMOTE_ADDR' => '192.0.2.1', 'HTTPS' => 'off']));
        $this->assertTrue($access->allowedTransport(['REMOTE_ADDR' => '192.0.2.1', 'HTTPS' => 'on']));
    }

    public function test_failed_logins_are_limited_across_sessions(): void
    {
        file_put_contents($this->root.'/.setup-access', bin2hex(random_bytes(32)));
        for ($i = 0; $i < 10; $i++) {
            $this->assertFalse((new SetupAccess($this->root))->login('wrong-key', '192.0.2.1'));
        }
        $this->expectException(RuntimeException::class);
        (new SetupAccess($this->root))->login('wrong-key', '192.0.2.1');
    }
}
