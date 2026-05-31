<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\UserType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'admin@judgerai.app',
                'password' => bcrypt('password'),
                'user_type' => UserType::Lawyer->value,
                'account_status' => AccountStatus::Active->value,
                'email_verified_at' => now(),
                'role' => 'super-admin',
            ],
            [
                'name' => 'Demo Lawyer',
                'email' => 'lawyer@judgerai.app',
                'password' => bcrypt('password'),
                'user_type' => UserType::Lawyer->value,
                'account_status' => AccountStatus::Active->value,
                'email_verified_at' => now(),
                'role' => 'lawyer',
            ],
            [
                'name' => 'Demo Individual',
                'email' => 'user@judgerai.app',
                'password' => bcrypt('password'),
                'user_type' => UserType::Individual->value,
                'account_status' => AccountStatus::Active->value,
                'email_verified_at' => now(),
                'role' => 'user',
            ],
            [
                'name' => 'Demo Law Student',
                'email' => 'student@judgerai.app',
                'password' => bcrypt('password'),
                'user_type' => UserType::LawStudent->value,
                'account_status' => AccountStatus::Active->value,
                'email_verified_at' => now(),
                'role' => 'user',
            ],
        ];

        foreach ($users as $userData) {
            $roleName = $userData['role'];
            unset($userData['role']);

            $user = User::firstOrCreate(['email' => $userData['email']], $userData);
            $role = Role::where('slug', $roleName)->first();
            if ($role) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }
        }
    }
}
