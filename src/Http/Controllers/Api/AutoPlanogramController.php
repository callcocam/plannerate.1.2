<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Callcocam\Plannerate\Models\Gondola;
use Callcocam\Plannerate\Services\Analysis\ABCAnalysisService;
use Callcocam\Plannerate\Services\Analysis\TargetStockAnalysisService;
use Callcocam\Plannerate\Services\FacingCalculatorService;
use Callcocam\Plannerate\Services\ProductDataExtractorService;
use Callcocam\Plannerate\Services\ProductPlacementService;
use Callcocam\Plannerate\Services\StepLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Controller para o Motor de Planograma Automático
 * 
 * Gerencia as operações do sistema automático de geração de planogramas
 */
class AutoPlanogramController extends Controller
{
    protected ABCAnalysisService $abcAnalysisService;
    protected TargetStockAnalysisService $targetStockAnalysisService;
    protected FacingCalculatorService $facingCalculator;
    protected ProductDataExtractorService $productDataExtractor;
    protected ProductPlacementService $productPlacement;

    public function __construct(
        ABCAnalysisService $abcAnalysisService,
        TargetStockAnalysisService $targetStockAnalysisService,
        FacingCalculatorService $facingCalculator,
        ProductDataExtractorService $productDataExtractor,
        ProductPlacementService $productPlacement
    ) {
        $this->abcAnalysisService = $abcAnalysisService;
        $this->targetStockAnalysisService = $targetStockAnalysisService;
        $this->facingCalculator = $facingCalculator;
        $this->productDataExtractor = $productDataExtractor;
        $this->productPlacement = $productPlacement;
    }

    /**
     * Calcula scores automáticos para produtos de uma gôndola
     * 
     * POST /api/plannerate/auto-planogram/calculate-scores
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function calculateScores(Request $request): JsonResponse
    {
        // Validação dos dados de entrada
        $validator = Validator::make($request->all(), [
            'gondola_id' => 'required|exists:gondolas,id',
            'weights' => 'array',
            'weights.quantity' => 'numeric|between:0,1',
            'weights.value' => 'numeric|between:0,1', 
            'weights.margin' => 'numeric|between:0,1',
            'start_date' => 'date|nullable',
            'end_date' => 'date|nullable|after_or_equal:start_date',
            'store_id' => 'integer|nullable',
        ], [
            'gondola_id.required' => 'ID da gôndola é obrigatório',
            'gondola_id.exists' => 'Gôndola não encontrada',
            'weights.*.numeric' => 'Pesos devem ser valores numéricos',
            'weights.*.between' => 'Pesos devem estar entre 0 e 1',
            'end_date.after_or_equal' => 'Data final deve ser posterior à data inicial',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos fornecidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Buscar a gôndola com relação ao planogram
            $gondola = Gondola::with(['sections.shelves.segments', 'planogram'])->findOrFail($request->gondola_id);
            
            // Buscar produtos usando o mesmo endpoint do Products.vue
            $productsData = $this->getAllProductsByPlanogramCategory($gondola, $request);
            
            if (empty($productsData)) {
                $planogram = $gondola->planogram;
                $hasCategory = $planogram && $planogram->category_id;

                if (!$planogram) {
                    $message = 'Gôndola não possui planograma associado. Associe um planograma válido para gerar automaticamente.';
                } elseif (!$hasCategory) {
                    $message = 'Planograma não possui categoria definida. Configure uma categoria para gerar automaticamente.';
                } else {
                    $message = 'Nenhum produto ativo encontrado na categoria do planograma para geração automática.';
                }

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'gondola' => [
                            'id' => $gondola->id,
                            'name' => $gondola->name,
                            'planogram_id' => $gondola->planogram_id,
                        ],
                        'planogram' => $planogram ? [
                            'id' => $planogram->id,
                            'name' => $planogram->name,
                            'has_category' => $hasCategory,
                            'category_id' => $planogram->category_id,
                        ] : null,
                        'calculation_info' => [
                            'products_analyzed' => 0,
                            'products_scored' => 0,
                            'calculation_date' => now()->toISOString(),
                            'period' => [
                                'start_date' => $request->input('start_date'),
                                'end_date' => $request->input('end_date'),
                            ],
                            'weights_used' => $request->input('weights', []),
                        ],
                        'scores' => [],
                        'summary' => [
                            'total_products' => 0,
                            'average_score' => 0,
                            'score_distribution' => [
                                'high' => 0,
                                'medium' => 0,
                                'low' => 0,
                            ],
                            'abc_distribution' => [],
                            'confidence_flags' => [],
                        ]
                    ]
                ]);
            }

        // Filtrar apenas produtos com dimensões válidas
        $productsWithDimensions = collect($productsData)->filter(function($product) {
            return isset($product['dimensions']) && 
                   isset($product['dimensions']['width']) && 
                   $product['dimensions']['width'] > 0;
        })->values()->toArray();
        
        // PASSO 1: Iniciar processo de distribuição automática
        StepLogger::startProcess($gondola->id, $gondola->name, count($productsWithDimensions));
        
        // Usar apenas produtos com dimensões válidas
        $productsData = $productsWithDimensions;
        
        // 🎯 NOVO FLUXO: ABC + Target Stock + Facing Inteligente (sem ScoreEngine)
        
        // 1. EXECUTAR ANÁLISE ABC - 🎯 USAR PARÂMETROS DO MODAL
        $abcParams = $request->input('abc_params', [
            'weights' => [
                'quantity' => 0.3,
                'value' => 0.5, 
                'margin' => 0.2
            ],
            'thresholds' => [
                'a' => 80,
                'b' => 95
            ]
        ]);
        $abcResults = $this->executeABCAnalysis($productsData, $abcParams);
        
        // 2. EXECUTAR ANÁLISE TARGET STOCK - 🎯 USAR PARÂMETROS DO MODAL
        $targetStockParams = $request->input('target_stock_params', [
            'coverageDays' => 7,
            'safetyStock' => 20,
            'serviceLevel' => 95
        ]);
        $targetStockResults = $this->executeTargetStockAnalysis($productsData, $targetStockParams, $gondola->planogram_id);
        
        // 3. PROCESSAR COM FACING INTELIGENTE
        $scores = $this->processProductsWithNewLogic($productsData, $abcResults, $targetStockResults, $gondola);

            // 4. APLICAR DISTRIBUIÇÃO AUTOMÁTICA SE SOLICITADO
            $autoDistribute = $request->boolean('auto_distribute', false);
            $distributionResult = null;
            
            if ($autoDistribute) {
                // Usar o novo método distributeIntelligently
                $this->clearGondola($gondola);
                $distributionResult = $this->distributeIntelligently($gondola, $scores);
            }

            // 5. PREPARAR RESPOSTA ESTRUTURADA COM NOVO FLUXO
            $response = [
                'success' => true,
                'message' => $autoDistribute 
                    ? '🎯 Análise ABC + Target Stock + Facing calculados e produtos distribuídos automaticamente'
                    : '🎯 Análise ABC + Target Stock + Facing calculados com sucesso',
                'data' => [
                    'gondola' => [
                        'id' => $gondola->id,
                        'name' => $gondola->name,
                        'planogram_id' => $gondola->planogram_id,
                    ],
                    'calculation_info' => [
                        'products_analyzed' => count($productsData),
                        'products_processed' => count($scores),
                        'calculation_date' => now()->toISOString(),
                        'method' => 'ABC + Target Stock + Facing Inteligente',
                        'period' => [
                            'start_date' => $request->input('start_date'),
                            'end_date' => $request->input('end_date'),
                        ],
                        'abc_params' => $abcParams,
                        'target_stock_params' => $targetStockParams,
                    ],
                    'abc_analysis' => [
                        'total_analyzed' => count($abcResults),
                        'distribution' => $this->getABCDistribution($abcResults)
                    ],
                    'target_stock_analysis' => [
                        'total_analyzed' => count($targetStockResults),
                        'urgency_distribution' => $this->getUrgencyDistribution($targetStockResults)
                    ],
                    'intelligent_scores' => $scores,
                    'summary' => $this->generateIntelligentSummary($scores),
                    'distribution' => $distributionResult
                ]
            ];

            Log::info('AutoPlanogram: Novo fluxo inteligente concluído', [
                'gondola_id' => $gondola->id,
                'produtos_analisados' => count($productsData),
                'produtos_processados' => count($scores),
                'abc_distribution' => $this->getABCDistribution($abcResults),
                'target_stock_analyzed' => count($targetStockResults),
                'auto_distribute' => $autoDistribute,
                'produtos_distribuidos' => $distributionResult['products_placed'] ?? 0,
                'method' => 'ABC + Target Stock + Facing Inteligente'
            ]);

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('AutoPlanogram: Erro no cálculo de scores', [
                'gondola_id' => $request->gondola_id,
                'erro' => $e->getMessage(),
                'linha' => $e->getLine(),
                'arquivo' => $e->getFile()
            ]);

            // Mensagens amigáveis baseadas no tipo de erro
            $message = 'Erro interno no cálculo de scores automáticos';
            
            if (str_contains($e->getMessage(), 'planogram')) {
                $message = 'Gôndola não possui planograma válido associado. Verifique a configuração.';
            } elseif (str_contains($e->getMessage(), 'mercadologico')) {
                $message = 'Erro na configuração do mercadológico. Verifique os níveis definidos.';
            } elseif (str_contains($e->getMessage(), 'products') || str_contains($e->getMessage(), 'Product')) {
                $message = 'Erro ao buscar produtos. Verifique se existem produtos ativos no sistema.';
            } elseif (str_contains($e->getMessage(), 'database') || str_contains($e->getMessage(), 'connection')) {
                $message = 'Erro de conexão com o banco de dados. Tente novamente em alguns instantes.';
            } elseif (str_contains($e->getMessage(), 'scores') || str_contains($e->getMessage(), 'ScoreEngine')) {
                $message = 'Erro no cálculo de pontuação. Verifique os dados de vendas e estoque.';
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'error_details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Aplica scores calculados aos segmentos da gôndola
     * 
     * POST /api/plannerate/auto-planogram/apply-scores
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function applyScores(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'gondola_id' => 'required|exists:gondolas,id',
            'scores' => 'required|array',
            'scores.*.product_id' => 'required',
            'scores.*.final_score' => 'required|numeric',
            'scores.*.abc_class' => 'required|string|in:A,B,C',
            'scores.*.confidence_flag' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos para aplicação de scores',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $gondola = Gondola::with(['sections.shelves.segments'])->findOrFail($request->gondola_id);
            $scores = $request->input('scores');
            
            $updatedSegments = 0;
            
            // Aplicar scores aos segmentos
            foreach ($gondola->sections as $section) {
                foreach ($section->shelves as $shelf) {
                    foreach ($shelf->segments as $segment) {
                        // Buscar score correspondente ao produto do segmento
                        $productId = $segment->layer ? $segment->layer->product_id : null;
                        $scoreData = $productId ? collect($scores)->firstWhere('product_id', $productId) : null;
                        
                        if ($scoreData) {
                            // Atualizar segmento com dados calculados
                            $segment->update([
                                'settings' => array_merge($segment->settings ?? [], [
                                    'auto_score' => $scoreData['final_score'],
                                    'abc_class' => $scoreData['abc_class'],
                                    'confidence_flag' => $scoreData['confidence_flag'],
                                    'last_calculation' => now()->toISOString(),
                                ])
                            ]);
                            
                            $updatedSegments++;
                        }
                    }
                }
            }

            Log::info('AutoPlanogram: Scores aplicados', [
                'gondola_id' => $gondola->id,
                'segmentos_atualizados' => $updatedSegments
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Scores aplicados com sucesso aos segmentos',
                'data' => [
                    'gondola_id' => $gondola->id,
                    'segments_updated' => $updatedSegments,
                    'applied_at' => now()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('AutoPlanogram: Erro na aplicação de scores', [
                'gondola_id' => $request->gondola_id,
                'erro' => $e->getMessage()
            ]);

            // Mensagens amigáveis baseadas no tipo de erro
            $message = 'Erro na aplicação de scores';
            
            if (str_contains($e->getMessage(), 'gondola') || str_contains($e->getMessage(), 'Gondola')) {
                $message = 'Gôndola não encontrada ou inacessível. Verifique se ela ainda existe.';
            } elseif (str_contains($e->getMessage(), 'scores')) {
                $message = 'Dados de scores inválidos. Calcule os scores novamente.';
            } elseif (str_contains($e->getMessage(), 'segments') || str_contains($e->getMessage(), 'shelves')) {
                $message = 'Erro na estrutura da gôndola. Verifique se ela possui seções e prateleiras válidas.';
            } elseif (str_contains($e->getMessage(), 'database') || str_contains($e->getMessage(), 'connection')) {
                $message = 'Erro de conexão com o banco de dados. Tente novamente em alguns instantes.';
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'error_details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Obtém configurações atuais do motor automático
     * 
     * GET /api/plannerate/auto-planogram/config
     * 
     * @return JsonResponse
     */
    public function getConfig(): JsonResponse
    {
        $scoreConfig = config('plannerate.score_engine', []);
        $templatesConfig = config('plannerate.templates', []);
        $shelfZonesConfig = config('plannerate.shelf_zones', []);
        
        return response()->json([
            'success' => true,
            'data' => [
                'score_engine' => [
                    'default_weights' => $scoreConfig['default_weights'] ?? [
                        'quantity' => 0.30,
                        'value' => 0.30,
                        'margin' => 0.40,
                    ],
                    'abc_bonuses' => $scoreConfig['abc_bonuses'] ?? [
                        'class_a' => 0.20,
                        'class_b' => 0.00,
                        'class_c' => -0.10,
                    ],
                    'stock_penalties' => $scoreConfig['stock_penalties'] ?? [
                        'deficit' => -0.15,
                        'excess' => -0.05,
                    ],
                    'confidence_flags' => $scoreConfig['confidence_flags'] ?? [
                        'OK' => 'Dados confiáveis',
                    ],
                ],
                'templates' => $templatesConfig,
                'shelf_zones' => $shelfZonesConfig,
                'performance' => config('plannerate.performance', []),
            ]
        ]);
    }

