<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_inherits_and_loses_permission_through_a_role(): void
    {
        $permission = Permission::create(['name' => 'users.view', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'reader', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $user = User::factory()->create();

        $this->assertFalse($user->can('users.view'));

        $user->assignRole($role);

        $this->assertTrue($user->hasRole('reader'));
        $this->assertTrue($user->can('users.view'));

        $user->removeRole($role);

        $this->assertFalse($user->can('users.view'));
    }

    public function test_permission_middleware_denies_and_allows_access(): void
    {
        Permission::create(['name' => 'users.view', 'guard_name' => 'web']);
        Route::middleware(['api', 'permission:users.view'])
            ->get('/api/permission-test', fn () => response()->json(['status' => 'ok']));
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/permission-test')
            ->assertForbidden();

        $user->givePermissionTo('users.view');

        $this->getJson('/api/permission-test')->assertOk();
    }
}
