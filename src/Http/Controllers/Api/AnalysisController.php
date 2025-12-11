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
use Callcocam\Plannerate\Models\GondolaZone;
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
     * Realiza distribuição hierárquica de produtos por categoria
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function hierarchicalDistribution(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gondola_id' => 'required|string',
            'planogram' => 'required|string',
            'products' => 'nullable|array',
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
            'targetStock.coverageDays.C' => 'required|integer|min:1',
            'filters' => 'required|array',
            'filters.usageStatus' => 'required|string|in:all,used,unused',
            'filters.includeDimensionless' => 'nullable|boolean',
            'useZones' => 'nullable|boolean',
            'storeId' => 'nullable|integer|exists:stores,id',
        ]);

        try {
            $gondola = Gondola::findOrFail($validated['gondola_id']);
            $planogram = Planogram::findOrFail($validated['planogram']);

            // Buscar todos os produtos disponíveis com relacionamentos necessários
            $query = \App\Models\Product::with(['category']);

            // Aplicar filtro de produtos usados/não usados
            if ($validated['filters']['usageStatus'] === 'unused') {
                // Buscar produtos que NÃO estão em nenhuma gôndola do planograma
                $allGondolas = Gondola::where('planogram_id', $planogram->id)
                    ->with(['sections.shelves.segments.layer'])
                    ->get();

                $usedProductIds = [];
                foreach ($allGondolas as $g) {
                    foreach ($g->sections as $section) {
                        foreach ($section->shelves as $shelf) {
                            foreach ($shelf->segments as $segment) {
                                if ($segment->layer && $segment->layer->product_id) {
                                    $usedProductIds[] = $segment->layer->product_id;
                                }
                            }
                        }
                    }
                }

                if (!empty($usedProductIds)) {
                    $query->whereNotIn('id', array_unique($usedProductIds));
                }
            } elseif ($validated['filters']['usageStatus'] === 'used') {
                // Buscar apenas produtos que já estão em alguma gôndola do planograma
                $allGondolas = Gondola::where('planogram_id', $planogram->id)
                    ->with(['sections.shelves.segments.layer'])
                    ->get();

                $usedProductIds = [];
                foreach ($allGondolas as $g) {
                    foreach ($g->sections as $section) {
                        foreach ($section->shelves as $shelf) {
                            foreach ($shelf->segments as $segment) {
                                if ($segment->layer && $segment->layer->product_id) {
                                    $usedProductIds[] = $segment->layer->product_id;
                                }
                            }
                        }
                    }
                }

                if (!empty($usedProductIds)) {
                    $query->whereIn('id', array_unique($usedProductIds));
                } else {
                    // Se não há produtos usados, retornar vazio
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'total_products' => 0,
                            'placed_products' => 0,
                            'failed_products' => 0,
                        ],
                        'message' => 'Nenhum produto usado encontrado no planograma'
                    ]);
                }
            }

            // Filtro de produtos sem dimensões
            if (!($validated['filters']['includeDimensionless'] ?? false)) {
                // Filtrar apenas produtos que possuem dimensões configuradas
                // O relacionamento dimensionsAvailable já verifica width > 0, height > 0, depth > 0
                $query->whereHas('dimensionsAvailable');
            }

            // Carregar produtos e formatar com dimensões
            $products = $query->get();
            $allProducts = $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'ean' => $product->ean ?? null,
                    'width' => (float) ($product->width ?? 0),
                    'height' => (float) ($product->height ?? 0),
                    'depth' => (float) ($product->depth ?? 0),
                    'category_id' => $product->category_id ?? null,
                ];
            })->toArray();

            // Obter nível mercadológico do planograma e filtrar produtos se necessário
            // O planograma tem category_id, mas também pode ter mercadologico_nivel em settings ou como accessor
            $mercadologicoNivel = $planogram->mercadologico_nivel ?? $planogram->category_id ?? null;
            $mercadologicoCategoryId = null;
            
            // Se mercadologico_nivel não existir, usar category_id diretamente
            if (!$mercadologicoNivel && $planogram->category_id) {
                $mercadologicoCategoryId = $planogram->category_id;
            }
            
            // Se ainda não temos categoryId, tentar extrair do mercadologico_nivel
            if (!$mercadologicoCategoryId && $mercadologicoNivel) {
                // Se mercadologico_nivel é um objeto, extrair o ID da categoria baseado no nível
                if (is_array($mercadologicoNivel) || is_object($mercadologicoNivel)) {
                    $mercadologicoNivel = (array) $mercadologicoNivel;
                    // Tentar encontrar o ID da categoria no objeto (pode estar em diferentes campos)
                    $mercadologicoCategoryId = $mercadologicoNivel['id'] ?? 
                                             $mercadologicoNivel['category_id'] ?? 
                                             $mercadologicoNivel['mercadologico_nivel_4'] ?? 
                                             $mercadologicoNivel['mercadologico_nivel_3'] ?? 
                                             $mercadologicoNivel['mercadologico_nivel_2'] ?? 
                                             null;
                } elseif (is_string($mercadologicoNivel) || is_numeric($mercadologicoNivel)) {
                    // Se é um ID direto
                    $mercadologicoCategoryId = $mercadologicoNivel;
                }
            }
            
            // Se há categoria mercadológica definida, filtrar produtos
            if ($mercadologicoCategoryId) {
                $category = \App\Models\Category::find($mercadologicoCategoryId);
                if ($category) {
                    // Buscar todos os descendentes da categoria
                    $descendantIds = $category->getAllDescendantIds();
                    $allCategoryIds = array_merge([$mercadologicoCategoryId], $descendantIds);
                    
                    // Filtrar produtos para incluir apenas os da categoria mercadológica
                    $allProducts = array_filter($allProducts, function($product) use ($allCategoryIds) {
                        return in_array($product['category_id'] ?? null, $allCategoryIds);
                    });
                    
                    Log::info('🔍 Produtos filtrados por nível mercadológico', [
                        'mercadologico_category_id' => $mercadologicoCategoryId,
                        'mercadologico_category_name' => $category->name,
                        'category_ids_searched' => count($allCategoryIds),
                        'products_filtered' => count($allProducts)
                    ]);
                }
            }

            Log::info('🚀 Distribuição Hierárquica - Produtos carregados', [
                'total' => count($allProducts),
                'filters' => $validated['filters'],
                'mercadologico_category_id' => $mercadologicoCategoryId
            ]);

            // Carregar configuração de zonas se solicitado
            $zonesConfig = null;
            if ($validated['useZones'] ?? false) {
                $zones = $gondola->zones()->ordered()->get();
                
                if ($zones->count() > 0) {
                    $zonesConfig = $zones->map(function ($zone) {
                        return [
                            'name' => $zone->name,
                            'shelf_indexes' => $zone->shelf_indexes,
                            'performance_multiplier' => $zone->performance_multiplier,
                            'rules' => $zone->rules,
                        ];
                    })->toArray();
                    
                    Log::info('📍 Zonas carregadas para distribuição', [
                        'zones_count' => count($zonesConfig),
                        'zones' => $zonesConfig
                    ]);
                } else {
                    Log::warning('⚠️ useZones=true mas nenhuma zona configurada');
                }
            }

            // Converter storeId para int se necessário
            $storeId = isset($validated['storeId']) ? (int) $validated['storeId'] : null;
            
            // Obter nível mercadológico do planograma (padrão: 'categoria')
            // O level_name é usado para determinar qual nível da hierarquia usar ao agrupar produtos
            // Se mercadologico_nivel é um objeto com level_name, usar esse, senão usar 'categoria'
            $mercadologicoLevel = 'categoria';
            if ($mercadologicoNivel) {
                if (is_array($mercadologicoNivel) || is_object($mercadologicoNivel)) {
                    $mercadologicoNivelArray = (array) $mercadologicoNivel;
                    $mercadologicoLevel = $mercadologicoNivelArray['level_name'] ?? 
                                         $mercadologicoNivelArray['level'] ?? 
                                         'categoria';
                }
            }
            
            Log::info('📊 Nível mercadológico do planograma', [
                'planogram_id' => $planogram->id,
                'mercadologico_level' => $mercadologicoLevel,
                'mercadologico_category_id' => $mercadologicoCategoryId,
                'mercadologico_nivel_raw' => $mercadologicoNivel
            ]);
            
            // Obter client_id do planograma para filtrar vendas
            $clientId = $planogram->client_id ?? null;
            
            // Executar distribuição
            $result = $this->hierarchicalDistribution->distributeByHierarchy(
                $gondola,
                $allProducts,
                $validated['weights'],
                $validated['targetStock'],
                $planogram->start_date,
                $planogram->end_date,
                $storeId,
                $clientId,
                $zonesConfig,
                $mercadologicoLevel
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Distribuição concluída com sucesso'
            ]);

        } catch (\Exception $e) {
            Log::error('Erro na distribuição hierárquica:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao executar distribuição: ' . $e->getMessage()
            ], 500);
        }
    }
} 
