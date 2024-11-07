<?php

namespace Tests\Feature\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('public');
        
        $this->tenant = Tenant::factory()->create();
        
        /** @var User $user */
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'preferences' => [
                'notifications' => [
                    'email' => true,
                    'sms' => false
                ],
                'theme' => 'light'
            ]
        ]);
        $this->user = $user;
    }

    public function test_can_view_profile(): void
    {
        $token = $this->getJwtToken($this->user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/profile');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'phone' => '+1234567890',
                    'preferences' => [
                        'notifications' => [
                            'email' => true,
                            'sms' => false
                        ],
                        'theme' => 'light'
                    ]
                ]
            ]);
    }

    public function test_can_update_profile(): void
    {
        $token = $this->getJwtToken($this->user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile', [
                'name' => 'John Smith',
                'phone' => '+1987654321'
            ]);

        $response->assertOk();
        
        $this->user->refresh();
        $this->assertEquals('John Smith', $this->user->name);
        $this->assertEquals('+1987654321', $this->user->phone);
    }

    public function test_can_update_password(): void
    {
        $token = $this->getJwtToken($this->user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password'
            ]);

        $response->assertOk();
        
        $this->user->refresh();
        $this->assertTrue(Hash::check('new-password', $this->user->password));
    }

    public function test_can_update_avatar(): void
    {
        $token = $this->getJwtToken($this->user);
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $file
            ]);

        $response->assertOk();
        
        $this->user->refresh();
        $this->assertNotNull($this->user->avatar_path);
        $this->assertTrue(Storage::disk('public')->exists($this->user->avatar_path));
    }

    public function test_can_update_preferences(): void
    {
        $token = $this->getJwtToken($this->user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile/preferences', [
                'preferences' => [
                    'notifications' => [
                        'email' => false,
                        'sms' => true
                    ],
                    'theme' => 'dark'
                ]
            ]);

        $response->assertOk();
        
        $this->user->refresh();
        $this->assertEquals('dark', $this->user->getPreference('theme'));
        $this->assertTrue($this->user->getPreference('notifications.sms'));
        $this->assertFalse($this->user->getPreference('notifications.email'));
    }

    public function test_validates_profile_update(): void
    {
        $token = $this->getJwtToken($this->user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile', [
                'email' => 'invalid-email',
                'phone' => 'invalid-phone'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'phone']);
    }

    public function test_validates_password_update(): void
    {
        $token = $this->getJwtToken($this->user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'new',
                'password_confirmation' => 'different'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'current_password',
                'password'
            ]);
    }

    public function test_validates_avatar_update(): void
    {
        $token = $this->getJwtToken($this->user);

        // Test non-image file
        $file = UploadedFile::fake()->create('document.pdf');
        
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $file
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);

        // Test oversized image
        $largeImage = UploadedFile::fake()->image('large-avatar.jpg')->size(2049);
        
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $largeImage
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    private function getJwtToken(User $user): string
    {
        return \Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);
    }
}
