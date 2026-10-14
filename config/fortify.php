<?php

return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => false,
    'home' => '/artisan/account',
    'prefix' => 'api',
    'domain' => null,
    'middleware' => ['api'],
    'limiters' => ['login' => 'login'],
    'views' => false,
    'features' => [],
];
