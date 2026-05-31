<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreNoteRequest;
use App\Http\Resources\Api\V1\NoteResource;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $notes = Note::where('user_id', auth('api')->id())
            ->when($request->legal_case_id, fn($q, $v) => $q->where('legal_case_id', $v))
            ->when($request->pinned, fn($q) => $q->where('is_pinned', true))
            ->orderBy('is_pinned', 'desc')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(
            new \Illuminate\Http\Resources\Json\AnonymousResourceCollection($notes, NoteResource::class)
        );
    }

    public function store(StoreNoteRequest $request): JsonResponse
    {
        $note = Note::create(array_merge($request->validated(), ['user_id' => auth('api')->id()]));
        return $this->created(new NoteResource($note), 'Note created.');
    }

    public function show(Note $note): JsonResponse
    {
        $this->authorize('view', $note);
        return $this->success(new NoteResource($note));
    }

    public function update(StoreNoteRequest $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);
        $note->update($request->validated());
        return $this->success(new NoteResource($note->fresh()), 'Note updated.');
    }

    public function destroy(Note $note): JsonResponse
    {
        $this->authorize('delete', $note);
        $note->delete();
        return $this->success(null, 'Note deleted.');
    }

    public function pin(Note $note): JsonResponse
    {
        $this->authorize('update', $note);
        $note->update(['is_pinned' => !$note->is_pinned]);
        return $this->success(new NoteResource($note->fresh()), $note->is_pinned ? 'Note pinned.' : 'Note unpinned.');
    }
}
