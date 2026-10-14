<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Fortify;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\SocialiteManager;
use Laravel\Telescope\TelescopeServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Tests\TestCase;

class ApiPlatformIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_platform_documentation_remains_available(): void
    {
        $this->withHeader('Accept', 'application/vnd.openapi+json')->get('/api/docs')
            ->assertOk()->assertJsonStructure(['openapi', 'paths']);
        $this->assertSame('/api', config('api-platform.defaults.route_prefix'));
        $this->assertSame(['application/vnd.api+json'], config('api-platform.formats.jsonapi'));
        $this->assertSame(['application/problem+json'], config('api-platform.error_formats.jsonproblem'));
    }

    public function test_api_platform_admin_does_not_conflict_with_filament(): void
    {
        $this->withoutVite();
        $this->get('/admin')->assertOk()->assertSee('API Platform admin');
        $this->get('/admin/example')->assertOk();
        $this->get('/filament')->assertRedirect('/filament/login');
        $this->get('/api/user')->assertNotFound();
    }

    public function test_supporting_services_are_resolvable(): void
    {
        $this->assertInstanceOf(SocialiteManager::class, app(Factory::class));
        $this->assertFalse(Fortify::$registersRoutes);
        $this->assertNotNull($this->app->getProvider(MediaLibraryServiceProvider::class));
        $this->assertNull($this->app->getProvider(TelescopeServiceProvider::class));
        $this->get('/_laravel-brain')->assertNotFound();
    }
}
