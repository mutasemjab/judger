<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('legal_case_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('role', ['user', 'assistant', 'system'])->default('user');
            $table->longText('content');
            $table->enum('source_type', ['case_document', 'knowledge_base', 'mixed', 'web', 'none'])->nullable();
            $table->json('sources')->nullable();
            $table->json('metadata')->nullable();
            $table->text('disclaimer')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->foreignId('parent_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('regenerated_from_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamps();

            $table->index('conversation_id');
            $table->index('user_id');
            $table->index('legal_case_id');
            $table->index('role');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
