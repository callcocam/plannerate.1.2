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
     * @param string|null $startDate - Data inicial
     * @param string|null $endDate - Data final
     * @param int|null $storeId - ID da loja
     * @return array - Resultado da distribuição
     */
    public function distributeByHierarchy(
        Gondola $gondola,
        array $allProducts,
        array $weights,
        array $targetStockParams,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null
    ): array {
        Log::info("🚀 Iniciando distribuição hierárquica por categoria", [
            'gondola_id' => $gondola->id,
            'gondola_name' => $gondola->name,
            'total_products' => count($allProducts),
            'weights' => $weights,
            'targetStockParams' => $targetStockParams,
            'date_range' => ['start' => $startDate, 'end' => $endDate],
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
            $abcGlobal
        );

        // 3. Preparar prateleiras em ordem linear
        $linearShelves = $this->getAllShelvesInLinearOrder($gondola);
        $currentShelfIndex = 0;

        $stats = [
            'total_products' => 0,
            'placed_products' => 0,
            'failed_products' => 0,
            'categories_processed' => [],
            'gondola_id' => $gondola->id
        ];

        // 4. Para cada categoria (em ordem de prioridade)
        foreach ($categoriesPriority as $categoryName => $categoryId) {

            Log::info("📦 Processando categoria", [
                'category' => $categoryName,
                'category_id' => $categoryId
            ]);

            // 5. Buscar TODOS os produtos dessa categoria
            if ($categoryId) {
                $categoryProducts = $this->categoryService->getAllProductsFromCategory($categoryId);
            } else {
                // Categoria sem ID (ex: "SEM_CATEGORIA")
                $categoryProducts = array_filter($allProducts, function($p) use ($categoryName) {
                    return $this->categoryService->extractCategoryFromProduct($p) === $categoryName;
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

            // 7. Ordenar por ABC local (A primeiro, depois B, depois C)
            $categoryProducts = $this->abcHierarchical->sortProductsByABC($categoryProducts, $abcLocal);

            // 8. Calcular Target Stock para todos os produtos da categoria
            $productIds = array_column($categoryProducts, 'id');
            $targetStockResults = $this->targetStockService->analyze(
                $productIds,
                $startDate,
                $endDate,
                $storeId
            );

            // 9. Distribuir categoria (usar ABC GLOBAL para target stock, ABC LOCAL para ordenação)
            $categoryStats = $this->distributeCategorySequentially(
                $categoryProducts,
                $abcLocal,        // ABC Local para ordenação
                $abcGlobal,       // ABC Global para target stock (mesma classificação do modal)
                $targetStockResults,
                $targetStockParams,
                $linearShelves,
                $currentShelfIndex,
                $categoryName
            );

            $stats['total_products'] += $categoryStats['total'];
            $stats['placed_products'] += $categoryStats['placed'];
            $stats['failed_products'] += $categoryStats['failed'];
            $stats['categories_processed'][$categoryName] = $categoryStats;
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

            // Calcular facing baseado no target stock
            $shelfDepth = $linearShelves[$currentShelfIndex]['shelf_depth'] ?? 40;
            $facing = $this->facingCalculator->calculateFacing(
                $product,
                $targetStock,
                $shelfDepth
            );

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

        // Tentar colocar nas prateleiras disponíveis
        while ($remainingFacing > 0 && $currentShelfIndex < count($linearShelves)) {
            $shelfData = &$linearShelves[$currentShelfIndex];
            $spaceAvailable = $shelfData['available_width'] - $shelfData['used_width'];

            // Calcular quantos facings cabem nesta prateleira
            $maxFacingInShelf = (int) floor($spaceAvailable / $productWidth);

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
}
