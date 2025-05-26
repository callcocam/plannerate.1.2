<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Callcocam\Plannerate\Services\Analysis\ABCAnalysisService;
use Callcocam\Plannerate\Services\Analysis\TargetStockAnalysisService;
use Callcocam\Plannerate\Services\Analysis\BCGAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnalysisController extends Controller
{
    protected $abcService;
    protected $targetStockService;
    protected $bcgService;

    public function __construct(
        ABCAnalysisService $abcService,
        TargetStockAnalysisService $targetStockService,
        BCGAnalysisService $bcgService
    ) {
        $this->abcService = $abcService;
        $this->targetStockService = $targetStockService;
        $this->bcgService = $bcgService;
    }

    /**
     * Realiza análise ABC dos produtos
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function abcAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'products' => 'required|array', 
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
            'storeId' => 'nullable|integer|exists:stores,id',
            'weights' => 'nullable|array',
            'thresholds' => 'nullable|array'
        ]);

        $result = $this->abcService->analyze(
            $request->products,
            $request->startDate,
            $request->endDate,
            $request->storeId,
            $request->weights,
            $request->thresholds
        );

        return response()->json($result);
    }

    /**
     * Realiza análise de estoque alvo dos produtos
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function targetStockAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'products' => 'required|array', 
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
            'storeId' => 'nullable|integer|exists:stores,id',
            'period' => 'nullable|integer|min:1'
        ]);

        $result = $this->targetStockService->analyze(
            $request->products,
            $request->startDate,
            $request->endDate,
            $request->storeId,
            $request->period
        );

        return response()->json($result);
    }

    /**
     * Realiza análise BCG dos produtos
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function bcgAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'products' => 'required|array', 
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
            'storeId' => 'nullable|integer|exists:stores,id',
            'marketShare' => 'nullable|numeric|min:0|max:1'
        ]);

        $result = $this->bcgService->analyze(
            $request->products,
            $request->startDate,
            $request->endDate,
            $request->storeId,
            $request->marketShare
        );

        return response()->json($result);
    }
} 