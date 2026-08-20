<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Julia Mamani',
            'email' => 'julia.mamani@gmail.com',
            'password_hash' => Hash::make('Demo1234'),
            'role' => 'paciente',
            'active' => true,
        ], $overrides));
    }

    public function test_login_returns_tokens(): void
    {
        $this->makeUser();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'julia.mamani@gmail.com',
            'password' => 'Demo1234',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'email', 'role'],
                    'access_token',
                    'token_type',
                    'expires_in',
                    'refresh_token',
                ],
            ]);
    }

    public function test_login_fails_with_wrong_credentials(): void
    {
        $this->makeUser();

        $this->postJson('/api/auth/login', [
            'email' => 'julia.mamani@gmail.com',
            'password' => 'incorrecta',
        ])->assertStatus(401);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);

        $this->makeUser();

        $this->postJson('/api/auth/login', [
            'email' => 'julia.mamani@gmail.com',
            'password' => 'Demo1234',
        ])->assertOk();
    }

    public function test_refresh_rotates_tokens(): void
    {
        $this->makeUser();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'julia.mamani@gmail.com',
            'password' => 'Demo1234',
        ])->json('data');

        $this->postJson('/api/auth/refresh', [
            'refresh_token' => $login['refresh_token'],
        ])->assertOk()
            ->assertJsonStructure([
                'data' => ['access_token', 'refresh_token'],
            ]);
    }

    public function test_logout_revokes_access(): void
    {
        $this->makeUser();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'julia.mamani@gmail.com',
            'password' => 'Demo1234',
        ])->json('data');

        $this->withToken($login['access_token'])
            ->postJson('/api/auth/logout', [
                'refresh_token' => $login['refresh_token'],
            ])->assertStatus(204);

        $this->app['auth']->forgetGuards();

        $this->withToken($login['access_token'])
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }

    public function test_register_creates_patient(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Nuevo Paciente',
            'email' => 'nuevo@gmail.com',
            'password' => 'Demo1234',
            'password_confirmation' => 'Demo1234',
            'dni' => '40001111',
            'phone' => '999888777',
            'dob' => '1990-01-01',
            'consent_29733' => true,
        ]);

        $response->assertCreated()->assertJsonPath('data.user.role', 'paciente');

        $this->assertDatabaseHas('patients', ['dni' => '40001111']);
    }
}
