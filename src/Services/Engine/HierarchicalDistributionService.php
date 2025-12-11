<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Services\Engine;

use Callcocam\Plannerate\Models\Gondola;
use Callcocam\Plannerate\Services\Analysis\TargetStockAnalysisService;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de Distribuição Hierárquica
 * 
 * Distribui produtos em uma gôndola respeitando a hierarquia de categorias
 * mercadológicas, ordenadas por score ABC, com facing baseado no target stock.
 */
class HierarchicalDistributionService
{
    public function __construct(
        private CategoryHierarchyService $categoryService,
        private ABCHierarchicalService $abcHierarchical,
        private FacingCalculatorService $facingCalculator,
        private TargetStockAnalysisService $targetStockService
    ) {}

    /**
     * Método principal: distribui produtos por hierarquia de categorias
     * 
     * @param Gondola $gondola - Gôndola a ser preenchida
     * @param array $allProducts - TODOS os produtos
     * @param array $weights - Pesos ABC
     * @param array $targetStockParams - Parâmetros de estoque alvo
     * @param string|null $startDate - Data inicial
     * @param string|null $endDate - Data final
     * @param int|null $storeId - ID da loja
     * @param string|null $clientId - ID do cliente (opcional)
     * @param array|null $zonesConfig - Configuração de zonas de performance (opcional)
     * @param string|null $mercadologicoLevel - Nível mercadológico do planograma (padrão: 'categoria')
     * @return array - Resultado da distribuição
     */
    public function distributeByHierarchy(
        Gondola $gondola,
        array $allProducts,
        array $weights,
        array $targetStockParams,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null,
        ?string $clientId = null,
        ?array $zonesConfig = null,
        ?string $mercadologicoLevel = 'categoria'
    ): array {
        // Definir nível mercadológico padrão se não fornecido
        $mercadologicoLevel = $mercadologicoLevel ?? 'categoria';
        
        Log::info("📊 Nível mercadológico configurado", [
            'mercadologico_level' => $mercadologicoLevel
        ]);
        // Log sobre zonas configuradas
        if ($zonesConfig) {
            Log::info("📍 Motor recebeu configuração de zonas", [
                'zones_count' => count($zonesConfig),
                'zones' => $zonesConfig
            ]);
        }

        Log::info("🚀 Iniciando distribuição hierárquica por categoria", [
            'gondola_id' => $gondola->id,
            'gondola_name' => $gondola->name,
            'total_products' => count($allProducts),
            'weights' => $weights,
            'targetStockParams' => $targetStockParams,
            'date_range' => ['start' => $startDate, 'end' => $endDate],
            'zones_enabled' => $zonesConfig !== null,
            'store_id' => $storeId
        ]);

        // 1. ABC GLOBAL
        $abcGlobal = $this->abcHierarchical->executeGlobalABC(
            $allProducts,
            $weights,
            $startDate,
            $endDate,
            $storeId
        );

        // 2. Identificar ordem das categorias
        $categoriesPriority = $this->abcHierarchical->extractCategoriesPriority(
            $allProducts,
            $abcGlobal,
            $mercadologicoLevel
        );

        // 3. Preparar prateleiras em ordem linear
        $linearShelves = $this->getAllShelvesInLinearOrder($gondola);
        $currentShelfIndex = 0;

        // 3.1 Se há zonas configuradas, separar prateleiras por zona
        $zonesData = null;
        if ($zonesConfig) {
            $zonesData = $this->separateShelvesByZones($linearShelves, $zonesConfig);
            Log::info("🎯 Prateleiras separadas por zonas", [
                'zones_count' => count($zonesData['zones']),
                'unassigned_shelves' => count($zonesData['unassigned']),
                'zones_details' => array_map(fn($z) => [
                    'name' => $z['config']['name'],
                    'shelves_count' => count($z['shelves']),
                    'multiplier' => $z['config']['performance_multiplier'],
                    'priority' => $z['config']['rules']['priority'] ?? 'N/A'
                ], $zonesData['zones'])
            ]);
        }

        $stats = [
            'total_products' => 0,
            'placed_products' => 0,
            'failed_products' => 0,
            'categories_processed' => [],
            'gondola_id' => $gondola->id,
            'zones_enabled' => $zonesConfig !== null
        ];

        // 4. Para cada categoria (em ordem de prioridade)
        foreach ($categoriesPriority as $categoryName => $categoryId) {

            Log::info("📦 Processando categoria", [
                'category' => $categoryName,
                'category_id' => $categoryId
            ]);

            // 5. FILTRAR produtos dessa categoria a partir de $allProducts
            // IMPORTANTE: Usar $allProducts (que já foi filtrado pelo controller)
            // em vez de buscar novamente do banco (que ignoraria filtros de "já usados")
            if ($categoryId) {
                // Buscar IDs da categoria e descendentes
                $category = \App\Models\Category::find($categoryId);
                $descendantIds = $category ? $category->getAllDescendantIds() : [];
                $allCategoryIds = array_merge([$categoryId], $descendantIds);
                
                // Filtrar $allProducts para incluir apenas produtos desta categoria
                $categoryProducts = array_filter($allProducts, function($p) use ($allCategoryIds) {
                    return in_array($p['category_id'] ?? null, $allCategoryIds);
                });
                
                Log::info("🔍 Produtos filtrados da categoria (usando allProducts)", [
                    'category_id' => $categoryId,
                    'category_ids_searched' => count($allCategoryIds),
                    'products_found' => count($categoryProducts)
                ]);
            } else {
                // Categoria sem ID (ex: "SEM_CATEGORIA")
                $categoryProducts = array_filter($allProducts, function($p) use ($categoryName, $mercadologicoLevel) {
                    return $this->categoryService->extractCategoryFromProduct($p, $mercadologicoLevel) === $categoryName;
                });
            }

            if (empty($categoryProducts)) {
                Log::warning("⚠️ Categoria sem produtos", [
                    'category' => $categoryName
                ]);
                continue;
            }

            // 6. ABC LOCAL
            $abcLocal = $this->abcHierarchical->executeLocalABC(
                $categoryProducts,
                $weights,
                $startDate,
                $endDate,
                $storeId
            );

            // 7. Ordenar por ABC local (A primeiro, depois B, depois C) + Tamanho
            $categoryProducts = $this->abcHierarchical->sortProductsByABC($categoryProducts, $abcLocal);
            
            // 7.5. AGRUPAR POR SUBCATEGORIA (2º nível de organização)
            $subcategoriesGrouped = $this->groupProductsBySubcategory($categoryProducts, $abcLocal, $categoryId);
            
            Log::info("📂 Produtos agrupados por subcategoria", [
                'category' => $categoryName,
                'total_products' => count($categoryProducts),
                'subcategories_count' => count($subcategoriesGrouped),
                'subcategories' => array_map(fn($sg) => [
                    'name' => $sg['subcategory_name'],
                    'products_count' => count($sg['products'])
                ], $subcategoriesGrouped)
            ]);

            // 8. Calcular Target Stock para todos os produtos da categoria
            $productIds = array_column($categoryProducts, 'id');
            $targetStockResults = $this->targetStockService->analyze(
                $productIds,
                $startDate,
                $endDate,
                $clientId,
                $storeId
            );

            // 9. Distribuir categoria SUBCATEGORIA POR SUBCATEGORIA
            $categoryStats = [
                'total' => 0,
                'placed' => 0,
                'failed' => 0
            ];
            
            foreach ($subcategoriesGrouped as $subcategoryData) {
                $subcategoryName = $subcategoryData['subcategory_name'];
                $subcategoryProducts = $subcategoryData['products'];
                
                Log::info("📦 Distribuindo subcategoria", [
                    'category' => $categoryName,
                    'subcategory' => $subcategoryName,
                    'products_count' => count($subcategoryProducts)
                ]);
                
                // Distribuir subcategoria
                if ($zonesData) {
                    $subcategoryStats = $this->distributeCategoryByZones(
                        $subcategoryProducts,
                        $abcLocal,
                        $abcGlobal,
                        $targetStockResults,
                        $targetStockParams,
                        $zonesData,
                        $linearShelves,
                        $currentShelfIndex,
                        "$categoryName → $subcategoryName"
                    );
                } else {
                    $subcategoryStats = $this->distributeCategorySequentially(
                        $subcategoryProducts,
                        $abcLocal,
                        $abcGlobal,
                        $targetStockResults,
                        $targetStockParams,
                        $linearShelves,
                        $currentShelfIndex,
                        "$categoryName → $subcategoryName"
                    );
                }
                
                // Acumular estatísticas
                $categoryStats['total'] += $subcategoryStats['total'];
                $categoryStats['placed'] += $subcategoryStats['placed'];
                $categoryStats['failed'] += $subcategoryStats['failed'];
            }

            $stats['total_products'] += $categoryStats['total'];
            $stats['placed_products'] += $categoryStats['placed'];
            $stats['failed_products'] += $categoryStats['failed'];
            $stats['categories_processed'][$categoryName] = $categoryStats;
            
            // Log do índice APÓS distribuir a categoria
            Log::info("📍 Índice de prateleira APÓS categoria", [
                'category' => $categoryName,
                'current_shelf_index' => $currentShelfIndex,
                'products_placed' => $categoryStats['placed']
            ]);
        }

        Log::info("✅ Distribuição hierárquica concluída", $stats);

        return $stats;
    }

