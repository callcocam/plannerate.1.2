<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Services;

use Illuminate\Support\Facades\Log;
use Callcocam\Plannerate\Services\FacingCalculatorService;
use Callcocam\Plannerate\Services\StepLogger;
use Callcocam\Plannerate\Models\Gondola;

/**
 * Serviço responsável pela colocação de produtos no planograma
 * Centraliza toda a lógica de distribuição, verticalização e cascata
 */
class ProductPlacementService
{
    protected FacingCalculatorService $facingCalculator;

    public function __construct(FacingCalculatorService $facingCalculator)
    {
        $this->facingCalculator = $facingCalculator;
    }

    /**
     * Distribui produtos sequencialmente aproveitando todo o espaço
     * 🎯 NOVO: Aceita produtos em ORDEM CATEGÓRICA (açúcar→arroz→feijão→sal)
     * Algoritmo Section-by-Section com verticalização por módulo RESPEITANDO categoria
     */
    public function placeProductsSequentially($gondola, array $products, array $structure): array
    {
        $productsPlaced = 0;
        $segmentsUsed = 0;
        $totalProductPlacements = 0;
        $moduleUsage = [];
        
        // PASSO 5: Iniciar algoritmo de distribuição section-by-section COM ORDEM CATEGÓRICA
        StepLogger::logCustomStep('ALGORITMO SECTION-BY-SECTION INICIADO COM CATEGORIA', [
            '🎯 ESTRATÉGIA' => 'Verticalização por módulo RESPEITANDO adjacência de categoria',
            '📊 PRODUTOS_SEQUENCIAIS' => count($products),
            '🏗️ ESTRUTURA' => [
                'TOTAL_MÓDULOS' => $structure['total_sections'],
                'TOTAL_SEGMENTOS' => $structure['total_segments']
            ],
            '📦 DISTRIBUIÇÃO' => 'Sequencial por categoria (açúcar→arroz→feijão→sal)'
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

        // 2. 🎯 NOVA LÓGICA: Dividir produtos sequenciais entre módulos por categoria
        $allFailedProducts = []; // Produtos que falharam em todos os módulos
        $totalModules = count($allSections);
        $totalProducts = count($products);
        $productsPerModule = ceil($totalProducts / max($totalModules, 1));
        $productIndex = 0;
        
        Log::info("📦 Distribuição sequencial por categoria iniciada", [
            'total_products' => $totalProducts,
            'total_modules' => $totalModules,
            'products_per_module' => $productsPerModule
        ]);
        
        foreach ($allSections as $section) {
            $moduleNumber = $section->ordering + 1; // Módulo 1, 2, 3, 4...
            
            // 3. 🎯 DETERMINAR PRODUTOS PARA ESTE MÓDULO SEQUENCIALMENTE
            $targetProducts = array_slice($products, $productIndex, $productsPerModule);
            $productIndex += $productsPerModule;
            
            if (empty($targetProducts)) {
                Log::info("⚪ Módulo {$moduleNumber} sem produtos para processar");
                continue;
            }
            
            // Identificar categoria dominante neste módulo
            $firstProduct = $targetProducts[0];
            $categoryName = strtoupper(explode(' ', $firstProduct['product']['name'] ?? 'OUTROS')[0]);
            
            Log::info("📦 Módulo {$moduleNumber} processará CATEGORIA: {$categoryName}", [
                'products_count' => count($targetProducts),
                'products_range' => "Produto " . ($productIndex - count($targetProducts) + 1) . " até {$productIndex}"
            ]);
            
            // PASSO 6: Iniciar processamento do módulo
            StepLogger::startModule($moduleNumber, "CATEGORIA: {$categoryName}", $targetProducts);
            
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
                
                StepLogger::logCustomStep('CASCATA EXECUTADA', [
                    '🔄 MÓDULO_ORIGEM' => $moduleNumber,
                    '📊 RESULTADOS' => [
                        'PRODUTOS_FALHARAM' => count($moduleResults['failed_products']),
                        'CASCATA_COLOCADOS' => $cascadeResults['products_placed'],
                        'AINDA_FALHARAM' => count($cascadeResults['still_failed'])
                    ]
                ]);
            }
            
            // PREENCHIMENTO OPORTUNÍSTICO - maximizar uso do espaço
            $opportunisticResults = $this->fillOpportunisticSpace($section, $targetProducts);
            $moduleResults['segments_used'] += $opportunisticResults['segments_used'];
            $moduleResults['total_placements'] += $opportunisticResults['total_placements'];
            
            // PASSO 7: Resultado do módulo processado
            StepLogger::logModuleResult($moduleNumber, [
                'products_placed' => $moduleResults['products_placed'],
                'segments_used' => $moduleResults['segments_used'],
                'total_placements' => $moduleResults['total_placements'],
                'failed_products' => $moduleResults['failed_products'] ?? [],
                'opportunistic_added' => $opportunisticResults['total_placements'],
                'success_rate' => count($targetProducts) > 0 ? 
                    round(($moduleResults['products_placed'] / count($targetProducts)) * 100, 1) : 0
            ]);
        }
        
        // 🔧 CORREÇÃO: Contar segmentos reais (incluindo criados dinamicamente)
        $totalActualSegments = $this->countTotalSegmentsInGondola($gondola);
        
        Log::info("🎉 DISTRIBUIÇÃO POR ADJACÊNCIA DE CATEGORIA CONCLUÍDA", [
            'products_placed' => $productsPlaced,
            'total_placements' => $totalProductPlacements,
            'segments_used' => $segmentsUsed,
            'segments_total_actual' => $totalActualSegments,
            'modules_used' => count($moduleUsage),
            'space_utilization' => round(($segmentsUsed / max($totalActualSegments, 1)) * 100, 1) . '%',
            'products_still_failed' => count($allFailedProducts),
            'placement_success_rate' => round(($productsPlaced / max(count($products), 1)) * 100, 1) . '%'  // 🔧 CORRIGIDO: usar $products em vez de $classifiedProducts
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
     * 🔧 CORREÇÃO: Conta segmentos reais na gôndola (incluindo criados dinamicamente)
     */
    protected function countTotalSegmentsInGondola(Gondola $gondola): int
    {
        $totalSegments = 0;
        
        foreach ($gondola->sections as $section) {
            foreach ($section->shelves as $shelf) {
                $totalSegments += $shelf->segments()->count();
            }
        }
        
        return $totalSegments;
    }

    /**
     * Verticaliza produtos dentro de uma section específica com distribuição em cascata
     */
    public function fillSectionVertically($section, array $products, array $structure): array
    {
        $productsPlaced = 0;
        $segmentsUsed = 0;
        $totalPlacements = 0;
        $productsDetails = [];
        $failedProducts = []; // Produtos que não couberam
        
        // Pegar prateleiras da section em ordem
        $shelves = $section->shelves()->orderBy('ordering')->get();
        
        // Preenchendo section verticalmente
        
        // Para cada produto, colocar verticalmente nas prateleiras desta section
        foreach ($products as $product) {
            // Calcular facing total usando o service
            $facingTotal = $product['intelligent_facing'] ?? 1;
            
            if ($facingTotal <= 0) {
                continue;
            }
            
            // Verticalizando produto na section
            
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
                
                StepLogger::logCustomStep('PRODUTO COLOCADO NA SECTION', [
                    '📦 PRODUTO' => [
                        'ID' => $product['product_id'],
                        'NOME' => $product['product']['name'] ?? 'N/A',
                        'CLASSE_ABC' => $product['abc_class'] ?? 'N/A'
                    ],
                    '🎯 LOCALIZAÇÃO' => [
                        'MÓDULO' => $section->ordering + 1,
                        'TOTAL_FACINGS' => $placementResult['total_placements']
                    ]
                ]);
            } else {
                $failedProducts[] = $product;
                StepLogger::logProductFailure($product, 
                    $placementResult['reason'] ?? 'Espaço insuficiente na section preferencial', 
                    ['módulo_tentado' => $section->ordering + 1]);
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
     * 🚫 MÉTODO OBSOLETO - Não usado mais no novo fluxo por categoria
     * Determina quais produtos devem ser colocados em cada módulo com balanceamento
     */
    protected function getProductsForModule_OBSOLETO(int $moduleNumber, array $classifiedProducts): array
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
     * Produtos balanceados para módulos extras (5, 6, 7...)
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
        
        return $productsForModule;
    }
    
    /**
     * Produtos balanceados para Módulo 1 (Nobre)
     */
    protected function getBalancedProductsForModule1(array $classifiedProducts): array
    {
        // Módulo 1: Produtos classe A com balanceamento
        $products = $classifiedProducts['A'];
        
        if (count($products) < 5 && !empty($classifiedProducts['B'])) {
            // Adicionar melhor produto B para balancear
            $products[] = $classifiedProducts['B'][0];
        }
        
        // Módulo 1 - Nobre configurado
        
        return $products;
    }
    
    /**
     * Produtos balanceados para Módulo 2 (Premium)  
     */
    protected function getBalancedProductsForModule2(array $classifiedProducts): array
    {
        // Módulo 2: Primeira metade B (excluindo o que foi para Módulo 1)
        $startIndex = count($classifiedProducts['A']) >= 5 ? 0 : 1; // Se Módulo 1 pegou 1 B, começar do índice 1
        $firstHalf = array_slice($classifiedProducts['B'], $startIndex, 4);
        
        // Módulo 2 - Premium configurado
        
        return $firstHalf;
    }
    
    /**
     * Produtos balanceados para Módulo 3 (Intermediário)
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
        
        // Módulo 3 - Intermediário configurado
        
        return $products;
    }
    
    /**
     * Produtos balanceados para Módulo 4 (Básico)
     */
    protected function getBalancedProductsForModule4(array $classifiedProducts): array
    {
        // Módulo 4: Produtos C restantes (excluindo os que foram para Módulo 3)
        $usedInModule3 = max(0, 5 - (count($classifiedProducts['B']) - 4)); // Quantos C foram pro Módulo 3
        $remainingC = array_slice($classifiedProducts['C'], $usedInModule3);
        
        // Módulo 4 - Básico configurado
        
        return $remainingC;
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
     * Tenta colocar produto em uma section de forma inteligente
     */
    public function tryPlaceProductInSection($section, array $product, int $facingTotal, $shelves): array
    {
        $productData = $product['product'] ?? [];
        if (!isset($productData['width']) || $productData['width'] <= 0) {
            Log::warning("❌ Produto sem largura válida - não pode ser colocado", [
                'product_id' => $product['product_id'] ?? 'unknown',
                'width' => $productData['width'] ?? 'null'
            ]);
            return ['success' => false, 'reason' => 'Produto sem largura válida'];
        }

        return $this->placeProductWithConsistentPattern($product, $facingTotal, $shelves);
    }

    // 🚫 REMOVIDO: findOptimalPattern() - Método sabotador que reduzia facing arbitrariamente
    // O facing agora é respeitado conforme calculado pelo FacingCalculatorService

    /**
     * NOVO: Tenta colocar um produto numa section usando o algoritmo de padrão consistente e arredondamento.
     */
    private function placeProductWithConsistentPattern($product, int $initialFacingTotal, $shelves): array
    {
        $productData = $product['product'] ?? [];
        $productWidth = floatval($productData['width']);
        $productId = $product['product_id'];

        $segmentsUsed = 0;
        $totalPlacements = 0;
        $successfulPlacements = [];
        
        // 1. Calcular a capacidade e contar prateleiras disponíveis
        $shelfCapacities = [];
        foreach ($shelves as $shelf) {
            $usedWidth = $this->calculateUsedWidthInShelf($shelf);
            $availableWidth = floatval($shelf->shelf_width ?? 125.0) - $usedWidth;
            $maxFacing = $productWidth > 0 ? floor($availableWidth / $productWidth) : 0;
            
            if ($maxFacing > 0) {
                 $shelfCapacities[] = [
                    'shelf' => $shelf,
                    'available_width' => $availableWidth,
                    'max_facing' => $maxFacing,
                ];
            }
        }

        if (empty($shelfCapacities)) {
            return ['success' => false, 'reason' => 'Nenhuma prateleira tem espaço para ao menos 1 facing'];
        }

        // 2. 🎯 RESPEITAR O FACING CALCULADO PELO FacingCalculatorService
        // (Removido Debug Pattern que reduzia facing arbitrariamente)
        $patternFacing = $initialFacingTotal; // Usar facing inteligente original
        
        $facingTotal = $initialFacingTotal;
        
        // 3. Lógica de arredondamento para "facing órfão"
        if ($facingTotal > 1 && $patternFacing > 1 && ($facingTotal % $patternFacing) == 1) {
            $abcClass = $product['abc_class'] ?? 'C';
            $urgency = $product['target_stock_data']['urgency'] ?? 'NORMAL';
            $shouldRoundUp = ($abcClass === 'A' || in_array($urgency, ['CRÍTICO', 'BAIXO']));

            if ($shouldRoundUp) {
                $facingTotal++; // Arredonda para cima
                StepLogger::logCustomStep('ARREDONDAMENTO DE FACING', [
                    '📦 PRODUTO' => $productId,
                    '📊 DE' => $initialFacingTotal,
                    'TO' => $facingTotal,
                    '📝 RAZÃO' => "PARA CIMA - Classe {$abcClass}, Urgência {$urgency}"
                ]);
            } else {
                $facingTotal--; // Arredonda para baixo
                StepLogger::logCustomStep('ARREDONDAMENTO DE FACING', [
                    '📦 PRODUTO' => $productId,
                    '📊 DE' => $initialFacingTotal,
                    'TO' => $facingTotal,
                    '📝 RAZÃO' => "PARA BAIXO - Classe {$abcClass}, Urgência {$urgency}"
                ]);
            }
        }

        // 4. Distribuir o facing de cima para baixo usando o padrão
        $remainingFacing = $facingTotal;
        foreach ($shelfCapacities as $capacityInfo) {
            if ($remainingFacing <= 0) {
                break;
            }

            $shelf = $capacityInfo['shelf'];
            // Recalcular capacidade na hora, pois prateleiras podem ser preenchidas por chamadas anteriores no loop
            $currentUsedWidth = $this->calculateUsedWidthInShelf($shelf);
            $currentAvailableWidth = floatval($shelf->shelf_width ?? 125.0) - $currentUsedWidth;
            $currentMaxFacing = $productWidth > 0 ? floor($currentAvailableWidth / $productWidth) : 0;

            if ($currentMaxFacing <= 0) {
                continue;
            }
            
            $facingToPlace = min($remainingFacing, $patternFacing, $currentMaxFacing);
            
            if ($facingToPlace > 0) {
                $success = $this->placeProductInShelfVertically($shelf, $product, $facingToPlace);

                if ($success) {
                    $segmentsUsed++;
                    $totalPlacements += $facingToPlace;
                    $remainingFacing -= $facingToPlace;
                    $successfulPlacements[] = ['shelf_id' => $shelf->id, 'facing' => $facingToPlace];
                }
            }
        }

        return [
            'success' => $totalPlacements > 0,
            'segments_used' => $segmentsUsed,
            'total_placements' => $totalPlacements,
            'successful_placements' => $successfulPlacements,
            'reason' => $totalPlacements > 0 ? null : 'Falha na colocação, mesmo com a nova lógica'
        ];
    }

    /**
     * Distribui produtos que falharam em outros módulos (CASCATA)
     */
    public function tryCascadeDistribution($allSections, array $failedProducts, string $excludeSectionId, array $structure): array
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
            
            // Tentativa de colocação em módulos alternativos
            foreach ($allSections as $section) {
                if ($section->id === $excludeSectionId) {
                    continue; // Pular a section que já falhou
                }
                
                // Tentando produto em módulo alternativo
                
                // Calcular facing conservador para cascata
                $conservativeFacing = $this->facingCalculator->calculateConservativeFacing($product);
                
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

    // Métodos auxiliares que serão implementados
    protected function calculateUsedWidthInShelf($shelf): float
    {
        $segments = $shelf->segments()->with('layer.product')->get();
        $usedWidth = 0;
        
        foreach ($segments as $segment) {
            if ($segment->layer && $segment->layer->product_id && $segment->layer->product) {
                $product = $segment->layer->product;
                $quantity = intval($segment->layer->quantity ?? 1);
                if (!$product->width || $product->width <= 0) {
                    continue; // Pular produtos sem largura válida
                }
                $productWidth = floatval($product->width);
                $segmentUsedWidth = $productWidth * $quantity;
                $usedWidth += $segmentUsedWidth;
            }
        }
        
        return $usedWidth;
    }
    
    protected function placeProductInShelfVertically($shelf, array $product, int $facing): bool
    {
        // 1. CALCULAR LARGURA NECESSÁRIA PARA O PRODUTO
        $productData = $product['product'] ?? [];
        if (!isset($productData['width']) || $productData['width'] <= 0) {
            Log::warning("❌ Produto sem largura válida - não pode ser colocado", [
                'product_id' => $product['product_id'] ?? 'unknown',
                'width' => $productData['width'] ?? 'null'
            ]);
            return false;
        }
        $productWidth = floatval($productData['width']);
        $requiredWidth = $productWidth * $facing;
        
        // 2. VERIFICAR LARGURA DISPONÍVEL NA PRATELEIRA
        $shelfWidth = floatval($shelf->shelf_width ?? 125);
        $usedWidth = $this->calculateUsedWidthInShelf($shelf);
        $availableWidth = $shelfWidth - $usedWidth;
        
        // 3. FACING ADAPTATIVO
        $adaptedFacing = $facing;
        $adaptedRequiredWidth = $requiredWidth;
        
        while ($adaptedFacing > 0 && $adaptedRequiredWidth > $availableWidth) {
            $adaptedFacing--;
            $adaptedRequiredWidth = $productWidth * $adaptedFacing;
        }
        
        if ($adaptedFacing <= 0 || $adaptedRequiredWidth > $availableWidth) {
            Log::warning("⚠️ Produto NÃO CABE mesmo com facing mínimo", [
                'shelf_id' => $shelf->id,
                'product_id' => $product['product_id'],
                'facing_minimum_tried' => $adaptedFacing,
                'required_width_cm' => $adaptedRequiredWidth,
                'available_width_cm' => $availableWidth
            ]);
            return false;
        }
        
        $facing = $adaptedFacing;
        $requiredWidth = $adaptedRequiredWidth;
        
        // 4. PROCURAR SEGMENTO VAZIO NA PRATELEIRA
        $segments = $shelf->segments()->orderBy('ordering')->get();
        
        foreach ($segments as $segment) {
            $segment->load('layer');
            $existingLayer = $segment->layer;
            
            if (!$existingLayer || !$existingLayer->product_id) {
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
                        
                        if ($segmentWidth < $requiredWidth) {
                            $segment->update(['width' => $requiredWidth]);
                        }
                        
                        StepLogger::logLayerCreation(
                            $existingLayer ? $existingLayer->id : 'new', 
                            $product, 
                            $facing, 
                            $segment->id
                        );
                        
                        return true;
                    } catch (\Exception $e) {
                        Log::error("❌ Erro ao salvar produto no banco", [
                            'segment_id' => $segment->id,
                            'product_id' => $product['product_id'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
        
        // 5. CRIAR NOVO SEGMENTO SE NECESSÁRIO
        return $this->createVerticalSegmentWithValidation($shelf, $product, $facing, $availableWidth);
    }
    
    protected function createVerticalSegmentWithValidation($shelf, array $product, int $facing, float $availableWidth): bool
    {
        $productData = $product['product'] ?? [];
        if (!isset($productData['width']) || $productData['width'] <= 0) {
            Log::warning("❌ Produto sem largura válida - não pode ser colocado", [
                'product_id' => $product['product_id'] ?? 'unknown',
                'width' => $productData['width'] ?? 'null'
            ]);
            return false;
        }
        $productWidth = floatval($productData['width']);
        $requiredWidth = $productWidth * $facing;
        
        // FACING ADAPTATIVO também para criação de segmento
        $adaptedFacing = $facing;
        $adaptedRequiredWidth = $requiredWidth;
        
        while ($adaptedFacing > 0 && $adaptedRequiredWidth > $availableWidth) {
            $adaptedFacing--;
            $adaptedRequiredWidth = $productWidth * $adaptedFacing;
        }
        
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
            
            StepLogger::logSegmentAction('created', $segment->id, $product, $facing, $requiredWidth);
            StepLogger::logLayerCreation($segment->layer->id ?? 'new', $product, $facing, $segment->id);

            return true;
        } catch (\Exception $e) {
            Log::error("❌ Erro ao criar segmento com produto", [
                'shelf_id' => $shelf->id,
                'product_id' => $product['product_id'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    protected function fillOpportunisticSpace($section, array $targetProducts): array
    {
        // Implementação temporária - será movida para ShelfSpaceCalculatorService
        return ['segments_used' => 0, 'total_placements' => 0];
    }
}
