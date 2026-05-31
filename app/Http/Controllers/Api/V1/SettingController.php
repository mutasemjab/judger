<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\UserResource;
use App\Jobs\GenerateDataExportJob;
use App\Models\DataExportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends BaseApiController
{
    public function getSettings(): JsonResponse
    {
        $user = auth('api')->user();
        return $this->success([
            'profile' => new UserResource($user),
            'language' => $user->language,
            'theme' => $user->theme,
            'notification_preferences' => $user->notification_preferences ?? [],
            'privacy_settings' => $user->privacy_settings ?? [],
            'security_settings' => $user->security_settings ?? [],
            'biometric_enabled' => $user->biometric_enabled,
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $validated = $request->validate([
            'language' => 'nullable|string|max:10',
            'theme' => 'nullable|in:light,dark,system',
            'notification_preferences' => 'nullable|array',
            'privacy_settings' => 'nullable|array',
            'security_settings' => 'nullable|array',
            'biometric_enabled' => 'nullable|boolean',
        ]);

        $user->update($validated);
        return $this->success(new UserResource($user->fresh()), 'Settings updated.');
    }

    public function requestDataExport(): JsonResponse
    {
        $user = auth('api')->user();

        $existing = DataExportRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'processing'])
            ->exists();

        if ($existing) {
            return $this->error('A data export is already in progress.', 422);
        }

        $request = DataExportRequest::create([
            'user_id' => $user->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        GenerateDataExportJob::dispatch($request->id);

        return $this->success(['request_id' => $request->id], 'Data export requested. You will be notified when ready.');
    }

    public function legalDisclaimer(): JsonResponse
    {
        return $this->success([
            'disclaimer' => config('ai.legal_disclaimer'),
        ]);
    }
}
