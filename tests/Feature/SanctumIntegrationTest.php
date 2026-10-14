<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SanctumIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_authentication_requires_a_valid_token(): void
    {
        Route::middleware(['api', 'auth:sanctum'])->get('/api/token-check', fn (Request $request) => ['id' => $request->user()->id]);
        $this->getJson('/api/token-check')->assertUnauthorized();
        $user = User::factory()->create();
        $token = $user->createToken('integration')->plainTextToken;
        $this->withToken($token)->getJson('/api/token-check')->assertOk()->assertJsonPath('id', $user->id);
    }

    public function test_csrf_cookie_supports_the_configured_frontend_origin(): void
    {
        $this->withHeader('Origin', 'http://localhost:9200')->get('/sanctum/csrf-cookie')
            ->assertNoContent()->assertCookie('XSRF-TOKEN')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:9200')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }
}