    // Método getProductsByPlanogramCategory removido - agora usa endpoint do Products.vue

    /**
     * Distribui produtos automaticamente na gôndola baseado nos scores
     * Lógica direta sem templates: Score maior = Posição melhor
     */
    protected function distributeProductsInGondola(Gondola $gondola, array $scores, array $productsData = []): array
    {
            // PASSO 2: Iniciar distribuição automática com dados enrichecidos
            StepLogger::logCustomStep('DADOS ENRICHECIDOS PREPARADOS', [
                '📊 SCORES_CALCULADOS' => count($scores),
                '📦 PRODUTOS_COM_DADOS' => count($productsData),
                '🔄 PRÓXIMA_ETAPA' => 'Classificação ABC e distribuição'
            ]);

        // 0. Enrichar scores com dados dos produtos (incluindo dimensões)
        $enrichedScores = $this->productDataExtractor->enrichScoresWithProductData($scores, $productsData);

        // 1. Ordenar produtos por score (maior para menor)
        usort($enrichedScores, function($a, $b) {
            return $b['final_score'] <=> $a['final_score'];
        });
        
        // Usar os scores enrichecidos daqui em diante
        $scores = $enrichedScores;

        // 2. Classificar produtos em ABC baseado no score
        $totalProducts = count($scores);
        $classifiedProducts = [
            'A' => [],
            'B' => [],
            'C' => []
        ];

        foreach ($scores as $index => $scoreData) {
            $percentile = ($index + 1) / $totalProducts * 100;
            
            if ($percentile <= 20) {
                $scoreData['abc_class'] = 'A';
                $classifiedProducts['A'][] = $scoreData;
            } elseif ($percentile <= 60) {
                $scoreData['abc_class'] = 'B';
                $classifiedProducts['B'][] = $scoreData;
            } else {
                $scoreData['abc_class'] = 'C';
                $classifiedProducts['C'][] = $scoreData;
            }
        }

        // PASSO 3: Classificar produtos em ABC
        StepLogger::logABCClassification($classifiedProducts);

        // 3. Obter estrutura da gôndola (seções, prateleiras, segmentos)
        $gondolaStructure = $this->analyzeGondolaStructure($gondola);
        
        // PASSO 4: Analisar estrutura da gôndola
        StepLogger::logGondolaStructure($gondolaStructure);
        
        // 4. Garantir que a gôndola tenha segmentos (criar se necessário)
        $this->ensureGondolaHasSegments($gondola);
        
        // 5. Limpar gôndola atual (remover produtos existentes)
        $this->clearGondola($gondola);

        // 5. Distribuir produtos sequencialmente aproveitando todo o espaço
        $distributionResult = $this->productPlacement->placeProductsSequentially(
            $gondola,
            $classifiedProducts,
            $gondolaStructure
        );

        // PASSO FINAL: Resultado da distribuição automática
        StepLogger::logFinalResult([
            'products_placed' => $distributionResult['products_placed'],
            'total_placements' => $distributionResult['total_placements'] ?? 0,
            'segments_used' => $distributionResult['segments_used'],
            'module_usage' => $distributionResult['module_usage'] ?? [],
            'space_utilization' => round(($distributionResult['segments_used'] / max($gondolaStructure['total_segments'], 1)) * 100, 1),
            'placement_success_rate' => round(($distributionResult['products_placed'] / max(count($scores), 1)) * 100, 1),
            'products_still_failed' => 0 // Será calculado no service
        ]);

        // Adicionar informações das classes ABC à resposta
        $distributionResult['abc_distribution'] = [
            'A' => count($classifiedProducts['A']),
            'B' => count($classifiedProducts['B']),
            'C' => count($classifiedProducts['C'])
        ];

        return $distributionResult;
    }

    /**
     * Analisa a estrutura da gôndola para distribuição
     */
    protected function analyzeGondolaStructure(Gondola $gondola): array
    {
        $structure = [
            'total_sections' => $gondola->sections->count(),
            'shelves_by_level' => [],
            'total_segments' => 0,
            'segments_by_shelf_level' => []
        ];

        foreach ($gondola->sections as $section) {
            foreach ($section->shelves as $shelf) {
                $shelfLevel = $shelf->ordering;
                
                if (!isset($structure['shelves_by_level'][$shelfLevel])) {
                    $structure['shelves_by_level'][$shelfLevel] = [];
                    $structure['segments_by_shelf_level'][$shelfLevel] = 0;
                }
                
                $structure['shelves_by_level'][$shelfLevel][] = $shelf;
                $segmentCount = $shelf->segments->count();
                $structure['segments_by_shelf_level'][$shelfLevel] += $segmentCount;
                $structure['total_segments'] += $segmentCount;
            }
        }

        return $structure;
    }

    /**
     * Garante que a gôndola tenha segmentos (cria se necessário)
     */
    protected function ensureGondolaHasSegments(Gondola $gondola): void
    {
        // Primeiro, corrigir segmentos incorretos
        $this->fixIncorrectSegments($gondola);
        
        foreach ($gondola->sections as $section) {
            foreach ($section->shelves as $shelf) {
                $segmentCount = $shelf->segments()->count();
                
                if ($segmentCount === 0) {
                    // Criar segmento padrão se não existir nenhum
                    $shelf->segments()->create([
                        'tenant_id' => $shelf->tenant_id,
                        'user_id' => $shelf->user_id,
                        'width' => $shelf->shelf_width,
                        'height' => $shelf->shelf_height,
                        'ordering' => 1,
                        'quantity' => 1,
                        'status' => 'published'
                    ]);
                    
                    StepLogger::logSegmentAction('created', $shelf->segments()->latest()->first()->id ?? 'unknown', 
                        ['product_id' => 'vazio', 'product' => ['name' => 'Segmento vazio padrão'], 'abc_class' => 'N/A'], 
                        0, floatval($shelf->shelf_width));
                }
            }
        }
    }

    /**
     * 🔧 NOVA FUNÇÃO: Corrige segmentos com largura incorreta
     * Verifica se largura do segmento bate com largura da prateleira
     */
    protected function fixIncorrectSegments(Gondola $gondola): void
    {
        $fixedSegments = 0;
        $deletedSegments = 0;
        
        StepLogger::logCustomStep('CORREÇÃO DE SEGMENTOS INICIADA', [
            '🔧 GONDOLA_ID' => $gondola->id,
            '🎯 OBJETIVO' => 'Corrigir segmentos com largura incorreta ou desnecessários'
        ]);
        
        foreach ($gondola->sections as $sectionIndex => $section) {
            foreach ($section->shelves as $shelfIndex => $shelf) {
                $shelfWidth = floatval($shelf->shelf_width ?? 125.0);
                $segments = $shelf->segments()->get();
                
                Log::info("🔍 Analisando prateleira", [
                    'section_ordering' => $section->ordering,
                    'shelf_ordering' => $shelf->ordering,
                    'shelf_width' => $shelfWidth,
                    'segments_count' => $segments->count()
                ]);
                
                foreach ($segments as $segment) {
                    $segmentWidth = floatval($segment->width ?? 0);
                    $hasProduct = $segment->layer && $segment->layer->product_id;
                    
                    // CRITÉRIO 1: Segmento com largura muito pequena (< 5cm) sem produto
                    if ($segmentWidth < 5.0 && !$hasProduct) {
                        Log::warning("❌ Segmento com largura suspeita detectado - DELETANDO", [
                            'segment_id' => $segment->id,
                            'shelf_id' => $shelf->id,
                            'section_ordering' => $section->ordering,
                            'shelf_ordering' => $shelf->ordering,
                            'segment_width' => $segmentWidth,
                            'shelf_width' => $shelfWidth,
                            'has_product' => $hasProduct,
                            'reason' => 'Largura muito pequena sem produto'
                        ]);
                        
                        $segment->delete();
                        $deletedSegments++;
                        continue;
                    }
                    
                    // CRITÉRIO 2: Segmento com largura quase igual à prateleira (>90%) sem produto
                    $widthPercentage = ($segmentWidth / $shelfWidth) * 100;
                    if ($widthPercentage > 90 && !$hasProduct && $segmentWidth != $shelfWidth) {
                        Log::warning("❌ Segmento com largura quase total detectado - CORRIGINDO", [
                            'segment_id' => $segment->id,
                            'shelf_id' => $shelf->id,
                            'section_ordering' => $section->ordering,
                            'shelf_ordering' => $shelf->ordering,
                            'segment_width' => $segmentWidth,
                            'shelf_width' => $shelfWidth,
                            'width_percentage' => round($widthPercentage, 1),
                            'has_product' => $hasProduct,
                            'reason' => 'Largura quase total sem produto - corrigindo para largura exata'
                        ]);
                        
                        $segment->update(['width' => $shelfWidth]);
                        $fixedSegments++;
                        continue;
                    }
                    
                    // CRITÉRIO 3: Múltiplos segmentos vazios na mesma prateleira
                    $emptySegments = $segments->filter(function($seg) {
                        return !($seg->layer && $seg->layer->product_id);
                    });
                    
                    if ($emptySegments->count() > 1 && !$hasProduct) {
                        Log::warning("❌ Múltiplos segmentos vazios detectados - DELETANDO extras", [
                            'segment_id' => $segment->id,
                            'shelf_id' => $shelf->id,
                            'section_ordering' => $section->ordering,
                            'shelf_ordering' => $shelf->ordering,
                            'empty_segments_total' => $emptySegments->count(),
                            'reason' => 'Segmento vazio extra'
                        ]);
                        
                        // Manter apenas o primeiro segmento vazio, deletar os outros
                        if ($segment->id !== $emptySegments->first()->id) {
                            $segment->delete();
                            $deletedSegments++;
                            continue;
                        }
                    }
                    
                    Log::info("✅ Segmento OK", [
                        'segment_id' => $segment->id,
                        'section_ordering' => $section->ordering,
                        'shelf_ordering' => $shelf->ordering,
                        'segment_width' => $segmentWidth,
                        'shelf_width' => $shelfWidth,
                        'has_product' => $hasProduct,
                        'status' => 'Mantido'
                    ]);
                }
            }
        }
        
        Log::info("🎯 Correção de segmentos concluída", [
            'gondola_id' => $gondola->id,
            'segments_fixed' => $fixedSegments,
            'segments_deleted' => $deletedSegments,
            'total_changes' => $fixedSegments + $deletedSegments
        ]);
    }

    /**
     * Remove todos os produtos da gôndola
     */
    protected function clearGondola(Gondola $gondola): void
    {
        foreach ($gondola->sections as $section) {
            foreach ($section->shelves as $shelf) {
                foreach ($shelf->segments as $segment) {
                    // Limpar layer do segmento (hasOne)
                    if ($segment->layer) {
                        $segment->layer->update(['product_id' => null]);
                    }
                }
            }
        }
    }






    /**
     * 🎯 NOVO: Gera resumo estatístico do fluxo inteligente (ABC + Target Stock + Facing)
     */
    protected function generateIntelligentSummary(array $scores): array
    {
        if (empty($scores)) {
            return [
                'total_products' => 0,
                'average_priority_score' => 0,
                'average_facing' => 0,
                'coverage_efficiency' => 0,
                'abc_distribution' => [],
                'urgency_distribution' => [],
                'facing_distribution' => []
            ];
        }

        $priorityScores = array_column($scores, 'priority_score');
        $facings = array_column($scores, 'intelligent_facing');
        $abcClasses = array_column($scores, 'abc_class');
        
        // Extrair dados de urgência dos facing_details
        $urgencies = [];
        $coverageEfficiencies = [];
        foreach ($scores as $score) {
            if (isset($score['facing_details']['urgency'])) {
                $urgencies[] = $score['facing_details']['urgency'];
            }
            if (isset($score['facing_details']['coverage_efficiency'])) {
                $coverageEfficiencies[] = $score['facing_details']['coverage_efficiency'];
            }
        }

        return [
            'total_products' => count($scores),
            'average_priority_score' => count($priorityScores) > 0 ? round(array_sum($priorityScores) / count($priorityScores), 4) : 0,
            'average_facing' => count($facings) > 0 ? round(array_sum($facings) / count($facings), 2) : 0,
            'average_coverage_efficiency' => !empty($coverageEfficiencies) ? 
                round(array_sum($coverageEfficiencies) / count($coverageEfficiencies), 1) : 0,
            'facing_distribution' => [
                'high' => count(array_filter($facings, fn($f) => $f >= 4)), // 4+ facings
                'medium' => count(array_filter($facings, fn($f) => $f >= 2 && $f < 4)), // 2-3 facings
                'low' => count(array_filter($facings, fn($f) => $f < 2)), // 1 facing
            ],
            'abc_distribution' => array_count_values($abcClasses),
            'urgency_distribution' => !empty($urgencies) ? array_count_values($urgencies) : [],
            'performance_metrics' => [
                'max_facing' => !empty($facings) ? max($facings) : 0,
                'min_facing' => !empty($facings) ? min($facings) : 0,
                'products_with_high_efficiency' => count(array_filter($coverageEfficiencies, fn($e) => $e > 80)),
                'products_needing_attention' => count(array_filter($coverageEfficiencies, fn($e) => $e < 50))
            ]
        ];
    }