    /**
     * Retorna todas as prateleiras em ordem linear
     * 
     * @param Gondola $gondola
     * @return array - Lista de prateleiras ordenadas
     */
    protected function getAllShelvesInLinearOrder(Gondola $gondola): array
    {
        $linearShelves = [];

        // Ordenar módulos por ordering
        $sections = $gondola->sections->sortBy('ordering');

        foreach ($sections as $section) {
            // Ordenar prateleiras por ordering
            $shelves = $section->shelves->sortBy('ordering');

            foreach ($shelves as $shelf) {
                $linearShelves[] = [
                    'shelf' => $shelf,
                    'section' => $section,
                    'available_width' => $section->width,
                    'used_width' => 0,
                    'shelf_depth' => $shelf->shelf_depth ?? 40
                ];
            }
        }

        Log::info("📐 Prateleiras em ordem linear", [
            'total_shelves' => count($linearShelves),
            'modules' => $sections->count()
        ]);

        return $linearShelves;
    }

    /**
     * Distribui produtos de uma categoria sequencialmente
     * 
     * @param array $products - Produtos ordenados por ABC local
     * @param array $abcLocal - Resultados do ABC LOCAL (usado para ordenação interna)
     * @param array $abcGlobal - Resultados do ABC GLOBAL (usado para target stock - mesma classificação do modal)
     * @param array $targetStockResults - Resultados da análise de vendas
     * @param array $targetStockParams - Parâmetros de target stock (service level e coverage days)
     * @param array &$linearShelves - Prateleiras (passado por referência)
     * @param int &$currentShelfIndex - Índice atual (passado por referência)
     * @param string $categoryName - Nome da categoria
     * @return array - Estatísticas da distribuição
     */
    protected function distributeCategorySequentially(
        array $products,
        array $abcLocal,
        array $abcGlobal,
        array $targetStockResults,
        array $targetStockParams,
        array &$linearShelves,
        int &$currentShelfIndex,
        string $categoryName
    ): array {
        Log::debug("🔧 DEBUG - Iniciando distribuição de categoria", [
            'category' => $categoryName,
            'products_count' => count($products),
            'shelves_count' => count($linearShelves),
            'current_shelf_index' => $currentShelfIndex
        ]);
        
        $placed = 0;
        $failed = 0;

        foreach ($products as $product) {
            // DEBUG: Ver os valores antes da verificação
            Log::debug("🔍 DEBUG - Tentando colocar produto", [
                'product_name' => $product['name'] ?? 'N/A',
                'currentShelfIndex' => $currentShelfIndex,
                'total_shelves' => count($linearShelves),
                'category' => $categoryName
            ]);
            
            // Verificar se ainda há prateleiras disponíveis
            if ($currentShelfIndex >= count($linearShelves)) {
                Log::warning("⚠️ Sem prateleiras disponíveis", [
                    'category' => $categoryName,
                    'remaining_products' => count($products) - $placed
                ]);
                $failed++;
                continue;
            }

            // Buscar classe ABC GLOBAL do produto (mesma que o modal usa)
            // IMPORTANTE: ABC usa EAN como product_id, não o ID interno!
            $productEan = $product['ean'] ?? $product['id'];
            $abcDataGlobal = collect($abcGlobal)->firstWhere('product_id', $productEan);
            $abcClassGlobal = $abcDataGlobal['abc_class'] ?? 'C';
            
            // Log para debug (Coca Cola)
            if ($productEan === '7894900027013') {
                $abcDataLocal = collect($abcLocal)->firstWhere('product_id', $productEan);
                Log::warning("🎯 COCA COLA - Classificação ABC", [
                    'product_name' => $product['name'] ?? 'N/A',
                    'product_id_interno' => $product['id'],
                    'product_ean' => $productEan,
                    'abc_class_LOCAL' => $abcDataLocal['abc_class'] ?? 'N/A',
                    'abc_class_GLOBAL' => $abcClassGlobal,
                    'composite_score_LOCAL' => $abcDataLocal['composite_score'] ?? 'N/A',
                    'composite_score_GLOBAL' => $abcDataGlobal['composite_score'] ?? 'N/A',
                    'category' => $categoryName,
                    '---INFO---' => 'Usando ABC GLOBAL para target stock (mesma classificação do modal)'
                ]);
            }
            
            // Buscar target stock do produto baseado na classe ABC GLOBAL (não local!)
            // Usar ABC GLOBAL para que os parâmetros sejam os mesmos do modal
            $targetStock = $this->getTargetStockForProduct($product, $targetStockResults, $targetStockParams, $abcClassGlobal);

            Log::info("🎯 Target Stock e Facing para produto", [
                'product_id' => $product['id'],
                'product_name' => $product['name'] ?? 'N/A',
                'abc_class_global' => $abcClassGlobal,
                'target_stock_calculated' => $targetStock,
                'target_stock_params' => [
                    'service_level' => $targetStockParams['serviceLevel'][$abcClassGlobal] ?? 'N/A',
                    'coverage_days' => $targetStockParams['coverageDays'][$abcClassGlobal] ?? 'N/A'
                ]
            ]);

            // Calcular facing baseado no target stock
            $shelfDepth = $linearShelves[$currentShelfIndex]['shelf_depth'] ?? 40;
            $facing = $this->facingCalculator->calculateFacing(
                $product,
                $targetStock,
                $shelfDepth
            );
            
            Log::info("📐 Facing final para distribuição", [
                'product_id' => $product['id'],
                'product_name' => $product['name'] ?? 'N/A',
                'target_stock' => $targetStock,
                'facing_calculated' => $facing,
                'product_depth' => $product['depth'] ?? 0,
                'shelf_depth' => $shelfDepth
            ]);

            // Tentar colocar produto
            $placedSuccessfully = $this->tryPlaceProduct(
                $product,
                $facing,
                $linearShelves,
                $currentShelfIndex
            );

            if ($placedSuccessfully) {
                $placed++;
            } else {
                $failed++;
            }
        }

        $successRate = count($products) > 0 ? round(($placed / count($products)) * 100, 1) : 0;

        Log::info("✅ Categoria distribuída", [
            'category' => $categoryName,
            'total' => count($products),
            'placed' => $placed,
            'failed' => $failed,
            'success_rate' => $successRate . '%',
            'current_shelf_index_FINAL' => $currentShelfIndex
        ]);

        return [
            'total' => count($products),
            'placed' => $placed,
            'failed' => $failed,
            'success_rate' => $successRate
        ];
    }

