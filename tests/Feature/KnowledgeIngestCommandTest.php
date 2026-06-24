<?php

namespace Tests\Feature;

use App\Enums\KnowledgeDocumentStatus;
use App\Models\KnowledgeDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class KnowledgeIngestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_a_local_directory_recursively(): void
    {
        Storage::fake('local');
        $sourceDir = $this->makeTempDirectory();

        try {
            mkdir($sourceDir.'/nested', 0777, true);
            file_put_contents($sourceDir.'/guide.txt', 'Legal guide content for import.');
            file_put_contents($sourceDir.'/nested/brief.docx', 'Fake docx body for registration.');
            file_put_contents($sourceDir.'/ignored.png', 'not supported');

            $this->artisan('knowledge:ingest', [
                'path' => $sourceDir,
                '--category' => 'Civil Law',
                '--register-only' => true,
            ])->assertExitCode(0);

            $this->assertDatabaseCount('knowledge_documents', 2);
            $this->assertDatabaseHas('knowledge_documents', [
                'original_name' => 'guide.txt',
                'category' => 'Civil Law',
                'status' => KnowledgeDocumentStatus::Uploaded->value,
            ]);
            $this->assertDatabaseHas('knowledge_documents', [
                'original_name' => 'brief.docx',
                'category' => 'Civil Law',
                'status' => KnowledgeDocumentStatus::Uploaded->value,
            ]);

            foreach (KnowledgeDocument::query()->get() as $document) {
                Storage::disk('local')->assertExists($document->file_path);
            }
        } finally {
            $this->deleteDirectory($sourceDir);
        }
    }

    public function test_dry_run_does_not_create_documents(): void
    {
        Storage::fake('local');
        $sourceDir = $this->makeTempDirectory();

        try {
            file_put_contents($sourceDir.'/guide.txt', 'Legal guide content for import.');

            $this->artisan('knowledge:ingest', [
                'path' => $sourceDir,
                '--dry-run' => true,
            ])->assertExitCode(0);

            $this->assertDatabaseCount('knowledge_documents', 0);
        } finally {
            $this->deleteDirectory($sourceDir);
        }
    }

    public function test_process_option_generates_embeddings_inline(): void
    {
        Storage::fake('local');
        $sourceDir = $this->makeTempDirectory();

        try {
            file_put_contents(
                $sourceDir.'/law.txt',
                str_repeat('This legal knowledge paragraph explains procedure and evidence. ', 40)
            );

            $this->artisan('knowledge:ingest', [
                'path' => $sourceDir,
                '--category' => 'Procedure',
                '--process' => true,
            ])->assertExitCode(0);

            $this->assertDatabaseHas('knowledge_documents', [
                'original_name' => 'law.txt',
                'category' => 'Procedure',
                'status' => KnowledgeDocumentStatus::Processed->value,
                'processed_chunks_count' => 1,
                'qdrant_points_count' => 1,
            ]);
        } finally {
            $this->deleteDirectory($sourceDir);
        }
    }

    private function makeTempDirectory(): string
    {
        $path = sys_get_temp_dir().'/judger-kb-'.Str::random(12);

        if (! mkdir($path, 0777, true) && ! is_dir($path)) {
            $this->fail('Unable to create temporary directory.');
        }

        return $path;
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
