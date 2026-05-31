<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Search\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends BaseApiController
{
    public function __construct(private GlobalSearchService $searchService) {}

    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2|max:255']);

        $results = $this->searchService->search(
            auth('api')->id(),
            $request->q,
            $request->integer('limit', 5)
        );

        return $this->success($results);
    }
}
