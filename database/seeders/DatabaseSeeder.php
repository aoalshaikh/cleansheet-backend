<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create roles and permissions
        $this->call(RolesAndPermissionsSeeder::class);

        // Create subscription plans
        $this->createSubscriptionPlans();

        if (app()->environment('local', 'development')) {
            // Create test data only in non-production environments
            $this->createTestData();
        }
    }

    /**
     * Create subscription plans.
     */
    protected function createSubscriptionPlans(): void
    {
        // Basic Plan
        SubscriptionPlan::create([
            'name' => 'Basic',
            'slug' => 'basic',
            'description' => 'Perfect for small academies',
            'price' => 49.99,
            'currency' => 'USD',
            'duration_in_days' => 30,
            'features' => [
                'teams' => true,
                'player_evaluations' => true,
                'attendance_tracking' => true,
                'match_management' => true,
                'notifications' => true,
                'limits' => [
                    'max_teams' => 3,
                    'max_players_per_team' => 20,
                    'max_coaches' => 5,
                ],
            ],
            'is_active' => true,
        ]);

        // Pro Plan
        SubscriptionPlan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'For growing academies',
            'price' => 99.99,
            'currency' => 'USD',
            'duration_in_days' => 30,
            'features' => [
                'teams' => true,
                'player_evaluations' => true,
                'attendance_tracking' => true,
                'match_management' => true,
                'notifications' => true,
                'advanced_analytics' => true,
                'custom_reports' => true,
                'limits' => [
                    'max_teams' => 10,
                    'max_players_per_team' => 30,
                    'max_coaches' => 15,
                ],
            ],
            'is_active' => true,
        ]);

        // Enterprise Plan
        SubscriptionPlan::create([
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'description' => 'For large academies',
            'price' => 199.99,
            'currency' => 'USD',
            'duration_in_days' => 30,
            'features' => [
                'teams' => true,
                'player_evaluations' => true,
                'attendance_tracking' => true,
                'match_management' => true,
                'notifications' => true,
                'advanced_analytics' => true,
                'custom_reports' => true,
                'api_access' => true,
                'white_label' => true,
                'limits' => [
                    'max_teams' => -1, // Unlimited
                    'max_players_per_team' => -1, // Unlimited
                    'max_coaches' => -1, // Unlimited
                ],
            ],
            'is_active' => true,
        ]);
    }

    /**
     * Create test data.
     */
    protected function createTestData(): void
    {
        // Create super admin
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'phone' => '+1234567890',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
        $superAdmin->assignRole(config('permission.super_admin_role'));

        // Create test organization with manager
        $manager = User::create([
            'name' => 'Test Manager',
            'email' => 'manager@example.com',
            'phone' => '+1234567891',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
        $manager->assignRole('manager');

        $organization = Organization::create([
            'tenant_id' => $manager->tenant_id,
            'name' => 'Test Academy',
            'slug' => 'test-academy',
            'description' => 'A test football academy',
            'metadata' => [
                'owner_id' => $manager->id,
            ],
        ]);

        // Create test coach
        $coach = User::create([
            'name' => 'Test Coach',
            'email' => 'coach@example.com',
            'phone' => '+1234567892',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'tenant_id' => $manager->tenant_id,
        ]);
        $coach->assignRole('coach');

        // Create test team
        $team = Team::create([
            'organization_id' => $organization->id,
            'coach_id' => $coach->id,
            'name' => 'Test Team',
            'slug' => 'test-team',
            'description' => 'A test team',
        ]);

        // Create test players
        for ($i = 1; $i <= 5; $i++) {
            $player = User::create([
                'name' => "Test Player {$i}",
                'email' => "player{$i}@example.com",
                'phone' => "+123456789{$i}",
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
                'tenant_id' => $manager->tenant_id,
            ]);
            $player->assignRole('player');

            // Add player to team
            $team->addPlayer($player);

            // If player is under 18, create a guardian
            if ($i <= 3) {
                $guardian = User::create([
                    'name' => "Guardian {$i}",
                    'email' => "guardian{$i}@example.com",
                    'phone' => "+123456799{$i}",
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'phone_verified_at' => now(),
                    'tenant_id' => $manager->tenant_id,
                    'metadata' => [
                        'is_guardian' => true,
                        'player_id' => $player->id,
                        'relationship' => 'parent',
                    ],
                ]);
                $guardian->assignRole('guardian');

                // Update player's metadata
                $player->update([
                    'metadata' => [
                        'guardian_id' => $guardian->id,
                        'date_of_birth' => now()->subYears(15)->format('Y-m-d'),
                    ],
                ]);
            }
        }
    }
}
