<?php

namespace App\Services\Knowledge;

use App\Enums\KnowledgeDocumentStatus;
use App\Jobs\ProcessKnowledgeDocumentJob;
use App\Models\KnowledgeDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class KnowledgeDocumentBackgroundLauncher
{
    public function start(KnowledgeDocument $document, bool $forceRestart = false): KnowledgeDocument
    {
        $previousStatus = $document->status?->value ?? KnowledgeDocumentStatus::Uploaded->value;

        $document->update([
            'status' => KnowledgeDocumentStatus::Processing->value,
            'processing_error' => __('messages.processing_preparing'),
            'processing_started_at' => now(),
            'stop_requested_at' => null,
            'processed_at' => null,
            'qdrant_points_count' => 0,
            'processed_chunks_count' => 0,
            'total_chunks_count' => 0,
        ]);

        try {
            Storage::disk('local')->delete("knowledge_processing/document_{$document->id}.json");
            app()->terminating(function () use ($document): void {
                ProcessKnowledgeDocumentJob::processNow($document->id);
            });

            Log::info('knowledge.processing.background_started', [
                'document_id' => $document->id,
                'restart' => $forceRestart,
                'mode' => 'terminating_callback',
            ]);

            return KnowledgeDocument::with('uploadedBy')->findOrFail($document->id);
        } catch (Throwable $exception) {
            $document->update([
                'status' => $previousStatus,
                'processing_error' => __('messages.processing_launch_failed'),
                'processing_started_at' => null,
                'stop_requested_at' => null,
                'processed_at' => null,
                'qdrant_points_count' => 0,
                'processed_chunks_count' => 0,
                'total_chunks_count' => 0,
            ]);

            Log::error('knowledge.processing.background_failed', [
                'document_id' => $document->id,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw new RuntimeException(__('messages.processing_launch_failed'));
        }
    }
}
