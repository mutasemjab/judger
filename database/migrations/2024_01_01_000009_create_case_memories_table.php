<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['fact', 'party', 'date', 'deadline', 'claim', 'defense', 'evidence', 'risk', 'task', 'strategy', 'general'])->nullable();
            $table->string('title')->nullable();
            $table->longText('content');
            $table->decimal('confidence', 3, 2)->nullable();
            $table->string('source')->nullable();
            $table->foreignId('source_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('legal_case_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_memories');
    }
};
