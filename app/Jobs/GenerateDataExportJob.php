<?php

namespace App\Jobs;

use App\Enums\DataExportStatus;
use App\Models\DataExportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateDataExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(private int $requestId) {}

    public function handle(): void
    {
        $request = DataExportRequest::findOrFail($this->requestId);
        $request->update(['status' => DataExportStatus::Processing->value]);

        try {
            $user = $request->user;
            $data = [
                'user' => $user->only(['name', 'email', 'user_type', 'created_at']),
                'cases' => $user->legalCases()->get()->toArray(),
                'tasks' => $user->tasks()->get()->toArray(),
                'notes' => $user->notes()->get()->toArray(),
                'hearings' => $user->hearings()->get()->toArray(),
                'exported_at' => now()->toIso8601String(),
            ];

            $fileName = "exports/user_{$user->id}_export_" . now()->timestamp . ".json";
            Storage::put($fileName, json_encode($data, JSON_PRETTY_PRINT));

            $request->update([
                'status' => DataExportStatus::Completed->value,
                'file_path' => $fileName,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $request->update(['status' => DataExportStatus::Failed->value]);
        }
    }
}
