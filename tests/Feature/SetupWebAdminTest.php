<?php

namespace Tests\Feature;

use App\Actions\Setup\AdminAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SetupWebAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_browser_admin_gets_both_permissions_and_a_hashed_password(): void
    {
        (new AdminAction)->handle(['confirm' => 'yes', 'create_account' => 'yes', 'email' => 'setup@example.com', 'name' => 'Setup Admin', 'password' => 'long-browser-password', 'password_confirmation' => 'long-browser-password']);
        $user = User::where('email', 'setup@example.com')->sole();
        $this->assertTrue(Hash::check('long-browser-password', $user->password));
        $this->assertTrue($user->can('access-admin-panel'));
        $this->assertTrue($user->can('run-artisan-runner-ui'));
    }

    public function test_existing_browser_admin_retains_name_and_password(): void
    {
        $user = User::factory()->create();
        $identity = $user->only(['name', 'password']);
        (new AdminAction)->handle(['confirm' => 'yes', 'email' => $user->email, 'name' => 'Do not replace', 'password' => 'different-password']);
        $this->assertSame($identity, $user->fresh()->only(['name', 'password']));
        $this->assertTrue($user->fresh()->can('access-admin-panel'));
        $this->assertTrue($user->fresh()->can('run-artisan-runner-ui'));
    }

    public function test_missing_account_creation_confirmation_and_invalid_passwords_leave_no_account(): void
    {
        foreach ([[], ['create_account' => 'yes', 'password' => 'short', 'password_confirmation' => 'short'], ['create_account' => 'yes', 'password' => 'long-browser-password', 'password_confirmation' => 'different']] as $values) {
            try {
                (new AdminAction)->handle($values + ['confirm' => 'yes', 'email' => 'setup@example.com', 'name' => 'Setup Admin']);
                $this->fail('Invalid account was accepted.');
            } catch (RuntimeException) {
                $this->assertDatabaseCount('users', 0);
                $this->assertDatabaseCount('permissions', 0);
            }
        }
    }
}
