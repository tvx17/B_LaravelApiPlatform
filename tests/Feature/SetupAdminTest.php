<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetupAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_creates_an_admin_only_after_both_confirmations(): void
    {
        $this->artisan('setup:admin')
            ->expectsQuestion('E-Mail des Administrators', 'admin@example.com')
            ->expectsConfirmation('Neues Konto anlegen?', 'yes')
            ->expectsQuestion('Anzeigename', 'Administrator')
            ->expectsQuestion('Passwort (mindestens 12 Zeichen)', 'a-long-test-password')
            ->expectsQuestion('Passwort bestätigen', 'a-long-test-password')
            ->expectsConfirmation('Filament- und Runner-Rechte für dieses Konto vergeben?', 'yes')
            ->assertSuccessful();

        $user = User::where('email', 'admin@example.com')->sole();
        $this->assertTrue(Hash::check('a-long-test-password', $user->password));
        $this->assertTrue($user->can('access-admin-panel'));
        $this->assertTrue($user->can('run-artisan-runner-ui'));
    }

    public function test_existing_account_password_and_name_are_preserved_when_granting_access(): void
    {
        $user = User::factory()->create();
        $original = $user->only(['name', 'password']);

        $this->artisan('setup:admin')
            ->expectsQuestion('E-Mail des Administrators', $user->email)
            ->expectsConfirmation('Filament- und Runner-Rechte für dieses Konto vergeben?', 'yes')
            ->assertSuccessful();

        $this->assertSame($original, $user->fresh()->only(['name', 'password']));
        $this->assertDatabaseCount('users', 1);
        $this->assertTrue($user->fresh()->can('access-admin-panel'));
    }

    public function test_declining_rights_does_not_create_a_new_account(): void
    {
        $this->artisan('setup:admin')
            ->expectsQuestion('E-Mail des Administrators', 'admin@example.com')
            ->expectsConfirmation('Neues Konto anlegen?', 'yes')
            ->expectsQuestion('Anzeigename', 'Administrator')
            ->expectsQuestion('Passwort (mindestens 12 Zeichen)', 'a-long-test-password')
            ->expectsQuestion('Passwort bestätigen', 'a-long-test-password')
            ->expectsConfirmation('Filament- und Runner-Rechte für dieses Konto vergeben?', 'no')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('permissions', 0);
    }

    public function test_non_interactive_admin_setup_is_rejected(): void
    {
        $this->artisan('setup:admin', ['--no-interaction' => true])->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }
}