    /**
     * 🔄 LEGADO: Gera resumo estatístico dos scores (mantido para compatibilidade)
     */
    protected function generateSummary(array $scores): array
    {
        if (empty($scores)) {
            return [
                'total_products' => 0,
                'average_score' => 0,
                'score_distribution' => [],
                'abc_distribution' => [],
                'confidence_flags' => [],
            ];
        }

        $finalScores = array_column($scores, 'final_score');
        $abcClasses = array_column($scores, 'abc_class');
        $confidenceFlags = array_column($scores, 'confidence_flag');

        return [
            'total_products' => count($scores),
            'average_score' => count($finalScores) > 0 ? round(array_sum($finalScores) / count($finalScores), 4) : 0,
            'min_score' => min($finalScores),
            'max_score' => max($finalScores),
            'score_distribution' => [
                'high' => count(array_filter($finalScores, fn($score) => $score > 0.7)),
                'medium' => count(array_filter($finalScores, fn($score) => $score >= 0.3 && $score <= 0.7)),
                'low' => count(array_filter($finalScores, fn($score) => $score < 0.3)),
            ],
            'abc_distribution' => array_count_values($abcClasses),
            'confidence_flags' => array_count_values($confidenceFlags),
        ];
    }


    // Método placeProductsSequentially movido para ProductPlacementService



