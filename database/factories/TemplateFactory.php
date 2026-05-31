<?php

namespace Database\Factories;

use App\Models\TemplateCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TemplateFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(3);
        return [
            'template_category_id' => TemplateCategory::factory(),
            'title' => $title,
            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->optional()->sentence(),
            'content' => fake()->paragraphs(3, true) . "\n\n{{client_name}}\n{{case_number}}\n{{date}}",
            'variables' => ['client_name', 'case_number', 'date', 'court'],
            'is_active' => true,
        ];
    }
}
