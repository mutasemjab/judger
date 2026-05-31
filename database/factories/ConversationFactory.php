<?php

namespace Database\Factories;

use App\Enums\ConversationType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'legal_case_id' => null,
            'type' => ConversationType::General->value,
            'title' => fake()->optional()->sentence(3),
        ];
    }

    public function general(): static
    {
        return $this->state([
            'type' => ConversationType::General->value,
            'legal_case_id' => null,
        ]);
    }
}
