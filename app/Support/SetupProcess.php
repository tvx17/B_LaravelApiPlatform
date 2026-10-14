<?php

namespace App\Support;

final class SetupProcess
{
    public static function wait($process, callable $heartbeat, float $interval = 2): int
    {
        $next = microtime(true);
        do {
            $status = proc_get_status($process);
            if (! $status['running']) {
                $closed = proc_close($process);

                return $status['exitcode'] >= 0 ? $status['exitcode'] : $closed;
            }
            if (microtime(true) >= $next) {
                $heartbeat();
                $next = microtime(true) + $interval;
            }
            usleep(100000);
        } while (true);
    }
}
