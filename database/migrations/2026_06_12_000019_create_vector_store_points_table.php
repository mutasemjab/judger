<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vector_store_points', function (Blueprint $table) {
            $table->id();
            $table->string('collection_name');
            $table->string('point_key');
            $table->string('source_type')->nullable();
            $table->foreignId('knowledge_document_id')->nullable()->constrained('knowledge_documents')->nullOnDelete();
            $table->foreignId('case_document_id')->nullable()->constrained('case_documents')->nullOnDelete();
            $table->foreignId('legal_case_id')->nullable()->constrained('legal_cases')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->nullable();
            $table->string('category')->nullable();
            $table->string('document_name')->nullable();
            $table->string('document_type')->nullable();
            $table->unsignedInteger('page_number')->nullable();
            $table->unsignedInteger('chunk_index')->nullable();
            $table->longText('vector');
            $table->longText('payload');
            $table->timestamps();

            $table->unique(['collection_name', 'point_key']);
            $table->index(['collection_name', 'status']);
            $table->index(['collection_name', 'knowledge_document_id']);
            $table->index(['collection_name', 'case_document_id']);
            $table->index(['collection_name', 'legal_case_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vector_store_points');
    }
};
