<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('disk')->default('local');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('document_type')->nullable();
            $table->enum('status', ['uploaded', 'processing', 'analyzed', 'failed'])->default('uploaded');
            $table->enum('ocr_status', ['pending', 'processing', 'completed', 'failed', 'not_required'])->nullable();
            $table->longText('extracted_text')->nullable();
            $table->longText('summary')->nullable();
            $table->json('insights')->nullable();
            $table->json('important_highlights')->nullable();
            $table->json('detected_names')->nullable();
            $table->json('detected_dates')->nullable();
            $table->json('detected_case_numbers')->nullable();
            $table->json('missing_document_suggestions')->nullable();
            $table->string('qdrant_collection')->nullable();
            $table->unsignedInteger('qdrant_points_count')->default(0);
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('legal_case_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('document_type');
        });

        Schema::create('document_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->longText('summary')->nullable();
            $table->json('insights')->nullable();
            $table->json('highlights')->nullable();
            $table->json('detected_entities')->nullable();
            $table->json('detected_dates')->nullable();
            $table->json('detected_risks')->nullable();
            $table->json('missing_documents')->nullable();
            $table->text('disclaimer');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_insights');
        Schema::dropIfExists('case_documents');
    }
};
