<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Callcocam\Plannerate\Models\Planogram;
use Callcocam\Plannerate\Models\Gondola;
use Callcocam\Plannerate\Services\Analysis\ABCAnalysisService;
use Callcocam\Plannerate\Services\Analysis\TargetStockAnalysisService;
use Callcocam\Plannerate\Services\Analysis\BCGAnalysisService;
use Callcocam\Plannerate\Services\Engine\CategoryHierarchyService;
use Callcocam\Plannerate\Services\Engine\ABCHierarchicalService;
use Callcocam\Plannerate\Services\Engine\FacingCalculatorService;
use Callcocam\Plannerate\Services\Engine\HierarchicalDistributionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AnalysisController extends Controller
{
    protected $abcService;
    protected $targetStockService;
    protected $bcgService;
    protected $categoryService;
    protected $abcHierarchical;
    protected $facingCalculator;
    protected $hierarchicalDistribution;

    public function __construct(
        ABCAnalysisService $abcService,
        TargetStockAnalysisService $targetStockService,
        BCGAnalysisService $bcgService,
        CategoryHierarchyService $categoryService,
        ABCHierarchicalService $abcHierarchical,
        FacingCalculatorService $facingCalculator,
        HierarchicalDistributionService $hierarchicalDistribution
    ) {
        $this->abcService = $abcService;
        $this->targetStockService = $targetStockService;
        $this->bcgService = $bcgService;
        $this->categoryService = $categoryService;
        $this->abcHierarchical = $abcHierarchical;
        $this->facingCalculator = $facingCalculator;
        $this->hierarchicalDistribution = $hierarchicalDistribution;
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

    /**
     * Realiza distribuição hierárquica por categoria mercadológica
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function hierarchicalDistribution(Request $request): JsonResponse
    {
        $request->validate([
            'gondola_id' => 'required|string|exists:gondolas,id',
            'products' => 'nullable|array', // Agora opcional
            'planogram' => 'required|string',
            'storeId' => 'nullable|integer|exists:stores,id',
            'weights' => 'required|array',
            'weights.quantity' => 'required|numeric|min:0|max:1',
            'weights.value' => 'required|numeric|min:0|max:1',
            'weights.margin' => 'required|numeric|min:0|max:1',
            'targetStock' => 'required|array',
            'targetStock.serviceLevel' => 'required|array',
            'targetStock.serviceLevel.A' => 'required|numeric|min:0|max:1',
            'targetStock.serviceLevel.B' => 'required|numeric|min:0|max:1',
            'targetStock.serviceLevel.C' => 'required|numeric|min:0|max:1',
            'targetStock.coverageDays' => 'required|array',
            'targetStock.coverageDays.A' => 'required|integer|min:1',
            'targetStock.coverageDays.B' => 'required|integer|min:1',
            'targetStock.coverageDays.C' => 'required|integer|min:1'
        ]);

        Log::info('🚀 Distribuição Hierárquica - Parâmetros recebidos:', [
            'gondola_id' => $request->gondola_id,
            'products_count' => count($request->products),
            'weights' => $request->weights,
            'targetStock' => $request->targetStock
        ]);

        try {
            // Buscar gôndola
            $gondola = Gondola::with(['sections.shelves'])->findOrFail($request->gondola_id);

            // Buscar planograma para datas E categoria mercadológica
            $planogram = Planogram::find($request->planogram);
            $startDate = $planogram->start_date ?? null;
            $endDate = $planogram->end_date ?? null;
            $categoryId = $planogram->category_id ?? null;

            Log::info('📋 Filtros do planograma', [
                'planogram_id' => $planogram->id,
                'category_id' => $categoryId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'products_received' => count($request->products ?? [])
            ]);

            // Buscar produtos completos (com dimensões) FILTRADOS pela categoria do planogram
            $query = \App\Models\Product::query()
                ->where('status', 'published') // Status correto no banco é 'published'
                ->whereHas('dimensions', function($q) {
                    $q->where('width', '>', 0)
                      ->where('height', '>', 0)
                      ->where('depth', '>', 0);
                })
                ->with('dimensions');

            // Se foram passados produtos específicos, filtrar por eles
            if (!empty($request->products)) {
                $query->whereIn('id', $request->products);
                Log::info('🔍 Filtrando por produtos específicos', [
                    'products_count' => count($request->products)
                ]);
            } else {
                Log::info('📦 Buscando TODOS os produtos do planograma');
            }

            // FILTRO IMPORTANTE: Se o planograma tem categoria definida,
            // buscar apenas produtos dessa categoria e suas subcategorias
            if ($categoryId) {
                // Buscar categoria e todos os descendentes
                $category = \App\Models\Category::find($categoryId);
                
                if ($category) {
                    $descendantIds = $category->getAllDescendantIds();
                    $allCategoryIds = array_merge([$categoryId], $descendantIds);
                    
                    $query->whereIn('category_id', $allCategoryIds);
                    
                    Log::info('✅ Filtro de categoria aplicado', [
                        'category_id' => $categoryId,
                        'category_name' => $category->name,
                        'descendants_count' => count($descendantIds),
                        'total_category_ids' => count($allCategoryIds)
                    ]);
                } else {
                    Log::warning('⚠️ Categoria do planograma não encontrada', [
                        'category_id' => $categoryId
                    ]);
                }
            } else {
                Log::info('⚠️ Planograma sem categoria definida - buscando TODOS os produtos');
            }

            // Debug: contar produtos SEM filtro de dimensões
            $totalProductsInCategory = \App\Models\Product::query()
                ->where('status', 'published') // Status correto no banco é 'published'
                ->whereIn('category_id', $allCategoryIds ?? [])
                ->count();
            
            Log::info('🔍 Debug de produtos na categoria', [
                'total_products_active' => $totalProductsInCategory,
                'query_with_dimensions' => $query->toSql()
            ]);

            $allProducts = $query->get()->map(function ($product) {
                $array = $product->toArray();
                // Garantir que os acessors de dimensão estejam disponíveis
                $array['width'] = $product->width;
                $array['height'] = $product->height;
                $array['depth'] = $product->depth;
                return $array;
            })->toArray();

            if (empty($allProducts)) {
                $categoryName = isset($category) ? $category->name : 'N/A';
                
                Log::warning('❌ Nenhum produto com dimensões válidas', [
                    'total_products_in_category' => $totalProductsInCategory,
                    'products_with_dimensions' => 0,
                    'category_name' => $categoryName
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => "Nenhum produto válido encontrado com dimensões válidas na categoria '{$categoryName}'. Total de produtos ativos na categoria: {$totalProductsInCategory}"
                ], 400);
            }

            Log::info('📦 Produtos filtrados para distribuição', [
                'total_products' => count($allProducts),
                'category_filter_applied' => $categoryId ? 'Sim' : 'Não'
            ]);

            // Executar distribuição hierárquica
            $result = $this->hierarchicalDistribution->distributeByHierarchy(
                $gondola,
                $allProducts,
                $request->weights,
                $request->targetStock,
                $startDate,
                $endDate,
                $request->storeId
            );

            return response()->json([
                'success' => true,
                'message' => 'Distribuição hierárquica concluída',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erro na distribuição hierárquica', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao executar distribuição hierárquica: ' . $e->getMessage()
            ], 500);
        }
    }
} 