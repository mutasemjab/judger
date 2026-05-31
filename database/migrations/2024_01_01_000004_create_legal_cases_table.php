<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('category')->nullable();
            $table->string('case_number')->nullable();
            $table->string('court')->nullable();
            $table->string('court_name')->nullable();
            $table->string('jurisdiction')->nullable();
            $table->string('client_name')->nullable();
            $table->string('opposing_party')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'pending', 'closed', 'archived'])->default('active');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->timestamp('next_hearing_at')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('tags')->nullable();
            $table->longText('notes')->nullable();
            $table->longText('summary')->nullable();
            $table->longText('ai_summary')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('team_id');
            $table->index('status');
            $table->index('priority');
            $table->index('next_hearing_at');
            $table->index('case_number');
            $table->index('created_at');
        });

        Schema::create('case_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color')->nullable();
            $table->timestamps();
        });

        Schema::create('case_case_tag', function (Blueprint $table) {
            $table->foreignId('case_id')->constrained('legal_cases')->cascadeOnDelete();
            $table->foreignId('case_tag_id')->constrained('case_tags')->cascadeOnDelete();
            $table->primary(['case_id', 'case_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_case_tag');
        Schema::dropIfExists('case_tags');
        Schema::dropIfExists('legal_cases');
    }
};
