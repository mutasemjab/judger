<?php

namespace Tests\Feature\Api;

use App\Enums\AccountStatus;
use App\Enums\UserType;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Auth\SocialIdentityVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_email_password_register_is_disabled(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Lawyer',
            'email' => 'lawyer@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => UserType::Lawyer->value,
        ]);

        $response->assertStatus(410)
            ->assertJsonPath('message', 'Email/password authentication is disabled. Use Google or Apple social login.');
    }

    public function test_email_password_login_is_disabled(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'account_status' => AccountStatus::Active,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(410)
            ->assertJsonPath('message', 'Email/password authentication is disabled. Use Google or Apple social login.');
    }

    public function test_social_login_creates_google_user_and_token(): void
    {
        $this->fakeSocialIdentity([
            'provider' => 'google',
            'provider_id' => 'google-123',
            'email' => 'mona@example.com',
            'email_verified' => true,
            'name' => 'Mona Salem',
            'avatar' => 'https://example.com/avatar.png',
            'claims' => [],
        ]);

        $response = $this->postJson('/api/v1/auth/social-login', [
            'provider' => 'google',
            'id_token' => 'verified-google-token',
            'user_type' => UserType::Lawyer->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'mona@example.com')
            ->assertJsonPath('data.user.auth_provider', 'google')
            ->assertJsonPath('data.provider', 'google')
            ->assertJsonPath('data.is_new_user', true)
            ->assertJsonStructure(['success', 'data' => ['user', 'token', 'token_type', 'expires_in']]);

        $this->assertDatabaseHas('users', [
            'email' => 'mona@example.com',
            'google_id' => 'google-123',
            'auth_provider' => 'google',
        ]);
        $this->assertDatabaseHas('user_subscriptions', ['status' => 'active']);
    }

    public function test_social_login_links_existing_email_user_to_apple(): void
    {
        $user = User::factory()->create([
            'email' => 'client@example.com',
            'apple_id' => null,
            'auth_provider' => null,
        ]);

        $this->fakeSocialIdentity([
            'provider' => 'apple',
            'provider_id' => 'apple-abc',
            'email' => 'client@example.com',
            'email_verified' => true,
            'name' => null,
            'avatar' => null,
            'claims' => [],
        ]);

        $response = $this->postJson('/api/v1/auth/social-login', [
            'provider' => 'apple',
            'id_token' => 'verified-apple-token',
            'name' => 'Client Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.auth_provider', 'apple')
            ->assertJsonPath('data.is_new_user', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'apple_id' => 'apple-abc',
            'auth_provider' => 'apple',
        ]);
    }

    public function test_social_login_rejects_suspended_user(): void
    {
        User::factory()->create([
            'email' => 'blocked@example.com',
            'google_id' => 'google-blocked',
            'account_status' => AccountStatus::Suspended,
        ]);

        $this->fakeSocialIdentity([
            'provider' => 'google',
            'provider_id' => 'google-blocked',
            'email' => 'blocked@example.com',
            'email_verified' => true,
            'name' => 'Blocked User',
            'avatar' => null,
            'claims' => [],
        ]);

        $response = $this->postJson('/api/v1/auth/social-login', [
            'provider' => 'google',
            'id_token' => 'verified-google-token',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Your account is suspended.');
    }

    public function test_password_reset_routes_are_removed(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'user@example.com',
        ])->assertStatus(404);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => 'token',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(404);
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

    private function fakeSocialIdentity(array $identity): void
    {
        $this->app->instance(SocialIdentityVerifier::class, new class($identity) extends SocialIdentityVerifier {
            public function __construct(private array $identity)
            {
            }

            public function verify(string $provider, string $idToken): array
            {
                return array_merge($this->identity, ['provider' => $provider]);
            }
        });
    }
}