    /**
     * Calcula o target stock (estoque alvo) de um produto baseado na classe ABC
     * 
     * Fórmula: target_stock = (média_diária * dias_cobertura_ABC) + estoque_segurança_ABC
     * Onde: estoque_segurança = desvio_padrão * z-score(service_level_ABC)
     * 
     * @param array $product - Produto
     * @param array $targetStockResults - Resultados da análise de vendas
     * @param array $targetStockParams - Parâmetros de target stock (serviceLevel e coverageDays por classe)
     * @param string $abcClass - Classe ABC do produto ('A', 'B' ou 'C')
     * @return int - Estoque alvo calculado
     */
    protected function getTargetStockForProduct(
        array $product, 
        array $targetStockResults, 
        array $targetStockParams,
        string $abcClass
    ): int {
        $result = collect($targetStockResults)->firstWhere('product_id', $product['id']);
        
        if (!$result) {
            Log::warning("⚠️ Análise de vendas não encontrada para produto", [
                'product_id' => $product['id'],
                'product_name' => $product['name'] ?? 'N/A',
                'abc_class' => $abcClass
            ]);
            return 3; // Mínimo padrão: 3 unidades
        }

        // Extrair dados da análise
        $averageSales = $result['average_sales'] ?? 0;
        $standardDeviation = $result['standard_deviation'] ?? 0;
        
        Log::debug("📥 Dados recebidos do TargetStockAnalysisService", [
            'product_id' => $product['id'],
            'product_name' => $product['name'] ?? 'N/A',
            'average_sales' => $averageSales,
            'standard_deviation' => $standardDeviation,
            'variability' => $result['variability'] ?? 'N/A',
            'currentStock' => $result['currentStock'] ?? 'N/A',
            'sales_by_day_count' => count($result['sales_by_day'] ?? [])
        ]);
        
        // Buscar parâmetros específicos da classe ABC
        $serviceLevel = $targetStockParams['serviceLevel'][$abcClass] ?? 0.95;
        $diasCobertura = $targetStockParams['coverageDays'][$abcClass] ?? 7;
        
        // Converter service level para z-score (fator de segurança)
        // Tabela de conversão Service Level → Z-Score
        // IMPORTANTE: Usar strings como chave porque PHP não suporta chaves float em arrays
        $zScoreMap = [
            '0.50' => 0.00,
            '0.60' => 0.253,
            '0.70' => 0.524,
            '0.75' => 0.674,
            '0.80' => 0.842,
            '0.85' => 1.036,
            '0.90' => 1.282,
            '0.95' => 1.645,
            '0.975' => 1.960,
            '0.99' => 2.326,
            '0.995' => 2.576
        ];
        
        // Encontrar z-score mais próximo
        $fatorSeguranca = 1.645; // Default 95%
        $minDiff = PHP_FLOAT_MAX;
        
        foreach ($zScoreMap as $levelStr => $zScore) {
            $level = (float) $levelStr; // Converter string para float
            $diff = abs($serviceLevel - $level);
            
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $fatorSeguranca = $zScore;
            }
        }
        
