<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_can_register_user(): void
    {
        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1234567890',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'date_of_birth' => '2000-01-01',
            'metadata' => [
                'position' => 'forward',
                'preferred_foot' => 'right'
            ]
        ];

        $response = $this->postJson('/api/v1/register', $userData);

        $response->assertCreated();
        
        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);

        /** @var User $user */
        $user = User::where('email', 'john.doe@example.com')->first();
        $this->assertTrue($user->hasRole('player'));
    }

    public function test_can_register_organization(): void
    {
        $organizationData = [
            'organization' => [
                'name' => 'Soccer Club',
                'description' => 'A professional soccer club',
                'contact_email' => 'contact@soccerclub.com',
                'contact_phone' => '+1234567890',
                'address' => '123 Sports St',
                'city' => 'Sportstown',
                'state' => 'ST',
                'country' => 'United States',
                'postal_code' => '12345'
            ],
            'admin' => [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@soccerclub.com',
                'phone' => '+1987654321',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!'
            ]
        ];

        $response = $this->postJson('/api/v1/organizations/signup', $organizationData);

        $response->assertCreated();
        
        // Verify tenant creation
        $this->assertDatabaseHas('tenants', [
            'name' => 'Soccer Club'
        ]);

        // Verify organization creation
        $this->assertDatabaseHas('organizations', [
            'name' => 'Soccer Club',
            'contact_email' => 'contact@soccerclub.com'
        ]);

        // Verify admin user creation
        $this->assertDatabaseHas('users', [
            'email' => 'admin@soccerclub.com',
            'first_name' => 'Admin',
            'last_name' => 'User'
        ]);

        /** @var User $admin */
        $admin = User::where('email', 'admin@soccerclub.com')->first();
        $this->assertTrue($admin->hasRole('admin'));
    }

    public function test_validates_required_user_fields(): void
    {
        $response = $this->postJson('/api/v1/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'first_name',
                'last_name',
                'email',
                'password'
            ]);
    }

    public function test_validates_required_organization_fields(): void
    {
        $response = $this->postJson('/api/v1/organizations/signup', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'organization.name',
                'organization.contact_email',
                'admin.first_name',
                'admin.email',
                'admin.password'
            ]);
    }

    public function test_prevents_duplicate_email_registration(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com'
        ]);

        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'existing@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!'
        ];

        $response = $this->postJson('/api/v1/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_validates_password_requirements(): void
    {
        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak'
        ];

        $response = $this->postJson('/api/v1/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_validates_phone_format(): void
    {
        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => 'invalid-phone',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!'
        ];

        $response = $this->postJson('/api/v1/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_organization_signup_creates_trial_subscription(): void
    {
        $organizationData = [
            'organization' => [
                'name' => 'Soccer Club',
                'contact_email' => 'contact@soccerclub.com'
            ],
            'admin' => [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@soccerclub.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!'
            ]
        ];

        $response = $this->postJson('/api/v1/organizations/signup', $organizationData);

        $response->assertCreated();

        /** @var Organization $organization */
        $organization = Organization::where('name', 'Soccer Club')->first();
        
        $this->assertTrue($organization->isInTrial());
        $this->assertTrue($organization->hasAccess());
    }

    public function test_organization_signup_sends_welcome_notification(): void
    {
        $organizationData = [
            'organization' => [
                'name' => 'Soccer Club',
                'contact_email' => 'contact@soccerclub.com'
            ],
            'admin' => [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@soccerclub.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!'
            ]
        ];

        $response = $this->postJson('/api/v1/organizations/signup', $organizationData);

        $response->assertCreated();

        /** @var User $admin */
        $admin = User::where('email', 'admin@soccerclub.com')->first();

        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $admin->id,
            'template_id' => 1, // Welcome template ID
            'channel' => 'email'
        ]);
    }

    public function test_organization_signup_with_existing_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $organizationData = [
            'organization' => [
                'name' => 'Soccer Club',
                'contact_email' => 'contact@soccerclub.com',
                'tenant_id' => $tenant->id
            ],
            'admin' => [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@soccerclub.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!'
            ]
        ];

        $response = $this->postJson('/api/v1/organizations/signup', $organizationData);

        $response->assertCreated();

        $organization = Organization::where('name', 'Soccer Club')->first();
        $this->assertEquals($tenant->id, $organization->tenant_id);
    }
}
