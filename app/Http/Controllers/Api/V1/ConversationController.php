<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConversationType;
use App\Http\Requests\Api\V1\ChatRequest;
use App\Http\Requests\Api\V1\StoreConversationRequest;
use App\Http\Resources\Api\V1\ConversationResource;
use App\Http\Resources\Api\V1\MessageResource;
use App\Models\Conversation;
use App\Models\LegalCase;
use App\Models\Message;
use App\Repositories\ConversationRepository;
use App\Services\Chat\LegalChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends BaseApiController
{
    public function __construct(
        private ConversationRepository $repository,
        private LegalChatService $chatService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->repository->forUser(
            auth('api')->id(),
            $request->only(['type']),
            $request->integer('per_page', 15)
        );
        return $this->paginated(
            new \Illuminate\Http\Resources\Json\AnonymousResourceCollection($paginator, ConversationResource::class)
        );
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        $userId = auth('api')->id();
        $type = $request->type;
        $legalCaseId = null;

        if ($type === ConversationType::Case->value) {
            $case = LegalCase::where('id', $request->legal_case_id)->where('user_id', $userId)->first();
            if (!$case) {
                return $this->error('Case not found or access denied.', 404);
            }
            $legalCaseId = $case->id;
        }

        $conversation = Conversation::create([
            'user_id' => $userId,
            'legal_case_id' => $legalCaseId,
            'type' => $type,
            'title' => $request->title,
        ]);

        return $this->created(new ConversationResource($conversation), 'Conversation created.');
    }

    public function show(Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        return $this->success(new ConversationResource($conversation));
    }

    public function destroy(Conversation $conversation): JsonResponse
    {
        $this->authorize('delete', $conversation);
        $conversation->delete();
        return $this->success(null, 'Conversation deleted.');
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->paginate($request->integer('per_page', 30));

        return $this->paginated(
            new \Illuminate\Http\Resources\Json\AnonymousResourceCollection($messages, MessageResource::class)
        );
    }

    public function chat(ChatRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        try {
            $result = $this->chatService->ask(auth('api')->id(), $conversation->id, $request->message);
            return $this->success($result, 'AI response generated successfully.');
        } catch (\Throwable $e) {
            return $this->error('Failed to generate response: ' . $e->getMessage(), 500);
        }
    }
}
