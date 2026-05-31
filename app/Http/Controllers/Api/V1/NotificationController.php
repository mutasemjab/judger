<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\NotificationResource;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $notifications = UserNotification::where('user_id', auth('api')->id())
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated(
            new \Illuminate\Http\Resources\Json\AnonymousResourceCollection($notifications, NotificationResource::class)
        );
    }

    public function markRead(int $id): JsonResponse
    {
        UserNotification::where('id', $id)->where('user_id', auth('api')->id())->update(['read_at' => now()]);
        return $this->success(null, 'Notification marked as read.');
    }

    public function markAllRead(): JsonResponse
    {
        UserNotification::where('user_id', auth('api')->id())->whereNull('read_at')->update(['read_at' => now()]);
        return $this->success(null, 'All notifications marked as read.');
    }

    public function destroy(int $id): JsonResponse
    {
        UserNotification::where('id', $id)->where('user_id', auth('api')->id())->delete();
        return $this->success(null, 'Notification deleted.');
    }
}
