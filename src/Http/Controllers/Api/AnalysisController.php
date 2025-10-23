<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Callcocam\Plannerate\Models\Planogram;
use Callcocam\Plannerate\Services\Analysis\ABCAnalysisService;
use Callcocam\Plannerate\Services\Analysis\TargetStockAnalysisService;
use Callcocam\Plannerate\Services\Analysis\BCGAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

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
            'planogram' => 'required|string',
            'storeId' => 'nullable|integer|exists:stores,id',
        ]);

        $planogram = Planogram::find($request->planogram);
        $startDate = $planogram->start_date;
        $endDate = $planogram->end_date;

        $result = $this->abcService->analyze(
            $request->products,
            $startDate,
            $endDate,
            $request->storeId
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
            'planogram' => 'required|string',
            'storeId' => 'nullable|integer|exists:stores,id',
        ]);

        $planogram = Planogram::find($request->planogram);
        $startDate = $planogram->start_date;
        $endDate = $planogram->end_date;

        $result = $this->targetStockService->analyze(
            $request->products,
            $startDate,
            $endDate,
            $request->storeId,
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
            'planogram' => 'required|string',
            'storeId' => 'nullable|integer|exists:stores,id',
            'xAxis' => 'nullable|string',
            'yAxis' => 'nullable|string'
        ]);

        // Log dos parâmetros recebidos no controller
        Log::info('BCG Controller - Parâmetros recebidos:', [
            'xAxis' => $request->xAxis,
            'yAxis' => $request->yAxis,
            'products_count' => count($request->products)
        ]);

        $planogram = Planogram::find($request->planogram);
        $startDate = $planogram->start_date;
        $endDate = $planogram->end_date;

        $result = $this->bcgService->analyze(
            $request->products,
            $startDate,
            $endDate,
            $request->xAxis,
            $request->yAxis,
            $request->storeId
        );

        return response()->json($result);
    }
}
