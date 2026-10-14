<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\Storage\EntryModel;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeServiceProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class TelescopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_telescope_is_not_registered_in_testing(): void
    {
        $this->assertNull($this->app->getProvider(TelescopeServiceProvider::class));
        $this->get('/telescope')->assertNotFound();
    }

    public function test_telescope_is_not_registered_in_production(): void
    {
        $this->app->instance('env', 'production');
        config(['telescope.enabled' => true]);
        $this->app->getProvider(AppServiceProvider::class)->register();

        $this->assertNull($this->app->getProvider(TelescopeServiceProvider::class));
        $this->get('/telescope')->assertNotFound();
    }

    public function test_local_dashboard_is_available(): void
    {
        $this->enableLocalTelescope();

        $this->get('/telescope')->assertOk()->assertViewIs('telescope::layout');
    }

    public function test_requests_are_stored_with_sensitive_details_masked(): void
    {
        $this->enableLocalTelescope();
        Telescope::startRecording();

        $this->withHeaders([
            'Authorization' => 'Bearer example-secret',
            'Cookie' => 'session=example-cookie',
            'X-CSRF-TOKEN' => 'example-csrf-header',
        ])->postJson('/up', [
            'password' => 'example-password',
            'password_confirmation' => 'example-password',
            '_token' => 'example-csrf-token',
        ])->assertStatus(405);

        Telescope::store($this->app->make(EntriesRepository::class));
        Telescope::stopRecording();
        $entry = EntryModel::where('type', 'request')->sole();

        $this->assertSame('/up', $entry->content['uri']);
        foreach (['password', 'password_confirmation', '_token'] as $parameter) {
            $this->assertSame('********', $entry->content['payload'][$parameter]);
        }
        foreach (['authorization', 'cookie', 'x-csrf-token'] as $header) {
            $this->assertSame('********', $entry->content['headers'][$header]);
        }
    }

    protected function tearDown(): void
    {
        Telescope::stopRecording();
        Telescope::flushEntries();

        parent::tearDown();
    }

    private function enableLocalTelescope(): void
    {
        $this->app->instance('env', 'local');
        config(['telescope.enabled' => true]);
        $this->app->getProvider(AppServiceProvider::class)->register();
        $this->app['router']->getRoutes()->refreshNameLookups();
    }
}
