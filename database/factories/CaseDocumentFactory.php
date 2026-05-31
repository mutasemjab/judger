<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CaseDocumentFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(3, true) . '.pdf';
        return [
            'legal_case_id' => LegalCase::factory(),
            'user_id' => User::factory(),
            'original_name' => $name,
            'file_name' => str_replace(' ', '_', $name),
            'file_path' => 'case_documents/' . str_replace(' ', '_', $name),
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(10000, 5000000),
            'document_type' => fake()->randomElement(['contract', 'court_order', 'evidence', 'correspondence', 'other']),
            'status' => DocumentStatus::Uploaded->value,
        ];
    }

    public function analyzed(): static
    {
        return $this->state([
            'status' => DocumentStatus::Analyzed->value,
            'qdrant_points_count' => fake()->numberBetween(1, 20),
            'processed_at' => now(),
        ]);
    }
}
