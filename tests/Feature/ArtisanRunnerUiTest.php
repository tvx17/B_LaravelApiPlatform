<?php

namespace Tests\Feature;

use App\Models\User;
use CodeSquirrel\ArtisanRunnerUI\ArtisanRunnerUIServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ArtisanRunnerUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_runner_and_filament_routes_have_separate_prefixes(): void
    {
        $this->enableRunnerForTesting();

        $this->assertSame(url('/artisan'), route('artisan-runner-ui.index'));
        $this->assertSame(url('/artisan/run'), route('artisan-runner-ui.run'));
        $this->assertSame(url('/artisan/login'), route('login'));
        $this->assertSame(url('/artisan/account'), route('runner.dashboard'));
        $this->assertSame(url('/artisan/logout'), route('logout'));
        $this->assertSame(url('/filament'), route('filament.admin.pages.dashboard'));
        $this->assertSame(url('/filament/login'), route('filament.admin.auth.login'));
        $this->assertSame(url('/filament/logout'), route('filament.admin.auth.logout'));

        foreach (['/artisan-runner-ui', '/login', '/logout', '/runner'] as $oldPath) {
            $this->get($oldPath)->assertNotFound();
        }
    }

    public function test_runner_routes_are_unavailable_by_default(): void
    {
        $this->get('/artisan')->assertNotFound();
        $this->postJson('/artisan/run', ['command' => 'about'])->assertNotFound();
    }

    public function test_enabled_runner_requires_authentication(): void
    {
        $this->enableRunnerForTesting();

        $this->getJson('/artisan')->assertUnauthorized();
        $this->postJson('/artisan/run', ['command' => 'about'])->assertUnauthorized();
        $this->get('/artisan')->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_ui_and_run_an_allowed_command(): void
    {
        $this->enableRunnerForTesting();
        config(['artisan-runner-ui.allowed' => ['about']]);
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('run-artisan-runner-ui', 'web'));
        $this->actingAs($user);

        $this->get('/artisan')->assertOk()->assertViewIs('artisan-runner-ui::index');
        $this->postJson('/artisan/run', ['command' => 'about'])
            ->assertOk()
            ->assertJsonPath('exit_code', 0)
            ->assertJsonStructure(['output']);
        $this->postJson('/artisan/run', ['command' => 'db:wipe'])->assertForbidden();
        $this->postJson('/artisan/run', ['command' => 'admin:grant-access', 'parameters' => ['email' => $user->email]])
            ->assertForbidden();
    }

    public function test_authenticated_user_without_permission_cannot_access_runner(): void
    {
        $this->enableRunnerForTesting();
        $this->actingAs(User::factory()->create());

        $this->getJson('/artisan')->assertForbidden();
        $this->postJson('/artisan/run', ['command' => 'about'])->assertForbidden();
    }

    public function test_revoking_permission_blocks_further_command_execution(): void
    {
        $this->enableRunnerForTesting();
        $permission = Permission::findOrCreate('run-artisan-runner-ui', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo($permission);
        $this->actingAs($user);
        $this->getJson('/artisan')->assertOk();

        $user->revokePermissionTo($permission);

        $this->postJson('/artisan/run', ['command' => 'about'])->assertForbidden();
    }

    public function test_runner_is_unavailable_in_production_even_when_enabled(): void
    {
        $this->enableRunnerForTesting();
        $this->app->instance('env', 'production');

        $this->getJson('/artisan')->assertNotFound();
        $this->postJson('/artisan/run', ['command' => 'about'])->assertNotFound();
    }

    public function test_runner_execution_requires_a_valid_csrf_token(): void
    {
        $this->enableRunnerForTesting();
        config(['artisan-runner-ui.environments' => ['local']]);
        $this->app->instance('env', 'local');
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('run-artisan-runner-ui', 'web'));
        $this->actingAs($user);

        $this->postJson('/artisan/run', ['command' => 'about'])->assertStatus(419);
        $this->withSession(['_token' => 'test-csrf-token'])
            ->postJson('/artisan/run', [
                'command' => 'about',
                '_token' => 'test-csrf-token',
            ])
            ->assertOk()
            ->assertJsonPath('exit_code', 0);
    }

    private function enableRunnerForTesting(): void
    {
        config([
            'artisan-runner-ui.enabled' => true,
            'artisan-runner-ui.environments' => ['testing'],
        ]);

        $this->app->getProvider(ArtisanRunnerUIServiceProvider::class)->boot();
        $this->app['router']->getRoutes()->refreshNameLookups();
    }
}
