<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AccountStatus;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $users = User::with('roles', 'subscription.plan')
            ->when($request->search, fn($q, $v) => $q->where('name', 'LIKE', "%{$v}%")->orWhere('email', 'LIKE', "%{$v}%"))
            ->when($request->status, fn($q, $v) => $q->where('account_status', $v))
            ->when($request->user_type, fn($q, $v) => $q->where('user_type', $v))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated(
            new \Illuminate\Http\Resources\Json\AnonymousResourceCollection($users, UserResource::class)
        );
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['roles', 'subscription.plan', 'legalCases', 'usageCounters']);
        return $this->success(new UserResource($user));
    }

    public function suspend(User $user): JsonResponse
    {
        if ($user->isAdmin()) {
            return $this->error('Cannot suspend admin users.', 422);
        }
        $user->update(['account_status' => AccountStatus::Suspended->value]);
        return $this->success(null, 'User suspended.');
    }

    public function activate(User $user): JsonResponse
    {
        $user->update(['account_status' => AccountStatus::Active->value]);
        return $this->success(null, 'User activated.');
    }
}
