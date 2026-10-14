<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_login_form(): void
    {
        $this->get('/artisan/login')->assertOk()->assertSee('Anmelden')->assertSee('name="_token"', false);
    }

    public function test_valid_credentials_start_a_session_and_regenerate_its_id(): void
    {
        $user = User::factory()->create();
        $this->withSession(['marker' => 'preserved']);
        $previousId = session()->getId();

        $this->post('/artisan/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('runner.dashboard'));

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotSame($previousId, session()->getId());
        $this->get('/artisan/account')->assertOk()->assertSee('Zugriffsberechtigung');
    }

    public function test_invalid_credentials_do_not_authenticate_or_flash_password(): void
    {
        $user = User::factory()->create();

        $this->from('/artisan/login')->post('/artisan/login', ['email' => $user->email, 'password' => 'incorrect'])
            ->assertRedirect('/artisan/login')
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest('web');
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/artisan/login', ['email' => 'unknown@example.com', 'password' => 'incorrect'])
                ->assertUnprocessable();
        }

        $this->postJson('/artisan/login', ['email' => 'unknown@example.com', 'password' => 'incorrect'])
            ->assertTooManyRequests();
        $this->assertGuest('web');
    }

    public function test_login_validates_non_string_email_input(): void
    {
        $this->postJson('/artisan/login', ['email' => ['invalid'], 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
        $this->assertGuest('web');
    }

    public function test_logout_invalidates_the_session_and_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['marker' => 'removed', '_token' => 'old-token']);
        $previousId = session()->getId();

        $this->post('/artisan/logout')
            ->assertRedirect(route('login'))
            ->assertSessionMissing('marker');

        $this->assertGuest('web');
        $this->assertNotSame($previousId, session()->getId());
        $this->assertNotSame('old-token', session()->token());
        $this->get('/artisan/account')->assertRedirect(route('login'));
    }

    public function test_login_rejects_requests_without_a_csrf_token(): void
    {
        $this->app->instance('env', 'local');

        $this->post('/artisan/login', ['email' => 'user@example.com', 'password' => 'password'])
            ->assertStatus(419);
        $this->assertGuest('web');
    }
}
