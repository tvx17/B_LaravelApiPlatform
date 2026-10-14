<?php

return [
    // Master switch. Off unless explicitly enabled.
    'enabled' => env('ARTISAN_RUNNER_UI_ENABLED', false),

    'path' => 'artisan',

    'middleware' => ['web', 'auth:web', 'can:run-artisan-runner-ui'],

    // Anything else returns 404
    'environments' => ['local', 'qa', 'staging', 'uat'],

    // If not empty, ONLY these commands are shown/runnable (wildcards allowed)
    'allowed' => [],

    // Never shown or runnable (wildcards allowed)
    'denied' => [
        'tinker', 'serve', 'down', 'up',
        'db:wipe', 'migrate:fresh', 'migrate:reset', 'migrate:refresh', 'migrate:rollback', 'migrate:generate',
        'key:generate', 'env:*', 'queue:work', 'queue:listen', 'schedule:work',
        'schedule:run', 'vendor:publish', 'package:discover', 'ide-helper:*',
        'runner:create-user', 'admin:grant-access', 'make:filament-user', 'setup:*',
    ],
];