        // Calcular target stock (mesma fórmula do modal)
        $estoqueBase = $averageSales * $diasCobertura; // minimumStock
        $estoqueSeguranca = $standardDeviation * $fatorSeguranca; // safetyStock
        $targetStock = (int) ceil($estoqueBase + $estoqueSeguranca);
        
        Log::info("📊 Target Stock calculado", [
            'product_id' => $product['id'],
            'product_ean' => $product['ean'] ?? 'N/A',
            'product_name' => $product['name'] ?? 'N/A',
            'abc_class' => $abcClass,
            '---FORMULA---' => 'targetStock = (averageSales × dias) + (stdDev × z)',
            'average_sales_daily' => round($averageSales, 2),
            'standard_deviation' => round($standardDeviation, 2),
            'service_level' => $serviceLevel,
            'dias_cobertura' => $diasCobertura,
            'z_score' => $fatorSeguranca,
            '---CALCULO---' => sprintf('(%s × %d) + (%s × %s)', 
                round($averageSales, 2), 
                $diasCobertura, 
                round($standardDeviation, 2), 
                round($fatorSeguranca, 3)
            ),
            'estoque_base_minimumStock' => round($estoqueBase, 2),
            'estoque_seguranca_safetyStock' => round($estoqueSeguranca, 2),
            'target_stock_FINAL' => $targetStock,
            '---COMPARAR_COM_MODAL---' => 'Use estes valores exatos no modal'
        ]);

