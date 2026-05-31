<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super-admin', 'description' => 'Full system access'],
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'Administrative access'],
            ['name' => 'User', 'slug' => 'user', 'description' => 'Standard user'],
            ['name' => 'Lawyer', 'slug' => 'lawyer', 'description' => 'Lawyer user'],
            ['name' => 'Law Firm Owner', 'slug' => 'law-firm-owner', 'description' => 'Law firm owner'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['slug' => $role['slug']], $role);
        }

        $permissions = [
            ['name' => 'Manage Users', 'slug' => 'manage-users'],
            ['name' => 'Manage Knowledge Base', 'slug' => 'manage-knowledge-base'],
            ['name' => 'Manage Templates', 'slug' => 'manage-templates'],
            ['name' => 'View Dashboard', 'slug' => 'view-dashboard'],
            ['name' => 'Manage Subscriptions', 'slug' => 'manage-subscriptions'],
            ['name' => 'View Activity Logs', 'slug' => 'view-activity-logs'],
            ['name' => 'Broadcast Notifications', 'slug' => 'broadcast-notifications'],
            ['name' => 'Manage Cases', 'slug' => 'manage-cases'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['slug' => $permission['slug']], $permission);
        }

        $admin = Role::where('slug', 'admin')->first();
        $superAdmin = Role::where('slug', 'super-admin')->first();

        if ($admin) {
            $admin->permissions()->syncWithoutDetaching(Permission::all()->pluck('id'));
        }
        if ($superAdmin) {
            $superAdmin->permissions()->syncWithoutDetaching(Permission::all()->pluck('id'));
        }
    }
}
