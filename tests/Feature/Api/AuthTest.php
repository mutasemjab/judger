<?php

namespace Tests\Feature\Api;

use App\Enums\AccountStatus;
use App\Enums\UserType;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SubscriptionPlan::factory()->free()->create();
        Role::query()->create([
            'name' => 'User',
            'slug' => 'user',
            'description' => 'Default user role',
        ]);
    }

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Lawyer',
            'email' => 'lawyer@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => UserType::Lawyer->value,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success', 'data' => ['user', 'token'],
            ]);

        $this->assertDatabaseHas('users', ['email' => 'lawyer@test.com']);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'account_status' => AccountStatus::Active,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['user', 'token']]);
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);
    }

    public function test_user_can_get_profile(): void
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/me', ['name' => 'Updated Name', 'language' => 'ar']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name', 'language' => 'ar']);
    }

    public function test_forgot_password_sends_reset_link_for_existing_user(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'If this email exists, a reset link has been sent.');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_returns_generic_success_for_unknown_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'missing@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'If this email exists, a reset link has been sent.');

        Notification::assertNothingSent();
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('old-password'),
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Password reset successful.');

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
