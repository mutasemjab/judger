<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class TemplateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'template_category_id' => $this->template_category_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'variables' => $this->variables,
            'is_active' => $this->is_active,
            'category' => $this->whenLoaded('category', fn() => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
