<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'kasir', ?int $outletId = 1, bool $active = true): User
    {
        return User::factory()->create([
            'role' => $role,
            'outlet_id' => $role === 'kasir' || $role === 'supervisor_pos' ? $outletId : null,
            'is_active' => $active,
            'password' => bcrypt('Password123!'),
        ]);
    }

    private function superadmin(): User
    {
        return $this->makeUser('superadmin', null);
    }

    private function login(string $email, string $password = 'Password123!'): array
    {
        return $this->postJson('/auth/login', ['email' => $email, 'password' => $password])->json();
    }

    public function test_login_success(): void
    {
        $user = $this->makeUser();
        $res = $this->postJson('/auth/login', ['email' => $user->email, 'password' => 'Password123!']);

        $res->assertOk()
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonStructure(['message', 'data' => ['access_token', 'refresh_token', 'expires_in', 'user']]);
    }

    public function test_login_wrong_password_401(): void
    {
        $user = $this->makeUser();
        $res = $this->postJson('/auth/login', ['email' => $user->email, 'password' => 'salah']);

        $res->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }

    public function test_login_inactive_403(): void
    {
        $user = $this->makeUser('kasir', 1, false);
        $res = $this->postJson('/auth/login', ['email' => $user->email, 'password' => 'Password123!']);

        $res->assertStatus(403)->assertJsonPath('code', 'ACCOUNT_DISABLED');
    }

    public function test_refresh_rotating(): void
    {
        $user = $this->makeUser();
        $pair = $this->login($user->email);

        $res = $this->postJson('/auth/refresh', ['refresh_token' => $pair['data']['refresh_token']]);
        $res->assertOk()->assertJsonStructure(['data' => ['access_token', 'refresh_token']]);

        // refresh lama sudah dicabut
        $reuse = $this->postJson('/auth/refresh', ['refresh_token' => $pair['data']['refresh_token']]);
        $reuse->assertStatus(401)->assertJsonPath('code', 'REFRESH_INVALID');
    }

    public function test_logout_blocks_access_token(): void
    {
        $user = $this->makeUser();
        $pair = $this->login($user->email);

        $this->postJson('/auth/logout', ['refresh_token' => $pair['data']['refresh_token']], [
            'Authorization' => 'Bearer '.$pair['data']['access_token'],
        ])->assertOk();

        $this->getJson('/auth/me', [
            'Authorization' => 'Bearer '.$pair['data']['access_token'],
        ])->assertStatus(401)->assertJsonPath('code', 'TOKEN_REVOKED');
    }

    public function test_me_inactive_user_403(): void
    {
        $user = $this->makeUser();
        $pair = $this->login($user->email);
        $user->update(['is_active' => false]);

        $this->getJson('/auth/me', [
            'Authorization' => 'Bearer '.$pair['data']['access_token'],
        ])->assertStatus(403)->assertJsonPath('code', 'ACCOUNT_DISABLED');
    }

    public function test_users_rbac_and_validation(): void
    {
        $admin = $this->superadmin();
        $kasir = $this->makeUser('kasir', 1);
        $aToken = $this->login($admin->email)['data']['access_token'];
        $kToken = $this->login($kasir->email)['data']['access_token'];
        $h = fn ($t) => ['Authorization' => 'Bearer '.$t];

        // kasir ditolak
        $this->getJson('/auth/users', $h($kToken))->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');

        // outlet wajib untuk kasir
        $this->postJson('/auth/users', [
            'name' => 'X', 'email' => 'x@demo.test', 'password' => 'Password123!', 'role' => 'kasir',
        ], $h($aToken))->assertStatus(422);

        // outlet harus null untuk finance
        $this->postJson('/auth/users', [
            'name' => 'Y', 'email' => 'y@demo.test', 'password' => 'Password123!',
            'role' => 'staff_finance', 'outlet_id' => 1,
        ], $h($aToken))->assertStatus(422);

        // sukses buat kasir
        $created = $this->postJson('/auth/users', [
            'name' => 'K2', 'email' => 'k2@demo.test', 'password' => 'Password123!',
            'role' => 'kasir', 'outlet_id' => 2,
        ], $h($aToken))->assertStatus(201);
        $newId = $created->json('data.id');

        // self-deactivate ditolak
        $this->patchJson("/auth/users/{$admin->id}/status", ['is_active' => false], $h($aToken))
            ->assertStatus(409);

        // deactivate user baru -> refresh miliknya ikut tercabut
        $pair = $this->login('k2@demo.test');
        $this->patchJson("/auth/users/{$newId}/status", ['is_active' => false], $h($aToken))->assertOk();
        $this->postJson('/auth/refresh', ['refresh_token' => $pair['data']['refresh_token']])
            ->assertStatus(401)->assertJsonPath('code', 'REFRESH_INVALID');
    }

    public function test_login_rate_limited_returns_json_envelope(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 61; $i++) {
            $res = $this->postJson('/auth/login', ['email' => $user->email, 'password' => 'salah']);
        }

        $res->assertStatus(429)->assertJsonPath('code', 'RATE_LIMITED');
    }
}
