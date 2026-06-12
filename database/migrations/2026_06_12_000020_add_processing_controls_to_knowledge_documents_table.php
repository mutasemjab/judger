<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->unsignedInteger('processed_chunks_count')->default(0)->after('qdrant_points_count');
            $table->unsignedInteger('total_chunks_count')->default(0)->after('processed_chunks_count');
            $table->timestamp('processing_started_at')->nullable()->after('processing_error');
            $table->timestamp('stop_requested_at')->nullable()->after('processing_started_at');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE knowledge_documents MODIFY status ENUM('uploaded','processing','processed','failed','cancelled') NOT NULL DEFAULT 'uploaded'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("UPDATE knowledge_documents SET status = 'failed' WHERE status = 'cancelled'");
            DB::statement("ALTER TABLE knowledge_documents MODIFY status ENUM('uploaded','processing','processed','failed') NOT NULL DEFAULT 'uploaded'");
        }

        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->dropColumn([
                'processed_chunks_count',
                'total_chunks_count',
                'processing_started_at',
                'stop_requested_at',
            ]);
        });
    }
};
