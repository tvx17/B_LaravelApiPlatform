<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateRunnerUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_user_with_hashed_password_and_runner_permission(): void
    {
        $this->artisan('runner:create-user', ['email' => 'runner@example.com', '--name' => 'Runner'])
            ->expectsQuestion('Passwort (mindestens 12 Zeichen)', 'a-long-test-password')
            ->expectsQuestion('Passwort bestätigen', 'a-long-test-password')
            ->assertSuccessful();

        $user = User::where('email', 'runner@example.com')->sole();
        $this->assertSame('Runner', $user->name);
        $this->assertTrue(Hash::check('a-long-test-password', $user->password));
        $this->assertTrue($user->can('run-artisan-runner-ui'));
    }

    public function test_command_rejects_mismatched_passwords_without_creating_user(): void
    {
        $this->artisan('runner:create-user', ['email' => 'runner@example.com'])
            ->expectsQuestion('Passwort (mindestens 12 Zeichen)', 'a-long-test-password')
            ->expectsQuestion('Passwort bestätigen', 'another-test-password')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_command_does_not_modify_an_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com']);
        $previousPassword = $user->password;

        $this->artisan('runner:create-user', ['email' => $user->email])->assertFailed();

        $this->assertSame($previousPassword, $user->fresh()->password);
        $this->assertFalse($user->fresh()->can('run-artisan-runner-ui'));
    }

    public function test_command_requires_interactive_password_entry(): void
    {
        $this->artisan('runner:create-user', ['email' => 'runner@example.com', '--no-interaction' => true])
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
