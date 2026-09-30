<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_login_and_logout_revokes_token(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'long-enough-password'])
            ->assertCreated()->assertJsonStructure(['user' => ['id', 'email'], 'token'])->assertJsonMissingPath('user.password');

        $token = $this->postJson('/api/auth/login', ['email' => 'ada@example.test', 'password' => 'long-enough-password'])
            ->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/customers')->assertOk();
        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/customers')->assertUnauthorized();
    }

    public function test_wrong_password_is_rejected_and_passwords_are_hashed(): void
    {
        $user = User::factory()->create(['email' => 'bob@example.test', 'password' => 'correct-password-123']);
        $this->assertNotSame('correct-password-123', $user->getRawOriginal('password'));
        $this->postJson('/api/auth/login', ['email' => 'bob@example.test', 'password' => 'nope'])->assertStatus(422);
    }

    public function test_short_passwords_are_rejected(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'X', 'email' => 'x@example.test', 'password' => 'short'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'x@example.test', 'password' => 'y'])->assertStatus(422);
        }
        $this->postJson('/api/auth/login', ['email' => 'x@example.test', 'password' => 'y'])->assertTooManyRequests();
    }

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/invoices')->assertUnauthorized();
    }
}
