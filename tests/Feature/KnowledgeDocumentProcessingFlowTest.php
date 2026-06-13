<?php

namespace Tests\Feature;

use App\Enums\KnowledgeDocumentStatus;
use App\Models\KnowledgeDocument;
use App\Services\Knowledge\KnowledgeDocumentBackgroundLauncher;
use App\Services\Knowledge\KnowledgeDocumentStepProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class KnowledgeDocumentProcessingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_process_now_uses_step_processor_instead_of_background_launcher(): void
    {
        $this->withoutMiddleware();

        $document = KnowledgeDocument::query()->create([
            'title' => 'Arabic Knowledge Guide',
            'category' => 'Procedure',
            'file_name' => 'arabic-guide.pdf',
            'file_path' => 'knowledge/arabic-guide.pdf',
            'original_name' => 'arabic-guide.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 4096,
            'status' => KnowledgeDocumentStatus::Uploaded->value,
        ]);

        $stepProcessor = Mockery::mock(KnowledgeDocumentStepProcessor::class);
        $stepProcessor->shouldReceive('processNextStep')
            ->once()
            ->withAnyArgs()
            ->andReturnUsing(fn () => KnowledgeDocument::with('uploadedBy')->findOrFail($document->id));
        $this->app->instance(KnowledgeDocumentStepProcessor::class, $stepProcessor);

        $launcher = Mockery::mock(KnowledgeDocumentBackgroundLauncher::class);
        $launcher->shouldNotReceive('start');
        $this->app->instance(KnowledgeDocumentBackgroundLauncher::class, $launcher);

        $this->post(route('admin.knowledge.process-now', ['knowledgeDocument' => $document]), [], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('id', $document->id);
    }

    public function test_stale_processing_uses_the_configured_timeout_window(): void
    {
        $this->withoutMiddleware();

        $document = KnowledgeDocument::query()->create([
            'title' => 'Large Arabic Digest',
            'category' => 'Cassation',
            'file_name' => 'large-arabic-digest.pdf',
            'file_path' => 'knowledge/large-arabic-digest.pdf',
            'original_name' => 'large-arabic-digest.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 4_000_000,
            'status' => KnowledgeDocumentStatus::Processing->value,
            'processing_started_at' => now()->subMinutes(20),
        ]);

        $document->timestamps = false;
        $document->forceFill([
            'updated_at' => now()->subMinutes(20),
        ])->saveQuietly();
        $document->timestamps = true;

        Config::set('ai.knowledge_processing_stale_minutes', 30);

        $this->get(route('admin.knowledge.statuses', ['ids' => $document->id]), [
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('data.0.id', $document->id)
            ->assertJsonPath('data.0.status', KnowledgeDocumentStatus::Processing->value);

        Config::set('ai.knowledge_processing_stale_minutes', 10);

        $this->get(route('admin.knowledge.statuses', ['ids' => $document->id]), [
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('data.0.id', $document->id)
            ->assertJsonPath('data.0.status', KnowledgeDocumentStatus::Failed->value)
            ->assertJsonPath('data.0.processing_error', __('messages.processing_timed_out'));
    }
}
