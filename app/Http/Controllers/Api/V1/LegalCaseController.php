<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreLegalCaseRequest;
use App\Http\Resources\Api\V1\LegalCaseResource;
use App\Models\LegalCase;
use App\Repositories\LegalCaseRepository;
use App\Services\Subscriptions\FeatureGateService;
use App\Services\Subscriptions\SubscriptionService;
use App\Services\Subscriptions\UsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalCaseController extends BaseApiController
{
    public function __construct(
        private LegalCaseRepository $repository,
        private FeatureGateService $featureGate
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->repository->forUser(
            auth('api')->id(),
            $request->only(['status', 'priority', 'search', 'sort', 'direction']),
            $request->integer('per_page', 15)
        );

        return $this->paginated(
            new \Illuminate\Http\Resources\Json\AnonymousResourceCollection(
                $paginator, LegalCaseResource::class
            )
        );
    }

    public function store(StoreLegalCaseRequest $request): JsonResponse
    {
        $userId = auth('api')->id();

        $gate = $this->featureGate->canAccess(auth('api')->user(), 'cases_created');
        if (!$gate['allowed']) {
            return $this->error(
                'You have reached the case limit for your plan.',
                403,
                null,
                ['feature' => 'cases_created', 'required_plan' => $gate['required_plan'], 'upgrade_required' => true]
            );
        }

        $case = $this->repository->create($userId, $request->validated());

        (new UsageService())->increment(auth('api')->user(), 'cases_created');

        return $this->created(new LegalCaseResource($case), 'Case created successfully.');
    }

    public function show(LegalCase $case): JsonResponse
    {
        $this->authorize('view', $case);
        return $this->success(new LegalCaseResource($case->loadCount('documents')));
    }

    public function update(StoreLegalCaseRequest $request, LegalCase $case): JsonResponse
    {
        $this->authorize('update', $case);
        $case->update($request->validated());
        return $this->success(new LegalCaseResource($case->fresh()), 'Case updated.');
    }

    public function destroy(LegalCase $case): JsonResponse
    {
        $this->authorize('delete', $case);
        $case->delete();
        return $this->success(null, 'Case deleted.');
    }

    public function overview(LegalCase $case): JsonResponse
    {
        $this->authorize('view', $case);

        $case->load(['documents', 'hearings', 'tasks', 'notes', 'conversations', 'memories']);

        return $this->success([
            'case' => new LegalCaseResource($case),
            'documents_count' => $case->documents->count(),
            'hearings_count' => $case->hearings->count(),
            'tasks_count' => $case->tasks->count(),
            'notes_count' => $case->notes->count(),
            'conversations_count' => $case->conversations->count(),
            'memories_count' => $case->memories->count(),
        ]);
    }
}
