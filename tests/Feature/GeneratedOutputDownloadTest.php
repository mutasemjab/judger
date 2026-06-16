<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\TemplateCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GeneratedOutputDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.provider' => 'mock']);
        Storage::fake('local');
    }

    public function test_ai_tool_response_includes_downloadable_file(): void
    {
        $user = User::factory()->create();

        $response = $this->apiAs($user)->postJson('/api/v1/ai-tools/memo-generator', [
            'text' => 'Prepare a memo about a contract breach and payment default.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.presentation.variant', 'generated_legal_document')
            ->assertJsonPath('data.download.available', true);

        $downloadUrl = $response->json('data.download.url');
        $this->assertNotEmpty($downloadUrl);

        $downloadResponse = $this->apiAs($user)->get($downloadUrl);
        $downloadResponse->assertOk();
        $this->assertStringContainsString('.md', $downloadResponse->headers->get('content-disposition', ''));
    }

    public function test_generated_template_document_includes_downloadable_file(): void
    {
        $user = User::factory()->create();

        $category = TemplateCategory::query()->create([
            'name' => 'Notices',
            'slug' => 'notices',
        ]);

        $template = Template::query()->create([
            'template_category_id' => $category->id,
            'title' => 'Payment Notice',
            'slug' => 'payment-notice-' . Str::lower(Str::random(6)),
            'content' => "Dear {{client_name}},\n\nCase: {{case_number}}",
            'variables' => ['client_name', 'case_number'],
            'is_active' => true,
        ]);

        $response = $this->apiAs($user)->postJson("/api/v1/templates/{$template->id}/generate", [
            'variables' => [
                'client_name' => 'Mona Salem',
                'case_number' => 'CASE-2026-18',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.download.available', true);

        $downloadUrl = $response->json('data.download.url');
        $this->assertNotEmpty($downloadUrl);

        $downloadResponse = $this->apiAs($user)->get($downloadUrl);
        $downloadResponse->assertOk();
        $this->assertStringContainsString('.md', $downloadResponse->headers->get('content-disposition', ''));
    }

    private function apiAs(User $user): self
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', "Bearer {$token}");
    }
}
