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
            $productIds = $this->getProductsByPlanogramCategory($gondola);
            
            if (empty($productIds)) {
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

            Log::info('AutoPlanogram: Calculando scores para gôndola', [
                'gondola_id' => $gondola->id,
                'gondola_name' => $gondola->name,
                'produtos_count' => count($productIds)
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
                $distributionResult = $this->distributeProductsInGondola($gondola, $scores);
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
     */
    protected function getProductsByPlanogramCategory(Gondola $gondola): array
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

        // 4. Aplicar filtros adicionais (iguais à sidebar)
        // Produtos com dimensões (igual sidebar: dimension: true)
        $productsQuery->whereHas('dimensions');
        
        // Produtos não usados na gôndola atual (igual sidebar: usageStatus: 'unused')
        $productIdsInGondola = $this->getProductIdsInGondola($gondola);
        if (!empty($productIdsInGondola)) {
            $productsQuery->whereNotIn('id', $productIdsInGondola);
        }
        
        // Produtos com EAN (sempre obrigatório)
        $productsQuery->whereNotNull('ean');
        
        // Aplicar limite igual à sidebar (LIST_LIMIT = 20)
        $products = $productsQuery->limit(20)->get();

        Log::info("Produtos encontrados para geração automática", [
            'gondola_id' => $gondola->id,
            'planogram_id' => $planogram->id,
            'mercadologico_level_used' => $levelUsed,
            'products_found' => $products->count()
        ]);

        return $products->pluck('id')->toArray();
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
    protected function distributeProductsInGondola(Gondola $gondola, array $scores): array
    {
        Log::info("Iniciando distribuição automática", [
            'gondola_id' => $gondola->id,
            'total_scores' => count($scores)
        ]);

        // 1. Ordenar produtos por score (maior para menor)
        usort($scores, function($a, $b) {
            return $b['final_score'] <=> $a['final_score'];
        });

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
     * Distribui produtos com VERTICALIZAÇÃO OBRIGATÓRIA + OCUPAÇÃO HORIZONTAL COMPLETA
     */
    protected function placeProductsSequentially(Gondola $gondola, array $classifiedProducts, array $structure): array
    {
        // NOVA LÓGICA: VERTICALIZAÇÃO INTELIGENTE POR FACING [[memory:8393313]]
        // Score calcula facing total → Distribui verticalmente entre prateleiras
        // Exemplo: Produto A com 6 faces → Prat1(2) + Prat2(2) + Prat3(2)
        
        // 1. COMBINAR TODOS OS PRODUTOS ORDENADOS POR PRIORIDADE
        $allProducts = [];
        foreach (['A', 'B', 'C'] as $class) {
            foreach ($classifiedProducts[$class] as $product) {
            $allProducts[] = $product;
        }
        }

        Log::info("🎯 NOVA LÓGICA: Verticalização Inteligente por Facing", [
            'total_products' => count($allProducts),
            'produtos_classe_A' => count($classifiedProducts['A']),
            'produtos_classe_B' => count($classifiedProducts['B']),
            'produtos_classe_C' => count($classifiedProducts['C'])
        ]);

        // 2. CALCULAR FACING TOTAL PARA CADA PRODUTO BASEADO NO SCORE
        $productsWithFacing = [];
        foreach ($allProducts as $product) {
            $facingTotal = $this->calculateTotalFacingByScore($product, $structure);
            $productsWithFacing[] = array_merge($product, [
                'facing_total' => $facingTotal
            ]);
            
            Log::info("📊 Facing calculado", [
                'product_id' => $product['product_id'],
                'abc_class' => $product['abc_class'],
                'score' => round($product['final_score'] ?? 0, 3),
                'facing_total' => $facingTotal
            ]);
        }

        // 3. PEGAR TODAS AS PRATELEIRAS EM ORDEM
        $allShelves = $this->getAllShelvesInOrder($gondola);
        
        Log::info("🏗️ Prateleiras para distribuição vertical", [
            'total_shelves' => count($allShelves),
            'shelf_ids' => array_map(function($shelf) { return $shelf->id; }, $allShelves)
        ]);

        // 4. DISTRIBUIR CADA PRODUTO VERTICALMENTE BASEADO NO FACING TOTAL
        $productsPlacedMap = []; // Mapear quantas vezes cada produto foi colocado
        $currentProductIndex = 0; // Índice global para percorrer produtos
        $placedProducts = 0; // Total de produtos colocados
        $usedSegments = 0; // Total de segmentos utilizados
        $totalProductPlacements = 0; // Total de colocações (facing)
        
        foreach ($allShelves as $shelf) {
            Log::info("📖 Processando prateleira", [
                'shelf_id' => $shelf->id,
                'shelf_ordering' => $shelf->ordering,
                'segments_count' => $shelf->segments->count(),
                'shelf_width' => $shelf->width ?? 'não definida',
                'current_product_index' => $currentProductIndex
            ]);
            
            // Distribuir produtos nesta prateleira com ocupação horizontal completa
            $shelfResults = $this->distributeProductsInShelf($shelf, $allProducts, $currentProductIndex);
            
            $placedProducts += $shelfResults['products_placed'];
            $usedSegments += $shelfResults['segments_used'];
            $totalProductPlacements += $shelfResults['total_placements'];
            $currentProductIndex = $shelfResults['next_product_index']; // Atualizar índice global
            
            Log::info("📊 Resultados da prateleira", [
                'shelf_id' => $shelf->id,
                'products_placed' => $shelfResults['products_placed'],
                'segments_used' => $shelfResults['segments_used'],
                'segments_created' => $shelfResults['segments_created'],
                'width_used' => $shelfResults['width_used'],
                'width_available' => $shelfResults['width_available']
            ]);
            
            // Mapear produtos colocados para controle de verticalização
            foreach ($shelfResults['products_map'] as $productId => $placements) {
                if (!isset($productsPlacedMap[$productId])) {
                    $productsPlacedMap[$productId] = 0;
                }
                $productsPlacedMap[$productId] += $placements;
            }
            
            // Resetar índice se chegou ao fim dos produtos (para verticalização)
            if ($currentProductIndex >= count($allProducts)) {
                $currentProductIndex = 0;
                Log::info("🔄 Resetando índice para verticalização", [
                    'total_products' => count($allProducts),
                    'back_to_index' => $currentProductIndex
                ]);
            }
        }
        
        // Análise de verticalização
        $uniqueProductsPlaced = count($productsPlacedMap);
        $avgVerticalPlacements = $totalProductPlacements / max($uniqueProductsPlaced, 1);
        
        Log::info("✅ DISTRIBUIÇÃO VERTICAL COMPLETA", [
            'unique_products_placed' => $uniqueProductsPlaced,
            'total_product_placements' => $totalProductPlacements,
            'avg_vertical_placements' => round($avgVerticalPlacements, 2),
            'segments_used' => $usedSegments,
            'space_utilization' => round(($usedSegments / max($structure['total_segments'], 1)) * 100, 1) . '%'
        ]);

        // Log produtos que não foram colocados (se houver)
        $productsNotPlaced = array_filter($allProducts, function($product) use ($productsPlacedMap) {
            return !isset($productsPlacedMap[$product['product_id']]);
        });
        
        if (!empty($productsNotPlaced)) {
            Log::warning("Produtos não colocados", [
                'count' => count($productsNotPlaced),
                'product_ids' => array_column($productsNotPlaced, 'product_id')
            ]);
        }

        $totalProductPlacements = 0;
        $segmentsUsed = 0;
        $productsPlacedMap = [];

        foreach ($productsWithFacing as $product) {
            $productId = $product['product_id'];
            $facingTotal = $product['facing_total'];
            
            Log::info("🔄 Iniciando distribuição vertical do produto", [
                'product_id' => $productId,
                'facing_total' => $facingTotal,
                'abc_class' => $product['abc_class']
            ]);
            
            // Distribuir o facing total entre as prateleiras disponíveis
            $verticalDistribution = $this->distributeProductVertically($product, $allShelves, $facingTotal);
            
            foreach ($verticalDistribution as $shelfId => $facingInShelf) {
                if ($facingInShelf > 0) {
                    $shelf = collect($allShelves)->firstWhere('id', $shelfId);
                    if ($shelf) {
                        $success = $this->placeProductInShelfWithVerticalFacing($shelf, $product, $facingInShelf);
                if ($success) {
                            $segmentsUsed++;
                            $totalProductPlacements += $facingInShelf;
                            
                            if (!isset($productsPlacedMap[$productId])) {
                                $productsPlacedMap[$productId] = 0;
                            }
                            $productsPlacedMap[$productId] += $facingInShelf;
                        }
                    }
                }
            }
            
            Log::info("✅ Produto distribuído verticalmente", [
                'product_id' => $productId,
                'facing_total_placed' => $productsPlacedMap[$productId] ?? 0,
                'shelves_used' => count(array_filter($verticalDistribution))
            ]);
        }

        // 5. ANÁLISE FINAL
        $uniqueProductsPlaced = count($productsPlacedMap);
        $averageFacingPerProduct = $uniqueProductsPlaced > 0 ? $totalProductPlacements / $uniqueProductsPlaced : 0;

        Log::info("🎉 DISTRIBUIÇÃO CONCLUÍDA - VERTICALIZAÇÃO INTELIGENTE", [
            'unique_products_placed' => $uniqueProductsPlaced,
            'total_product_placements' => $totalProductPlacements,
            'avg_facing_per_product' => round($averageFacingPerProduct, 1),
            'segments_used' => $segmentsUsed,
            'products_NOT_placed' => count($allProducts) - $uniqueProductsPlaced
        ]);

        return [
            'products_placed' => $uniqueProductsPlaced,
            'total_placements' => $totalProductPlacements,
            'segments_used' => $segmentsUsed
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
        foreach ($products as $product) {
            $productData = $product['product'] ?? [];
            $totalWidth += floatval($productData['width'] ?? 25);
        }
        
        return $totalWidth / count($products);
    }
    
    /**
     * NOVA FUNÇÃO: Calcula facing otimizado baseado no score e espaço disponível [[memory:8393313]]
     */
    protected function calculateOptimalFacing($product, float $availableWidth): int
    {
        $productData = $product['product'] ?? [];
        $productWidth = floatval($productData['width'] ?? 25);
        $abcClass = $product['abc_class'] ?? 'C';
        $score = floatval($product['score'] ?? 0);
        
        // Facing base baseado na classe ABC
        $baseFacing = $this->getFacingByClass($abcClass);
        
        // Ajustar baseado no score (produtos com score alto = mais facing)
        $scoreMultiplier = 1.0;
        if ($score > 0.5) {
            $scoreMultiplier = 1.5; // Score alto = 50% mais facing
        } elseif ($score > 0.3) {
            $scoreMultiplier = 1.2; // Score médio = 20% mais facing
        }
        
        $optimalFacing = ceil($baseFacing * $scoreMultiplier);
        
        // Verificar se cabe no espaço disponível
        $maxPossibleFacing = floor($availableWidth / $productWidth);
        $finalFacing = min($optimalFacing, $maxPossibleFacing, 8); // Máximo 8 facing
        
        return max(1, $finalFacing); // Mínimo 1 facing
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
     * NOVA FUNÇÃO: Distribui o facing total verticalmente entre prateleiras [[memory:8393313]]
     * Exemplo: 6 faces → Prat1(2) + Prat2(2) + Prat3(2)
     */
    protected function distributeProductVertically($product, $allShelves, int $facingTotal): array
    {
        $distribution = [];
        $totalShelves = count($allShelves);
        
        if ($totalShelves == 0 || $facingTotal <= 0) {
            return $distribution;
        }
        
        // Distribuir facing igualmente entre as prateleiras disponíveis
        $facingPerShelf = floor($facingTotal / $totalShelves);
        $remainder = $facingTotal % $totalShelves;
        
        foreach ($allShelves as $index => $shelf) {
            $facingInThisShelf = $facingPerShelf;
            
            // Distribuir o restante nas primeiras prateleiras (melhor posicionamento)
            if ($index < $remainder) {
                $facingInThisShelf++;
            }
            
            if ($facingInThisShelf > 0) {
                $distribution[$shelf->id] = $facingInThisShelf;
            }
        }
        
        Log::info("📐 Distribuição vertical calculada", [
            'product_id' => $product['product_id'],
            'facing_total' => $facingTotal,
            'shelves_count' => $totalShelves,
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
}
