<?php

namespace App\Support;

use RuntimeException;

final class ComposerSetup
{
    public static function run(): int
    {
        // Composer's ProcessExecutor closes STDIN on Windows. Inherit the real console here.
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 2).'/scripts/Setup.php'], [STDIN, STDOUT, STDERR], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Setup-Prozess konnte nicht gestartet werden.');
        }

        return proc_close($process);
    }
}