    /**
     * Coloca produto no segmento com facing personalizado
     */
    protected function placeProductInSegmentWithFacing($segment, array $productData, int $facing): bool
    {
        try {
            // Recarregar relacionamento
            $segment->load('layer');
            $existingLayer = $segment->layer;
            
            if ($existingLayer && !$existingLayer->product_id) {
                // Layer vazia, atualizar
                $existingLayer->update([
                    'product_id' => $productData['product_id'],
                    'quantity' => $facing
                ]);
                return true;
            } elseif (!$existingLayer) {
                // Criar nova layer
                $segment->layer()->create([
                    'tenant_id' => $segment->tenant_id,
                    'user_id' => $segment->user_id,
                    'product_id' => $productData['product_id'],
                    'quantity' => $facing,
                    'status' => 'published'
                ]);
                return true;
            }
            
            return false; // Segmento ocupado
            
        } catch (\Exception $e) {
            Log::error("Erro ao colocar produto no segmento", [
                'segment_id' => $segment->id,
                'product_id' => $productData['product_id'],
                'facing' => $facing,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Extrai largura do produto com fallback seguro
     */
    protected function getProductWidth(array $productData): float
    {
        if (!isset($productData['width']) || $productData['width'] <= 0) {
            throw new \InvalidArgumentException("Produto deve ter largura válida > 0");
        }
        return floatval($productData['width']);
    }

    /**
     * NOVA FUNÇÃO: Calcula largura média dos produtos [[memory:8393313]]
     */
    protected function calculateAverageProductWidth(array $products): float
    {
        if (empty($products)) return 25.0; // Default
        
        $totalWidth = 0;
        $validWidths = 0;
        
        foreach ($products as $product) {
            $productData = $product['product'] ?? [];
            $width = $this->getProductWidth($productData);
            $totalWidth += $width;
            $validWidths++;
            
            // Cálculo de largura média
        }
        
        $avgWidth = $validWidths > 0 ? ($totalWidth / $validWidths) : 25.0;
        
        Log::info("Largura média calculada", [
            'total_products' => count($products),
            'valid_widths' => $validWidths,
            'avg_width' => round($avgWidth, 2),
            'total_width_sum' => $totalWidth
        ]);
        
        return $avgWidth;
    }
    
    // Método calculateOptimalFacing movido para FacingCalculatorService

    















    // Método enrichScoresWithProductData não é mais necessário



    // Métodos movidos para ProductPlacementService:
    // - getProductsForModule(), fillSectionVertically(), tryPlaceProductInSection(), tryCascadeDistribution()
    // - getBalancedProductsForExtraModules(), getBalancedProductsForModule1-4()
    
    // Método calculateConservativeFacing movido para FacingCalculatorService
    
    /**
     * NOVO: Coloca produto em prateleira específica com facing definido + VALIDAÇÃO DE LARGURA
     */
    protected function placeProductInShelfVertically($shelf, array $product, int $facing): bool
    {
        // 1. CALCULAR LARGURA NECESSÁRIA PARA O PRODUTO
        $productData = $product['product'] ?? [];
        $productWidth = $this->getProductWidth($productData);
        $requiredWidth = $productWidth * $facing;
        
        // 2. VERIFICAR LARGURA DISPONÍVEL NA PRATELEIRA
        $shelfWidth = floatval($shelf->shelf_width ?? 125); // Largura padrão 125cm se não definida
        $usedWidth = $this->calculateUsedWidthInShelf($shelf);
        $availableWidth = $shelfWidth - $usedWidth;
        
        // Verificando capacidade da prateleira
        
        // 3. FACING ADAPTATIVO: Usar service para calcular
        $adaptiveResult = $this->facingCalculator->calculateAdaptiveFacing($product, $availableWidth, $facing);
        $adaptedFacing = $adaptiveResult['facing'];
        $adaptedRequiredWidth = $adaptiveResult['required_width'];
        
        Log::info("🔧 DEBUG: Facing adaptativo", [
            'product_id' => $product['product_id'],
            'requested_facing' => $facing,
            'adapted_facing' => $adaptedFacing,
            'available_width' => $availableWidth,
            'required_width' => $adaptedRequiredWidth
        ]);
        
        // Facing adaptativo aplicado
        
        // Se não cabe nem com 1 facing, rejeitar
        if ($adaptedFacing <= 0 || $adaptedRequiredWidth > $availableWidth) {
            Log::warning("⚠️ Produto NÃO CABE mesmo com facing mínimo", [
                'shelf_id' => $shelf->id,
                'product_id' => $product['product_id'],
                'facing_minimum_tried' => $adaptedFacing,
                'required_width_cm' => $adaptedRequiredWidth,
                'available_width_cm' => $availableWidth,
                'deficit_cm' => $adaptedRequiredWidth - $availableWidth
            ]);
            return false;
        }
        
        // Usar facing adaptado para o resto da função
        $facing = $adaptedFacing;
        $requiredWidth = $adaptedRequiredWidth;
        
        // 4. PROCURAR SEGMENTO VAZIO NA PRATELEIRA
        $segments = $shelf->segments()->orderBy('ordering')->get();
        
        foreach ($segments as $segment) {
            $segment->load('layer');
            $existingLayer = $segment->layer;
            
            if (!$existingLayer || !$existingLayer->product_id) {
                // Verificar se o segmento tem largura suficiente
                $segmentWidth = floatval($segment->width ?? 0);
                
                if ($segmentWidth >= $requiredWidth || $segmentWidth == 0) {
                    try {
                        if ($existingLayer) {
                            $existingLayer->update([
                                'product_id' => $product['product_id'],
                                'quantity' => $facing
                            ]);
                        } else {
                            $segment->layer()->create([
                                'tenant_id' => $segment->tenant_id,
                                'user_id' => $segment->user_id,
                                'product_id' => $product['product_id'],
                                'quantity' => $facing,
                                'status' => 'published'
                            ]);
                        }
                        
                        // Atualizar largura do segmento se necessário
                        if ($segmentWidth < $requiredWidth) {
                            $segment->update(['width' => $requiredWidth]);
                        }
                        
                        Log::info("✅ Produto colocado COM VALIDAÇÃO de largura", [
                            'segment_id' => $segment->id,
                            'product_id' => $product['product_id'],
                            'facing' => $facing,
                            'segment_width_updated' => $requiredWidth
                        ]);
                        
                        return true;
                    } catch (\Exception $e) {
                        Log::error("❌ Erro ao colocar produto verticalmente", [
                            'segment_id' => $segment->id,
                            'product_id' => $product['product_id'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
        
        // 5. SE NÃO ENCONTROU SEGMENTO VAZIO, CRIAR NOVO (com validação de largura)
        return $this->createVerticalSegmentWithValidation($shelf, $product, $facing, $availableWidth);
    }
    
    /**
     * NOVO: Calcula largura já utilizada na prateleira CORRIGIDO
     */
    protected function calculateUsedWidthInShelf($shelf): float
    {
        $segments = $shelf->segments()->with('layer.product')->get();
        $usedWidth = 0;
        $productsFound = [];
        
        foreach ($segments as $segment) {
            // Só contabilizar segmentos que têm produtos ativos
            if ($segment->layer && $segment->layer->product_id && $segment->layer->product) {
                $product = $segment->layer->product;
                $productId = $segment->layer->product_id;
                $quantity = intval($segment->layer->quantity ?? 1);
                
                // Calcular largura real baseada no produto e quantidade
                if (!$product->width || $product->width <= 0) {
                    Log::warning("❌ Produto sem largura válida ignorado", [
                        'product_id' => $product->id ?? 'unknown',
                        'width' => $product->width ?? 'null'
                    ]);
                    continue;
                }
                $productWidth = $this->getProductWidth(['width' => $product->width]);
                $segmentUsedWidth = $productWidth * $quantity;
                
                $usedWidth += $segmentUsedWidth;
                
                $productsFound[] = [
                    'product_id' => $productId,
                    'product_width' => $productWidth,
                    'quantity' => $quantity,
                    'segment_width' => $segmentUsedWidth
                ];
            }
        }
        
        // Largura corrigida calculada na prateleira
        
        return $usedWidth;
    }
    
    /**
     * NOVO: Cria segmento vertical COM VALIDAÇÃO de largura
     */
    protected function createVerticalSegmentWithValidation($shelf, array $product, int $facing, float $availableWidth): bool
    {
        $productData = $product['product'] ?? [];
        $productWidth = $this->getProductWidth($productData);
        $requiredWidth = $productWidth * $facing;
        
        // FACING ADAPTATIVO também para criação de segmento
        $adaptiveResult = $this->facingCalculator->calculateAdaptiveFacing($product, $availableWidth, $facing);
        $adaptedFacing = $adaptiveResult['facing'];
        $adaptedRequiredWidth = $adaptiveResult['required_width'];
        
        // Facing adaptativo no novo segmento
        
        // Verificar se há largura suficiente mesmo com facing reduzido
        if ($adaptedFacing <= 0 || $adaptedRequiredWidth > $availableWidth) {
            Log::warning("⚠️ Não é possível criar segmento mesmo com facing mínimo", [
                'shelf_id' => $shelf->id,
                'product_id' => $product['product_id'],
                'facing_minimum_tried' => $adaptedFacing,
                'required_width_cm' => $adaptedRequiredWidth,
                'available_width_cm' => $availableWidth
            ]);
            return false;
        }
        
        // Usar facing adaptado
        $facing = $adaptedFacing;
        $requiredWidth = $adaptedRequiredWidth;
        
        try {
            $segment = $shelf->segments()->create([
                'tenant_id' => $shelf->tenant_id,
                'user_id' => $shelf->user_id,
                'width' => $requiredWidth,
                'ordering' => $shelf->segments()->count(),
                'quantity' => 1,
                'status' => 'published'
            ]);

            $segment->layer()->create([
                'tenant_id' => $segment->tenant_id,
                'user_id' => $segment->user_id,
                'product_id' => $product['product_id'],
                'quantity' => $facing,
                'status' => 'published'
            ]);
            
            Log::info("✅ Segmento criado COM VALIDAÇÃO de largura", [
                'segment_id' => $segment->id,
                'shelf_id' => $shelf->id,
                'product_id' => $product['product_id'],
                'segment_width_cm' => $requiredWidth,
                'facing' => $facing
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("❌ Erro ao criar segmento vertical validado", [
                'shelf_id' => $shelf->id,
                'product_id' => $product['product_id'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    
    /**
     * Retorna estratégia do módulo para logs
     */
    protected function getModuleStrategy(int $moduleNumber): string
    {
        return match($moduleNumber) {
            1 => 'NOBRE - Classe A + melhor B (balanceado)',
            2 => 'PREMIUM - Classe B (4 produtos)',
            3 => 'INTERMEDIÁRIO - Classe B + melhores C (balanceado)',
            4 => 'BÁSICO - Classe C restantes (balanceado)',
            default => "EXTRA $moduleNumber - Produtos restantes (distribuição equilibrada)"
        };
    }

    /**
     * NOVO: Preenchimento oportunístico - maximiza uso do espaço após distribuição principal
     */
    protected function fillOpportunisticSpace($section, array $products): array
    {
        $segmentsUsed = 0;
        $totalPlacements = 0;
        
        $shelves = $section->shelves()->orderBy('ordering')->get();
        
        // Iniciando preenchimento oportunístico
        
        foreach ($shelves as $shelf) {
            // 1. EXPANDIR FACING DOS PRODUTOS EXISTENTES
            $expandResults = $this->expandExistingFacing($shelf, $products);
            $segmentsUsed += $expandResults['segments_used'];
            $totalPlacements += $expandResults['total_placements'];
            
            // 2. PREENCHER PRATELEIRAS VAZIAS
            $fillResults = $this->fillEmptyShelfSpace($shelf, $products);
            $segmentsUsed += $fillResults['segments_used'];
            $totalPlacements += $fillResults['total_placements'];
        }
        
        // Preenchimento oportunístico concluído
        
        return [
            'segments_used' => $segmentsUsed,
            'total_placements' => $totalPlacements
        ];
    }
    
    /**
     * Expande facing de produtos já colocados se há espaço
     */
    protected function expandExistingFacing($shelf, array $products): array
    {
        $segmentsUsed = 0;
        $totalPlacements = 0;
        
        $availableWidth = $this->getShelfAvailableWidth($shelf);
        
        if ($availableWidth < 15.0) { // Menos que um produto pequeno
            return ['segments_used' => 0, 'total_placements' => 0];
        }
        
        // Pegar produtos que já estão na prateleira
        $segments = $shelf->segments()->with('layer.product')->get();
        
        foreach ($segments as $segment) {
            if ($segment->layer && $segment->layer->product_id && $availableWidth > 0) {
                $product = $segment->layer->product;
                if ($product) {
                    if (!$product->width || $product->width <= 0) {
                        continue; // Pular produtos sem largura válida
                    }
                    $productWidth = $this->getProductWidth(['width' => $product->width]);
                    $currentFacing = $segment->layer->quantity ?? 1;
                    
                    // Calcular quantos facings adicionais cabem
                    $additionalFacings = floor($availableWidth / $productWidth);
                    
                    if ($additionalFacings > 0) {
                        try {
                            // Expandir facing do produto existente
                            $newFacing = $currentFacing + $additionalFacings;
                            $segment->layer->update(['quantity' => $newFacing]);
                            
                            $additionalWidth = $additionalFacings * $productWidth;
                            $segment->update(['width' => $segment->width + $additionalWidth]);
                            
                            $totalPlacements += $additionalFacings;
                            $availableWidth -= $additionalWidth;
                            
                            // Facing expandido com sucesso
                            
                            break; // Um produto por vez
                        } catch (\Exception $e) {
                            Log::error("❌ Erro ao expandir facing", [
                                'segment_id' => $segment->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            }
        }
        
        return [
            'segments_used' => $segmentsUsed,
            'total_placements' => $totalPlacements
        ];
    }
    
    /**
     * Preenche espaços vazios da prateleira com novos produtos
     */
    protected function fillEmptyShelfSpace($shelf, array $products): array
    {
        $segmentsUsed = 0;
        $totalPlacements = 0;
        
        $availableWidth = $this->getShelfAvailableWidth($shelf);
        
        if ($availableWidth < 15.0) { // Menos que um produto pequeno
            return ['segments_used' => 0, 'total_placements' => 0];
        }
        
        // Tentar colocar produtos que ainda não estão na prateleira
        foreach ($products as $product) {
            if ($availableWidth > 0) {
                $productData = $product['product'] ?? [];
                $productWidth = $this->getProductWidth($productData);
                
                // Calcular quantos facings cabem
                $possibleFacings = floor($availableWidth / $productWidth);
                
                if ($possibleFacings > 0) {
                    $success = $this->placeProductInShelfVertically($shelf, $product, $possibleFacings);
                    
                    if ($success) {
                        $segmentsUsed++;
                        $totalPlacements += $possibleFacings;
                        $usedSpace = $possibleFacings * $productWidth;
                        $availableWidth -= $usedSpace;
                        
                        // Produto adicionado oportunisticamente
                        
                        if ($availableWidth < 15.0) {
                            break; // Prateleira quase cheia
                        }
                    }
                }
            }
        }
        
        return [
            'segments_used' => $segmentsUsed,
            'total_placements' => $totalPlacements
        ];
    }

    // Método applyDynamicFilters não é mais necessário - usa endpoint do Products.vue

    /**
     * 🧠 Geração inteligente com ABC + Target Stock
     * 
     * POST /api/plannerate/auto-planogram/generate-intelligent
     */
    public function generateIntelligent(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            // 1. VALIDAÇÃO
            $validator = Validator::make($request->all(), [
                'gondola_id' => 'required|exists:gondolas,id',
                'filters' => 'array',
                'abc_params' => 'required|array',
                'abc_params.weights' => 'required|array',
                'abc_params.thresholds' => 'required|array',
                'target_stock_params' => 'required|array',
                'facing_limits' => 'required|array',
                'auto_distribute' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos fornecidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $gondola = Gondola::with(['sections.shelves.segments.layer', 'planogram'])->findOrFail($request->gondola_id);
            
            // 2. BUSCAR TODOS OS PRODUTOS (SEM LIMITE)
            $allProducts = $this->getAllProductsByPlanogramCategory($gondola, $request);
            
            // Filtrar apenas produtos com dimensões válidas
            $productsWithDimensions = collect($allProducts)->filter(function($product) {
                return isset($product['dimensions']) && 
                       isset($product['dimensions']['width']) && 
                       $product['dimensions']['width'] > 0;
            })->values()->toArray();
            
            Log::info('🔍 Filtragem de produtos por dimensões', [
                'produtos_total' => count($allProducts),
                'produtos_with_dimensions' => count($productsWithDimensions),
                'produtos_sem_dimensions' => count($allProducts) - count($productsWithDimensions)
            ]);
            
            if (empty($productsWithDimensions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhum produto com dimensões válidas encontrado para análise inteligente'
                ], 404);
            }
            
            // Usar apenas produtos com dimensões válidas
            $allProducts = $productsWithDimensions;
            
            Log::info("🧠 Geração inteligente iniciada", [
                'gondola_id' => $gondola->id,
                'total_products' => count($allProducts),
                'abc_params' => $request->abc_params,
                'target_stock_params' => $request->target_stock_params
            ]);
            
            // 3. EXECUTAR ANÁLISE ABC
            $abcResults = $this->executeABCAnalysis($allProducts, $request->abc_params);
            
            // 4. EXECUTAR ANÁLISE TARGET STOCK
            $targetStockResults = $this->executeTargetStockAnalysis($allProducts, $request->target_stock_params, $gondola->planogram_id);
            
            // 5. PROCESSAR PRODUTOS COM DADOS INTELIGENTES
            $processedProducts = $this->processProductsWithNewLogic(
                $allProducts,
                $abcResults,
                $targetStockResults,
                $gondola
            );
            
            // 🔒 VALIDAÇÃO: Consistência após processamento
            $this->validateProductCount('After Processing', count($allProducts), count($processedProducts));
            
            // 6. DISTRIBUIR NA GÔNDOLA
            $distributionResult = null;
            if ($request->auto_distribute) {
                $this->clearGondola($gondola);
                $distributionResult = $this->distributeIntelligently($gondola, $processedProducts);
            }
            
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::info("✅ Geração inteligente concluída", [
                'gondola_id' => $gondola->id,
                'processing_time_ms' => $processingTime,
                'products_processed' => count($processedProducts),
                'products_placed' => $distributionResult['products_placed'] ?? 0
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Planograma inteligente gerado com sucesso',
                'data' => [
                    'gondola' => [
                        'id' => $gondola->id,
                        'name' => $gondola->name,
                        'sections' => $gondola->sections->count(),
                    ],
                    'products_processed' => count($processedProducts),
                    'distribution_result' => $distributionResult
                ],
                'metadata' => [
                    'abc_analysis' => [
                        'products_analyzed' => count($abcResults),
                        'class_distribution' => $this->getABCDistribution($abcResults)
                    ],
                    'target_stock_analysis' => [
                        'products_analyzed' => count($targetStockResults),
                        'urgency_distribution' => $this->getUrgencyDistribution($targetStockResults)
                    ],
                    'processing_time_ms' => $processingTime
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro na geração inteligente', [
                'gondola_id' => $request->gondola_id,
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro na geração inteligente: ' . $e->getMessage(),
                'error_details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * 📊 Executar análise ABC
     */
    protected function executeABCAnalysis(array $products, array $abcParams): array
    {
        $productIds = collect($products)->pluck('id')->toArray();
        
        // Usar o ABCAnalysisService para obter dados brutos
        $analysisData = $this->abcAnalysisService->analyze($productIds);
        
        // Calcular score composto para ordenação
        $weights = $abcParams['weights'];
        $scoredData = array_map(function($productData) use ($weights) {
            $productData['composite_score'] = 
                ($productData['quantity'] * $weights['quantity']) +
                ($productData['value'] * $weights['value']) +
                ($productData['margin'] * $weights['margin']);
            return $productData;
        }, $analysisData);

        // Ordenar produtos pelo score composto (maior primeiro)
        usort($scoredData, fn($a, $b) => $b['composite_score'] <=> $a['composite_score']);
        
        // Classificar em ABC baseado nos thresholds
        $totalProducts = count($scoredData);
        if ($totalProducts === 0) {
            return [];
        }

        $classifiedProducts = [];
        
        foreach ($scoredData as $index => $productData) {
            $percentile = ($index + 1) / $totalProducts * 100;
            
            if ($percentile <= $abcParams['thresholds']['a']) {
                $productData['abc_class'] = 'A';
            } elseif ($percentile <= $abcParams['thresholds']['b']) {
                $productData['abc_class'] = 'B';
            } else {
                $productData['abc_class'] = 'C';
            }
            
            // 🎯 CORRIGIDO: Mapeamento consistente de IDs
            // O 'id' do ABCAnalysisService é o EAN do produto
            $productEan = $productData['id'];
            $productModel = \App\Models\Product::where('ean', $productEan)->first();
            
            if ($productModel) {
                // Adicionar ID interno para compatibilidade
                $productData['product_id'] = $productModel->id;
                // Manter o EAN como 'id' para compatibilidade com TargetStockAnalysisService
                $productData['id'] = $productEan;
            } else {
                Log::warning("⚠️ Produto não encontrado no banco", [
                    'ean' => $productEan,
                    'abc_class' => $productData['abc_class'] ?? 'N/A'
                ]);
                $productData['product_id'] = null;
            }

            $classifiedProducts[] = $productData;
        }
        
        // Log para resumo da análise
        $classA = array_filter($classifiedProducts, fn($p) => $p['abc_class'] === 'A');
        $classB = array_filter($classifiedProducts, fn($p) => $p['abc_class'] === 'B');
        $classC = array_filter($classifiedProducts, fn($p) => $p['abc_class'] === 'C');

        Log::info("📊 Análise ABC (Novo Fluxo) concluída", [
            'total_products' => count($classifiedProducts),
            'distribution' => [
                'class_A' => count($classA),
                'class_B' => count($classB),
                'class_C' => count($classC)
            ]
        ]);
        
        return $classifiedProducts;
    }

    /**
     * 📦 Executar análise Target Stock
     */
    protected function executeTargetStockAnalysis(array $products, array $targetStockParams, string $planogramId): array
    {
        $results = [];
        
        // ✅ USAR TARGETSTOCKANALYSISSERVICE (mesmo do TargetStockResultModal)
        $productIds = array_column($products, 'id');
        
        // Buscar período do planograma
        $planogram = \App\Models\Planogram::find($planogramId);
        if (!$planogram) {
            Log::error("❌ Planograma não encontrado para análise target stock", ['planogram_id' => $planogramId]);
            return [];
        }
        
        // ✅ USAR O MESMO SERVIÇO QUE O TARGETSTOCKRESULTMODAL
        $analysisResults = $this->targetStockAnalysisService->analyze(
            $productIds,
            $planogram->start_date,
            $planogram->end_date,
            null // store_id
        );
        
        Log::info("🔍 DEBUG: TargetStockAnalysisService retornou", [
            'total_results' => count($analysisResults),
            'first_result_keys' => array_keys(array_slice($analysisResults, 0, 3)),
            'sample_product_ids' => array_slice($productIds, 0, 3)
        ]);
        
        // Converter resultado para formato esperado pelo AutoPlanogramController
        // O TargetStockAnalysisService retorna array indexado numericamente, então vamos converter
        $analysisByProductId = [];
        foreach ($analysisResults as $analysis) {
            $analysisByProductId[$analysis['product_id']] = $analysis;
        }
        
        foreach ($products as $product) {
            $productId = $product['id'];
            $analysis = $analysisByProductId[$productId] ?? null;
            
            if ($analysis) {
                // ✅ APLICAR A MESMA FÓRMULA DO USETARGETSTOCK (FRONTEND)
                $averageSales = $analysis['average_sales'] ?? 0;
                $standardDeviation = $analysis['standard_deviation'] ?? 0;
                $currentStock = $analysis['currentStock'] ?? 0;
                
                // 🎯 SIMPLIFICADO: Usar classificação padrão 'A' para Target Stock
                // A classificação ABC específica será aplicada no FacingCalculatorService
                $classification = 'A'; // Padrão para Target Stock - ABC será aplicado no facing
                
                // ✅ USAR PARÂMETROS POR CLASSE ABC (igual ao TargetStockParamsPopover.vue)
                $serviceLevels = [
                    ['classification' => 'A', 'level' => 0.70], // 70% como no TargetStockResultModal
                    ['classification' => 'B', 'level' => 0.80], // 80% como no TargetStockResultModal  
                    ['classification' => 'C', 'level' => 0.90]  // 90% como no TargetStockResultModal
                ];
                $replenishmentParams = [
                    ['classification' => 'A', 'coverageDays' => 2], // 2 dias como no TargetStockResultModal
                    ['classification' => 'B', 'coverageDays' => 5], // 5 dias como no TargetStockResultModal
                    ['classification' => 'C', 'coverageDays' => 7]  // 7 dias como no TargetStockResultModal
                ];
                
                // Log removido - informação desnecessária
                
                // Encontrar parâmetros para a classificação específica
                $serviceLevel = 70; // padrão
                $coverageDays = 2; // padrão
                
                foreach ($serviceLevels as $sl) {
                    if ($sl['classification'] === $classification) {
                        $serviceLevel = $sl['level'] * 100;
                        break;
                    }
                }
                
                foreach ($replenishmentParams as $rp) {
                    if ($rp['classification'] === $classification) {
                        $coverageDays = $rp['coverageDays'];
                        break;
                    }
                }
                
                // ✅ CALCULAR TARGET STOCK COM A MESMA FÓRMULA DO FRONTEND
                // 1. Calcular Z-Score baseado no Service Level
                $zScore = $this->calculateZScore($serviceLevel);
                
                // 2. Calcular Estoque de Segurança dinamicamente (Z-Score × Desvio Padrão)
                $safetyStock = $zScore * $standardDeviation;
                
                // 3. Calcular Estoque Mínimo (Demanda Média × Dias de Cobertura)
                $minimumStock = $averageSales * $coverageDays;
                
                // 4. Calcular Estoque Alvo (Mínimo + Segurança)
                $targetStock = $minimumStock + $safetyStock;
                
                Log::info("🧮 Cálculo detalhado do Target Stock", [
                    'product_id' => $productId,
                    'product_name' => $product['name'],
                    'average_sales' => $averageSales,
                    'standard_deviation' => $standardDeviation,
                    'service_level' => $serviceLevel,
                    'coverage_days' => $coverageDays,
                    'z_score' => round($zScore, 3),
                    'safety_stock_calculated' => round($safetyStock, 2),
                    'minimum_stock' => round($minimumStock, 2),
                    'target_stock_final' => round($targetStock, 2),
                    'formula' => "($averageSales × $coverageDays) + ($zScore × $standardDeviation) = $minimumStock + $safetyStock = $targetStock"
                ]);
                
            $stockRatio = $targetStock > 0 ? $currentStock / $targetStock : 1;
            $urgency = $this->determineStockUrgency($stockRatio);
            
            $results[] = [
                    'product_id' => $productId,
                    'product_name' => $product['name'] ?? 'Produto sem nome',
                    'daily_sales' => $averageSales,
                'current_stock' => $currentStock,
                    'target_stock' => round($targetStock),
                'stock_ratio' => $stockRatio,
                'urgency' => $urgency,
                    'coverage_days' => $averageSales > 0 ? floor($currentStock / $averageSales) : 999
                ];
                
                Log::info("📦 Target Stock calculado via TargetStockAnalysisService", [
                    'product_id' => $productId,
                    'product_name' => $product['name'],
                    'target_stock' => round($targetStock),
                    'current_stock' => $currentStock,
                    'urgency' => $urgency,
                    'source' => 'targetStockAnalysisService_unified',
                    'z_score' => $zScore,
                    'safety_stock' => $safetyStock,
                    'minimum_stock' => $minimumStock
                ]);
            }
        }
        
        // Calcular estatísticas detalhadas da análise Target Stock
        $criticalProducts = array_filter($results, fn($r) => $r['urgency'] === 'CRÍTICO');
        $lowStockProducts = array_filter($results, fn($r) => $r['urgency'] === 'BAIXO');
        $normalProducts = array_filter($results, fn($r) => $r['urgency'] === 'NORMAL');

        $resultsCount = count($results);
        $stockStats = [
            'avg_current_stock' => $resultsCount > 0 ? round(array_sum(array_column($results, 'current_stock')) / $resultsCount, 1) : 0,
            'avg_target_stock' => $resultsCount > 0 ? round(array_sum(array_column($results, 'target_stock')) / $resultsCount, 1) : 0,
            'avg_stock_ratio' => $resultsCount > 0 ? round(array_sum(array_column($results, 'stock_ratio')) / $resultsCount, 2) : 0
        ];

        Log::info("📦 Análise Target Stock concluída", [
            'total_products' => count($results),
            'urgency_distribution' => [
                'critical' => count($criticalProducts),
                'low_stock' => count($lowStockProducts),
                'normal' => count($normalProducts)
            ],
            'urgency_percentages' => [
                'critical' => $resultsCount > 0 ? round((count($criticalProducts) / $resultsCount) * 100, 1) . '%' : '0%',
                'low_stock' => $resultsCount > 0 ? round((count($lowStockProducts) / $resultsCount) * 100, 1) . '%' : '0%',
                'normal' => $resultsCount > 0 ? round((count($normalProducts) / $resultsCount) * 100, 1) . '%' : '0%'
            ],
            'stock_stats' => $stockStats,
            'critical_products_sample' => array_slice(
                array_map(fn($p) => $p['product_name'] . ' (Stock: ' . $p['current_stock'] . '/' . $p['target_stock'] . ')', 
                    $criticalProducts
                ), 0, 3
            )
        ]);
        
        return $results;
    }

    /**
     * 🧠 NOVO: Processa produtos com a nova lógica transparente
     */
    protected function processProductsWithNewLogic(
        array $products, 
        array $abcResults, 
        array $targetStockResults,
        Gondola $gondola
    ): array
    {
        Log::info("🧠 Iniciando processamento de produtos (Novo Fluxo)", [
            'total_products' => count($products)
        ]);
        
        // Obter dados da primeira prateleira para usar como referência de dimensões
        $firstShelf = $gondola->sections()->first()->shelves()->first();
        
        // 🔍 DEBUG: Verificar profundidades de todas as prateleiras
        $allShelfDepths = [];
        foreach ($gondola->sections as $section) {
            foreach ($section->shelves as $shelf) {
                $allShelfDepths[] = [
                    'section' => $section->ordering,
                    'shelf' => $shelf->ordering,
                    'depth' => $shelf->shelf_depth
                ];
            }
        }
        
        Log::info("🔍 Verificando profundidades das prateleiras", [
            'total_shelves' => count($allShelfDepths),
            'shelf_depths' => $allShelfDepths,
            'first_shelf_depth' => $firstShelf->shelf_depth ?? 40,
            'all_same_depth' => count(array_unique(array_column($allShelfDepths, 'depth'))) === 1
        ]);
        
        $shelfData = [
            'height' => $firstShelf->shelf_height ?? 40,
            'depth' => $firstShelf->shelf_depth ?? 40,
        ];

        $processedProducts = array_map(function($product) use ($abcResults, $targetStockResults, $shelfData) {
            
            // 1. OBTER DADOS ABC - CORRIGIDO: Buscar por ID interno e EAN
            $productId = $product['id'];
            $productEan = $product['ean'] ?? null;
            
            // Primeiro tentar por ID interno
            $abcData = collect($abcResults)->firstWhere('product_id', $productId);
            
            // Se não encontrou, tentar por EAN (que é o 'id' original do ABCAnalysisService)
            if (!$abcData && $productEan) {
                $abcData = collect($abcResults)->firstWhere('id', $productEan);
            }
            
            // Se ainda não encontrou, usar dados padrão
            if (!$abcData) {
                Log::warning("⚠️ Dados ABC não encontrados para produto", [
                    'product_id' => $productId,
                    'product_ean' => $productEan,
                    'product_name' => $product['name'] ?? 'N/A',
                    'abc_results_count' => count($abcResults)
                ]);
                
                $abcData = [
                    'abc_class' => 'C',
                    'composite_score' => 0,
                    'id' => $productEan ?? $productId,
                    'product_id' => $productId
                ];
            }
            
            // 2. OBTER DADOS TARGET STOCK - CORRIGIDO: Buscar por ID interno
            $targetStockData = collect($targetStockResults)->firstWhere('product_id', $productId);
            
            if (!$targetStockData) {
                // Se não encontrou, tentar por EAN (fallback)
                if ($productEan) {
                    $targetStockData = collect($targetStockResults)->firstWhere('product_id', $productEan);
                }
                
                // Log do problema e usar dados padrão baseados na classe ABC
                Log::warning("❌ Target Stock não encontrado para produto", [
                    'product_id' => $productId,
                    'product_ean' => $productEan,
                    'product_name' => $product['name'] ?? 'N/A',
                    'target_stock_results_count' => count($targetStockResults),
                    'first_target_result_id' => $targetStockResults[0]['product_id'] ?? 'N/A'
                ]);
                
                // Fallback inteligente baseado na classe ABC
                $abcClass = $abcData['abc_class'] ?? 'C';
                $defaultTargetStock = match($abcClass) {
                    'A' => 10, // Produtos A: estoque maior
                    'B' => 6,  // Produtos B: estoque médio
                    'C' => 3,  // Produtos C: estoque menor
                    default => 2
                };
                
                $targetStockData = [
                    'target_stock' => $defaultTargetStock,
                    'current_stock' => 0,
                    'urgency' => 'NORMAL'
                ];
            }
            
            // 3. 🎯 CALCULAR FACING INTELIGENTE (ABC + Target Stock + Dimensões)
            // Log para debug da integração
            Log::info("🔧 DEBUG: Dados antes do facing calculator", [
                'product_id' => $productId,
                'product_name' => $product['name'] ?? 'N/A',
                'abc_class' => $abcData['abc_class'] ?? 'N/A',
                'target_stock' => $targetStockData['target_stock'] ?? 'N/A',
                'current_stock' => $targetStockData['current_stock'] ?? 'N/A',
                'urgency' => $targetStockData['urgency'] ?? 'N/A'
            ]);
            
            $facingResult = $this->facingCalculator->calculateIntelligentFacing(
                $product, 
                $abcData,
                $targetStockData,
                $shelfData
            );
            
            // 4. CALCULAR PRIORIDADE BASEADA NO FACING INTELIGENTE E ABC
            $priority = ($facingResult['coverage_efficiency'] * 0.6) + 
                       ($abcData['composite_score'] * 0.4);
            
            return [
                'product_id' => $product['id'],
                'abc_class' => $facingResult['abc_class'],
                'composite_score' => $abcData['composite_score'] ?? 0,
                'target_stock_data' => $targetStockData,
                'intelligent_facing' => $facingResult['facing'],
                'facing_details' => $facingResult, // 🆕 Dados completos do facing inteligente
                'priority_score' => $priority,
                'product' => [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'width' => $product['dimensions']['width'] ?? null,
                    'height' => $product['dimensions']['height'] ?? null,
                    'depth' => $product['dimensions']['depth'] ?? null,
                    'dimensions' => $product['dimensions'] ?? []
                ]
            ];
            
        }, $products);

        Log::info("✅ Processamento de produtos (Novo Fluxo) concluído", [
            'products_processed' => count($processedProducts)
        ]);

        return $processedProducts;
    }

    /**
     * 🎯 NOVO SISTEMA: Distribuição Linear de Produtos
     * 
     * Distribui produtos sequencialmente por prateleiras, eliminando desperdício de espaço.
     * 
     * ALGORITMO:
     * 1. Manter lógica ABC por categoria
     * 2. Distribuir categoria completa por prateleiras lineares
     * 3. Nunca alterar facing calculado
     * 4. Falha em vez de adaptação
     */
    protected function distributeIntelligently(Gondola $gondola, array $processedProducts): array
    {
        Log::info("🎯 Iniciando distribuição linear de produtos", [
            'total_products' => count($processedProducts)
        ]);
        
        $gondolaStructure = $this->analyzeGondolaStructure($gondola);
        $this->ensureGondolaHasSegments($gondola);
        
        // 🎯 NOVO SISTEMA: Distribuição Linear de Produtos
        $distributionResult = $this->productPlacement->placeProductsSequentially($gondola, $processedProducts, $gondolaStructure);
        
        Log::info("🏪 Distribuição linear por categoria concluída", [
            'products_placed' => $distributionResult['products_placed'],
            'total_placements' => $distributionResult['total_placements'],
            'segments_used' => $distributionResult['segments_used'],
            'products_failed' => count($distributionResult['failed_products'] ?? [])
        ]);
        
        return $distributionResult;
    }

    /**
     * 🎯 NOVO SISTEMA: Planejamento Sequencial por Categoria
     * 
     * FASE 1: Análise e Ordenação
     * - Executar ABC geral nos produtos
     * - Ordenar categorias por prioridade: Açúcar → Arroz → Feijão → Sal
     * - Para cada categoria, aplicar ABC interno e calcular facing inteligente
     * 
     * FASE 2: Cálculo de Capacidade Total
     * - Para cada categoria, calcular exatamente quanto espaço ela precisa
     * - Considerar que cada módulo tem 4 prateleiras de 125cm = 500cm total por módulo
     * 
     * FASE 3: Alocação Sequencial
     * - Categoria Açúcar precisa 1.200cm → Alocar módulos 1-3 (1.500cm disponível)
     * - Categoria Arroz precisa 800cm → Alocar módulos 4-5 (1.000cm disponível)
     * - Zero mistura de categorias em módulos diferentes
     */
    protected function distributeSequentiallyByCategory(Gondola $gondola, array $processedProducts, array $gondolaStructure): array
    {
        Log::info("🎯 Iniciando Planejamento Sequencial por Categoria", [
            'total_products' => count($processedProducts),
            'total_modules' => $gondolaStructure['total_sections']
        ]);
        
        // FASE 1: Análise e Ordenação
        $categoryAnalysis = $this->analyzeCategoryCapacity($processedProducts, $gondolaStructure);
        
        // FASE 2: Cálculo de Capacidade Total
        $moduleAllocation = $this->calculateModuleAllocation($categoryAnalysis, $gondolaStructure);
        
        // FASE 3: Alocação Sequencial
        $distributionResult = $this->executeSequentialAllocation($gondola, $moduleAllocation, $gondolaStructure);
        
        Log::info("✅ Planejamento Sequencial por Categoria concluído", [
            'categories_processed' => count($categoryAnalysis['categories']),
            'modules_allocated' => count($moduleAllocation['allocations']),
            'products_placed' => $distributionResult['products_placed'],
            'total_placements' => $distributionResult['total_placements']
        ]);
        
        return $distributionResult;
    }

    /**
     * 🎯 NOVO: Distribuição baseada em ABC por categoria (MÉTODO LEGADO - MANTIDO PARA COMPATIBILIDADE)
     * 1. Analisa TODOS os produtos e aplica ABC
     * 2. Identifica categoria prioritária (maior valor ABC)
     * 3. Coleta TODOS os produtos dessa categoria
     * 4. Aplica ABC dentro da categoria
     * 5. Distribui por módulos respeitando ABC interno
     * 6. Repete para próxima categoria
     */
    protected function distributeByCategoryABC(Gondola $gondola, array $processedProducts, array $gondolaStructure): array
    {
        $totalProductsPlaced = 0;
        $totalPlacements = 0;
        $segmentsUsed = 0;
        $categoriesProcessed = 0;
        
        // 1. 📊 ANÁLISE ABC GERAL - Identificar categoria prioritária
        $categoryPriority = $this->analyzeCategoryPriority($processedProducts);
        
        Log::info("📊 Categoria prioritária identificada", [
            'priority_category' => $categoryPriority['category'],
            'total_abc_value' => $categoryPriority['total_abc_value'],
            'products_count' => $categoryPriority['products_count']
        ]);
        
        // 2. 🔄 PROCESSAR CATEGORIAS POR ORDEM DE PRIORIDADE ABC
        $remainingProducts = $processedProducts;
        
        foreach ($categoryPriority['categories_ordered'] as $categoryInfo) {
            $categoryName = $categoryInfo['category'];
            $categoryProducts = $categoryInfo['products'];
            
            Log::info("🎯 Processando categoria: {$categoryName}", [
                'products_count' => count($categoryProducts),
                'category_abc_value' => $categoryInfo['total_abc_value']
            ]);
            
            // 3. 📈 APLICAR ABC DENTRO DA CATEGORIA
            $categoryProductsABC = $this->applyABCWithinCategory($categoryProducts);
            
            // 4. 🏪 DISTRIBUIR CATEGORIA NOS MÓDULOS
            $categoryResult = $this->distributeCategoryInModules($gondola, $categoryProductsABC, $gondolaStructure, $categoryName);
            
            // 5. 📊 CONSOLIDAR RESULTADOS
            $totalProductsPlaced += $categoryResult['products_placed'];
            $totalPlacements += $categoryResult['total_placements'];
            $segmentsUsed += $categoryResult['segments_used'];
            $categoriesProcessed++;
            
            Log::info("✅ Categoria {$categoryName} processada", [
                'products_placed' => $categoryResult['products_placed'],
                'total_placements' => $categoryResult['total_placements'],
                'segments_used' => $categoryResult['segments_used']
            ]);
            
            // 6. 🧹 REMOVER PRODUTOS JÁ PROCESSADOS
            $remainingProducts = array_filter($remainingProducts, function($product) use ($categoryProducts) {
                $productId = $product['product']['id'];
                return !collect($categoryProducts)->contains('product.id', $productId);
            });
        }
        
        return [
            'products_placed' => $totalProductsPlaced,
            'total_placements' => $totalPlacements,
            'segments_used' => $segmentsUsed,
            'categories_processed' => $categoriesProcessed,
            'remaining_products' => count($remainingProducts)
        ];
    }

    /**
     * 🎯 FASE 1: Análise e Ordenação - Calcula capacidade necessária por categoria
     * 
     * Para cada categoria:
     * - Agrupa produtos por primeira palavra do nome
     * - Aplica ABC interno dentro da categoria
     * - Calcula largura total necessária baseada em facing × dimensões
     * - Ordena categorias por prioridade ABC
     */
    protected function analyzeCategoryCapacity(array $processedProducts, array $gondolaStructure): array
    {
        Log::info("📊 FASE 1: Iniciando análise de capacidade por categoria", [
            'total_products' => count($processedProducts)
        ]);
        
        // 1. Agrupar produtos por categoria (primeira palavra do nome)
        $categoryGroups = [];
        
        foreach ($processedProducts as $product) {
            $productName = strtoupper($product['product']['name'] ?? 'OUTROS');
            $categoryKey = explode(' ', $productName)[0]; // Primeira palavra
            
            if (!isset($categoryGroups[$categoryKey])) {
                $categoryGroups[$categoryKey] = [
                    'category' => $categoryKey,
                    'products' => [],
                    'total_abc_value' => 0,
                    'total_width_needed' => 0,
                    'total_facings' => 0
                ];
            }
            
            $categoryGroups[$categoryKey]['products'][] = $product;
            
            // ✅ CORRIGIDO: Calcular largura necessária baseada no facing otimizado
            $productWidth = $product['product']['width'] ?? 20; // cm
            $facing = $product['intelligent_facing'] ?? 1;
            
            // ✅ NOVA LÓGICA: Calcular largura real considerando distribuição uniforme
            // Em vez de usar facing × largura, usar largura média por prateleira
            $shelvesPerModule = 4; // Assumindo 4 prateleiras por módulo
            $facingsPerShelf = ceil($facing / $shelvesPerModule);
            $widthNeeded = $productWidth * $facingsPerShelf; // Largura por prateleira, não total
            
            $categoryGroups[$categoryKey]['total_abc_value'] += $product['priority_score'] ?? 0;
            $categoryGroups[$categoryKey]['total_width_needed'] += $widthNeeded;
            $categoryGroups[$categoryKey]['total_facings'] += $facing;
        }
        
        // 2. Aplicar ABC interno dentro de cada categoria
        foreach ($categoryGroups as $categoryKey => &$category) {
            $category['products'] = $this->applyABCWithinCategory($category['products']);
        }
        
        // 3. Ordenar categorias por prioridade ABC (maior valor primeiro)
        $categoriesOrdered = collect($categoryGroups)->sortByDesc('total_abc_value')->values()->toArray();
        
        // 4. Calcular estatísticas finais
        $totalWidthNeeded = array_sum(array_column($categoriesOrdered, 'total_width_needed'));
        $totalModules = $gondolaStructure['total_sections'];
        $moduleCapacity = $this->calculateModuleCapacity($gondolaStructure);
        $totalCapacity = $totalModules * $moduleCapacity;
        
        Log::info("📊 FASE 1: Análise de capacidade concluída", [
            'categories_found' => count($categoriesOrdered),
            'total_width_needed_cm' => round($totalWidthNeeded, 1),
            'total_capacity_cm' => $totalCapacity,
            'capacity_utilization_percent' => round(($totalWidthNeeded / $totalCapacity) * 100, 1),
            'categories_summary' => array_map(function($cat) {
                return [
                    'category' => $cat['category'],
                    'products_count' => count($cat['products']),
                    'total_width_cm' => round($cat['total_width_needed'], 1),
                    'total_facings' => $cat['total_facings'],
                    'abc_value' => round($cat['total_abc_value'], 2)
                ];
            }, $categoriesOrdered)
        ]);
        
        return [
            'categories' => $categoriesOrdered,
            'total_width_needed' => $totalWidthNeeded,
            'total_capacity' => $totalCapacity,
            'capacity_utilization' => $totalWidthNeeded / $totalCapacity,
            'module_capacity' => $moduleCapacity
        ];
    }

    /**
     * 📊 Analisa prioridade das categorias baseado em ABC (MÉTODO LEGADO - MANTIDO PARA COMPATIBILIDADE)
     */
    protected function analyzeCategoryPriority(array $processedProducts): array
    {
        // 1. Agrupar produtos por categoria
        $categoryGroups = [];
        
        foreach ($processedProducts as $product) {
            $productName = strtoupper($product['product']['name'] ?? 'OUTROS');
            $categoryKey = explode(' ', $productName)[0]; // Primeira palavra
            
            if (!isset($categoryGroups[$categoryKey])) {
                $categoryGroups[$categoryKey] = [
                    'category' => $categoryKey,
                    'products' => [],
                    'total_abc_value' => 0,
                    'total_quantity' => 0,
                    'total_margin' => 0
                ];
            }
            
            $categoryGroups[$categoryKey]['products'][] = $product;
            
            // Somar valores ABC da categoria
            $categoryGroups[$categoryKey]['total_abc_value'] += $product['priority_score'] ?? 0;
            $categoryGroups[$categoryKey]['total_quantity'] += $product['abc_data']['quantity'] ?? 0;
            $categoryGroups[$categoryKey]['total_margin'] += $product['abc_data']['margin'] ?? 0;
        }
        
        // 2. Ordenar categorias por valor ABC total (descrescente)
        $categoriesOrdered = collect($categoryGroups)->sortByDesc('total_abc_value')->values()->toArray();
        
        // 3. Identificar categoria prioritária
        $priorityCategory = $categoriesOrdered[0] ?? null;
        
        Log::info("📊 Análise de prioridade das categorias", [
            'total_categories' => count($categoriesOrdered),
            'priority_category' => $priorityCategory['category'] ?? 'NENHUMA',
            'priority_abc_value' => $priorityCategory['total_abc_value'] ?? 0,
            'categories_summary' => array_map(function($cat) {
                return [
                    'category' => $cat['category'],
                    'products_count' => count($cat['products']),
                    'total_abc_value' => round($cat['total_abc_value'], 2)
                ];
            }, $categoriesOrdered)
        ]);
        
        return [
            'category' => $priorityCategory['category'] ?? 'OUTROS',
            'total_abc_value' => $priorityCategory['total_abc_value'] ?? 0,
            'products_count' => count($priorityCategory['products'] ?? []),
            'categories_ordered' => $categoriesOrdered
        ];
    }

    /**
     * 🏗️ Calcula capacidade de um módulo baseado na estrutura da gôndola
     * Cada módulo = 4 prateleiras × 125cm = 500cm total
     */
    protected function calculateModuleCapacity(array $gondolaStructure): float
    {
        // Assumir que cada prateleira tem 125cm de largura (padrão)
        $shelfWidth = 125.0; // cm
        
        // Contar prateleiras por módulo (assumindo 4 prateleiras por módulo)
        $shelvesPerModule = 4;
        
        $moduleCapacity = $shelfWidth * $shelvesPerModule;
        
        Log::info("🏗️ Capacidade do módulo calculada", [
            'shelf_width_cm' => $shelfWidth,
            'shelves_per_module' => $shelvesPerModule,
            'module_capacity_cm' => $moduleCapacity
        ]);
        
        return $moduleCapacity;
    }

    /**
     * 🎯 FASE 2: Cálculo de Capacidade Total - Aloca módulos por categoria
     * 
     * Para cada categoria:
     * - Calcula quantos módulos completos precisa
     * - Calcula módulo parcial se necessário
     * - Aloca módulos sequencialmente (1, 2, 3, 4...)
     * - Garante que categorias não se misturem
     */
    protected function calculateModuleAllocation(array $categoryAnalysis, array $gondolaStructure): array
    {
        Log::info("🎯 FASE 2: Iniciando cálculo de alocação de módulos", [
            'total_categories' => count($categoryAnalysis['categories']),
            'module_capacity_cm' => $categoryAnalysis['module_capacity']
        ]);
        
        $allocations = [];
        $currentModuleIndex = 0; // ✅ CORRIGIDO: Começar do módulo 0 (corresponde ao ordering das sections)
        $totalModules = $gondolaStructure['total_sections'];
        $moduleCapacity = $categoryAnalysis['module_capacity'];
        
        foreach ($categoryAnalysis['categories'] as $category) {
            $categoryName = $category['category'];
            $widthNeeded = $category['total_width_needed'];
            
            // Calcular quantos módulos completos a categoria precisa
            $fullModulesNeeded = floor($widthNeeded / $moduleCapacity);
            $remainingWidth = $widthNeeded % $moduleCapacity;
            
            // Calcular módulos alocados
            $allocatedModules = [];
            $totalAllocatedCapacity = 0;
            
            // Alocar módulos completos
            for ($i = 0; $i < $fullModulesNeeded && $currentModuleIndex < $totalModules; $i++) {
                $allocatedModules[] = [
                    'module_index' => $currentModuleIndex,
                    'capacity_used' => $moduleCapacity,
                    'capacity_available' => $moduleCapacity,
                    'is_full' => true
                ];
                $totalAllocatedCapacity += $moduleCapacity;
                $currentModuleIndex++;
            }
            
            // Alocar módulo parcial se necessário e se há módulos disponíveis
            if ($remainingWidth > 0 && $currentModuleIndex < $totalModules) {
                $allocatedModules[] = [
                    'module_index' => $currentModuleIndex,
                    'capacity_used' => $remainingWidth,
                    'capacity_available' => $moduleCapacity,
                    'is_full' => false
                ];
                $totalAllocatedCapacity += $remainingWidth;
                $currentModuleIndex++;
            }
            
            $allocations[] = [
                'category' => $categoryName,
                'width_needed' => $widthNeeded,
                'allocated_modules' => $allocatedModules,
                'total_allocated_capacity' => $totalAllocatedCapacity,
                'efficiency_percent' => $widthNeeded > 0 ? round(($totalAllocatedCapacity / $widthNeeded) * 100, 1) : 0,
                'products' => $category['products']
            ];
            
            Log::info("📦 Módulos alocados para categoria", [
                'category' => $categoryName,
                'width_needed_cm' => round($widthNeeded, 1),
                'full_modules' => $fullModulesNeeded,
                'partial_module_width' => round($remainingWidth, 1),
                'total_allocated_cm' => round($totalAllocatedCapacity, 1),
                'modules_allocated' => count($allocatedModules),
                'efficiency_percent' => $widthNeeded > 0 ? round(($totalAllocatedCapacity / $widthNeeded) * 100, 1) : 0
            ]);
        }
        
        Log::info("🎯 FASE 2: Alocação de módulos concluída", [
            'total_allocations' => count($allocations),
            'modules_used' => $currentModuleIndex,
            'modules_available' => $totalModules,
            'modules_remaining' => max(0, $totalModules - $currentModuleIndex)
        ]);
        
        return [
            'allocations' => $allocations,
            'modules_used' => $currentModuleIndex,
            'modules_available' => $totalModules,
            'modules_remaining' => max(0, $totalModules - $currentModuleIndex)
        ];
    }

    /**
     * 📈 Aplica análise ABC dentro de uma categoria específica
     */
    protected function applyABCWithinCategory(array $categoryProducts): array
    {
        if (empty($categoryProducts)) {
            return [];
        }
        
        // Usar priority_score como base para ordenação ABC dentro da categoria
        $sortedProducts = collect($categoryProducts)->sortByDesc(function($product) {
            return $product['priority_score'] ?? 0;
        })->values()->toArray();
        
        // Aplicar classificação ABC dentro da categoria
        $totalProducts = count($sortedProducts);
        $thresholdA = ceil($totalProducts * 0.2); // Top 20% = Classe A
        $thresholdB = ceil($totalProducts * 0.5); // Próximos 30% = Classe B
        
        foreach ($sortedProducts as $index => &$product) {
            if ($index < $thresholdA) {
                $product['category_abc_class'] = 'A';
            } elseif ($index < $thresholdB) {
                $product['category_abc_class'] = 'B';
            } else {
                $product['category_abc_class'] = 'C';
            }
        }
        
        Log::info("📈 ABC aplicado dentro da categoria", [
            'total_products' => $totalProducts,
            'class_a_count' => $thresholdA,
            'class_b_count' => $thresholdB - $thresholdA,
            'class_c_count' => $totalProducts - $thresholdB
        ]);
        
        return $sortedProducts;
    }

    /**
     * 🎯 FASE 3: Alocação Sequencial - Executa distribuição respeitando limites de módulos
     * 
     * Para cada categoria:
     * - Coloca TODOS os produtos da categoria nos módulos alocados
     * - Respeita limite de capacidade de cada módulo
     * - Quebra facing dentro do módulo se necessário
     * - Zero mistura de categorias
     */
    protected function executeSequentialAllocation(Gondola $gondola, array $moduleAllocation, array $gondolaStructure): array
    {
        Log::info("🎯 FASE 3: Iniciando alocação sequencial", [
            'total_allocations' => count($moduleAllocation['allocations'])
        ]);
        
        $totalProductsPlaced = 0;
        $totalPlacements = 0;
        $segmentsUsed = 0;
        $categoriesProcessed = 0;
        
        // Obter todas as sections (módulos) da gôndola
        $allSections = $gondola->sections()
            ->with(['shelves.segments.layer'])
            ->orderBy('ordering')
            ->get();
        
        foreach ($moduleAllocation['allocations'] as $allocation) {
            $categoryName = $allocation['category'];
            $products = $allocation['products'];
            $allocatedModules = $allocation['allocated_modules'];
            
            Log::info("📦 Processando categoria sequencialmente", [
                'category' => $categoryName,
                'products_count' => count($products),
                'modules_allocated' => count($allocatedModules)
            ]);
            
            // Distribuir produtos da categoria nos módulos alocados
            $categoryResult = $this->distributeCategoryInAllocatedModules(
                $gondola,
                $allSections,
                $products,
                $allocatedModules,
                $categoryName
            );
            
            // Consolidar resultados
            $totalProductsPlaced += $categoryResult['products_placed'];
            $totalPlacements += $categoryResult['total_placements'];
            $segmentsUsed += $categoryResult['segments_used'];
            $categoriesProcessed++;
            
            Log::info("✅ Categoria processada sequencialmente", [
                'category' => $categoryName,
                'products_placed' => $categoryResult['products_placed'],
                'total_placements' => $categoryResult['total_placements'],
                'segments_used' => $categoryResult['segments_used']
            ]);
        }
        
        Log::info("🎯 FASE 3: Alocação sequencial concluída", [
            'categories_processed' => $categoriesProcessed,
            'total_products_placed' => $totalProductsPlaced,
            'total_placements' => $totalPlacements,
            'segments_used' => $segmentsUsed
        ]);
        
        return [
            'products_placed' => $totalProductsPlaced,
            'total_placements' => $totalPlacements,
            'segments_used' => $segmentsUsed,
            'categories_processed' => $categoriesProcessed
        ];
    }

    /**
     * 🏪 Distribui uma categoria específica nos módulos alocados
     */
    protected function distributeCategoryInAllocatedModules(Gondola $gondola, $allSections, array $products, array $allocatedModules, string $categoryName): array
    {
        $productsPlaced = 0;
        $totalPlacements = 0;
        $segmentsUsed = 0;
        $remainingProducts = $products;
        
        Log::info("🏪 Distribuindo categoria nos módulos alocados", [
            'category' => $categoryName,
            'products_count' => count($products),
            'modules_count' => count($allocatedModules)
        ]);
        
        foreach ($allocatedModules as $moduleAllocation) {
            $moduleIndex = $moduleAllocation['module_index'];
            $moduleCapacity = $moduleAllocation['capacity_available'];
            $capacityUsed = $moduleAllocation['capacity_used'];
            
            // Encontrar a section correspondente ao módulo
            $section = $allSections->where('ordering', $moduleIndex)->first();
            
            if (!$section) {
                Log::warning("⚠️ Section não encontrada para módulo", [
                    'module_index' => $moduleIndex,
                    'category' => $categoryName
                ]);
                continue;
            }
            
            Log::info("📦 Processando módulo para categoria", [
                'category' => $categoryName,
                'module_index' => $moduleIndex,
                'module_capacity_cm' => $moduleCapacity,
                'capacity_used_cm' => $capacityUsed,
                'remaining_products' => count($remainingProducts)
            ]);
            
            // Distribuir produtos restantes neste módulo
            $moduleResult = $this->distributeProductsInModule(
                $section,
                $remainingProducts,
                $moduleCapacity,
                $categoryName
            );
            
            // Atualizar contadores
            $productsPlaced += $moduleResult['products_placed'];
            $totalPlacements += $moduleResult['total_placements'];
            $segmentsUsed += $moduleResult['segments_used'];
            
            // Remover produtos colocados da lista restante
            $remainingProducts = $moduleResult['remaining_products'];
            
            Log::info("✅ Módulo processado para categoria", [
                'category' => $categoryName,
                'module_index' => $moduleIndex,
                'products_placed' => $moduleResult['products_placed'],
                'total_placements' => $moduleResult['total_placements'],
                'remaining_products' => count($remainingProducts)
            ]);
            
            // Se não há mais produtos para colocar, parar
            if (empty($remainingProducts)) {
                break;
            }
        }
        
        return [
            'products_placed' => $productsPlaced,
            'total_placements' => $totalPlacements,
            'segments_used' => $segmentsUsed,
            'remaining_products' => $remainingProducts
        ];
    }

    /**
     * 🏪 Distribui uma categoria específica nos módulos (MÉTODO LEGADO - MANTIDO PARA COMPATIBILIDADE)
     */
    protected function distributeCategoryInModules(Gondola $gondola, array $categoryProducts, array $gondolaStructure, string $categoryName): array
    {
        Log::info("🏪 Distribuindo categoria {$categoryName} nos módulos", [
            'products_count' => count($categoryProducts),
            'category_name' => $categoryName
        ]);
        
        // 🎯 LÓGICA SEQUENCIAL: Colocar produtos sequencialmente com TODOS os facings
        // O ProductPlacementService já está configurado para distribuir multi-prateleira
        $distributionResult = $this->productPlacement->placeProductsSequentially(
            $gondola,
            $categoryProducts, // Produtos da categoria já ordenados por ABC interno
            $gondolaStructure
        );
        
        Log::info("✅ Categoria {$categoryName} distribuída sequencialmente", [
            'products_placed' => $distributionResult['products_placed'],
            'total_placements' => $distributionResult['total_placements'],
            'segments_used' => $distributionResult['segments_used']
        ]);
        
        return $distributionResult;
    }

    /**
     * 🏪 Distribui produtos de uma categoria dentro de um módulo específico
     * 
     * Respeita a capacidade do módulo e quebra facing se necessário
     * Exemplo: 20 faces = 5 por prateleira em 4 prateleiras
     */
    protected function distributeProductsInModule($section, array $products, float $moduleCapacity, string $categoryName): array
    {
        $productsPlaced = 0;
        $totalPlacements = 0;
        $segmentsUsed = 0;
        $remainingProducts = [];
        $usedCapacity = 0;
        
        Log::info("🏪 Distribuindo produtos no módulo", [
            'category' => $categoryName,
            'module_capacity_cm' => $moduleCapacity,
            'products_count' => count($products)
        ]);
        
        // Obter prateleiras do módulo em ordem
        $shelves = $section->shelves()->orderBy('ordering')->get();
        
        foreach ($products as $product) {
            $productWidth = $product['product']['width'] ?? 20;
            $desiredFacing = $product['intelligent_facing'] ?? 1;
            $totalWidthNeeded = $productWidth * $desiredFacing;
            
            // Verificar se o produto cabe no módulo
            if ($usedCapacity + $totalWidthNeeded > $moduleCapacity) {
                // Produto não cabe, adicionar à lista de restantes
                $remainingProducts[] = $product;
                continue;
            }
            
            // Tentar colocar o produto nas prateleiras do módulo
            $placementResult = $this->placeProductInModuleShelves(
                $shelves,
                $product,
                $desiredFacing,
                $categoryName
            );
            
            if ($placementResult['success']) {
                $productsPlaced++;
                $totalPlacements += $placementResult['placements'];
                $segmentsUsed += $placementResult['segments_used'];
                $usedCapacity += $placementResult['width_used'];
                
                Log::info("✅ Produto colocado no módulo", [
                    'category' => $categoryName,
                    'product_id' => $product['product_id'],
                    'product_name' => $product['product']['name'] ?? 'N/A',
                    'facing_placed' => $placementResult['placements'],
                    'width_used_cm' => $placementResult['width_used']
                ]);
            } else {
                // Produto não conseguiu ser colocado, adicionar à lista de restantes
                $remainingProducts[] = $product;
                
                Log::warning("⚠️ Produto não conseguiu ser colocado no módulo", [
                    'category' => $categoryName,
                    'product_id' => $product['product_id'],
                    'product_name' => $product['product']['name'] ?? 'N/A',
                    'reason' => $placementResult['reason'] ?? 'Desconhecido'
                ]);
            }
        }
        
        Log::info("🏪 Distribuição no módulo concluída", [
            'category' => $categoryName,
            'products_placed' => $productsPlaced,
            'total_placements' => $totalPlacements,
            'segments_used' => $segmentsUsed,
            'capacity_used_cm' => round($usedCapacity, 1),
            'capacity_available_cm' => round($moduleCapacity - $usedCapacity, 1),
            'remaining_products' => count($remainingProducts)
        ]);
        
        return [
            'products_placed' => $productsPlaced,
            'total_placements' => $totalPlacements,
            'segments_used' => $segmentsUsed,
            'remaining_products' => $remainingProducts,
            'capacity_used' => $usedCapacity
        ];
    }

    /**
     * 🏪 Coloca um produto nas prateleiras de um módulo
     * 
     * ✅ CORRIGIDO: Distribui uniformemente entre todas as prateleiras
     * Exemplo: 20 faces = 5 faces por prateleira em 4 prateleiras
     */
    protected function placeProductInModuleShelves($shelves, array $product, int $desiredFacing, string $categoryName): array
    {
        $productWidth = $product['product']['width'] ?? 20;
        $remainingFacing = $desiredFacing;
        $totalPlacements = 0;
        $segmentsUsed = 0;
        $widthUsed = 0;
        
        Log::info("🏪 Colocando produto nas prateleiras do módulo (DISTRIBUIÇÃO UNIFORME)", [
            'category' => $categoryName,
            'product_id' => $product['product_id'],
            'desired_facing' => $desiredFacing,
            'product_width_cm' => $productWidth,
            'shelves_count' => $shelves->count()
        ]);
        
        // ✅ NOVA ESTRATÉGIA: Distribuir uniformemente entre todas as prateleiras
        $totalShelves = $shelves->count();
        $facingsPerShelf = ceil($desiredFacing / $totalShelves);
        
        foreach ($shelves as $shelfIndex => $shelf) {
            if ($remainingFacing <= 0) {
                break; // Todos os facings foram colocados
            }
            
            // Calcular quantos facings cabem nesta prateleira
            $shelfWidth = floatval($shelf->shelf_width ?? 125.0);
            $availableWidth = $this->getShelfAvailableWidth($shelf);
            $facingsThatFit = floor($availableWidth / $productWidth);
            
            if ($facingsThatFit <= 0) {
                Log::info("⚠️ Prateleira sem espaço", [
                    'category' => $categoryName,
                    'shelf_ordering' => $shelf->ordering,
                    'available_width' => $availableWidth,
                    'product_width' => $productWidth
                ]);
                continue; // Prateleira não tem espaço suficiente
            }
            
            // ✅ DISTRIBUIÇÃO UNIFORME: Calcular facings para esta prateleira
            $facingsForThisShelf = min($remainingFacing, $facingsThatFit, $facingsPerShelf);
            
            if ($facingsForThisShelf <= 0) {
                continue;
            }
            
            // Tentar colocar o produto nesta prateleira
            $placementResult = $this->placeProductInShelfVertically($shelf, $product, $facingsForThisShelf);
            
            if ($placementResult) {
                $remainingFacing -= $facingsForThisShelf;
                $totalPlacements += $facingsForThisShelf;
                $segmentsUsed++;
                $widthUsed += $facingsForThisShelf * $productWidth;
                
                Log::info("✅ Facings colocados na prateleira (UNIFORME)", [
                    'category' => $categoryName,
                    'product_id' => $product['product_id'],
                    'shelf_ordering' => $shelf->ordering,
                    'facings_placed' => $facingsForThisShelf,
                    'facings_remaining' => $remainingFacing,
                    'distribution_strategy' => 'uniform'
                ]);
            } else {
                Log::warning("⚠️ Falha ao colocar produto na prateleira", [
                    'category' => $categoryName,
                    'product_id' => $product['product_id'],
                    'shelf_ordering' => $shelf->ordering,
                    'facings_tried' => $facingsForThisShelf
                ]);
            }
        }
        
        $success = $remainingFacing <= 0; // Sucesso se todos os facings foram colocados
        
        return [
            'success' => $success,
            'placements' => $totalPlacements,
            'segments_used' => $segmentsUsed,
            'width_used' => $widthUsed,
            'reason' => $success ? 'Produto colocado com sucesso' : 'Não foi possível colocar todos os facings'
        ];
    }

    /**
     * 🔄 NOVO: Reordena produtos por adjacência de categoria (MÉTODO LEGADO - MANTIDO PARA COMPATIBILIDADE)
     * Agrupa produtos similares (ex: todos os açúcares juntos, todos os arrozes juntos)
     */
    protected function reorderProductsByCategory(array $processedProducts): array
    {
        Log::info("🔄 Iniciando reordenação por categoria", [
            'total_products' => count($processedProducts)
        ]);

        // 1. Agrupar produtos por categoria base (primeira palavra)
        $categoryGroups = [];
        
        foreach ($processedProducts as $product) {
            // Extrair primeira palavra do nome do produto
            $productName = strtoupper($product['product']['name'] ?? 'OUTROS');
            $categoryKey = explode(' ', $productName)[0]; // Primeira palavra do nome do produto
            
            if (!isset($categoryGroups[$categoryKey])) {
                $categoryGroups[$categoryKey] = [];
            }
            
            $categoryGroups[$categoryKey][] = $product;
        }

        Log::info("📊 Grupos de categoria criados", [
            'total_groups' => count($categoryGroups),
            'groups' => array_map(fn($group) => count($group), $categoryGroups)
        ]);

        // 2. Ordenar grupos por importância (baseado no melhor produto de cada grupo)
        uksort($categoryGroups, function($groupA, $groupB) use ($categoryGroups) {
            $maxScoreA = max(array_column($categoryGroups[$groupA], 'priority_score'));
            $maxScoreB = max(array_column($categoryGroups[$groupB], 'priority_score'));
            return $maxScoreB <=> $maxScoreA; // Maior score primeiro
        });

        // 3. Ordenar produtos dentro de cada grupo por priority_score
        foreach ($categoryGroups as $categoryKey => &$products) {
            usort($products, function($a, $b) {
                return $b['priority_score'] - $a['priority_score'];
            });
        }

        // 4. Achatar grupos em lista única
        $reorderedProducts = [];
        foreach ($categoryGroups as $categoryKey => $products) {
            Log::info("📦 Processando grupo de categoria", [
                'categoria' => $categoryKey,
                'produtos_count' => count($products),
                'primeiro_produto' => $products[0]['product']['name'] ?? 'N/A'
            ]);
            
            $reorderedProducts = array_merge($reorderedProducts, $products);
        }

        Log::info("✅ Reordenação por categoria concluída", [
            'produtos_reordenados' => count($reorderedProducts),
            'grupos_processados' => count($categoryGroups)
        ]);

        return $reorderedProducts;
    }
    
    /**
     * 🔒 VALIDAÇÃO: Verifica consistência de contagem de produtos entre etapas
     */
    protected function validateProductCount(string $stage, int $expected, int $actual): void
    {
        if ($expected !== $actual) {
            Log::warning("🚨 INCONSISTÊNCIA DE PRODUTOS DETECTADA", [
                'stage' => $stage,
                'expected_count' => $expected,
                'actual_count' => $actual,
                'difference' => $actual - $expected,
                'error_type' => $actual > $expected ? 'DUPLICAÇÃO' : 'PERDA'
            ]);
        } else {
            Log::info("✅ Validação de contagem aprovada", [
                'stage' => $stage,
                'product_count' => $actual
            ]);
        }
    }

    /**
     * 📏 HELPER: Calcula largura disponível na prateleira usando dados do banco
     */
    protected function getShelfAvailableWidth($shelf): float 
    {
        $shelfWidth = floatval($shelf->shelf_width ?? 125.0);
        $usedWidth = $this->calculateUsedWidthInShelf($shelf);
        return $shelfWidth - $usedWidth;
    }

    // Métodos auxiliares
    protected function getAllProductsByPlanogramCategory(Gondola $gondola, $request): array
    {
        Log::info("🔄 Usando endpoint do Products.vue para consistência");

        // Preparar parâmetros iguais ao Products.vue
        $filters = $request->input('filters', []);
        
        // CORREÇÃO: Buscar mercadologico_nivel via category_id do planogram
        $mercadologicoNivel = null;
        if ($gondola->planogram && $gondola->planogram->category_id) {
            $category = \App\Models\Category::find($gondola->planogram->category_id);
            $mercadologicoNivel = $category ? $category->nivel : null;
            
            Log::info("🔍 Mercadológico obtido da categoria", [
                'planogram_id' => $gondola->planogram->id,
                'category_id' => $gondola->planogram->category_id,
                'mercadologico_nivel' => $mercadologicoNivel,
                'category_name' => $category->name ?? 'N/A'
            ]);
        } else {
            Log::warning("⚠️ Planograma sem categoria definida", [
                'planogram_id' => $gondola->planogram->id ?? 'N/A',
                'has_planogram' => !!$gondola->planogram,
                'category_id' => $gondola->planogram->category_id ?? 'N/A'
            ]);
        }

        // Preparar filtro de categoria no formato esperado pelo ProductController
        $categoryFilter = null;
        if ($gondola->planogram && $gondola->planogram->category_id) {
            $category = \App\Models\Category::find($gondola->planogram->category_id);
            if ($category) {
                // Criar objeto com o nível hierárquico correto
                $categoryFilter = json_encode([
                    $category->level_name => $category->id
                ]);
            }
        }

        $params = [
            'category' => $categoryFilter, // Usar formato JSON esperado pelo ProductController
            'hangable' => $filters['hangable'] ?? false,
            'stackable' => $filters['stackable'] ?? false,
            'dimension' => $filters['dimension'] ?? true,
            'sales' => $filters['sales'] ?? true,
            'planogram_id' => $gondola->planogram->id,
            'client_id' => $gondola->planogram->client_id,
            'page' => 1,
            'limit' => $filters['limit'] ?? 999999, // Respeita limite dos filtros
        ];

        // Aplicar filtro de produtos não utilizados se necessário
        if ($filters['unusedOnly'] ?? true) {
            // Buscar produtos já na gôndola
            $productIdsInGondola = [];
            foreach ($gondola->sections as $section) {
                foreach ($section->shelves as $shelf) {
                foreach ($shelf->segments as $segment) {
                    // Verificar se layer existe e tem produto
                    if ($segment->layer && $segment->layer->product_id) {
                        $productIdsInGondola[] = $segment->layer->product_id;
                    }
                }
                }
            }
            if (!empty($productIdsInGondola)) {
                $params['notInGondola'] = array_unique($productIdsInGondola);
            }
        }

        try {
            // Usar o mesmo controller do projeto principal
            $productController = new \App\Http\Controllers\Api\ProductController();
            $productRequest = new \Illuminate\Http\Request($params);
            
            Log::info("📞 Chamando ProductController->filteredProducts()", [
                'params' => $params,
                'expected_products' => '~999'
            ]);

            $response = $productController->filteredProducts($productRequest);
            
            if (method_exists($response, 'getData')) {
                $data = $response->getData(true);
            } else {
                $data = $response;
            }

            $products = $data['data'] ?? [];
            
            Log::info("✅ Produtos obtidos via Products.vue endpoint", [
                'total_products' => count($products),
                'first_3_products' => array_slice(array_column($products, 'name'), 0, 3)
            ]);

            return $products;

        } catch (\Exception $e) {
            Log::error("❌ Erro ao usar endpoint do Products.vue", [
                'error' => $e->getMessage(),
                'fallback' => 'Usando método original'
            ]);

            // Fallback: retornar array vazio se falhar
            Log::error("❌ Fallback: Retornando array vazio");
            return [];
        }
    }

    // ✅ REMOVIDO: getDailySales() - agora usa TargetStockAnalysisService

    // ✅ REMOVIDO: getCurrentStock() - agora usa TargetStockAnalysisService

    // ✅ REMOVIDO: calculateTargetStock() - agora usa TargetStockAnalysisService

    protected function determineStockUrgency(float $stockRatio): string
    {
        return match(true) {
            $stockRatio < 0.3 => 'CRÍTICO',
            $stockRatio < 0.6 => 'BAIXO',
            $stockRatio < 0.9 => 'NORMAL',
            default => 'ALTO'
        };
    }

    protected function getABCDistribution(array $abcResults): array
    {
        return [
            'A' => count(array_filter($abcResults, fn($p) => $p['abc_class'] === 'A')),
            'B' => count(array_filter($abcResults, fn($p) => $p['abc_class'] === 'B')),
            'C' => count(array_filter($abcResults, fn($p) => $p['abc_class'] === 'C'))
        ];
    }

    protected function getUrgencyDistribution(array $targetStockResults): array
    {
        return [
            'CRÍTICO' => count(array_filter($targetStockResults, fn($r) => $r['urgency'] === 'CRÍTICO')),
            'BAIXO' => count(array_filter($targetStockResults, fn($r) => $r['urgency'] === 'BAIXO')),
            'NORMAL' => count(array_filter($targetStockResults, fn($r) => $r['urgency'] === 'NORMAL')),
            'ALTO' => count(array_filter($targetStockResults, fn($r) => $r['urgency'] === 'ALTO'))
        ];
    }
    
    /**
     * Calcula o Z-Score baseado no nível de serviço
     * Usando a mesma fórmula do frontend (useTargetStock.ts)
     */
    private function calculateZScore(float $serviceLevel): float
    {
        $p = $serviceLevel / 100;
        
        // Coeficientes da aproximação
        $a1 = -39.6968302866538;
        $a2 = 220.946098424521;
        $a3 = -275.928510446969;
        $a4 = 138.357751867269;
        $a5 = -30.6647980661472;
        $a6 = 2.50662827745924;
        
        $b1 = -54.4760987982241;
        $b2 = 161.585836858041;
        $b3 = -155.698979859887;
        $b4 = 66.8013118877197;
        $b5 = -13.2806815528857;
        
        $c1 = -7.78489400243029E-03;
        $c2 = -0.322396458041136;
        $c3 = -2.40075827716184;
        $c4 = -2.54973253934373;
        $c5 = 4.37466414146497;
        $c6 = 2.93816398269878;
        
        $d1 = 7.78469570904146E-03;
        $d2 = 0.32246712907004;
        $d3 = 2.445134137143;
        $d4 = 3.75440866190746;
        
        $pLow = 0.02425;
        $pHigh = 1 - $pLow;
        
        if ($p < $pLow) {
            $q = sqrt(-2 * log($p));
            return ((((($c1 * $q + $c2) * $q + $c3) * $q + $c4) * $q + $c5) * $q + $c6) / 
                   (((($d1 * $q + $d2) * $q + $d3) * $q + $d4) * $q + 1);
        } elseif ($p <= $pHigh) {
            $q = $p - 0.5;
            $r = $q * $q;
            return ((((($a1 * $r + $a2) * $r + $a3) * $r + $a4) * $r + $a5) * $r + $a6) * $q / 
                   ((((($b1 * $r + $b2) * $r + $b3) * $r + $b4) * $r + $b5) * $r + 1);
        } else {
            $q = sqrt(-2 * log(1 - $p));
            return -((((($c1 * $q + $c2) * $q + $c3) * $q + $c4) * $q + $c5) * $q + $c6) / 
                    (((($d1 * $q + $d2) * $q + $d3) * $q + $d4) * $q + 1);
        }
    }

}
