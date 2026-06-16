<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AiToolType;
use App\Http\Requests\Api\V1\AiToolRequest;
use App\Http\Resources\Api\V1\AiToolOutputResource;
use App\Models\AiToolOutput;
use App\Services\Documents\GeneratedFileExportService;
use App\Services\Tools\AiToolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiToolController extends BaseApiController
{
    public function __construct(
        private AiToolService $toolService,
        private GeneratedFileExportService $exportService
    ) {}

    private function runTool(AiToolType $toolType, AiToolRequest $request): JsonResponse
    {
        try {
            $result = $this->toolService->run($toolType, auth('api')->id(), $request->validated());
            return $this->success($result, "{$toolType->label()} completed.");
        } catch (\Throwable $e) {
            return $this->error('Tool failed: ' . $e->getMessage(), 500);
        }
    }

    public function caseSummarizer(AiToolRequest $request): JsonResponse { return $this->runTool(AiToolType::CaseSummarizer, $request); }
    public function documentSummarizer(AiToolRequest $request): JsonResponse { return $this->runTool(AiToolType::DocumentSummarizer, $request); }
    public function contractAnalyzer(AiToolRequest $request): JsonResponse { return $this->runTool(AiToolType::ContractAnalyzer, $request); }
    public function riskEstimator(AiToolRequest $request): JsonResponse { return $this->runTool(AiToolType::RiskEstimator, $request); }
    public function memoGenerator(AiToolRequest $request): JsonResponse { return $this->runTool(AiToolType::MemoGenerator, $request); }
    public function legalNoticeGenerator(AiToolRequest $request): JsonResponse { return $this->runTool(AiToolType::LegalNoticeGenerator, $request); }
    public function demandLetterGenerator(AiToolRequest $request): JsonResponse { return $this->runTool(AiToolType::DemandLetterGenerator, $request); }
    public function timelineGenerator(AiToolRequest $request): JsonResponse { return $this->runTool(AiToolType::TimelineGenerator, $request); }
    public function checklistGenerator(AiToolRequest $request): JsonResponse { return $this->runTool(AiToolType::ChecklistGenerator, $request); }
    public function clientExplanationSimplifier(AiToolRequest $request): JsonResponse { return $this->runTool(AiToolType::ClientExplanationSimplifier, $request); }
    public function defenseAssistant(AiToolRequest $request): JsonResponse { return $this->runTool(AiToolType::DefenseAssistant, $request); }

    public function history(Request $request): JsonResponse
    {
        $history = AiToolOutput::where('user_id', auth('api')->id())
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated(
            new \Illuminate\Http\Resources\Json\AnonymousResourceCollection($history, AiToolOutputResource::class)
        );
    }

    public function download(AiToolOutput $output): mixed
    {
        if ((int) $output->user_id !== (int) auth('api')->id()) {
            return $this->forbidden('You do not have access to this AI output.');
        }

        $download = $this->exportService->exportAiToolOutput($output);

        return $this->exportService->downloadResponse($download);
    }
}
