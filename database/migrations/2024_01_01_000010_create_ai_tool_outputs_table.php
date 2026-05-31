<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tool_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_case_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('case_document_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('tool_type', [
                'case_summarizer',
                'document_summarizer',
                'contract_analyzer',
                'risk_estimator',
                'memo_generator',
                'legal_notice_generator',
                'demand_letter_generator',
                'timeline_generator',
                'checklist_generator',
                'client_explanation_simplifier',
                'defense_assistant',
            ]);
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->longText('content')->nullable();
            $table->text('disclaimer');
            $table->string('source_type')->nullable();
            $table->json('sources')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('tool_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tool_outputs');
    }
};
