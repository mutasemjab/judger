<?php

namespace Database\Factories;

use App\Enums\CasePriority;
use App\Enums\CaseStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LegalCaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'category' => fake()->randomElement(['Civil', 'Criminal', 'Family', 'Commercial', 'Labor', 'Administrative']),
            'case_number' => fake()->optional()->numerify('CASE-####'),
            'court' => fake()->optional()->randomElement(['First Instance Court', 'Court of Appeal', 'Supreme Court']),
            'jurisdiction' => fake()->optional()->country(),
            'client_name' => fake()->optional()->name(),
            'opposing_party' => fake()->optional()->name(),
            'description' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(CaseStatus::values()),
            'priority' => fake()->randomElement(CasePriority::values()),
            'start_date' => fake()->optional()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => CaseStatus::Active->value]);
    }

    public function urgent(): static
    {
        return $this->state(['priority' => CasePriority::Urgent->value]);
    }
}
