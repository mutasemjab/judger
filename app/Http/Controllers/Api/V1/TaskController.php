<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreTaskRequest;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $tasks = Task::where('user_id', auth('api')->id())
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->priority, fn($q, $v) => $q->where('priority', $v))
            ->when($request->legal_case_id, fn($q, $v) => $q->where('legal_case_id', $v))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(
            new \Illuminate\Http\Resources\Json\AnonymousResourceCollection($tasks, TaskResource::class)
        );
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = Task::create(array_merge($request->validated(), ['user_id' => auth('api')->id()]));
        return $this->created(new TaskResource($task), 'Task created.');
    }

    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);
        return $this->success(new TaskResource($task));
    }

    public function update(StoreTaskRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);
        $task->update($request->validated());
        return $this->success(new TaskResource($task->fresh()), 'Task updated.');
    }

    public function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);
        $task->delete();
        return $this->success(null, 'Task deleted.');
    }

    public function complete(Task $task): JsonResponse
    {
        $this->authorize('update', $task);
        $task->update(['status' => 'completed', 'completed_at' => now()]);
        return $this->success(new TaskResource($task->fresh()), 'Task marked as completed.');
    }
}
