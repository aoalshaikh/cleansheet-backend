<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_email(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'password'
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'roles'
                ]
            ]);
    }

    public function test_users_can_authenticate_using_phone(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '+1234567890',
            'password' => bcrypt('password')
        ]);

        $response = $this->postJson('/api/v1/login', [
            'phone' => '+1234567890',
            'password' => 'password'
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => [
                    'id',
                    'name',
                    'phone',
                    'roles'
                ]
            ]);
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password'
        ]);

        $response->assertUnauthorized();
    }

    public function test_users_can_logout(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/logout');

        $response->assertOk();
        $this->assertGuest('api');
    }

    public function test_users_can_refresh_token(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/refresh');

        $response->assertOk()
            ->assertJsonStructure(['token']);

        $this->assertNotEquals($token, $response->json('token'));
    }

    public function test_users_cannot_access_protected_routes_without_token(): void
    {
        $response = $this->getJson('/api/v1/profile');
        $response->assertUnauthorized();
    }

    public function test_users_cannot_access_protected_routes_with_invalid_token(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/v1/profile');

        $response->assertUnauthorized();
    }

    public function test_users_cannot_access_protected_routes_with_expired_token(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        // Configure JWT to expire immediately
        config(['jwt.ttl' => 0]);
        $token = JWTAuth::fromUser($user);

        sleep(1); // Wait for token to expire

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/profile');

        $response->assertUnauthorized();

        // Reset JWT configuration
        config(['jwt.ttl' => 60]);
    }

    public function test_users_can_request_password_reset(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com'
        ]);

        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'test@example.com'
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'test@example.com'
        ]);
    }

    public function test_users_can_reset_password(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com'
        ]);

        // Request password reset
        $this->postJson('/api/v1/forgot-password', [
            'email' => 'test@example.com'
        ]);

        $token = DB::table('password_reset_tokens')
            ->where('email', 'test@example.com')
            ->first()
            ->token;

        // Reset password
        $response = $this->postJson('/api/v1/reset-password', [
            'email' => 'test@example.com',
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password'
        ]);

        $response->assertOk();

        // Try logging in with new password
        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'new-password'
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token']);
    }
}
