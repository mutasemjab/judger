<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Models\CaseDocument;
use App\Models\DocumentInsight;
use App\Models\UserNotification;
use App\Services\AI\AiProviderManager;
use App\Services\Documents\DocumentAnalysisService;
use App\Services\Documents\DocumentTextExtractor;
use App\Services\Documents\TextChunker;
use App\Services\Vector\QdrantVectorStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessCaseDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(private int $documentId) {}

    public function handle(): void
    {
        $document = CaseDocument::findOrFail($this->documentId);
        $document->update(['status' => DocumentStatus::Processing->value]);

        $collectionName = config('ai.qdrant_case_collection');
        $vectorSize = config('ai.embedding_dimensions', 1536);

        $vectorStore = new QdrantVectorStore();
        $vectorStore->ensureCollection($collectionName, $vectorSize);
        $vectorStore->deleteByFilter($collectionName, ['case_document_id' => $document->id]);

        $extractor = new DocumentTextExtractor();
        $pages = $extractor->extract($document->file_path, $document->disk);

        $fullText = implode("\n\n", array_column($pages, 'text'));
        if (!empty($fullText)) {
            $document->update(['extracted_text' => mb_substr($fullText, 0, 65000)]);
        }

        $chunker = new TextChunker();
        $chunks = $chunker->chunk($pages);

        $provider = AiProviderManager::resolve();
        $pointsCount = 0;

        foreach ($chunks as $chunk) {
            if (empty(trim($chunk['content']))) {
                continue;
            }

            $embedding = $provider->embedding($chunk['content']);
            $pointId = "case_{$document->id}_{$chunk['chunk_index']}";

            $vectorStore->upsertPoint($collectionName, $pointId, $embedding, [
                'source_type' => 'case_document',
                'case_document_id' => $document->id,
                'legal_case_id' => $document->legal_case_id,
                'user_id' => $document->user_id,
                'document_name' => $document->original_name,
                'document_type' => $document->document_type ?? 'document',
                'page_number' => $chunk['page_number'],
                'chunk_index' => $chunk['chunk_index'],
                'content' => $chunk['content'],
                'snippet' => $chunk['snippet'],
                'status' => 'processed',
            ]);

            $pointsCount++;
        }

        $analysisService = new DocumentAnalysisService();
        $analysis = $analysisService->analyze(mb_substr($fullText, 0, 8000), $document->document_type ?? 'document');

        DocumentInsight::updateOrCreate(
            ['case_document_id' => $document->id],
            [
                'legal_case_id' => $document->legal_case_id,
                'user_id' => $document->user_id,
                'summary' => $analysis['summary'] ?? '',
                'insights' => $analysis['insights'] ?? [],
                'highlights' => $analysis['highlights'] ?? [],
                'detected_entities' => $analysis['detected_names'] ?? [],
                'detected_dates' => $analysis['detected_dates'] ?? [],
                'detected_risks' => $analysis['detected_risks'] ?? [],
                'missing_documents' => $analysis['missing_documents'] ?? [],
                'disclaimer' => $analysis['disclaimer'],
            ]
        );

        $document->update([
            'status' => DocumentStatus::Analyzed->value,
            'qdrant_collection' => $collectionName,
            'qdrant_points_count' => $pointsCount,
            'summary' => $analysis['summary'] ?? null,
            'insights' => $analysis['insights'] ?? null,
            'important_highlights' => $analysis['highlights'] ?? null,
            'detected_names' => $analysis['detected_names'] ?? null,
            'detected_dates' => $analysis['detected_dates'] ?? null,
            'detected_case_numbers' => $analysis['detected_case_numbers'] ?? null,
            'missing_document_suggestions' => $analysis['missing_documents'] ?? null,
            'processed_at' => now(),
            'processing_error' => null,
        ]);

        UserNotification::create([
            'user_id' => $document->user_id,
            'type' => 'analysis_ready',
            'title' => 'Document Analysis Complete',
            'body' => "Your document \"{$document->original_name}\" has been analyzed.",
            'data' => [
                'document_id' => $document->id,
                'case_id' => $document->legal_case_id,
            ],
        ]);
    }

    public function failed(Throwable $exception): void
    {
        CaseDocument::where('id', $this->documentId)->update([
            'status' => DocumentStatus::Failed->value,
            'processing_error' => $exception->getMessage(),
        ]);
    }
}
