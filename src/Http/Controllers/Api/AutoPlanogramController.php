<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Callcocam\Plannerate\Models\Gondola;
use Callcocam\Plannerate\Services\Engine\ScoreEngineService;
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
    protected ScoreEngineService $scoreEngine;

    public function __construct(ScoreEngineService $scoreEngine)
    {
        $this->scoreEngine = $scoreEngine;
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
            
            // Buscar produtos baseado na categoria do planogram (não da gôndola)
            $productsData = $this->getProductsByPlanogramCategory($gondola, $request);
            
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

            // Extrair IDs dos produtos para o ScoreEngine
            $productIds = collect($productsData)->pluck('id')->toArray();
            
            Log::info('AutoPlanogram: Calculando scores para gôndola', [
                'gondola_id' => $gondola->id,
                'gondola_name' => $gondola->name,
                'produtos_count' => count($productIds),
                'produtos_with_dimensions' => collect($productsData)->filter(function($product) {
                    return isset($product['dimensions']) && $product['dimensions'] !== null;
                })->count()
            ]);

            // Calcular scores usando o ScoreEngine
            $scores = $this->scoreEngine->calculateScores(
                $productIds,
                $request->input('weights', []),
                $request->input('start_date'),
                $request->input('end_date'),
                $request->input('store_id')
            );

            // Aplicar distribuição automática se solicitado
            $autoDistribute = $request->boolean('auto_distribute', false);
            $distributionResult = null;
            
            if ($autoDistribute) {
                $distributionResult = $this->distributeProductsInGondola($gondola, $scores, $productsData);
            }

            // Preparar resposta estruturada
            $response = [
                'success' => true,
                'message' => $autoDistribute 
                    ? 'Scores calculados e produtos distribuídos automaticamente'
                    : 'Scores calculados com sucesso',
                'data' => [
                    'gondola' => [
                        'id' => $gondola->id,
                        'name' => $gondola->name,
                        'planogram_id' => $gondola->planogram_id,
                    ],
                    'calculation_info' => [
                        'products_analyzed' => count($productIds),
                        'products_scored' => count($scores),
                        'calculation_date' => now()->toISOString(),
                        'period' => [
                            'start_date' => $request->input('start_date'),
                            'end_date' => $request->input('end_date'),
                        ],
                        'weights_used' => $request->input('weights', []),
                    ],
                    'scores' => $scores,
                    'summary' => $this->generateSummary($scores),
                    'distribution' => $distributionResult
                ]
            ];

            Log::info('AutoPlanogram: Cálculo concluído', [
                'gondola_id' => $gondola->id,
                'produtos_analisados' => count($productIds),
                'produtos_com_score' => count($scores),
                'score_medio' => $response['data']['summary']['average_score'],
                'auto_distribute' => $autoDistribute,
                'produtos_distribuidos' => $distributionResult['products_placed'] ?? 0
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
                            // Nota: Campos 'score', 'abc_class', 'confidence_flag' serão adicionados na próxima etapa
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

    /**
     * Busca produtos baseado na categoria do planogram (não da gôndola)
     * Usa o category_id do planogram para encontrar produtos da mesma categoria
     * Aplica filtros dinâmicos vindos do modal AutoGenerateModal.vue
     */
    protected function getProductsByPlanogramCategory(Gondola $gondola, $request): array
    {
        // 1. Obter o planogram da gôndola
        $planogram = $gondola->planogram;
        
        Log::info("Debug planogram carregado", [
            'gondola_id' => $gondola->id,
            'gondola_planogram_id' => $gondola->planogram_id,
            'planogram_found' => $planogram ? 'SIM' : 'NÃO',
            'planogram_data' => $planogram ? [
                'id' => $planogram->id,
                'name' => $planogram->name,
                'category_id' => $planogram->category_id,
            ] : null
        ]);
        
        if (!$planogram) {
            Log::warning("Gôndola sem planogram associado", [
                'gondola_id' => $gondola->id,
                'gondola_name' => $gondola->name
            ]);
            
            // Retornar array vazio em vez de exception
            return [];
        }

        // 2. Verificar se o planogram tem categoria definida
        if (!$planogram->category_id) {
            Log::warning("Planogram sem categoria definida", [
                'gondola_id' => $gondola->id,
                'planogram_id' => $planogram->id,
                'planogram_name' => $planogram->name ?? 'N/A',
                'category_id' => $planogram->category_id
            ]);
            
            // Retornar array vazio em vez de exception
            return [];
        }

        // 3. Buscar produtos baseado na hierarquia da categoria do planogram
        $productsQuery = \App\Models\Product::query();
        $categoryId = $planogram->category_id;
        
        // Obter a categoria e sua hierarquia completa
        $category = \App\Models\Category::find($categoryId);
        if (!$category) {
            Log::warning("Categoria do planogram não encontrada", [
                'category_id' => $categoryId,
                'planogram_id' => $planogram->id
            ]);
            return [];
        }

        // Usar a mesma lógica da API de produtos (ProductController)
        // Criar objeto mercadológico baseado no level_name da categoria
        $mercadologicoNivel = [];
        $mercadologicoNivel[$category->level_name] = $categoryId;
        
        // Buscar descendentes da categoria (subcategorias)
        $descendants = $this->getCategoryDescendants($categoryId);
        $descendants[] = $categoryId; // Incluir a própria categoria
        
        // Aplicar filtro nos produtos
        $productsQuery->whereIn('category_id', $descendants);
        
        $levelUsed = $category->level_name . ': ' . $category->name . ' (+ ' . (count($descendants)-1) . ' descendentes)';
        
        Log::info("Busca hierárquica na categoria", [
            'categoria' => $category->name,
            'category_id' => $categoryId,
            'level_name' => $category->level_name,
            'mercadologico_nivel' => $mercadologicoNivel,
            'total_categorias_incluidas' => count($descendants),
            'descendentes' => array_slice($descendants, 0, 5) // Primeiras 5 para debug
        ]);

        // 4. Produtos com EAN (sempre obrigatório)
        $productsQuery->whereNotNull('ean');
        
        // 5. Aplicar filtros dinâmicos vindos do modal AutoGenerateModal.vue
        $filters = $request->input('filters', []);
        $this->applyDynamicFilters($productsQuery, $filters, $gondola);
        
        // Buscar produtos com limite dinâmico  
        $limit = $filters['limit'] ?? 20; // Padrão 20 se não informado
        $products = $productsQuery->with(['dimensions'])->limit($limit)->get();

        Log::info("Produtos encontrados para geração automática COM FILTROS DINÂMICOS", [
            'gondola_id' => $gondola->id,
            'planogram_id' => $planogram->id,
            'mercadologico_level_used' => $levelUsed,
            'products_found' => $products->count(),
            'products_with_dimensions' => $products->filter(function($product) {
                return $product->dimensions !== null;
            })->count(),
            'limit_applied' => $limit,
            'filters_applied' => $filters
        ]);

        // Retornar dados completos dos produtos ao invés de apenas IDs
        return $products->toArray();
    }

    /**
     * Extrai IDs dos produtos de uma gôndola (método legacy - mantido para compatibilidade)
     * @deprecated Use getProductsByPlanogramMercadologico para geração automática
     */
    protected function extractProductIds(Gondola $gondola): array
    {
        $productIds = [];
        
        foreach ($gondola->sections as $section) {
            foreach ($section->shelves as $shelf) {
                foreach ($shelf->segments as $segment) {
                    if ($segment->layer && $segment->layer->product_id) {
                        $productIds[] = $segment->layer->product_id;
                    }
                }
            }
        }
        
        return array_unique($productIds);
    }

    /**
     * Método auxiliar para buscar todos os descendentes de uma categoria
     * Copiado do ProductController para manter consistência
     */
    private function getCategoryDescendants($category)
    {
        $descendants = [];

        // Busca filhos diretos
        if (is_string($category)) {
            $children = \App\Models\Category::where('category_id', $category)->get();
        } else {
            $children = \App\Models\Category::where('category_id', $category->id)->get();
        }

        foreach ($children as $child) {
            $descendants[] = $child->id;
            // Recursivamente busca descendentes dos filhos
            $descendants = array_merge($descendants, $this->getCategoryDescendants($child));
        }

        return $descendants;
    }

    /**
     * Obtém IDs dos produtos já usados na gôndola atual
     * Para filtrar apenas produtos "unused" (igual à sidebar)
     */
    private function getProductIdsInGondola(Gondola $gondola): array
    {
        $productIds = [];
        
        foreach ($gondola->sections as $section) {
            foreach ($section->shelves as $shelf) {
                foreach ($shelf->segments as $segment) {
                    if ($segment->layer && $segment->layer->product_id) {
                        $productIds[] = $segment->layer->product_id;
                    }
                }
            }
        }
        
        return array_unique($productIds);
    }

    /**
     * Distribui produtos automaticamente na gôndola baseado nos scores
     * Lógica direta sem templates: Score maior = Posição melhor
     */
    protected function distributeProductsInGondola(Gondola $gondola, array $scores, array $productsData = []): array
    {
        Log::info("Iniciando distribuição automática", [
            'gondola_id' => $gondola->id,
            'total_scores' => count($scores),
            'products_data_count' => count($productsData)
        ]);

        // 0. Enrichar scores com dados dos produtos (incluindo dimensões)
        $enrichedScores = $this->enrichScoresWithProductData($scores, $productsData);

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

        // 3. Obter estrutura da gôndola (seções, prateleiras, segmentos)
        $gondolaStructure = $this->analyzeGondolaStructure($gondola);
        
        Log::info("Estrutura da gôndola analisada", [
            'gondola_id' => $gondola->id,
            'total_sections' => $gondolaStructure['total_sections'],
            'total_segments' => $gondolaStructure['total_segments'],
            'shelves_by_level' => array_map('count', $gondolaStructure['shelves_by_level'])
        ]);
        
        // 4. Garantir que a gôndola tenha segmentos (criar se necessário)
        $this->ensureGondolaHasSegments($gondola);
        
        // 5. Limpar gôndola atual (remover produtos existentes)
        $this->clearGondola($gondola);

        // 5. Distribuir produtos sequencialmente aproveitando todo o espaço
        $distributionResult = $this->placeProductsSequentially(
            $gondola,
            $classifiedProducts,
            $gondolaStructure
        );

        Log::info("Distribuição automática concluída", [
            'gondola_id' => $gondola->id,
            'produtos_classe_A' => count($classifiedProducts['A']),
            'produtos_classe_B' => count($classifiedProducts['B']),
            'produtos_classe_C' => count($classifiedProducts['C']),
            'produtos_colocados' => $distributionResult['products_placed'],
            'segmentos_utilizados' => $distributionResult['segments_used']
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
                    
                    Log::info("Segmento criado automaticamente", [
                        'shelf_id' => $shelf->id,
                        'gondola_id' => $gondola->id
                    ]);
                }
            }
        }
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
     * Distribui produtos nas prateleiras baseado na classificação ABC
     */
    protected function placeProductsByABCLevels(Gondola $gondola, array $classifiedProducts, array $structure): array
    {
        $placedProducts = 0;
        $usedSegments = 0;
        $placement_log = [];

        // Definir níveis de prateleira por classe ABC
        $shelfLevels = array_keys($structure['shelves_by_level']);
        sort($shelfLevels);
        
        $levelMapping = [
            'A' => $this->getBestShelfLevels($shelfLevels), // Níveis centrais (olhos/mãos)
            'B' => $this->getMiddleShelfLevels($shelfLevels), // Níveis intermediários
            'C' => $this->getWorstShelfLevels($shelfLevels)  // Níveis extremos
        ];

        // Distribuir cada classe ABC
        foreach (['A', 'B', 'C'] as $class) {
            $products = $classifiedProducts[$class];
            $targetLevels = $levelMapping[$class];
            
            $classResult = $this->placeProductsInLevels(
                $gondola, 
                $products, 
                $targetLevels, 
                $structure
            );
            
            $placedProducts += $classResult['placed'];
            $usedSegments += $classResult['segments_used'];
            $placement_log[$class] = $classResult;
        }

        return [
            'products_placed' => $placedProducts,
            'segments_used' => $usedSegments,
            'placement_by_class' => $placement_log,
            'gondola_structure' => $structure
        ];
    }

    /**
     * Determina os melhores níveis de prateleira (Classe A)
     */
    protected function getBestShelfLevels(array $allLevels): array
    {
        $totalLevels = count($allLevels);
        if ($totalLevels <= 2) return $allLevels;
        
        // Pega os níveis centrais (meio da gôndola)
        $middleIndex = intval($totalLevels / 2);
        return array_slice($allLevels, max(0, $middleIndex - 1), 2);
    }

    /**
     * Determina os níveis intermediários (Classe B)
     */
    protected function getMiddleShelfLevels(array $allLevels): array
    {
        $totalLevels = count($allLevels);
        if ($totalLevels <= 3) return $allLevels;
        
        $bestLevels = $this->getBestShelfLevels($allLevels);
        return array_diff($allLevels, array_merge($bestLevels, $this->getWorstShelfLevels($allLevels)));
    }

    /**
     * Determina os piores níveis de prateleira (Classe C)
     */
    protected function getWorstShelfLevels(array $allLevels): array
    {
        $totalLevels = count($allLevels);
        if ($totalLevels <= 2) return [];
        
        // Pega primeiro e último nível (extremos)
        return [$allLevels[0], $allLevels[$totalLevels - 1]];
    }

    /**
     * Coloca produtos em níveis específicos de prateleira
     */
    protected function placeProductsInLevels(Gondola $gondola, array $products, array $targetLevels, array $structure): array
    {
        $placed = 0;
        $segmentsUsed = 0;
        
        foreach ($products as $productData) {
            $productId = $productData['product_id'];
            $placed_in_level = false;
            
            // Tentar colocar nos níveis preferidos
            foreach ($targetLevels as $level) {
                if (!isset($structure['shelves_by_level'][$level])) continue;
                
                foreach ($structure['shelves_by_level'][$level] as $shelf) {
                    foreach ($shelf->segments as $segment) {
                        // Recarregar o relacionamento para garantir dados atualizados
                        $segment->load('layer');
                        $existingLayer = $segment->layer;
                        
                        if ($existingLayer && !$existingLayer->product_id) {
                            // Layer vazia encontrada, colocar produto com facing inteligente
                            $optimalFacing = $this->calculateOptimalFacing(
                                $productData,
                                $segment
                            );
                            
                            $existingLayer->update([
                                'product_id' => $productId,
                                'quantity' => $optimalFacing
                            ]);
                            $placed++;
                            $segmentsUsed++;
                            $placed_in_level = true;
                            break 3; // Sair de todos os loops
                        } elseif (!$existingLayer) {
                            // Criar nova layer no segmento (hasOne)
                            try {
                                // Calcular facing inteligente baseado no score e dimensões
                                $optimalFacing = $this->calculateOptimalFacing(
                                    $productData,
                                    $segment
                                );
                                
                                $layer = $segment->layer()->create([
                                    'tenant_id' => $segment->tenant_id,
                                    'user_id' => $segment->user_id,
                                    'product_id' => $productId,
                                    'quantity' => $optimalFacing,
                                    'status' => 'published'
                                ]);
                                
                                Log::info("Layer criada com sucesso", [
                                    'layer_id' => $layer->id,
                                    'segment_id' => $segment->id,
                                    'product_id' => $productId
                                ]);
                                
                                $placed++;
                                $segmentsUsed++;
                                $placed_in_level = true;
                                break 3; // Sair de todos os loops - IMPORTANTE: cada produto em um segmento
                            } catch (\Exception $e) {
                                Log::error("Erro ao criar layer", [
                                    'segment_id' => $segment->id,
                                    'product_id' => $productId,
                                    'erro' => $e->getMessage()
                                ]);
                            }
                        }
                    }
                }
            }
            
            // Se não conseguiu colocar nos níveis preferidos, tentar em qualquer lugar
            if (!$placed_in_level) {
                foreach ($gondola->sections as $section) {
                    foreach ($section->shelves as $shelf) {
                        foreach ($shelf->segments as $segment) {
                            // Recarregar o relacionamento para garantir dados atualizados
                            $segment->load('layer');
                            $existingLayer = $segment->layer;
                            
                            if ($existingLayer && !$existingLayer->product_id) {
                                // Layer vazia encontrada, colocar produto
                                $existingLayer->update(['product_id' => $productId]);
                                $placed++;
                                $segmentsUsed++;
                                $placed_in_level = true;
                                break 3; // Sair de todos os loops
                            } elseif (!$existingLayer) {
                                // Criar nova layer no segmento (hasOne)
                                try {
                                    $layer = $segment->layer()->create([
                                        'tenant_id' => $segment->tenant_id,
                                        'user_id' => $segment->user_id,
                                        'product_id' => $productId,
                                        'quantity' => 1,
                                        'status' => 'published'
                                    ]);
                                    
                                    Log::info("Layer criada com sucesso (fallback)", [
                                        'layer_id' => $layer->id,
                                        'segment_id' => $segment->id,
                                        'product_id' => $productId
                                    ]);
                                    
                                    $placed++;
                                    $segmentsUsed++;
                                    $placed_in_level = true;
                                    break 3; // Sair de todos os loops
                                } catch (\Exception $e) {
                                    Log::error("Erro ao criar layer (fallback)", [
                                        'segment_id' => $segment->id,
                                        'product_id' => $productId,
                                        'erro' => $e->getMessage()
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return [
            'placed' => $placed,
            'segments_used' => $segmentsUsed,
            'total_products' => count($products)
        ];
    }

    /**
     * Gera resumo estatístico dos scores
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
            'average_score' => round(array_sum($finalScores) / count($finalScores), 4),
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

    // /**
    //  * Calcula o número ótimo de frentes baseado no score, classe ABC e dimensões
    //  */
    // protected function calculateOptimalFacing(array $productData, $segment): int
    // {
    //     $productId = $productData['product_id'];
    //     $finalScore = $productData['final_score'];
    //     $abcClass = $productData['abc_class'] ?? 'C';
        
    //     // Buscar produto para obter dimensões
    //     $product = \App\Models\Product::with('dimensions')->find($productId);
    //     if (!$product || !$product->dimensions) {
    //         Log::warning("Produto sem dimensões para cálculo de facing", [
    //             'product_id' => $productId
    //         ]);
    //         return 1; // Facing mínimo
    //     }
        
    //     // Obter larguras (em cm)
    //     $productWidth = $product->dimensions->width ?? 10; // Padrão 10cm se não definido
    //     $segmentWidth = $segment->width ?? 100; // Padrão 100cm se não definido
        
    //     // Definir regras por classe ABC
    //     $facingRules = [
    //         'A' => ['min' => 2, 'max' => 6, 'score_multiplier' => 1.0],
    //         'B' => ['min' => 1, 'max' => 3, 'score_multiplier' => 0.7],
    //         'C' => ['min' => 1, 'max' => 2, 'score_multiplier' => 0.4]
    //     ];
        
    //     $rules = $facingRules[$abcClass] ?? $facingRules['C'];
        
    //     // Calcular facing baseado no score normalizado
    //     $normalizedScore = max(0, min(1, $finalScore)); // Garantir entre 0 e 1
    //     $scoreBasedFacing = $rules['min'] + 
    //         ($normalizedScore * $rules['score_multiplier'] * ($rules['max'] - $rules['min']));
        
    //     // Arredondar para inteiro
    //     $desiredFacing = (int) round($scoreBasedFacing);
        
    //     // Verificar limitação física (largura)
    //     $maxFacingByWidth = (int) floor($segmentWidth / $productWidth);
        
    //     // Aplicar limitações
    //     $optimalFacing = min(
    //         $desiredFacing,           // Facing desejado pelo score
    //         $maxFacingByWidth,        // Limitação física
    //         $rules['max']             // Limitação da classe ABC
    //     );
        
    //     // Garantir mínimo de 1
    //     $optimalFacing = max(1, $optimalFacing);
        
    //     Log::info("Facing calculado", [
    //         'product_id' => $productId,
    //         'abc_class' => $abcClass,
    //         'final_score' => $finalScore,
    //         'product_width' => $productWidth,
    //         'segment_width' => $segmentWidth,
    //         'desired_facing' => $desiredFacing,
    //         'max_by_width' => $maxFacingByWidth,
    //         'optimal_facing' => $optimalFacing
    //     ]);
        
    //     return $optimalFacing;
    // }

    /**
     * Distribui produtos com VERTICALIZAÇÃO POR MÓDULO - ALGORITMO SECTION-BY-SECTION
     */
    protected function placeProductsSequentially(Gondola $gondola, array $classifiedProducts, array $structure): array
    {
        $productsPlaced = 0;
        $segmentsUsed = 0;
        $totalProductPlacements = 0;
        $moduleUsage = [];
        
        Log::info("🎯 ALGORITMO SECTION-BY-SECTION - VERTICALIZAÇÃO POR MÓDULO", [
            'classe_A' => count($classifiedProducts['A']),
            'classe_B' => count($classifiedProducts['B']),
            'classe_C' => count($classifiedProducts['C']),
            'total_sections' => $structure['total_sections']
        ]);

        // 1. PEGAR TODAS AS SECTIONS (MÓDULOS) DA GONDOLA EM ORDEM
        $allSections = $gondola->sections()
            ->with(['shelves.segments.layer'])
            ->orderBy('ordering')
            ->get();
            
        Log::info("📋 Sections encontradas", [
            'total_sections' => $allSections->count(),
            'section_ids' => $allSections->pluck('id')->toArray(),
            'section_orderings' => $allSections->pluck('ordering')->toArray()
        ]);

        // 2. PROCESSAR CADA MÓDULO (SECTION) INDIVIDUALMENTE COM DISTRIBUIÇÃO EM CASCATA
        $allFailedProducts = []; // Produtos que falharam em todos os módulos
        
        foreach ($allSections as $section) {
            $moduleNumber = $section->ordering + 1; // Módulo 1, 2, 3, 4...
            
            Log::info("🏗️ Processando Módulo COM CASCATA", [
                'module_number' => $moduleNumber,
                'section_id' => $section->id,
                'section_ordering' => $section->ordering
            ]);
            
            // 3. DETERMINAR PRODUTOS PARA ESTE MÓDULO BASEADO NA POSIÇÃO
            $targetProducts = $this->getProductsForModule($moduleNumber, $classifiedProducts);
            
            if (empty($targetProducts)) {
                Log::info("⚠️ Nenhum produto designado para este módulo", [
                    'module_number' => $moduleNumber
                ]);
                continue;
            }
            
            Log::info("🎯 Produtos selecionados para o módulo", [
                'module_number' => $moduleNumber,
                'products_count' => count($targetProducts),
                'product_ids' => array_column($targetProducts, 'product_id')
            ]);
            
            // 4. VERTICALIZAR PRODUTOS DENTRO DO MÓDULO
            $moduleResults = $this->fillSectionVertically($section, $targetProducts, $structure);
            
            // 5. CONSOLIDAR RESULTADOS
            $productsPlaced += $moduleResults['products_placed'];
            $segmentsUsed += $moduleResults['segments_used'];
            $totalProductPlacements += $moduleResults['total_placements'];
            
            $moduleUsage[$moduleNumber] = [
                'products_placed' => $moduleResults['products_placed'],
                'total_placements' => $moduleResults['total_placements'],
                'segments_used' => $moduleResults['segments_used'],
                'products' => $moduleResults['products_details'],
                'failed_products' => count($moduleResults['failed_products'] ?? [])
            ];
            
            // 6. DISTRIBUIÇÃO EM CASCATA: Tentar produtos que falharam em outros módulos
            if (!empty($moduleResults['failed_products'])) {
                $cascadeResults = $this->tryCascadeDistribution(
                    $allSections, 
                    $moduleResults['failed_products'], 
                    $section->id, 
                    $structure
                );
                
                $productsPlaced += $cascadeResults['products_placed'];
                $segmentsUsed += $cascadeResults['segments_used'];
                $totalProductPlacements += $cascadeResults['total_placements'];
                
                // Produtos que ainda falharam após cascata
                $allFailedProducts = array_merge($allFailedProducts, $cascadeResults['still_failed']);
                
                Log::info("🔄 CASCATA executada para produtos que falharam", [
                    'module_number' => $moduleNumber,
                    'failed_products' => count($moduleResults['failed_products']),
                    'cascade_placed' => $cascadeResults['products_placed'],
                    'still_failed' => count($cascadeResults['still_failed'])
                ]);
            }
            
            // NOVA FASE: PREENCHIMENTO OPORTUNÍSTICO - maximizar uso do espaço
            $opportunisticResults = $this->fillOpportunisticSpace($section, $targetProducts);
            $moduleResults['segments_used'] += $opportunisticResults['segments_used'];
            $moduleResults['total_placements'] += $opportunisticResults['total_placements'];
            
            Log::info("✅ Módulo processado COM CASCATA E OPORTUNÍSTICO", [
                'module_number' => $moduleNumber,
                'products_placed' => $moduleResults['products_placed'],
                'segments_used' => $moduleResults['segments_used'],
                'total_placements' => $moduleResults['total_placements'],
                'opportunistic_added' => $opportunisticResults['total_placements']
            ]);
        }
        
        Log::info("🎉 DISTRIBUIÇÃO SECTION-BY-SECTION CONCLUÍDA COM CASCATA", [
            'products_placed' => $productsPlaced,
            'total_placements' => $totalProductPlacements,
            'segments_used' => $segmentsUsed,
            'modules_used' => count($moduleUsage),
            'space_utilization' => round(($segmentsUsed / max($structure['total_segments'], 1)) * 100, 1) . '%',
            'products_still_failed' => count($allFailedProducts),
            'placement_success_rate' => round(($productsPlaced / max(count($classifiedProducts['A']) + count($classifiedProducts['B']) + count($classifiedProducts['C']), 1)) * 100, 1) . '%'
        ]);
        
        // Log detalhado dos produtos que ainda falharam
        if (!empty($allFailedProducts)) {
            Log::warning("❌ PRODUTOS QUE NÃO COUBERAM EM NENHUM MÓDULO", [
                'count' => count($allFailedProducts),
                'failed_products' => array_map(function($product) {
                    return [
                        'product_id' => $product['product_id'],
                        'abc_class' => $product['abc_class'],
                        'width' => $product['product']['width'] ?? 'N/A',
                        'score' => $product['final_score'] ?? 'N/A'
                    ];
                }, array_slice($allFailedProducts, 0, 10)) // Primeiros 10 para não sobrecarregar o log
            ]);
        }

        return [
            'products_placed' => $productsPlaced,
            'total_placements' => $totalProductPlacements,
            'segments_used' => $segmentsUsed,
            'module_usage' => $moduleUsage
        ];
    }

    /**
     * Obtém todas as prateleiras organizadas por ordering
     */
    protected function getAllShelvesInOrder(Gondola $gondola): array
    {
        $allShelves = [];
        
        // Buscar todas as prateleiras de todas as seções
        foreach ($gondola->sections as $section) {
            foreach ($section->shelves as $shelf) {
                $allShelves[] = $shelf;
            }
        }
        
        // Ordenar por ordering (do menor para o maior)
        usort($allShelves, function ($a, $b) {
            return ($a->ordering ?? 0) <=> ($b->ordering ?? 0);
        });
        
        Log::info("Prateleiras coletadas e ordenadas", [
            'total_shelves' => count($allShelves),
            'ordering_sequence' => array_map(function($shelf) {
                return $shelf->ordering ?? 'null';
            }, $allShelves)
        ]);
        
        return $allShelves;
    }

    /**
     * Distribui produtos em uma prateleira com ocupação horizontal completa
     */
    protected function distributeProductsInShelf($shelf, array $allProducts, int $startProductIndex = 0): array
    {
        // NOVA ABORDAGEM: Calcular espaço real e distribuir de forma inteligente [[memory:8393313]]
        // 1. CALCULAR ESPAÇO TOTAL DISPONÍVEL
        $physicalShelfWidth = floatval($shelf->shelf_width ?? 125);
        $totalShelfWidth = $physicalShelfWidth;
        
        Log::info("🏗️ Calculando capacidade da prateleira", [
            'shelf_id' => $shelf->id,
            'physical_width_cm' => $totalShelfWidth
        ]);
        
        // 2. CALCULAR QUANTOS PRODUTOS CABEM FISICAMENTE
        $avgProductWidth = $this->calculateAverageProductWidth($allProducts);
        $maxProductsCapacity = max(1, floor($totalShelfWidth / $avgProductWidth));
        
        Log::info("📊 Capacidade calculada", [
            'shelf_width' => $totalShelfWidth,
            'avg_product_width' => $avgProductWidth,
            'max_products_capacity' => $maxProductsCapacity,
            'products_available' => count($allProducts)
        ]);
        
        // 3. SELECIONAR PRODUTOS BASEADO NO SCORE
        $productsToPlace = array_slice($allProducts, $startProductIndex, min($maxProductsCapacity, count($allProducts) - $startProductIndex));
        
        // 4. DISTRIBUIR PRODUTOS (1 segmento por tipo de produto)
        $segments = $shelf->segments;
        $segmentsUsed = 0;
        $productsPlaced = 0;
        $totalPlacements = 0;
        $productsMap = [];
        $widthUsed = 0;
        $currentSegmentIndex = 0;
        
        // LOOP PRINCIPAL: Distribuir cada produto selecionado
        foreach ($productsToPlace as $product) {
            $productId = $product['product_id'];
            $productData = $product['product'] ?? [];
            $productWidth = floatval($productData['width'] ?? 25);
            $abcClass = $product['abc_class'] ?? 'C';
            
            // Calcular facing otimizado baseado no score e espaço disponível
            $availableWidth = $totalShelfWidth - $widthUsed;
            $optimalFacing = $this->calculateOptimalFacing($product, $availableWidth);
            $productTotalWidth = $productWidth * $optimalFacing;
            
            // Verificar se cabe na prateleira
            if ($widthUsed + $productTotalWidth <= $totalShelfWidth) {
                // Usar segmento existente ou criar novo
                $segment = null;
                
                if ($currentSegmentIndex < $segments->count()) {
                    // Usar segmento existente
                    $segment = $segments[$currentSegmentIndex];
                    Log::info("📦 Usando segmento existente", [
                        'segment_id' => $segment->id,
                        'segment_index' => $currentSegmentIndex
                    ]);
                } else {
                    // Criar novo segmento
                    $segment = $this->createOptimalSegment($shelf, $currentSegmentIndex, $productTotalWidth);
                    if ($segment) {
                        Log::info("🆕 Segmento criado para produto", [
                            'segment_id' => $segment->id,
                            'product_id' => $productId,
                            'facing' => $optimalFacing
                        ]);
                    }
                }
                
                if ($segment) {
                    // Colocar produto no segmento com facing otimizado
                    $success = $this->placeProductInSegmentWithFacing($segment, $product, $optimalFacing);
                    
                    if ($success) {
                        $segmentsUsed++;
                        $productsPlaced++;
                        $totalPlacements += $optimalFacing;
                        $widthUsed += $productTotalWidth;
                        $currentSegmentIndex++;
                        
                        $productsMap[$productId] = $optimalFacing;
                        
                        Log::info("✅ Produto distribuído com sucesso", [
                            'product_id' => $productId,
                            'abc_class' => $abcClass,
                            'facing' => $optimalFacing,
                            'width_used' => $productTotalWidth,
                            'segment_id' => $segment->id,
                            'cumulative_width' => $widthUsed,
                            'utilization' => round(($widthUsed / $totalShelfWidth) * 100, 1) . '%'
                        ]);
                    }
                }
        } else {
                Log::info("⚠️ Produto não cabe - capacidade atingida", [
                    'product_id' => $productId,
                    'width_needed' => $productTotalWidth,
                    'width_available' => $totalShelfWidth - $widthUsed,
                    'facing_requested' => $optimalFacing
                ]);
            }
        }
        
        Log::info("📦 Prateleira processada com NOVA LÓGICA", [
            'shelf_id' => $shelf->id,
            'segments_used' => $segmentsUsed,
            'width_used' => $widthUsed,
            'width_utilization' => round(($widthUsed / $totalShelfWidth) * 100, 1) . '%',
            'products_placed' => $productsPlaced,
            'total_facing' => $totalPlacements,
            'avg_facing_per_product' => $productsPlaced > 0 ? round($totalPlacements / $productsPlaced, 1) : 0
        ]);

        return [
            'segments_used' => $segmentsUsed,
            'products_placed' => $productsPlaced,
            'total_placements' => $totalPlacements,
            'products_map' => $productsMap,
            'width_used' => $widthUsed,
            'width_available' => $totalShelfWidth,
            'segments_created' => $currentSegmentIndex,
            'next_product_index' => $startProductIndex + $productsPlaced
        ];
    }

    /**
     * Cria um novo segmento dinamicamente para ocupar espaço horizontal
     * CORREÇÃO: Verifica se não ultrapassa largura física da prateleira
     */
    protected function createDynamicSegment($shelf, int $position, float $width)
    {
        try {
            // VERIFICAÇÃO: Calcular largura total atual dos segmentos
            $currentTotalWidth = $shelf->segments()->sum('width');
            $physicalShelfWidth = floatval($shelf->shelf_width ?? null);
            
            // Se há largura física definida, verificar se não ultrapassa
            if ($physicalShelfWidth > 0) {
                $futureWidth = $currentTotalWidth + $width;
                if ($futureWidth > $physicalShelfWidth) {
                    Log::warning("⚠️ Segmento não criado: ultrapassaria largura física", [
                        'shelf_id' => $shelf->id,
                        'current_width_cm' => $currentTotalWidth,
                        'new_segment_width_cm' => $width,
                        'future_width_cm' => $futureWidth,
                        'physical_limit_cm' => $physicalShelfWidth,
                        'overflow_cm' => $futureWidth - $physicalShelfWidth
                    ]);
                    return null;
                }
            }

            $segment = $shelf->segments()->create([
                'tenant_id' => $shelf->tenant_id,
                'user_id' => $shelf->user_id,
                'width' => $width,
                'ordering' => $position,
                'position' => $position,
                'quantity' => 1, // Altura padrão
                'spacing' => 0,
                'alignment' => 'left',
                'status' => 'published'
            ]);

            Log::info("🆕 Segmento criado com sucesso", [
                'segment_id' => $segment->id,
                'shelf_id' => $shelf->id,
                'width_cm' => $width,
                'position' => $position,
                'current_total_width_cm' => $currentTotalWidth + $width,
                'physical_limit_cm' => $physicalShelfWidth ?? 'não definida'
            ]);

            return $segment;
        } catch (\Exception $e) {
            Log::error("❌ Erro ao criar segmento dinamicamente", [
                'shelf_id' => $shelf->id,
                'position' => $position,
                'width' => $width,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Define facing baseado na classe ABC
     */
    protected function getFacingByClass(string $abcClass): int
    {
        switch ($abcClass) {
            case 'A':
                return 4; // Produtos A: 4 facing (maior visibilidade)
            case 'B':
                return 2; // Produtos B: 2 facing (visibilidade média)
            case 'C':
                return 1; // Produtos C: 1 facing (visibilidade mínima)
            default:
                return 1;
        }
    }

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
     * NOVA FUNÇÃO: Calcula largura média dos produtos [[memory:8393313]]
     */
    protected function calculateAverageProductWidth(array $products): float
    {
        if (empty($products)) return 25.0; // Default
        
        $totalWidth = 0;
        $validWidths = 0;
        
        foreach ($products as $product) {
            $productData = $product['product'] ?? [];
            $width = floatval($productData['width'] ?? 25);
            $totalWidth += $width;
            $validWidths++;
            
            Log::debug("Calculando largura média", [
                'product_id' => $product['product_id'] ?? 'unknown',
                'width' => $width,
                'has_product_data' => isset($product['product'])
            ]);
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
    
    /**
     * CORREÇÃO: Calcula facing otimizado REALISTA baseado no espaço disponível
     * Prioriza garantir que o produto SEMPRE cabe, mesmo que com facing menor
     */
    protected function calculateOptimalFacing($product, float $availableWidth): int
    {
        $productData = $product['product'] ?? [];
        $productWidth = floatval($productData['width'] ?? 25);
        $abcClass = $product['abc_class'] ?? 'C';
        $finalScore = floatval($product['final_score'] ?? 0);
        
        Log::debug("🧮 Calculando facing REALISTA", [
            'product_id' => $product['product_id'] ?? 'unknown',
            'product_width' => $productWidth,
            'available_width' => $availableWidth,
            'abc_class' => $abcClass,
            'final_score' => $finalScore
        ]);
        
        // 1. PRIMEIRO: Verificar se o produto tem largura válida
        if ($productWidth <= 0) {
            Log::warning("⚠️ Produto com largura inválida", [
                'product_id' => $product['product_id'] ?? 'unknown',
                'product_width' => $productWidth,
                'available_width' => $availableWidth
            ]);
            return 0; // Largura inválida, não pode ser colocado
        }
        
        // 2. Verificar se o produto cabe pelo menos 1 vez
        if ($productWidth > $availableWidth) {
            Log::warning("⚠️ Produto não cabe nem 1 vez no espaço disponível", [
                'product_id' => $product['product_id'] ?? 'unknown',
                'product_width' => $productWidth,
                'available_width' => $availableWidth,
                'deficit' => $productWidth - $availableWidth
            ]);
            return 0; // Não cabe
        }
        
        // 3. Calcular facing máximo possível fisicamente
        $maxPhysicalFacing = floor($availableWidth / $productWidth);
        
        // 4. Facing desejado baseado na classe ABC (MAIS CONSERVADOR)
        $desiredFacing = match($abcClass) {
            'A' => min(4, $maxPhysicalFacing), // Classe A: máximo 4 facing (mais conservador)
            'B' => min(3, $maxPhysicalFacing), // Classe B: máximo 3 facing
            'C' => min(2, $maxPhysicalFacing), // Classe C: máximo 2 facing
            default => min(1, $maxPhysicalFacing)
        };
        
        // 5. Ajuste baseado no score (bonus mais moderado)
        $normalizedScore = max(0, min(1, $finalScore));
        if ($normalizedScore > 0.7) {
            $desiredFacing = min($desiredFacing + 1, $maxPhysicalFacing); // Score alto: +1 facing
        } elseif ($normalizedScore < 0.3) {
            $desiredFacing = max(1, $desiredFacing - 1); // Score baixo: -1 facing
        }
        
        // 6. Garantir que sempre cabe pelo menos 1 facing
        $finalFacing = max(1, min($desiredFacing, $maxPhysicalFacing));
        
        // Calcular eficiência de uso do espaço
        $usedWidth = $finalFacing * $productWidth;
        $widthEfficiency = round(($usedWidth / $availableWidth) * 100, 1);
        
        Log::info("✅ Facing REALISTA calculado", [
            'product_id' => $product['product_id'] ?? 'unknown',
            'abc_class' => $abcClass,
            'product_width' => $productWidth,
            'available_width' => $availableWidth,
            'max_physical_facing' => $maxPhysicalFacing,
            'desired_facing' => $desiredFacing,
            'final_facing' => $finalFacing,
            'used_width' => $usedWidth,
            'width_efficiency' => $widthEfficiency . '%',
            'score_adjustment' => $normalizedScore > 0.7 ? '+1' : ($normalizedScore < 0.3 ? '-1' : '0')
        ]);
        
        return $finalFacing;
    }

    /**
     * CORREÇÃO: Define facing máximo baseado no tamanho do produto (como na foto do usuário)
     * Produtos grandes = poucos facing + verticalização automática
     */
    protected function getMaxFacingByProductSize(float $productWidth): int
    {
        // Baseado na análise da imagem do usuário:
        // Produtos grandes (AUTO ALLEGRO) = poucos facing, produtos pequenos = múltiplos facing
        
        if ($productWidth >= 22) {
            // Produtos muito grandes (≥22mm): Max 2-3 facing → FORÇA verticalização
            $maxFacing = 3;
            $category = 'MUITO GRANDE';
        } elseif ($productWidth >= 18) {
            // Produtos grandes (18-21mm): Max 4 facing → Encourage verticalização  
            $maxFacing = 4;
            $category = 'GRANDE';
        } elseif ($productWidth >= 15) {
            // Produtos médios (15-17mm): Max 6 facing → Verticalização opcional
            $maxFacing = 6;
            $category = 'MÉDIO';
        } else {
            // Produtos pequenos (<15mm): Max 8 facing → Mais facing por prateleira
            $maxFacing = 8;
            $category = 'PEQUENO';
        }

        Log::debug("📏 Facing limitado por tamanho do produto", [
            'product_width' => $productWidth,
            'category' => $category,
            'max_facing_per_shelf' => $maxFacing,
            'logic' => 'Produtos grandes → menos facing → mais verticalização'
        ]);

        return $maxFacing;
    }
    
    /**
     * NOVA FUNÇÃO: Cria segmento otimizado para o produto [[memory:8393313]]
     */
    protected function createOptimalSegment($shelf, int $position, float $width)
    {
        try {
            $segment = $shelf->segments()->create([
                'tenant_id' => $shelf->tenant_id,
                'user_id' => $shelf->user_id,
                'width' => $width,
                'ordering' => $position,
                'position' => $position,
                'quantity' => 1,
                'spacing' => 0,
                'alignment' => 'left',
                'status' => 'published'
            ]);

            return $segment;
        } catch (\Exception $e) {
            Log::error("❌ Erro ao criar segmento otimizado", [
                'shelf_id' => $shelf->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Obtém todos os segmentos em ordem de preenchimento
     */
    protected function getAllSegmentsInOrder(Gondola $gondola, array $structure): array
    {
        $allSegments = [];
        
        // Ordenar níveis (prateleiras) - começar do nível mais alto para o mais baixo
        $levels = array_keys($structure['shelves_by_level']);
        rsort($levels); // Nível mais alto primeiro (como na imagem)
        
        foreach ($levels as $level) {
            $shelves = $structure['shelves_by_level'][$level];
            
            // Para cada prateleira neste nível, pegar todos os segmentos
            foreach ($shelves as $shelf) {
                foreach ($shelf->segments as $segment) {
                    $allSegments[] = $segment;
                }
            }
        }
        
        return $allSegments;
    }

    /**
     * Conta segmentos por nível para debug
     */
    protected function getSegmentsCountByLevel(array $segments): array
    {
        $countByLevel = [];
        
        foreach ($segments as $segment) {
            $shelf = $segment->shelf;
            $level = $shelf->ordering;
            
            if (!isset($countByLevel[$level])) {
                $countByLevel[$level] = 0;
            }
            $countByLevel[$level]++;
        }
        
        return $countByLevel;
    }

    /**
     * Distribui produtos com verticalização (alinhamento vertical por marca/categoria)
     */
    protected function placeProductsWithVerticalization(Gondola $gondola, array $classifiedProducts, array $structure): array
    {
        $placedProducts = 0;
        $usedSegments = 0;
        $placement_log = [];

        // Analisar estrutura para verticalização
        $verticalStructure = $this->analyzeVerticalStructure($structure);
        
        Log::info("Estrutura vertical analisada", [
            'total_columns' => $verticalStructure['total_columns'],
            'levels_per_column' => $verticalStructure['levels_per_column'],
            'segments_per_level' => $verticalStructure['segments_per_level']
        ]);

        // Distribuir cada classe ABC com verticalização
        foreach (['A', 'B', 'C'] as $class) {
            $products = $classifiedProducts[$class];
            if (empty($products)) continue;
            
            // Agrupar produtos por marca para verticalização
            $productsByBrand = $this->groupProductsByBrand($products);
            
            $classResult = $this->placeProductsVerticallyByBrand(
                $gondola, 
                $productsByBrand,
                $class,
                $verticalStructure
            );
            
            $placedProducts += $classResult['placed'];
            $usedSegments += $classResult['segments_used'];
            $placement_log[$class] = $classResult;
        }

        return [
            'products_placed' => $placedProducts,
            'segments_used' => $usedSegments,
            'placement_by_class' => $placement_log,
            'gondola_structure' => $structure,
            'vertical_structure' => $verticalStructure
        ];
    }

    /**
     * Analisa estrutura da gôndola para distribuição vertical
     */
    protected function analyzeVerticalStructure(array $structure): array
    {
        $levels = array_keys($structure['shelves_by_level']);
        sort($levels);
        
        // Calcular número de colunas (segmentos por nível)
        $segmentsPerLevel = [];
        $totalColumns = 0;
        
        foreach ($levels as $level) {
            $shelves = $structure['shelves_by_level'][$level];
            $segmentsInLevel = 0;
            
            foreach ($shelves as $shelf) {
                $segmentsInLevel += $shelf->segments->count();
            }
            
            $segmentsPerLevel[$level] = $segmentsInLevel;
            $totalColumns = max($totalColumns, $segmentsInLevel);
        }
        
        return [
            'levels' => $levels,
            'total_columns' => $totalColumns,
            'levels_per_column' => count($levels),
            'segments_per_level' => $segmentsPerLevel
        ];
    }

    /**
     * Agrupa produtos por marca para verticalização
     */
    protected function groupProductsByBrand(array $products): array
    {
        $productsByBrand = [];
        
        foreach ($products as $productData) {
            $productId = $productData['product_id'];
            
            // Buscar produto para obter marca
            $product = \App\Models\Product::find($productId);
            if (!$product) continue;
            
            // Usar marca ou nome do produto como agrupador
            $brandKey = $product->brand ?? $this->extractBrandFromName($product->name);
            
            if (!isset($productsByBrand[$brandKey])) {
                $productsByBrand[$brandKey] = [];
            }
            
            $productsByBrand[$brandKey][] = $productData;
        }
        
        // Ordenar marcas por score médio (melhor marca primeiro)
        uasort($productsByBrand, function($brandA, $brandB) {
            $avgScoreA = array_sum(array_column($brandA, 'final_score')) / count($brandA);
            $avgScoreB = array_sum(array_column($brandB, 'final_score')) / count($brandB);
            return $avgScoreB <=> $avgScoreA;
        });
        
        return $productsByBrand;
    }

    /**
     * Extrai marca do nome do produto (fallback)
     */
    protected function extractBrandFromName(string $productName): string
    {
        // Pegar primeira palavra como "marca"
        $words = explode(' ', $productName);
        return $words[0] ?? 'MARCA_DESCONHECIDA';
    }

    /**
     * Distribui produtos verticalmente por marca
     */
    protected function placeProductsVerticallyByBrand(Gondola $gondola, array $productsByBrand, string $class, array $verticalStructure): array
    {
        $placed = 0;
        $segmentsUsed = 0;
        $currentColumn = 0;
        
        foreach ($productsByBrand as $brand => $products) {
            Log::info("Distribuindo marca verticalmente", [
                'brand' => $brand,
                'class' => $class,
                'products_count' => count($products),
                'current_column' => $currentColumn
            ]);
            
            // Distribuir produtos desta marca em coluna vertical
            $brandResult = $this->placeProductsInVerticalColumn(
                $gondola,
                $products,
                $currentColumn,
                $verticalStructure
            );
            
            $placed += $brandResult['placed'];
            $segmentsUsed += $brandResult['segments_used'];
            
            // Avançar para próxima coluna
            $currentColumn += $brandResult['columns_used'];
            
            // Verificar se ainda há colunas disponíveis
            if ($currentColumn >= $verticalStructure['total_columns']) {
                Log::warning("Limite de colunas atingido", [
                    'current_column' => $currentColumn,
                    'total_columns' => $verticalStructure['total_columns']
                ]);
                break;
            }
        }
        
        return [
            'placed' => $placed,
            'segments_used' => $segmentsUsed,
            'columns_used' => $currentColumn
        ];
    }

    /**
     * Coloca produtos de uma marca em coluna vertical
     */
    protected function placeProductsInVerticalColumn(Gondola $gondola, array $products, int $columnIndex, array $verticalStructure): array
    {
        $placed = 0;
        $segmentsUsed = 0;
        $columnsUsed = 1;
        
        // Percorrer níveis de cima para baixo
        $levels = $verticalStructure['levels'];
        rsort($levels); // Começar do nível mais alto
        
        $productIndex = 0;
        
        foreach ($levels as $level) {
            if ($productIndex >= count($products)) break;
            
            $productData = $products[$productIndex];
            
            // Encontrar segmento na posição da coluna
            $segment = $this->findSegmentAtColumnPosition($gondola, $level, $columnIndex);
            
            if ($segment) {
                // Colocar produto no segmento
                $success = $this->placeProductInSegment($segment, $productData);
                
                if ($success) {
                    $placed++;
                    $segmentsUsed++;
                    $productIndex++;
                    
                    Log::info("Produto colocado em coluna vertical", [
                        'product_id' => $productData['product_id'],
                        'level' => $level,
                        'column' => $columnIndex,
                        'segment_id' => $segment->id
                    ]);
                }
            }
        }
        
        return [
            'placed' => $placed,
            'segments_used' => $segmentsUsed,
            'columns_used' => $columnsUsed
        ];
    }

    /**
     * Encontra segmento na posição específica da coluna
     */
    protected function findSegmentAtColumnPosition(Gondola $gondola, int $level, int $columnIndex)
    {
        $segmentCounter = 0;
        
        foreach ($gondola->sections as $section) {
            foreach ($section->shelves as $shelf) {
                // Verificar se é o nível correto
                if ($shelf->ordering != $level) continue;
                
                foreach ($shelf->segments as $segment) {
                    if ($segmentCounter == $columnIndex) {
                        return $segment;
                    }
                    $segmentCounter++;
                }
            }
        }
        
        return null;
    }

    /**
     * Coloca produto em segmento específico
     */
    protected function placeProductInSegment($segment, array $productData): bool
    {
        try {
            // Recarregar relacionamento
            $segment->load('layer');
            $existingLayer = $segment->layer;
            
            // Calcular facing ótimo
            $optimalFacing = $this->calculateOptimalFacing($productData, $segment);
            
            if ($existingLayer && !$existingLayer->product_id) {
                // Layer vazia, atualizar
                $existingLayer->update([
                    'product_id' => $productData['product_id'],
                    'quantity' => $optimalFacing
                ]);
                return true;
            } elseif (!$existingLayer) {
                // Criar nova layer
                $segment->layer()->create([
                    'tenant_id' => $segment->tenant_id,
                    'user_id' => $segment->user_id,
                    'product_id' => $productData['product_id'],
                    'quantity' => $optimalFacing,
                    'status' => 'published'
                ]);
                return true;
            }
            
            return false; // Segmento ocupado
            
        } catch (\Exception $e) {
            Log::error("Erro ao colocar produto em segmento", [
                'segment_id' => $segment->id,
                'product_id' => $productData['product_id'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * NOVA FUNÇÃO: Calcula facing total baseado no score do produto [[memory:8393313]]
     */
    protected function calculateTotalFacingByScore($product, $structure): int
    {
        $abcClass = $product['abc_class'] ?? 'C';
        $score = $product['final_score'] ?? 0;
        
        // Facing base por classe ABC
        $baseFacing = match($abcClass) {
            'A' => 6, // Produtos A: facing alto
            'B' => 4, // Produtos B: facing médio  
            'C' => 2, // Produtos C: facing baixo
            default => 1
        };
        
        // Ajustar baseado no score dentro da classe
        if ($score > 0.5) {
            $baseFacing = ceil($baseFacing * 1.5); // Score alto = +50%
        } elseif ($score > 0.3) {
            $baseFacing = ceil($baseFacing * 1.2); // Score médio = +20%
        }
        
        return min($baseFacing, 10); // Máximo 10 faces total
    }

    /**
     * CORREÇÃO: Distribui o facing verticalmente APENAS no módulo correto baseado na classe ABC
     * Exemplo: Classe A → Módulo 4, Classe B → Módulos 2-3, Classe C → Módulo 1
     */
    protected function distributeProductVertically($product, $allShelves, int $facingTotal): array
    {
        $distribution = [];
        
        if (empty($allShelves) || $facingTotal <= 0) {
            return $distribution;
        }
        
        // Determinar módulo baseado na classe ABC
        $abcClass = $product['abc_class'] ?? 'C';
        $targetModule = $this->getTargetModuleByClass($abcClass);
        
        Log::info("🎯 Selecionando módulo para produto", [
            'product_id' => $product['product_id'],
            'abc_class' => $abcClass,
            'target_module' => $targetModule,
            'facing_total' => $facingTotal
        ]);
        
        // Filtrar apenas prateleiras do módulo alvo
        $targetShelves = $this->getShelvesFromModule($allShelves, $targetModule);
        
        if (empty($targetShelves)) {
            Log::warning("⚠️ Nenhuma prateleira encontrada no módulo alvo", [
                'product_id' => $product['product_id'],
                'target_module' => $targetModule,
                'total_shelves' => count($allShelves)
            ]);
            
            // Fallback: usar primeiras prateleiras disponíveis
            $targetShelves = array_slice($allShelves, 0, 4);
        }
        
        $totalShelves = count($targetShelves);
        
        // Distribuir facing apenas entre 2-3 prateleiras do módulo (não todas as 4)
        $maxShelvesToUse = min(3, $totalShelves); // Máximo 3 prateleiras
        $shelvesToUse = array_slice($targetShelves, 0, $maxShelvesToUse);
        
        // Distribuir facing entre as prateleiras selecionadas
        $facingPerShelf = floor($facingTotal / count($shelvesToUse));
        $remainder = $facingTotal % count($shelvesToUse);
        
        foreach ($shelvesToUse as $index => $shelf) {
            $facingInThisShelf = $facingPerShelf;
            
            // Distribuir o restante nas primeiras prateleiras (melhor posicionamento)
            if ($index < $remainder) {
                $facingInThisShelf++;
            }
            
            if ($facingInThisShelf > 0) {
                $distribution[$shelf->id] = $facingInThisShelf;
            }
        }
        
        Log::info("📐 Distribuição vertical CORRIGIDA por módulo", [
            'product_id' => $product['product_id'],
            'abc_class' => $abcClass,
            'target_module' => $targetModule,
            'facing_total' => $facingTotal,
            'total_shelves_available' => count($allShelves),
            'target_shelves_found' => count($targetShelves),
            'shelves_used' => count($shelvesToUse),
            'facing_per_shelf' => $facingPerShelf,
            'remainder' => $remainder,
            'distribution' => $distribution
        ]);
        
        return $distribution;
    }

    /**
     * NOVA FUNÇÃO: Coloca produto em prateleira específica com facing definido [[memory:8393313]]
     */
    protected function placeProductInShelfWithVerticalFacing($shelf, $product, int $facing): bool
    {
        // Procurar segmento vazio na prateleira
        $segments = $shelf->segments()->orderBy('ordering')->get();
        
        foreach ($segments as $segment) {
            $segment->load('layer');
            $existingLayer = $segment->layer;
            
            if (!$existingLayer || !$existingLayer->product_id) {
                // Segmento vazio - criar layer com facing
                try {
                    $segment->layer()->create([
                        'tenant_id' => $segment->tenant_id,
                        'user_id' => $segment->user_id,
                        'product_id' => $product['product_id'],
                        'quantity' => $facing, // FACING = QUANTITY no segmento
                        'status' => 'published'
                    ]);
                    
                    Log::info("✅ Produto colocado verticalmente", [
                        'product_id' => $product['product_id'],
                        'shelf_id' => $shelf->id,
                        'segment_id' => $segment->id,
                        'facing' => $facing
                    ]);
                    
                    return true;
                } catch (\Exception $e) {
                    Log::error("❌ Erro ao criar layer vertical", [
                        'segment_id' => $segment->id,
                        'product_id' => $product['product_id'],
                        'facing' => $facing,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        // Se não encontrou segmento vazio, criar novo
        return $this->createNewVerticalSegment($shelf, $product, $facing);
    }

    /**
     * NOVA FUNÇÃO: Cria novo segmento quando necessário para verticalização [[memory:8393313]]
     */
    protected function createNewVerticalSegment($shelf, $product, int $facing): bool
    {
        try {
            $productData = $product['product'] ?? [];
            $productWidth = floatval($productData['width'] ?? 25);
            $totalWidth = $productWidth * $facing;
            
            // Criar segmento com largura baseada no facing
            $segment = $shelf->segments()->create([
                'tenant_id' => $shelf->tenant_id,
                'user_id' => $shelf->user_id,
                'width' => $totalWidth,
                'ordering' => $shelf->segments()->count(),
                'position' => $shelf->segments()->count(),
                'quantity' => 1,
                'spacing' => 0,
                'alignment' => 'left',
                'status' => 'published'
            ]);

            // Criar layer com o facing calculado
            $segment->layer()->create([
                'tenant_id' => $segment->tenant_id,
                'user_id' => $segment->user_id,
                'product_id' => $product['product_id'],
                'quantity' => $facing, // FACING = QUANTITY
                'status' => 'published'
            ]);

            Log::info("🆕 Segmento vertical criado", [
                'product_id' => $product['product_id'],
                'shelf_id' => $shelf->id,
                'segment_id' => $segment->id,
                'facing' => $facing,
                'width' => $totalWidth
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("❌ Erro ao criar segmento vertical", [
                'shelf_id' => $shelf->id,
                'product_id' => $product['product_id'],
                'facing' => $facing,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * NOVA FUNÇÃO: Enricha scores com dados dos produtos (incluindo dimensões)
     */
    protected function enrichScoresWithProductData(array $scores, array $productsData): array
    {
        // Criar mapa de produtos por ID para acesso rápido
        $productMap = collect($productsData)->keyBy('id');
        
        $enrichedScores = [];
        foreach ($scores as $scoreData) {
            $productId = $scoreData['product_id'];
            $product = $productMap->get($productId);
            
            if ($product) {
                // Adicionar dados do produto ao score
                $scoreData['product'] = [
                    'id' => $product['id'],
                    'name' => $product['name'] ?? 'Produto sem nome',
                    'ean' => $product['ean'] ?? '',
                    'width' => $product['dimensions']['width'] ?? 25, // Fallback para 25mm
                    'height' => $product['dimensions']['height'] ?? 40,
                    'depth' => $product['dimensions']['depth'] ?? 30,
                ];
                
                Log::info("Produto enrichado com dimensões", [
                    'product_id' => $productId,
                    'width' => $scoreData['product']['width'],
                    'has_dimensions' => isset($product['dimensions']) && $product['dimensions'] !== null
                ]);
            } else {
                // Produto não encontrado, usar fallbacks
                $scoreData['product'] = [
                    'id' => $productId,
                    'name' => 'Produto não encontrado',
                    'ean' => '',
                    'width' => 25, // Fallback padrão
                    'height' => 40,
                    'depth' => 30,
                ];
                
                Log::warning("Produto não encontrado nos dados fornecidos", [
                    'product_id' => $productId
                ]);
            }
            
            $enrichedScores[] = $scoreData;
        }
        
        Log::info("Scores enrichados com sucesso", [
            'total_scores' => count($enrichedScores),
            'avg_width' => collect($enrichedScores)->avg('product.width'),
            'products_with_real_dimensions' => collect($enrichedScores)->filter(function($score) {
                return $score['product']['width'] !== 25; // Não é fallback
            })->count()
        ]);
        
        return $enrichedScores;
    }

    /**
     * NOVA FUNÇÃO: Determina módulo alvo baseado na classe ABC
     */
    protected function getTargetModuleByClass(string $abcClass): int
    {
        // LÓGICA CORRETA: Módulo 1 = mais nobre, Módulo 4 = menos nobre
        return match($abcClass) {
            'A' => 1, // Classe A → Módulo 1 (MAIS NOBRE)
            'B' => 2, // Classe B → Módulo 2 (intermediário)  
            'C' => 4, // Classe C → Módulo 4 (MENOS NOBRE)
            default => 4
        };
    }

    /**
     * NOVA FUNÇÃO: Filtra prateleiras que pertencem ao módulo específico
     * Baseado na análise dos logs: 16 prateleiras, 4 por seção, ordering de 0 a 3
     */
    protected function getShelvesFromModule(array $allShelves, int $targetModule): array
    {
        $shelvesInModule = [];
        
        // CORREÇÃO: Agrupar prateleiras por seção ID primeiro
        $sectionGroups = [];
        foreach ($allShelves as $shelf) {
            $sectionId = $shelf->section_id ?? null;
            if ($sectionId) {
                if (!isset($sectionGroups[$sectionId])) {
                    $sectionGroups[$sectionId] = [];
                }
                $sectionGroups[$sectionId][] = $shelf;
            }
        }
        
        // Ordenar seções por ID para ter ordem consistente
        ksort($sectionGroups);
        $orderedSections = array_keys($sectionGroups);
        
        Log::info("🏗️ Estrutura de seções descoberta", [
            'total_sections' => count($orderedSections),
            'section_ids' => $orderedSections,
            'target_module' => $targetModule,
            'shelves_per_section' => array_map('count', $sectionGroups)
        ]);
        
        // Mapear módulo para seção (Módulo 1 = primeira seção, etc.)
        $targetSectionIndex = $targetModule - 1;
        
        if (isset($orderedSections[$targetSectionIndex])) {
            $targetSectionId = $orderedSections[$targetSectionIndex];
            $shelvesInModule = $sectionGroups[$targetSectionId] ?? [];
            
            Log::info("🎯 Prateleiras do módulo selecionado", [
                'target_module' => $targetModule,
                'target_section_id' => $targetSectionId,
                'target_section_index' => $targetSectionIndex,
                'shelves_found' => count($shelvesInModule),
                'shelf_ids' => array_map(function($shelf) {
                    return $shelf->id;
                }, $shelvesInModule)
            ]);
        } else {
            Log::warning("⚠️ Módulo alvo não encontrado na estrutura", [
                'target_module' => $targetModule,
                'target_section_index' => $targetSectionIndex,
                'available_sections' => count($orderedSections)
            ]);
        }
        
        return $shelvesInModule;
    }

    /**
     * NOVO: Determina quais produtos devem ser colocados em cada módulo COM BALANCEAMENTO
     * Evita overflow distribuindo produtos de forma equilibrada entre os módulos
     * SUPORTA MÓDULOS EXTRAS (5, 6, 7...) recebendo produtos restantes
     */
    protected function getProductsForModule(int $moduleNumber, array $classifiedProducts): array
    {
        $totalProducts = count($classifiedProducts['A']) + count($classifiedProducts['B']) + count($classifiedProducts['C']);
        $avgProductsPerModule = $totalProducts > 0 ? ceil($totalProducts / 6) : 0; // Assumindo 6 módulos
        
        // DISTRIBUIÇÃO BALANCEADA: evitar overflow em qualquer módulo
        $productsForModule = match($moduleNumber) {
            1 => $this->getBalancedProductsForModule1($classifiedProducts), // Módulo 1: A + melhores B
            2 => $this->getBalancedProductsForModule2($classifiedProducts), // Módulo 2: B restantes
            3 => $this->getBalancedProductsForModule3($classifiedProducts), // Módulo 3: B + melhores C  
            4 => $this->getBalancedProductsForModule4($classifiedProducts), // Módulo 4: C restantes
            default => $this->getBalancedProductsForExtraModules($moduleNumber, $classifiedProducts) // Módulos extras: produtos restantes
        };
        
        Log::info("📋 Produtos BALANCEADOS por módulo", [
            'module_number' => $moduleNumber,
            'strategy' => $this->getModuleStrategy($moduleNumber),
            'products_count' => count($productsForModule),
            'avg_per_module' => $avgProductsPerModule,
            'product_ids' => array_column($productsForModule, 'product_id'),
            'classe_A_total' => count($classifiedProducts['A']),
            'classe_B_total' => count($classifiedProducts['B']),
            'classe_C_total' => count($classifiedProducts['C'])
        ]);
        
        return $productsForModule;
    }
    
    /**
     * NOVO: Produtos balanceados para módulos EXTRAS (5, 6, 7...)
     * Distribui produtos restantes que não couberam nos módulos principais
     */
    protected function getBalancedProductsForExtraModules(int $moduleNumber, array $classifiedProducts): array
    {
        // Coletar todos os produtos disponíveis
        $allProducts = array_merge(
            $classifiedProducts['A'],
            $classifiedProducts['B'], 
            $classifiedProducts['C']
        );
        
        if (empty($allProducts)) {
            return [];
        }
        
        $totalProducts = count($allProducts);
        
        // Produtos dos primeiros 4 módulos (aproximadamente)
        $productsInMainModules = min($totalProducts, 4 * 5); // ~20 produtos nos módulos principais
        $remainingProducts = max(0, $totalProducts - $productsInMainModules);
        
        if ($remainingProducts == 0) {
            return []; // Não há produtos restantes
        }
        
        // Distribuir produtos restantes entre módulos extras (5+)
        $extraModulesCount = $moduleNumber - 4; // Quantos módulos extras existem até este
        $avgProductsPerExtraModule = ceil($remainingProducts / max(1, $extraModulesCount));
        
        // Calcular range de produtos restantes para este módulo extra
        $extraModuleIndex = $moduleNumber - 5; // Índice baseado em 0 para módulos extras
        $startIndex = $productsInMainModules + ($extraModuleIndex * $avgProductsPerExtraModule);
        $endIndex = min($startIndex + $avgProductsPerExtraModule, $totalProducts);
        
        if ($startIndex >= $totalProducts) {
            // Não há produtos suficientes para este módulo
            return [];
        }
        
        // Extrair produtos para este módulo
        $productsForModule = array_slice($allProducts, $startIndex, $endIndex - $startIndex);
        
        Log::info("🔄 Módulo EXTRA com produtos restantes", [
            'module_number' => $moduleNumber,
            'total_products' => $totalProducts,
            'products_main_modules' => $productsInMainModules,
            'remaining_products' => $remainingProducts,
            'extra_modules_count' => $extraModulesCount,
            'avg_per_extra_module' => $avgProductsPerExtraModule,
            'start_index' => $startIndex,
            'end_index' => $endIndex,
            'products_count' => count($productsForModule),
            'product_ids' => array_column($productsForModule, 'product_id')
        ]);
        
        return $productsForModule;
    }
    
    /**
     * NOVO: Produtos balanceados para Módulo 1 (Nobre)
     */
    protected function getBalancedProductsForModule1(array $classifiedProducts): array
    {
        // Módulo 1: TODOS produtos A + 1 melhor produto B (se necessário para balanceamento)
        $products = $classifiedProducts['A'];
        
        if (count($products) < 5 && !empty($classifiedProducts['B'])) {
            // Adicionar melhor produto B para balancear
            $products[] = $classifiedProducts['B'][0];
        }
        
        Log::info("🥇 Módulo 1 - Nobre", [
            'classe_A_count' => count($classifiedProducts['A']),
            'classe_B_added' => count($products) - count($classifiedProducts['A']),
            'total_products' => count($products)
        ]);
        
        return $products;
    }
    
    /**
     * NOVO: Produtos balanceados para Módulo 2 (Premium)  
     */
    protected function getBalancedProductsForModule2(array $classifiedProducts): array
    {
        // Módulo 2: Primeira metade B (excluindo o que foi para Módulo 1)
        $startIndex = count($classifiedProducts['A']) >= 5 ? 0 : 1; // Se Módulo 1 pegou 1 B, começar do índice 1
        $firstHalf = array_slice($classifiedProducts['B'], $startIndex, 4);
        
        Log::info("🥈 Módulo 2 - Premium", [
            'start_index' => $startIndex,
            'products_count' => count($firstHalf),
            'product_ids' => array_column($firstHalf, 'product_id')
        ]);
        
        return $firstHalf;
    }
    
    /**
     * NOVO: Produtos balanceados para Módulo 3 (Intermediário)
     */
    protected function getBalancedProductsForModule3(array $classifiedProducts): array
    {
        // Módulo 3: Segunda metade B + primeiros produtos C para balancear
        $startIndex = count($classifiedProducts['A']) >= 5 ? 4 : 5; // Ajustar baseado no Módulo 2
        $secondHalfB = array_slice($classifiedProducts['B'], $startIndex);
        
        $products = $secondHalfB;
        $needed = 5 - count($products);
        
        if ($needed > 0 && !empty($classifiedProducts['C'])) {
            $firstC = array_slice($classifiedProducts['C'], 0, $needed);
            $products = array_merge($products, $firstC);
        }
        
        Log::info("🥉 Módulo 3 - Intermediário", [
            'classe_B_count' => count($secondHalfB),
            'classe_C_added' => count($products) - count($secondHalfB),
            'total_products' => count($products)
        ]);
        
        return $products;
    }
    
    /**
     * NOVO: Produtos balanceados para Módulo 4 (Básico)
     */
    protected function getBalancedProductsForModule4(array $classifiedProducts): array
    {
        // Módulo 4: Produtos C restantes (excluindo os que foram para Módulo 3)
        $usedInModule3 = max(0, 5 - (count($classifiedProducts['B']) - 4)); // Quantos C foram pro Módulo 3
        $remainingC = array_slice($classifiedProducts['C'], $usedInModule3);
        
        Log::info("📍 Módulo 4 - Básico", [
            'used_in_module3' => $usedInModule3,
            'remaining_count' => count($remainingC),
            'product_ids' => array_column($remainingC, 'product_id')
        ]);
        
        return $remainingC;
    }
    
    
    /**
     * NOVO: Verticaliza produtos dentro de uma section específica COM DISTRIBUIÇÃO EM CASCATA
     */
    protected function fillSectionVertically($section, array $products, array $structure): array
    {
        $productsPlaced = 0;
        $segmentsUsed = 0;
        $totalPlacements = 0;
        $productsDetails = [];
        $failedProducts = []; // Produtos que não couberam
        
        // Pegar prateleiras da section em ordem
        $shelves = $section->shelves()->orderBy('ordering')->get();
        
        Log::info("🏗️ Preenchendo section verticalmente COM CASCATA", [
            'section_id' => $section->id,
            'section_ordering' => $section->ordering,
            'shelves_count' => $shelves->count(),
            'products_to_place' => count($products)
        ]);
        
        // Para cada produto, colocar verticalmente nas prateleiras desta section
        foreach ($products as $product) {
            $facingTotal = $this->calculateTotalFacingByScore($product, $structure);
            
            if ($facingTotal <= 0) {
                continue;
            }
            
            Log::info("🔄 Verticalizando produto na section", [
                'product_id' => $product['product_id'],
                'abc_class' => $product['abc_class'],
                'facing_total' => $facingTotal,
                'section_ordering' => $section->ordering
            ]);
            
            // NOVA ABORDAGEM: Tentar colocar o produto de forma inteligente
            $placementResult = $this->tryPlaceProductInSection($section, $product, $facingTotal, $shelves);
            
            if ($placementResult['success']) {
                $productsPlaced++;
                $segmentsUsed += $placementResult['segments_used'];
                $totalPlacements += $placementResult['total_placements'];
                
                $productsDetails[] = [
                    'product_id' => $product['product_id'],
                    'abc_class' => $product['abc_class'],
                    'facing_total' => $placementResult['total_placements'],
                    'shelves_used' => $placementResult['segments_used']
                ];
                
                Log::info("✅ Produto colocado com sucesso na section", [
                    'product_id' => $product['product_id'],
                    'section_ordering' => $section->ordering,
                    'total_placements' => $placementResult['total_placements']
                ]);
            } else {
                $failedProducts[] = $product;
                Log::warning("⚠️ Produto não coube na section preferencial", [
                    'product_id' => $product['product_id'],
                    'section_ordering' => $section->ordering,
                    'reason' => $placementResult['reason'] ?? 'Espaço insuficiente'
                ]);
            }
        }
        
        Log::info("📊 Resultado do preenchimento da section", [
            'section_ordering' => $section->ordering,
            'products_placed' => $productsPlaced,
            'products_failed' => count($failedProducts),
            'total_placements' => $totalPlacements,
            'segments_used' => $segmentsUsed
        ]);
        
        return [
            'products_placed' => $productsPlaced,
            'segments_used' => $segmentsUsed,
            'total_placements' => $totalPlacements,
            'products_details' => $productsDetails,
            'failed_products' => $failedProducts // NOVO: Retornar produtos que falharam
        ];
    }
    
    /**
     * NOVO: Tenta colocar produto em uma section de forma inteligente
     */
    protected function tryPlaceProductInSection($section, array $product, int $facingTotal, $shelves): array
    {
        $productData = $product['product'] ?? [];
        $productWidth = floatval($productData['width'] ?? 25);
        $productId = $product['product_id'];
        
        $segmentsUsed = 0;
        $totalPlacements = 0;
        $successfulPlacements = [];
        
        // Distribuir facing entre as prateleiras da section
        $facingPerShelf = floor($facingTotal / $shelves->count());
        $remainder = $facingTotal % $shelves->count();
        
        foreach ($shelves as $index => $shelf) {
            $facingInThisShelf = $facingPerShelf;
            
            // Distribuir restante nas primeiras prateleiras
            if ($index < $remainder) {
                $facingInThisShelf++;
            }
            
            if ($facingInThisShelf > 0) {
                // Calcular largura disponível na prateleira
                $usedWidth = $this->calculateUsedWidthInShelf($shelf);
                $availableWidth = 125.0 - $usedWidth;
                
                // Usar facing realista baseado no espaço disponível
                $realisticFacing = $this->calculateOptimalFacing($product, $availableWidth);
                $actualFacing = min($facingInThisShelf, $realisticFacing);
                
                if ($actualFacing > 0) {
                    $success = $this->placeProductInShelfVertically($shelf, $product, $actualFacing);
                    
                    if ($success) {
                        $segmentsUsed++;
                        $totalPlacements += $actualFacing;
                        $successfulPlacements[] = [
                            'shelf_id' => $shelf->id,
                            'facing' => $actualFacing
                        ];
                        
                        Log::debug("✅ Produto colocado na prateleira", [
                            'product_id' => $productId,
                            'shelf_id' => $shelf->id,
                            'facing_requested' => $facingInThisShelf,
                            'facing_actual' => $actualFacing,
                            'available_width' => $availableWidth
                        ]);
                    } else {
                        Log::debug("⚠️ Falha ao colocar produto na prateleira", [
                            'product_id' => $productId,
                            'shelf_id' => $shelf->id,
                            'facing_attempted' => $actualFacing
                        ]);
                    }
                } else {
                    Log::debug("⚠️ Facing zero calculado para prateleira", [
                        'product_id' => $productId,
                        'shelf_id' => $shelf->id,
                        'available_width' => $availableWidth,
                        'product_width' => $productWidth
                    ]);
                }
            }
        }
        
        $success = $totalPlacements > 0;
        $reason = $success ? null : 'Nenhuma prateleira tinha espaço suficiente';
        
        return [
            'success' => $success,
            'segments_used' => $segmentsUsed,
            'total_placements' => $totalPlacements,
            'successful_placements' => $successfulPlacements,
            'reason' => $reason
        ];
    }
    
    /**
     * NOVO: Distribui produtos que falharam em outros módulos (CASCATA)
     */
    protected function tryCascadeDistribution($allSections, array $failedProducts, string $excludeSectionId, array $structure): array
    {
        $productsPlaced = 0;
        $segmentsUsed = 0;
        $totalPlacements = 0;
        $stillFailed = [];
        
        Log::info("🔄 INICIANDO DISTRIBUIÇÃO EM CASCATA", [
            'failed_products_count' => count($failedProducts),
            'exclude_section_id' => $excludeSectionId,
            'available_sections' => $allSections->count() - 1
        ]);
        
        // Para cada produto que falhou, tentar em todos os outros módulos
        foreach ($failedProducts as $product) {
            $productPlaced = false;
            $productId = $product['product_id'];
            
            // Tentar em todas as sections exceto a que já falhou
            foreach ($allSections as $section) {
                if ($section->id === $excludeSectionId) {
                    continue; // Pular a section que já falhou
                }
                
                Log::debug("🔍 Tentando produto em módulo alternativo", [
                    'product_id' => $productId,
                    'target_module' => $section->ordering + 1,
                    'section_id' => $section->id
                ]);
                
                // Calcular facing conservador para cascata
                $conservativeFacing = $this->calculateConservativeFacing($product);
                
                // Tentar colocar o produto nesta section
                $shelves = $section->shelves()->orderBy('ordering')->get();
                $placementResult = $this->tryPlaceProductInSection($section, $product, $conservativeFacing, $shelves);
                
                if ($placementResult['success']) {
                    $productsPlaced++;
                    $segmentsUsed += $placementResult['segments_used'];
                    $totalPlacements += $placementResult['total_placements'];
                    $productPlaced = true;
                    
                    Log::info("✅ CASCATA bem-sucedida", [
                        'product_id' => $productId,
                        'abc_class' => $product['abc_class'],
                        'original_module' => 'failed',
                        'cascade_module' => $section->ordering + 1,
                        'placements' => $placementResult['total_placements']
                    ]);
                    
                    break; // Produto colocado, não tentar em outros módulos
                }
            }
            
            // Se não conseguiu colocar em nenhum módulo, adicionar à lista de falhados
            if (!$productPlaced) {
                $stillFailed[] = $product;
                Log::warning("❌ Produto falhou em TODOS os módulos", [
                    'product_id' => $productId,
                    'abc_class' => $product['abc_class'],
                    'product_width' => $product['product']['width'] ?? 'N/A'
                ]);
            }
        }
        
        Log::info("🎯 CASCATA concluída", [
            'original_failed' => count($failedProducts),
            'cascade_placed' => $productsPlaced,
            'still_failed' => count($stillFailed),
            'cascade_success_rate' => count($failedProducts) > 0 ? round(($productsPlaced / count($failedProducts)) * 100, 1) . '%' : '0%'
        ]);
        
        return [
            'products_placed' => $productsPlaced,
            'segments_used' => $segmentsUsed,
            'total_placements' => $totalPlacements,
            'still_failed' => $stillFailed
        ];
    }
    
    /**
     * NOVO: Calcula facing conservador para distribuição em cascata
     */
    protected function calculateConservativeFacing(array $product): int
    {
        $abcClass = $product['abc_class'] ?? 'C';
        
        // Facing muito conservador para cascata (garantir que cabe)
        $conservativeFacing = match($abcClass) {
            'A' => 2, // Classe A: apenas 2 facing na cascata
            'B' => 1, // Classe B: apenas 1 facing na cascata
            'C' => 1, // Classe C: apenas 1 facing na cascata
            default => 1
        };
        
        Log::debug("🔄 Facing conservador para cascata", [
            'product_id' => $product['product_id'],
            'abc_class' => $abcClass,
            'conservative_facing' => $conservativeFacing
        ]);
        
        return $conservativeFacing;
    }
    
    /**
     * NOVO: Coloca produto em prateleira específica com facing definido + VALIDAÇÃO DE LARGURA
     */
    protected function placeProductInShelfVertically($shelf, array $product, int $facing): bool
    {
        // 1. CALCULAR LARGURA NECESSÁRIA PARA O PRODUTO
        $productData = $product['product'] ?? [];
        $productWidth = floatval($productData['width'] ?? 25);
        $requiredWidth = $productWidth * $facing;
        
        // 2. VERIFICAR LARGURA DISPONÍVEL NA PRATELEIRA
        $shelfWidth = floatval($shelf->shelf_width ?? 125); // Largura padrão 125cm se não definida
        $usedWidth = $this->calculateUsedWidthInShelf($shelf);
        $availableWidth = $shelfWidth - $usedWidth;
        
        Log::info("🔍 Verificando capacidade da prateleira", [
            'shelf_id' => $shelf->id,
            'shelf_ordering' => $shelf->ordering,
            'product_id' => $product['product_id'],
            'facing' => $facing,
            'product_width_cm' => $productWidth,
            'required_width_cm' => $requiredWidth,
            'shelf_width_cm' => $shelfWidth,
            'used_width_cm' => $usedWidth,
            'available_width_cm' => $availableWidth,
            'fits' => $requiredWidth <= $availableWidth
        ]);
        
        // 3. FACING ADAPTATIVO: Reduzir facing se não cabe
        $adaptedFacing = $facing;
        $adaptedRequiredWidth = $requiredWidth;
        
        // Tentar reduzir facing até caber ou chegar a 1
        while ($adaptedFacing > 0 && $adaptedRequiredWidth > $availableWidth) {
            $adaptedFacing--;
            $adaptedRequiredWidth = $productWidth * $adaptedFacing;
        }
        
        Log::info("🔄 FACING ADAPTATIVO aplicado", [
            'shelf_id' => $shelf->id,
            'product_id' => $product['product_id'],
            'facing_original' => $facing,
            'facing_adapted' => $adaptedFacing,
            'width_original' => $requiredWidth,
            'width_adapted' => $adaptedRequiredWidth,
            'available_width_cm' => $availableWidth,
            'optimization' => $facing > $adaptedFacing ? 'REDUZIDO' : 'MANTIDO'
        ]);
        
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
                $productWidth = floatval($product->width ?? 25);
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
        
        Log::debug("📏 Largura CORRIGIDA calculada na prateleira", [
            'shelf_id' => $shelf->id,
            'total_segments' => $segments->count(),
            'segments_with_products' => count($productsFound),
            'used_width_cm' => $usedWidth,
            'products_details' => $productsFound
        ]);
        
        return $usedWidth;
    }
    
    /**
     * NOVO: Cria segmento vertical COM VALIDAÇÃO de largura
     */
    protected function createVerticalSegmentWithValidation($shelf, array $product, int $facing, float $availableWidth): bool
    {
        $productData = $product['product'] ?? [];
        $productWidth = floatval($productData['width'] ?? 25);
        $requiredWidth = $productWidth * $facing;
        
        // FACING ADAPTATIVO também para criação de segmento
        $adaptedFacing = $facing;
        $adaptedRequiredWidth = $requiredWidth;
        
        // Tentar reduzir facing até caber ou chegar a 1
        while ($adaptedFacing > 0 && $adaptedRequiredWidth > $availableWidth) {
            $adaptedFacing--;
            $adaptedRequiredWidth = $productWidth * $adaptedFacing;
        }
        
        Log::info("🔄 FACING ADAPTATIVO no novo segmento", [
            'shelf_id' => $shelf->id,
            'product_id' => $product['product_id'],
            'facing_original' => $facing,
            'facing_adapted' => $adaptedFacing,
            'width_adapted' => $adaptedRequiredWidth,
            'available_width_cm' => $availableWidth
        ]);
        
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
     * NOVO: Cria novo segmento para verticalização quando necessário (versão legacy)
     */
    protected function createVerticalSegment($shelf, array $product, int $facing): bool
    {
        // Redirecionar para versão com validação
        $shelfWidth = floatval($shelf->shelf_width ?? 125);
        $usedWidth = $this->calculateUsedWidthInShelf($shelf);
        $availableWidth = $shelfWidth - $usedWidth;
        
        return $this->createVerticalSegmentWithValidation($shelf, $product, $facing, $availableWidth);
    }
    
    /**
     * NOVO: Retorna estratégia do módulo para logs (versão balanceada)
     * SUPORTA MÓDULOS EXTRAS (5, 6, 7...)
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
        
        Log::info("🎯 INICIANDO PREENCHIMENTO OPORTUNÍSTICO", [
            'section_id' => $section->id,
            'section_ordering' => $section->ordering,
            'shelves_count' => $shelves->count(),
            'products_available' => count($products)
        ]);
        
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
        
        Log::info("🎉 PREENCHIMENTO OPORTUNÍSTICO CONCLUÍDO", [
            'section_id' => $section->id,
            'segments_added' => $segmentsUsed,
            'placements_added' => $totalPlacements
        ]);
        
        return [
            'segments_used' => $segmentsUsed,
            'total_placements' => $totalPlacements
        ];
    }
    
    /**
     * NOVO: Expande facing de produtos já colocados se há espaço
     */
    protected function expandExistingFacing($shelf, array $products): array
    {
        $segmentsUsed = 0;
        $totalPlacements = 0;
        
        $usedWidth = $this->calculateUsedWidthInShelf($shelf);
        $availableWidth = 125.0 - $usedWidth;
        
        if ($availableWidth < 15.0) { // Menos que um produto pequeno
            return ['segments_used' => 0, 'total_placements' => 0];
        }
        
        // Pegar produtos que já estão na prateleira
        $segments = $shelf->segments()->with('layer.product')->get();
        
        foreach ($segments as $segment) {
            if ($segment->layer && $segment->layer->product_id && $availableWidth > 0) {
                $product = $segment->layer->product;
                if ($product) {
                    $productWidth = floatval($product->width ?? 25);
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
                            
                            Log::info("📈 FACING EXPANDIDO", [
                                'shelf_id' => $shelf->id,
                                'product_id' => $product->id,
                                'facing_anterior' => $currentFacing,
                                'facing_novo' => $newFacing,
                                'facings_adicionados' => $additionalFacings,
                                'width_adicional' => $additionalWidth
                            ]);
                            
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
     * NOVO: Preenche espaços vazios da prateleira com novos produtos
     */
    protected function fillEmptyShelfSpace($shelf, array $products): array
    {
        $segmentsUsed = 0;
        $totalPlacements = 0;
        
        $usedWidth = $this->calculateUsedWidthInShelf($shelf);
        $availableWidth = 125.0 - $usedWidth;
        
        if ($availableWidth < 15.0) { // Menos que um produto pequeno
            return ['segments_used' => 0, 'total_placements' => 0];
        }
        
        // Tentar colocar produtos que ainda não estão na prateleira
        foreach ($products as $product) {
            if ($availableWidth > 0) {
                $productData = $product['product'] ?? [];
                $productWidth = floatval($productData['width'] ?? 25);
                
                // Calcular quantos facings cabem
                $possibleFacings = floor($availableWidth / $productWidth);
                
                if ($possibleFacings > 0) {
                    $success = $this->placeProductInShelfVertically($shelf, $product, $possibleFacings);
                    
                    if ($success) {
                        $segmentsUsed++;
                        $totalPlacements += $possibleFacings;
                        $usedSpace = $possibleFacings * $productWidth;
                        $availableWidth -= $usedSpace;
                        
                        Log::info("🆕 PRODUTO ADICIONADO OPORTUNÍSTICAMENTE", [
                            'shelf_id' => $shelf->id,
                            'shelf_ordering' => $shelf->ordering,
                            'product_id' => $product['product_id'],
                            'facings_added' => $possibleFacings,
                            'width_used' => $usedSpace,
                            'remaining_width' => $availableWidth
                        ]);
                        
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

    /**
     * NOVO: Aplica filtros dinâmicos vindos do modal AutoGenerateModal.vue
     */
    protected function applyDynamicFilters($productsQuery, array $filters, $gondola): void
    {
        Log::info("🎛️ Aplicando filtros dinâmicos do modal", [
            'filters_received' => $filters,
            'gondola_id' => $gondola->id
        ]);
        
        // FILTRO 1: Produtos com dimensões (dimension)
        if ($filters['dimension'] ?? true) {
            $productsQuery->whereHas('dimensions');
            Log::debug("✅ Filtro aplicado: apenas produtos com dimensões");
        }
        
        // FILTRO 2: Produtos não utilizados na gôndola (unusedOnly)  
        if ($filters['unusedOnly'] ?? true) {
            $productIdsInGondola = $this->getProductIdsInGondola($gondola);
            if (!empty($productIdsInGondola)) {
                $productsQuery->whereNotIn('id', $productIdsInGondola);
                Log::debug("✅ Filtro aplicado: produtos não utilizados", [
                    'produtos_na_gondola' => count($productIdsInGondola)
                ]);
            }
        }
        
        // FILTRO 3: Produtos com histórico de vendas (sales)
        if ($filters['sales'] ?? true) {
            // Aqui você pode implementar a lógica de vendas quando tiver os dados
            // Exemplo: $productsQuery->whereHas('sales');
            Log::debug("⏳ Filtro de vendas: aguardando implementação de dados de venda");
        }
        
        // FILTRO 4: Produtos penduráveis (hangable)
        if ($filters['hangable'] ?? false) {
            // Implementar quando tiver campo para produtos penduráveis
            // Exemplo: $productsQuery->where('hangable', true);  
            Log::debug("⏳ Filtro penduráveis: aguardando campo na base de dados");
        }
        
        // FILTRO 5: Produtos empilháveis (stackable)
        if ($filters['stackable'] ?? false) {
            // Implementar quando tiver campo para produtos empilháveis
            // Exemplo: $productsQuery->where('stackable', true);
            Log::debug("⏳ Filtro empilháveis: aguardando campo na base de dados");
        }
        
        Log::info("🎯 Filtros dinâmicos aplicados com sucesso", [
            'dimension' => $filters['dimension'] ?? true,
            'unusedOnly' => $filters['unusedOnly'] ?? true, 
            'sales' => $filters['sales'] ?? true,
            'hangable' => $filters['hangable'] ?? false,
            'stackable' => $filters['stackable'] ?? false,
            'limit' => $filters['limit'] ?? 20
        ]);
    }

}
