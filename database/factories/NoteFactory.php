<?php

namespace Database\Factories;

use App\Enums\NoteType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'legal_case_id' => null,
            'title' => fake()->optional()->sentence(3),
            'content' => fake()->paragraphs(2, true),
            'type' => NoteType::Manual->value,
            'is_pinned' => false,
        ];
    }
}
