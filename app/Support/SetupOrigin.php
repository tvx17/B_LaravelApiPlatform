<?php

namespace App\Support;

use RuntimeException;

final class SetupOrigin
{
    public static function fromServer(array $server): string
    {
        $host = $server['HTTP_HOST'] ?? '';
        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?|\[[a-f0-9:]+\])(?::([0-9]{1,5}))?$/i', $host, $match)
            || (isset($match[1]) && ((int) $match[1] < 1 || (int) $match[1] > 65535))) {
            throw new RuntimeException('Ungültiger Host in der Setup-Adresse.');
        }
        $https = ! empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off';

        return ($https ? 'https' : 'http').'://'.$host;
    }
}
