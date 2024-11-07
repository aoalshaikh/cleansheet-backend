<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Organization permissions
            'view organizations',
            'create organizations',
            'update organizations',
            'delete organizations',
            'manage organization members',
            'manage organization settings',
            'view organization stats',
            'view organization financials',

            // Team permissions
            'view teams',
            'create teams',
            'update teams',
            'delete teams',
            'manage team members',
            'manage team schedule',
            'manage team tiers',
            'view team stats',
            'manage team matches',

            // Player permissions
            'view players',
            'create players',
            'update players',
            'delete players',
            'evaluate players',
            'view player stats',
            'manage player subscriptions',

            // Match permissions
            'view matches',
            'create matches',
            'update matches',
            'delete matches',
            'manage match events',
            'manage match lineups',
            'view match stats',

            // Evaluation permissions
            'create evaluations',
            'update evaluations',
            'delete evaluations',
            'view evaluations',
            'export evaluations',

            // Schedule permissions
            'view schedules',
            'create schedules',
            'update schedules',
            'delete schedules',
            'manage attendance',

            // Notification permissions
            'send notifications',
            'manage notification templates',
            'view notifications',

            // Subscription permissions
            'manage subscriptions',
            'view subscription plans',
            'create subscriptions',
            'cancel subscriptions',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        
        // Super Admin
        $superAdmin = Role::create(['name' => config('permission.super_admin_role', 'super-admin')]);
        // Super admin has all permissions
        $superAdmin->givePermissionTo(Permission::all());

        // Manager
        $manager = Role::create(['name' => 'manager']);
        $manager->givePermissionTo([
            'view organizations',
            'update organizations',
            'manage organization members',
            'manage organization settings',
            'view organization stats',
            'view organization financials',
            'view teams',
            'create teams',
            'update teams',
            'delete teams',
            'manage team members',
            'manage team schedule',
            'manage team tiers',
            'view team stats',
            'manage team matches',
            'view players',
            'create players',
            'update players',
            'delete players',
            'view player stats',
            'view matches',
            'create matches',
            'update matches',
            'delete matches',
            'manage match events',
            'manage match lineups',
            'view match stats',
            'view evaluations',
            'export evaluations',
            'view schedules',
            'create schedules',
            'update schedules',
            'delete schedules',
            'manage attendance',
            'send notifications',
            'manage notification templates',
            'view notifications',
        ]);

        // Coach
        $coach = Role::create(['name' => 'coach']);
        $coach->givePermissionTo([
            'view teams',
            'update teams',
            'manage team members',
            'manage team schedule',
            'manage team tiers',
            'view team stats',
            'manage team matches',
            'view players',
            'evaluate players',
            'view player stats',
            'view matches',
            'create matches',
            'update matches',
            'manage match events',
            'manage match lineups',
            'view match stats',
            'create evaluations',
            'update evaluations',
            'delete evaluations',
            'view evaluations',
            'view schedules',
            'create schedules',
            'update schedules',
            'manage attendance',
            'send notifications',
            'view notifications',
        ]);

        // Player
        $player = Role::create(['name' => 'player']);
        $player->givePermissionTo([
            'view teams',
            'view team stats',
            'view matches',
            'view match stats',
            'view evaluations',
            'view schedules',
            'view notifications',
            'manage player subscriptions',
        ]);

        // Guardian
        $guardian = Role::create(['name' => 'guardian']);
        $guardian->givePermissionTo([
            'view players',
            'update players',
            'view player stats',
            'view evaluations',
            'export evaluations',
            'view schedules',
            'view notifications',
            'manage player subscriptions',
        ]);
    }
}
