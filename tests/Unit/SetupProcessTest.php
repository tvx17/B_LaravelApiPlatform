<?php

namespace Tests\Unit;

use App\Support\SetupProcess;
use PHPUnit\Framework\TestCase;

class SetupProcessTest extends TestCase
{
    public function test_running_process_emits_heartbeats_and_preserves_failure_exit_code(): void
    {
        $output = tmpfile();
        try {
            $process = proc_open([PHP_BINARY, '-r', 'usleep(350000); exit(17);'], [$output, $output, $output], $pipes);
            $this->assertIsResource($process);
            $beats = 0;
            $exit = SetupProcess::wait($process, function () use (&$beats): void {
                $beats++;
            }, 0.1);
            $this->assertSame(17, $exit);
            $this->assertGreaterThanOrEqual(2, $beats);
        } finally {
            fclose($output);
        }
    }
}
