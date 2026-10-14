<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Like the reference, authentication routes are explicitly owned by the application.
        Fortify::ignoreRoutes();
    }
}
