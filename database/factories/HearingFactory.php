<?php

namespace Database\Factories;

use App\Enums\HearingStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HearingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'legal_case_id' => null,
            'title' => fake()->sentence(3),
            'date' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'start_time' => fake()->optional()->time('H:i'),
            'location' => fake()->optional()->address(),
            'status' => HearingStatus::Scheduled->value,
        ];
    }
}