        return max(3, $targetStock); // Mínimo 3 unidades
    }

    /**
     * Tenta colocar um produto nas prateleiras
     * Distribui o facing em múltiplas prateleiras se necessário
     * 
     * @param array $product - Produto a ser colocado
     * @param int $facing - Número de facings desejado
     * @param array &$linearShelves - Prateleiras
     * @param int &$currentShelfIndex - Índice da prateleira atual
     * @return bool - true se colocou todo o facing, false se não conseguiu
     */
    protected function tryPlaceProduct(
        array $product,
        int $facing,
        array &$linearShelves,
        int &$currentShelfIndex
    ): bool {
        $productWidth = $product['width'] ?? 0;
        $remainingFacing = $facing;

        Log::debug("🔧 Tentando distribuir produto", [
            'product_name' => $product['name'] ?? 'N/A',
            'facing_total' => $facing,
            'product_width' => $productWidth . 'cm'
        ]);

        // Validar dimensões do produto
        if ($productWidth <= 0) {
            Log::warning("⚠️ Produto sem largura válida, pulando", [
                'product_name' => $product['name'] ?? 'N/A',
                'product_width' => $productWidth
            ]);
            return false;
        }

        // Tentar colocar nas prateleiras disponíveis
        while ($remainingFacing > 0 && $currentShelfIndex < count($linearShelves)) {
            $shelfData = &$linearShelves[$currentShelfIndex];
            $spaceAvailable = $shelfData['available_width'] - $shelfData['used_width'];

            // Calcular quantos facings cabem nesta prateleira (proteger contra divisão por zero)
            $maxFacingInShelf = $productWidth > 0 ? (int) floor($spaceAvailable / $productWidth) : 0;

            if ($maxFacingInShelf > 0) {
                // Colocar o que couber (ou tudo se couber)
                $facingToPlace = min($remainingFacing, $maxFacingInShelf);
                $widthUsed = $facingToPlace * $productWidth;

                // Criar segmento
                $this->createSegment(
                    $shelfData['shelf'],
                    $product,
                    $facingToPlace
                );

                $shelfData['used_width'] += $widthUsed;
                $remainingFacing -= $facingToPlace;

                Log::info("✅ Produto colocado (parcial)", [
                    'product_name' => $product['name'] ?? 'N/A',
                    'facing_placed' => $facingToPlace,
                    'facing_remaining' => $remainingFacing,
                    'width_used' => $widthUsed . 'cm',
                    'shelf_index' => $currentShelfIndex,
                    'space_remaining' => round($spaceAvailable - $widthUsed, 2) . 'cm'
                ]);

                // Se ainda precisa colocar mais, avançar para próxima prateleira
                if ($remainingFacing > 0) {
                    $currentShelfIndex++;
                }
            } else {
                // Prateleira cheia, avançar
                Log::debug("⏭️ Prateleira cheia, avançando", [
                    'shelf_index' => $currentShelfIndex,
                    'space_available' => $spaceAvailable . 'cm',
                    'product_width' => $productWidth . 'cm'
                ]);
                $currentShelfIndex++;
            }
        }

        // Verificar se conseguiu colocar todo o facing
        if ($remainingFacing > 0) {
            Log::warning("⚠️ Produto parcialmente colocado (sem espaço)", [
                'product_name' => $product['name'] ?? 'N/A',
                'facing_placed' => $facing - $remainingFacing,
                'facing_missing' => $remainingFacing
            ]);
            return false;
        }

        return true;
    }

    /**
     * Cria segmento e layer para o produto
     * 
     * @param \Callcocam\Plannerate\Models\Shelf $shelf - Prateleira
     * @param array $product - Produto
     * @param int $facing - Número de facings
     */
    protected function createSegment($shelf, array $product, int $facing): void
    {
        $productWidth = $product['width'] ?? 0;
        
        // Validar dimensões antes de criar segmento
        if ($productWidth <= 0) {
            Log::warning("⚠️ Tentativa de criar segmento com produto sem largura válida", [
                'product_name' => $product['name'] ?? 'N/A',
                'product_width' => $productWidth
            ]);
            return;
        }
        
        $totalWidth = $productWidth * $facing;

        // Criar segmento
        $segment = $shelf->segments()->create([
            'width' => $totalWidth,
            'ordering' => $shelf->segments()->count(),
            'quantity' => 1,
            'status' => 'published'
        ]);

        // Criar layer
        $segment->layer()->create([
            'product_id' => $product['id'],
            'quantity' => $facing,
            'status' => 'published'
        ]);

        Log::info("📦 Segmento e layer criados", [
            'segment_id' => $segment->id,
            'product_id' => $product['id'],
            'product_name' => $product['name'] ?? 'N/A',
            'facing' => $facing,
            'width' => $totalWidth . 'cm',
            'shelf_id' => $shelf->id
        ]);
    }

    /**
     * Distribui produtos de uma categoria considerando zonas configuradas
     * 
     * @param array $products - Produtos ordenados por ABC local
     * @param array $abcLocal - Resultados do ABC LOCAL
     * @param array $abcGlobal - Resultados do ABC GLOBAL
     * @param array $targetStockResults - Resultados da análise de vendas
     * @param array $targetStockParams - Parâmetros de target stock
     * @param array $zonesData - Dados das zonas separadas
     * @param array &$linearShelves - Prateleiras (por referência)
     * @param int &$currentShelfIndex - Índice atual (por referência)
     * @param string $categoryName - Nome da categoria
     * @return array - Estatísticas da distribuição
     */
    protected function distributeCategoryByZones(
        array $products,
        array $abcLocal,
        array $abcGlobal,
        array $targetStockResults,
        array $targetStockParams,
        array $zonesData,
        array &$linearShelves,
        int &$currentShelfIndex,
        string $categoryName
    ): array {
        Log::info("🎯 Distribuindo categoria por zonas", [
            'category' => $categoryName,
            'products_count' => count($products),
            'zones_count' => count($zonesData['zones'])
        ]);

        $placed = 0;
        $failed = 0;
        $productsUsed = []; // Rastrear produtos já alocados

        // Processar cada zona (já ordenadas por performance_multiplier)
        foreach ($zonesData['zones'] as $zoneData) {
            $zoneConfig = $zoneData['config'];
            $zoneShelves = $zoneData['shelves'];
            
            Log::info("📍 Processando zona", [
                'zone_name' => $zoneConfig['name'],
                'shelves_count' => count($zoneShelves),
                'multiplier' => $zoneConfig['performance_multiplier'],
                'priority' => $zoneConfig['rules']['priority'] ?? 'N/A'
            ]);

            // Filtrar produtos que atendem às regras desta zona
            $zoneProducts = $this->filterProductsByZoneRules($products, $zoneConfig, $abcLocal);
            
            // Remover produtos já usados em outras zonas
            $zoneProducts = array_filter($zoneProducts, function($p) use ($productsUsed) {
                $ean = $p['ean'] ?? $p['id'];
                return !in_array($ean, $productsUsed);
            });

            // Ordenar produtos pela prioridade da zona
            $priority = $zoneConfig['rules']['priority'] ?? 'high_margin';
            $zoneProducts = $this->sortProductsByZonePriority(array_values($zoneProducts), $priority, $abcLocal);

            Log::info("🔍 Produtos filtrados para zona", [
                'zone_name' => $zoneConfig['name'],
                'products_count' => count($zoneProducts),
                'first_3_products' => array_slice(array_column($zoneProducts, 'name'), 0, 3)
            ]);

            // Distribuir produtos nas prateleiras desta zona
            foreach ($zoneProducts as $product) {
                // Buscar prateleira disponível na zona
                $shelfFound = false;
                foreach ($zoneShelves as $zoneShelf) {
                    $globalIndex = $zoneShelf['global_index'];
                    
                    // Buscar ABC GLOBAL e Target Stock
                    $productEan = $product['ean'] ?? $product['id'];
                    $abcDataGlobal = collect($abcGlobal)->firstWhere('product_id', $productEan);
                    $abcClassGlobal = $abcDataGlobal['abc_class'] ?? 'C';
                    $targetStock = $this->getTargetStockForProduct($product, $targetStockResults, $targetStockParams, $abcClassGlobal);

                    // Calcular facing baseado no target stock (SEM aplicar multiplicador de zona)
                    $shelfDepth = $linearShelves[$globalIndex]['shelf_depth'] ?? 40;
                    $facing = $this->facingCalculator->calculateFacing(
                        $product,
                        $targetStock,
                        $shelfDepth
                    );

                    // Tentar colocar na prateleira desta zona
                    $placedSuccessfully = $this->tryPlaceProduct(
                        $product,
                        $facing,
                        $linearShelves,
                        $globalIndex
                    );

                    if ($placedSuccessfully) {
                        $placed++;
                        $productsUsed[] = $productEan;
                        $shelfFound = true;
                        
                        Log::debug("✅ Produto alocado na zona", [
                            'product' => $product['name'] ?? 'N/A',
                            'zone' => $zoneConfig['name'],
                            'shelf_index' => $globalIndex,
                            'facing' => $facing
                        ]);
                        
                        break; // Produto alocado, próximo produto
                    }
                }

                if (!$shelfFound) {
                    $failed++;
                }
            }
        }

        // Distribuir produtos restantes nas prateleiras não atribuídas
        $remainingProducts = array_filter($products, function($p) use ($productsUsed) {
            $ean = $p['ean'] ?? $p['id'];
            return !in_array($ean, $productsUsed);
        });

        if (count($remainingProducts) > 0 && count($zonesData['unassigned']) > 0) {
            Log::info("📦 Distribuindo produtos restantes em prateleiras não atribuídas", [
                'remaining_products' => count($remainingProducts),
                'unassigned_shelves' => count($zonesData['unassigned'])
            ]);

            // Distribuir em prateleiras não atribuídas
            foreach (array_values($remainingProducts) as $product) {
                foreach ($zonesData['unassigned'] as $unassignedShelf) {
                    $globalIndex = $unassignedShelf['global_index'];
                    
                    $productEan = $product['ean'] ?? $product['id'];
                    $abcDataGlobal = collect($abcGlobal)->firstWhere('product_id', $productEan);
                    $abcClassGlobal = $abcDataGlobal['abc_class'] ?? 'C';
                    $targetStock = $this->getTargetStockForProduct($product, $targetStockResults, $targetStockParams, $abcClassGlobal);

                    $shelfDepth = $linearShelves[$globalIndex]['shelf_depth'] ?? 40;
                    $facing = $this->facingCalculator->calculateFacing(
                        $product,
                        $targetStock,
                        $shelfDepth
                    );

                    $placedSuccessfully = $this->tryPlaceProduct(
                        $product,
                        $facing,
                        $linearShelves,
                        $globalIndex
                    );

                    if ($placedSuccessfully) {
                        $placed++;
                        break;
                    }
                }
            }
        }

        $successRate = count($products) > 0 ? round(($placed / count($products)) * 100, 1) : 0;

        Log::info("✅ Categoria distribuída por zonas", [
            'category' => $categoryName,
            'total' => count($products),
            'placed' => $placed,
            'failed' => $failed,
            'success_rate' => $successRate . '%'
        ]);

        return [
            'total' => count($products),
            'placed' => $placed,
            'failed' => $failed,
            'success_rate' => $successRate
        ];
    }

    /**
     * Filtra produtos que atendem às regras de uma zona
     * 
     * @param array $products - Produtos a filtrar
     * @param array $zone - Configuração da zona
     * @param array $abcLocal - Resultados ABC local
     * @return array - Produtos filtrados
     */
    protected function filterProductsByZoneRules(array $products, array $zone, array $abcLocal): array
    {
        $rules = $zone['rules'] ?? [];
        
        // Calcular estatísticas para filtros baseados em prioridade
        $margins = array_filter(array_column($products, 'margin_percent'));
        $prices = array_filter(array_column($products, 'sale_price'));
        $sales = array_filter(array_column($products, 'total_sales'));
        
        $avgMargin = !empty($margins) ? array_sum($margins) / count($margins) : 0;
        $avgPrice = !empty($prices) ? array_sum($prices) / count($prices) : 0;
        $avgSales = !empty($sales) ? array_sum($sales) / count($sales) : 0;
        
        return array_filter($products, function($product) use ($rules, $abcLocal, $avgMargin, $avgPrice, $avgSales) {
            $productEan = $product['ean'] ?? $product['id'];
            $abcData = collect($abcLocal)->firstWhere('product_id', $productEan);
            $abcClass = $abcData['abc_class'] ?? 'C';
            
            // Filtro por Priority - TODOS os tipos funcionam como FILTRO
            if (isset($rules['priority'])) {
                $priority = $rules['priority'];
                
                switch ($priority) {
                    case 'class_a':
                    case 'class_b':
                    case 'class_c':
                        // Filtrar APENAS pela classe ABC
                        $targetClass = strtoupper(substr($priority, -1));
                        if ($abcClass !== $targetClass) {
                            return false;
                        }
                        break;
                        
                    case 'high_margin':
                        // Filtrar apenas produtos com margem acima da média
                        $productMargin = $product['margin_percent'] ?? 0;
                        if ($productMargin < $avgMargin) {
                            return false;
                        }
                        break;
                        
                    case 'low_price':
                        // Filtrar apenas produtos com preço abaixo da média
                        $productPrice = $product['sale_price'] ?? PHP_FLOAT_MAX;
                        if ($productPrice > $avgPrice) {
                            return false;
                        }
                        break;
                        
                    case 'high_rotation':
                        // Filtrar apenas produtos com vendas acima da média
                        $productSales = $product['total_sales'] ?? 0;
                        if ($productSales < $avgSales) {
                            return false;
                        }
                        break;
                        
                    case 'reference_brand':
                        // Filtrar apenas marcas de referência (usar array de marcas se definido)
                        if (!empty($rules['reference_brands'])) {
                            $productBrand = $product['brand'] ?? '';
                            if (!in_array($productBrand, $rules['reference_brands'])) {
                                return false;
                            }
                        }
                        break;
                        
                    case 'new_products':
                        // Filtrar produtos com poucas ou nenhuma venda (produtos novos)
                        $productSales = $product['total_sales'] ?? 0;
                        if ($productSales > ($avgSales * 0.5)) { // Menos da metade da média
                            return false;
                        }
                        break;
                        
                    case 'complementary':
                        // Produtos complementares - aceitar todos (comportamento neutro)
                        // Não aplica filtro adicional
                        break;
                }
            }
            
            // Filtro ABC (array de classes permitidas - adicional ao priority)
            if (!empty($rules['abc_filter'])) {
                if (!in_array($abcClass, $rules['abc_filter'])) {
                    return false;
                }
            }
            
            // Filtro de margem mínima
            if (isset($rules['min_margin_percent']) && isset($product['margin_percent'])) {
                if ($product['margin_percent'] < $rules['min_margin_percent']) {
                    return false;
                }
            }
            
            // Filtro de margem máxima
            if (isset($rules['max_margin_percent']) && isset($product['margin_percent'])) {
                if ($product['margin_percent'] > $rules['max_margin_percent']) {
                    return false;
                }
            }
            
            // Filtro de marcas de referência (adicional - usado quando não é priority)
            if (!empty($rules['reference_brands']) && (!isset($rules['priority']) || $rules['priority'] !== 'reference_brand')) {
                $productBrand = $product['brand'] ?? '';
                if (!in_array($productBrand, $rules['reference_brands'])) {
                    return false;
                }
            }
            
            return true; // Produto passou em todos os filtros
        });
    }

    /**
     * Ordena produtos de acordo com a prioridade da zona
     * 
     * @param array $products - Produtos a ordenar
     * @param string $priority - Tipo de prioridade
     * @param array $abcLocal - Resultados ABC local
     * @return array - Produtos ordenados
     */
    protected function sortProductsByZonePriority(array $products, string $priority, array $abcLocal): array
    {
        $sortedProducts = $products;
        
        usort($sortedProducts, function($a, $b) use ($priority, $abcLocal) {
            switch ($priority) {
                case 'high_margin':
                    $marginA = $a['margin_percent'] ?? 0;
                    $marginB = $b['margin_percent'] ?? 0;
                    return $marginB <=> $marginA; // Maior margem primeiro
                    
                case 'low_price':
                    $priceA = $a['sale_price'] ?? PHP_FLOAT_MAX;
                    $priceB = $b['sale_price'] ?? PHP_FLOAT_MAX;
                    return $priceA <=> $priceB; // Menor preço primeiro
                    
                case 'high_rotation':
                    $salesA = $a['total_sales'] ?? 0;
                    $salesB = $b['total_sales'] ?? 0;
                    return $salesB <=> $salesA; // Maior venda primeiro
                    
                case 'class_a':
                case 'class_b':
                case 'class_c':
                    $targetClass = strtoupper(substr($priority, -1));
                    $eanA = $a['ean'] ?? $a['id'];
                    $eanB = $b['ean'] ?? $b['id'];
                    $abcA = collect($abcLocal)->firstWhere('product_id', $eanA)['abc_class'] ?? 'C';
                    $abcB = collect($abcLocal)->firstWhere('product_id', $eanB)['abc_class'] ?? 'C';
                    
                    // Priorizar produtos da classe alvo
                    if ($abcA === $targetClass && $abcB !== $targetClass) return -1;
                    if ($abcA !== $targetClass && $abcB === $targetClass) return 1;
                    return 0;
                    
                default:
                    return 0; // Manter ordem atual
            }
        });
        
        return $sortedProducts;
    }

    /**
     * Separa prateleiras lineares por zonas
     * 
     * @param array $linearShelves - Todas as prateleiras
     * @param array $zonesConfig - Configuração de zonas
     * @return array - ['zones' => [...], 'unassigned' => [...]]
     */
    protected function separateShelvesByZones(array $linearShelves, array $zonesConfig): array
    {
        $zonesShelves = [];
        $usedShelfIndexes = [];
        
        // Ordenar zonas por performance_multiplier (maior primeiro)
        usort($zonesConfig, function($a, $b) {
            $multA = $a['performance_multiplier'] ?? 1.0;
            $multB = $b['performance_multiplier'] ?? 1.0;
            return $multB <=> $multA; // Maior multiplicador primeiro (zonas premium)
        });
        
        foreach ($zonesConfig as $zone) {
            $shelfIndexes = $zone['shelf_indexes'] ?? [];
            $zoneShelves = [];
            
            foreach ($shelfIndexes as $index) {
                if (isset($linearShelves[$index])) {
                    $zoneShelves[] = [
                        'shelf_data' => $linearShelves[$index],
                        'global_index' => $index
                    ];
                    $usedShelfIndexes[] = $index;
                }
            }
            
            $zonesShelves[] = [
                'config' => $zone,
                'shelves' => $zoneShelves,
                'start_index' => min($shelfIndexes),
                'end_index' => max($shelfIndexes)
            ];
        }
        
        // Prateleiras não atribuídas a zonas
        $unassignedShelves = [];
        foreach ($linearShelves as $index => $shelf) {
            if (!in_array($index, $usedShelfIndexes)) {
                $unassignedShelves[] = [
                    'shelf_data' => $shelf,
                    'global_index' => $index
                ];
            }
        }
        
        return [
            'zones' => $zonesShelves,
            'unassigned' => $unassignedShelves
        ];
    }

    /**
     * Agrupa produtos por subcategoria e ordena as subcategorias por relevância
     * 
     * @param array $products - Produtos já ordenados por ABC
     * @param array $abcLocal - Resultados ABC local
     * @param string|null $genericCategoryId - ID da categoria genérica (para contexto)
     * @return array - Array de subcategorias com seus produtos
     */
    protected function groupProductsBySubcategory(array $products, array $abcLocal, ?string $genericCategoryId = null): array
    {
        $subcategoriesMap = [];
        
        // 1. Agrupar produtos por subcategoria
        foreach ($products as $product) {
            // Extrair subcategoria do produto (categoria mais específica relativa à genérica)
            $subcategoryName = $this->extractSubcategoryName($product, $genericCategoryId);
            
            if (!isset($subcategoriesMap[$subcategoryName])) {
                $subcategoriesMap[$subcategoryName] = [
                    'subcategory_name' => $subcategoryName,
                    'products' => [],
                    'count_A' => 0,
                    'total_score' => 0
                ];
            }
            
            // Adicionar produto ao grupo
            $subcategoriesMap[$subcategoryName]['products'][] = $product;
            
            // Calcular métricas para ordenação
            $productEan = $product['ean'] ?? $product['id'];
            $abcData = collect($abcLocal)->firstWhere('product_id', $productEan);
            
            if ($abcData) {
                if (($abcData['abc_class'] ?? '') === 'A') {
                    $subcategoriesMap[$subcategoryName]['count_A']++;
                }
                $subcategoriesMap[$subcategoryName]['total_score'] += $abcData['composite_score'] ?? 0;
            }
        }
        
        // 2. Ordenar subcategorias por relevância (mais A's primeiro, depois por score total)
        usort($subcategoriesMap, function($a, $b) {
            // Primeiro: maior quantidade de produtos A
            if ($a['count_A'] !== $b['count_A']) {
                return $b['count_A'] <=> $a['count_A'];
            }
            // Segundo: maior score total
            return $b['total_score'] <=> $a['total_score'];
        });
        
        return $subcategoriesMap;
    }

    /**
     * Extrai o nome da subcategoria de um produto (relativa à categoria genérica)
     * 
     * @param array $product - Dados do produto
     * @param string|null $genericCategoryId - ID da categoria genérica
     * @return string - Nome da subcategoria (baseado em level_name)
     */
    protected function extractSubcategoryName(array $product, ?string $genericCategoryId = null): string
    {
        // Buscar categoria do produto (a mais específica)
        $categoryId = $product['category_id'] ?? null;
        
        if (!$categoryId) {
            return 'GERAL';
        }
        
        try {
            $category = \App\Models\Category::find($categoryId);
            if (!$category) {
                return 'GERAL';
            }
            
            // Se não temos categoria genérica, usar a categoria do produto diretamente
            if (!$genericCategoryId) {
                return strtoupper($category->name);
            }
            
            // Buscar a categoria genérica para entender a estrutura
            $genericCategory = \App\Models\Category::find($genericCategoryId);
            if (!$genericCategory) {
                return strtoupper($category->name);
            }
            
            // Se a categoria do produto é a mesma que a genérica, retornar "GERAL"
            if ($categoryId === $genericCategoryId) {
                return 'GERAL';
            }
            
            // Buscar na hierarquia do produto a categoria que tem level_name = "subcategoria"
            // E que seja filha (direta ou indireta) da categoria genérica
            $hierarchy = $category->getFullHierarchy();
            
            // Procurar pela categoria genérica na hierarquia
            $genericFound = false;
            $subcategoryCandidate = null;
            
            foreach ($hierarchy as $cat) {
                // Encontramos a categoria genérica
                if ($cat->id === $genericCategoryId) {
                    $genericFound = true;
                    continue;
                }
                
                // Se já passamos pela categoria genérica, procurar pela subcategoria
                if ($genericFound) {
                    // Procurar o primeiro nível após a categoria genérica que tem level_name = "subcategoria"
                    // OU se não tiver, pegar o próximo nível
                    if ($cat->level_name === 'subcategoria') {
                        return strtoupper($cat->name);
                    }
                    
                    // Guardar como candidato (primeiro após a categoria genérica)
                    if (!$subcategoryCandidate) {
                        $subcategoryCandidate = $cat;
                    }
                }
            }
            
            // Se encontramos um candidato (primeiro nível após categoria genérica), usar ele
            if ($subcategoryCandidate) {
                return strtoupper($subcategoryCandidate->name);
            }
            
            // Se não encontramos, usar o nome da categoria do produto
            return strtoupper($category->name);
            
        } catch (\Exception $e) {
            Log::warning("Erro ao buscar subcategoria", [
                'category_id' => $categoryId,
                'generic_category_id' => $genericCategoryId,
                'error' => $e->getMessage()
            ]);
        }
        
        return 'GERAL';
    }
}
