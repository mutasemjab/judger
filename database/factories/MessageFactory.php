<?php

namespace Database\Factories;

use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'user_id' => User::factory(),
            'role' => MessageRole::User->value,
            'content' => fake()->paragraph(),
            'is_pinned' => false,
        ];
    }

    public function assistant(): static
    {
        return $this->state([
            'role' => MessageRole::Assistant->value,
            'user_id' => null,
            'disclaimer' => config('ai.legal_disclaimer'),
        ]);
    }
}
