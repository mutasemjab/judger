<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\UserType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'password' => bcrypt('password'),
            'user_type' => fake()->randomElement(UserType::values()),
            'account_status' => AccountStatus::Active->value,
            'email_verified_at' => now(),
            'language' => 'en',
            'theme' => 'system',
            'biometric_enabled' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function lawyer(): static
    {
        return $this->state(['user_type' => UserType::Lawyer->value]);
    }

    public function individual(): static
    {
        return $this->state(['user_type' => UserType::Individual->value]);
    }

    public function lawStudent(): static
    {
        return $this->state(['user_type' => UserType::LawStudent->value]);
    }

    public function suspended(): static
    {
        return $this->state(['account_status' => AccountStatus::Suspended->value]);
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }
}
