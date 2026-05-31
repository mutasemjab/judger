<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreHearingRequest;
use App\Http\Resources\Api\V1\HearingResource;
use App\Models\Hearing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HearingController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $hearings = Hearing::where('user_id', auth('api')->id())
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->legal_case_id, fn($q, $v) => $q->where('legal_case_id', $v))
            ->orderBy('date')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(
            new \Illuminate\Http\Resources\Json\AnonymousResourceCollection($hearings, HearingResource::class)
        );
    }

    public function store(StoreHearingRequest $request): JsonResponse
    {
        $hearing = Hearing::create(array_merge($request->validated(), ['user_id' => auth('api')->id()]));
        return $this->created(new HearingResource($hearing), 'Hearing created.');
    }

    public function show(Hearing $hearing): JsonResponse
    {
        $this->authorize('view', $hearing);
        return $this->success(new HearingResource($hearing));
    }

    public function update(StoreHearingRequest $request, Hearing $hearing): JsonResponse
    {
        $this->authorize('update', $hearing);
        $hearing->update($request->validated());
        return $this->success(new HearingResource($hearing->fresh()), 'Hearing updated.');
    }

    public function destroy(Hearing $hearing): JsonResponse
    {
        $this->authorize('delete', $hearing);
        $hearing->delete();
        return $this->success(null, 'Hearing deleted.');
    }

    public function calendar(Request $request): JsonResponse
    {
        $hearings = Hearing::where('user_id', auth('api')->id())
            ->when($request->month, function ($q) use ($request) {
                $q->whereYear('date', $request->year ?? now()->year)
                    ->whereMonth('date', $request->month);
            })
            ->orderBy('date')
            ->get();

        return $this->success(HearingResource::collection($hearings));
    }

    public function upcoming(Request $request): JsonResponse
    {
        $hearings = Hearing::where('user_id', auth('api')->id())
            ->where('status', 'scheduled')
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->limit(10)
            ->get();

        return $this->success(HearingResource::collection($hearings));
    }
}
