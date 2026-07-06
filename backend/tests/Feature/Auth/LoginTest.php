<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_with_correct_credentials(): void
    {
        User::factory()->create([
            'email' => 'owner@example.test',
            'password' => Hash::make('correct-password'),
            'is_super_admin' => false,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.test',
            'password' => 'correct-password',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'email', 'is_super_admin']]);
        $this->assertFalse($response->json('user.is_super_admin'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'owner@example.test',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.test',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_super_admin_flag_is_returned_for_role_based_redirect(): void
    {
        User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('correct-password'),
            'is_super_admin' => true,
            'tenant_id' => null,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.test',
            'password' => 'correct-password',
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('user.is_super_admin'));
    }
}
