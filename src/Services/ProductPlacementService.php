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
     * 🎯 NOVO SISTEMA: Distribuição Linear de Produtos
     * 
     * Distribui produtos sequencialmente por prateleiras, eliminando desperdício de espaço
     * causado pela alocação fixa de módulos por categoria.
     * 
     * ALGORITMO:
     * 1. Manter lógica ABC por categoria
     * 2. Distribuir categoria completa por prateleiras lineares
     * 3. Nunca alterar facing calculado
     * 4. Falha em vez de adaptação
     */
    public function placeProductsSequentially($gondola, array $classifiedProducts, array $structure): array
    {
        $productsPlaced = 0;
        $segmentsUsed = 0;
        $totalProductPlacements = 0;
        $failedProducts = [];
        
        // 🎯 CONVERTER PRODUTOS CLASSIFICADOS EM LISTA ÚNICA
        $allProducts = [];
        
        if (isset($classifiedProducts['A']) || isset($classifiedProducts['B']) || isset($classifiedProducts['C'])) {
            foreach (['A', 'B', 'C'] as $class) {
                if (isset($classifiedProducts[$class])) {
                    $allProducts = array_merge($allProducts, $classifiedProducts[$class]);
                }
            }
        } else {
            $allProducts = $classifiedProducts;
        }
        
        Log::info("🎯 INICIANDO DISTRIBUIÇÃO LINEAR DE PRODUTOS", [
            'total_products' => count($allProducts),
            'strategy' => 'LINEAR: ABC por categoria → Distribuição sequencial por prateleiras'
        ]);
        
        // 🎯 OBTER TODAS AS PRATELEIRAS EM ORDEM LINEAR
        $allShelves = $this->getAllShelvesLinearOrder($gondola);
        
        Log::info("📋 Prateleiras em ordem linear obtidas", [
            'total_shelves' => $allShelves->count(),
            'modules_count' => $gondola->sections->count()
        ]);
        
        // 🎯 ALGORITMO PRINCIPAL: Processar categorias por ordem de ABC global
        $remainingProducts = $allProducts;
        
        while (!empty($remainingProducts)) {
            // 1.1. Encontrar categoria com maior ABC global
            $topProduct = $this->getProductWithHighestAbc($remainingProducts);
            $currentCategory = $this->getProductCategory($topProduct);
            
        Log::info("📦 Processando categoria prioritária", [
            'category' => $currentCategory,
                'top_product' => $topProduct['product']['name'] ?? 'N/A',
            'abc_score' => $topProduct['final_score'] ?? $topProduct['priority_score'] ?? 0,
            'remaining_products' => count($remainingProducts)
        ]);
            
            // 1.2. Extrair TODOS os produtos desta categoria
            $categoryProducts = $this->filterProductsByCategory($remainingProducts, $currentCategory);
            
            // 1.3. Ordenar por ABC interno da categoria
            $categoryProducts = $this->sortByInternalAbc($categoryProducts);
            
            Log::info("🔄 Categoria extraída e ordenada", [
                'category' => $currentCategory,
                'products_count' => count($categoryProducts),
                'first_product' => $categoryProducts[0]['product']['name'] ?? 'N/A'
            ]);
            
            // 1.4. Distribuir categoria completa por prateleiras lineares
            $categoryResult = $this->distributeCategoryLinear($allShelves, $categoryProducts, $currentCategory);
            
            // 1.5. Consolidar resultados
            $productsPlaced += $categoryResult['products_placed'];
            $totalProductPlacements += $categoryResult['total_placements'];
            $segmentsUsed += $categoryResult['segments_used'];
            $failedProducts = array_merge($failedProducts, $categoryResult['failed_products']);
            
            // 1.6. Remover categoria processada da lista global
            $remainingProducts = $this->removeCategoryFromList($remainingProducts, $currentCategory);
            
            Log::info("✅ Categoria processada", [
                'category' => $currentCategory,
                'products_placed' => $categoryResult['products_placed'],
                'products_failed' => count($categoryResult['failed_products']),
                'remaining_categories' => $this->countRemainingCategories($remainingProducts)
            ]);
        }
        
        Log::info("🎯 DISTRIBUIÇÃO LINEAR CONCLUÍDA", [
            'total_products_processed' => count($allProducts),
            'products_placed' => $productsPlaced,
            'total_placements' => $totalProductPlacements,
            'segments_used' => $segmentsUsed,
            'products_failed' => count($failedProducts),
            'success_rate' => count($allProducts) > 0 ? round(($productsPlaced / count($allProducts)) * 100, 1) . '%' : '0%'
        ]);

        return [
            'products_placed' => $productsPlaced,
            'segments_used' => $segmentsUsed,
            'total_placements' => $totalProductPlacements,
            'failed_products' => $failedProducts
        ];
    }

    /**
     * 🎯 NOVO: Obter todas as prateleiras em ordem linear
     * 
     * Ordem resultante:
     * - Módulo 1: Prateleira 1, 2, 3, 4
     * - Módulo 2: Prateleira 1, 2, 3, 4
     * - Módulo 3: Prateleira 1, 2, 3, 4
     */
    private function getAllShelvesLinearOrder($gondola): \Illuminate\Support\Collection
    {
        $linearShelves = collect();
        
        // Pegar módulos em ordem: 1, 2, 3, 4...
        $sections = $gondola->sections()
            ->orderBy('ordering')
            ->with(['shelves' => function($query) {
                $query->orderBy('ordering');
            }])
            ->get();
        
        // Para cada módulo, adicionar prateleiras em ordem
        foreach ($sections as $section) {
            foreach ($section->shelves as $shelf) {
                $linearShelves->push($shelf);
            }
        }
        
        Log::info("📋 Ordem linear das prateleiras criada", [
            'total_modules' => $sections->count(),
            'total_shelves' => $linearShelves->count(),
            'shelves_per_module' => $sections->count() > 0 ? round($linearShelves->count() / $sections->count(), 1) : 0
        ]);
        
        return $linearShelves;
    }

    /**
     * 🎯 NOVO: Encontrar produto com maior ABC global
     */
    private function getProductWithHighestAbc(array $products): array
    {
        $topProduct = null;
        $highestScore = -1;
        
        foreach ($products as $product) {
            // Usar final_score que é o campo correto dos dados processados
            $score = $product['final_score'] ?? $product['priority_score'] ?? 0;
            if ($score > $highestScore) {
                $highestScore = $score;
                $topProduct = $product;
            }
        }
        
        return $topProduct ?? $products[0];
    }

    /**
     * 🎯 NOVO: Obter categoria do produto (primeira palavra do nome)
     */
    private function getProductCategory(array $product): string
    {
        $productName = strtoupper($product['product']['name'] ?? 'OUTROS');
        return explode(' ', $productName)[0];
    }

    /**
     * 🎯 NOVO: Filtrar produtos por categoria
     */
    private function filterProductsByCategory(array $products, string $category): array
    {
        return array_filter($products, function($product) use ($category) {
            return $this->getProductCategory($product) === $category;
        });
    }

    /**
     * 🎯 NOVO: Ordenar produtos por ABC interno da categoria
     */
    private function sortByInternalAbc(array $products): array
    {
        usort($products, function($a, $b) {
            // Usar final_score que é o campo correto dos dados processados
            $scoreA = $a['final_score'] ?? $a['priority_score'] ?? 0;
            $scoreB = $b['final_score'] ?? $b['priority_score'] ?? 0;
            return $scoreB <=> $scoreA; // Maior score primeiro
        });
        
        return $products;
    }

    /**
     * 🎯 NOVO: Remover categoria da lista global
     */
    private function removeCategoryFromList(array $products, string $category): array
    {
        return array_filter($products, function($product) use ($category) {
            return $this->getProductCategory($product) !== $category;
        });
    }

    /**
     * 🎯 NOVO: Contar categorias restantes
     */
    private function countRemainingCategories(array $products): int
    {
        $categories = [];
        foreach ($products as $product) {
            $categories[$this->getProductCategory($product)] = true;
        }
        return count($categories);
    }

    /**
     * 🎯 NOVO: Distribuir categoria completa por prateleiras lineares
     * 
     * NOVA ESTRATÉGIA: Distribuir facing entre múltiplas prateleiras
     * - Produto com 20 facings pode ser dividido: 5+5+5+5 em 4 prateleiras
     * - Maximiza aproveitamento do espaço disponível
     * - Mantém adjacência da categoria
     */
    private function distributeCategoryLinear(\Illuminate\Support\Collection $allShelves, array $categoryProducts, string $categoryName): array
    {
        $productsPlaced = 0;
        $totalPlacements = 0;
        $segmentsUsed = 0;
        $failedProducts = [];
        
        Log::info("🎯 Iniciando distribuição linear da categoria", [
            'category' => $categoryName,
            'products_count' => count($categoryProducts),
            'shelves_available' => $allShelves->count()
        ]);
        
        foreach ($categoryProducts as $product) {
            $totalFacing = $product['intelligent_facing'] ?? 1; // NUNCA ALTERAR o total
            $width = $product['product']['width'] ?? 20;
            $productName = $product['product']['name'] ?? 'N/A';
            $remainingFacing = $totalFacing;
            $facingPlaced = 0;
            
            Log::info("📦 Tentando distribuir produto", [
                'category' => $categoryName,
                'product' => $productName,
                'total_facing' => $totalFacing,
                'width_cm' => $width,
                'total_width_needed' => $width * $totalFacing
            ]);
            
            // 🎯 NOVA LÓGICA: Distribuir facing entre múltiplas prateleiras
            foreach ($allShelves as $shelfIndex => $shelf) {
                if ($remainingFacing <= 0) break;
                
                $availableWidth = $this->getAvailableWidth($shelf);
                $maxFacingInShelf = floor($availableWidth / $width);
                
                if ($maxFacingInShelf > 0) {
                    $facingToPlace = min($maxFacingInShelf, $remainingFacing);
                    $success = $this->placeProductInShelf($shelf, $product, $facingToPlace);
                    
                    if ($success) {
                        $remainingFacing -= $facingToPlace;
                        $facingPlaced += $facingToPlace;
                        $totalPlacements += $facingToPlace;
                        $segmentsUsed++; // Conta cada novo segmento criado
                        
                        Log::info("✅ Facing parcial colocado", [
                            'category' => $categoryName,
                            'product' => $productName,
                            'shelf_module' => $shelf->section->ordering ?? 'N/A',
                            'shelf_position' => $shelf->ordering ?? 'N/A',
                            'facing_placed' => $facingToPlace,
                            'width_used' => $width * $facingToPlace,
                            'remaining_facing' => $remainingFacing,
                            'progress' => $facingPlaced . '/' . $totalFacing
                        ]);
                    }
                }
            }
            
            // Verificar resultado final do produto
            if ($remainingFacing == 0) {
                // Produto completamente distribuído
                $productsPlaced++;
                Log::info("🎯 Produto completamente distribuído", [
                    'category' => $categoryName,
                    'product' => $productName,
                    'total_facing' => $totalFacing,
                    'segments_created' => $facingPlaced > 0 ? ceil($facingPlaced) : 0
                ]);
            } else {
                // Produto parcialmente colocado ou falhou completamente
                if ($facingPlaced > 0) {
                    $productsPlaced++; // Considerar como colocado mesmo que parcial
                    Log::warning("⚠️ Produto parcialmente distribuído", [
                        'category' => $categoryName,
                        'product' => $productName,
                        'facing_placed' => $facingPlaced,
                        'facing_failed' => $remainingFacing,
                        'success_rate' => round(($facingPlaced / $totalFacing) * 100, 1) . '%'
                    ]);
                } else {
                    $failedProducts[] = $product;
                    $this->logProductFailure($product, 'Não coube em nenhuma prateleira', $categoryName);
                }
            }
        }
        
        Log::info("✅ Distribuição linear da categoria concluída", [
            'category' => $categoryName,
            'products_placed' => $productsPlaced,
            'products_failed' => count($failedProducts),
            'total_placements' => $totalPlacements,
            'segments_used' => $segmentsUsed,
            'success_rate' => count($categoryProducts) > 0 ? round(($productsPlaced / count($categoryProducts)) * 100, 1) . '%' : '0%'
        ]);
        
        return [
            'products_placed' => $productsPlaced,
            'total_placements' => $totalPlacements,
            'segments_used' => $segmentsUsed,
            'failed_products' => $failedProducts
        ];
    }

    /**
     * 🎯 NOVO: Calcular largura disponível na prateleira
     */
    private function getAvailableWidth($shelf): float
    {
        $shelfWidth = floatval($shelf->shelf_width ?? 125.0);
        $usedWidth = $this->calculateUsedWidthInShelf($shelf);
        return $shelfWidth - $usedWidth;
    }

    /**
     * 🎯 NOVO: Calcular largura já utilizada na prateleira
     */
    private function calculateUsedWidthInShelf($shelf): float
    {
        $segments = $shelf->segments()->with('layer.product')->get();
        $usedWidth = 0;
        
        foreach ($segments as $segment) {
            if ($segment->layer && $segment->layer->product_id && $segment->layer->product) {
                $product = $segment->layer->product;
                $quantity = intval($segment->layer->quantity ?? 1);
                
                if ($product->width && $product->width > 0) {
                $productWidth = floatval($product->width);
                $segmentUsedWidth = $productWidth * $quantity;
                $usedWidth += $segmentUsedWidth;
                }
            }
        }
        
        return $usedWidth;
    }
    
    /**
     * 🎯 NOVO: Colocar produto na prateleira
     */
    private function placeProductInShelf($shelf, array $product, int $facing): bool
    {
        try {
            $productWidth = $product['product']['width'] ?? 20;
        $requiredWidth = $productWidth * $facing;
            
            // Criar novo segmento para o produto
            $segment = $shelf->segments()->create([
                'tenant_id' => $shelf->tenant_id,
                'user_id' => $shelf->user_id,
                'width' => $requiredWidth,
                'ordering' => $shelf->segments()->count(),
                'quantity' => 1,
                'status' => 'published'
            ]);

            // Criar layer com o produto
            $segment->layer()->create([
                'tenant_id' => $segment->tenant_id,
                'user_id' => $segment->user_id,
                'product_id' => $product['product_id'],
                'quantity' => $facing,
                'status' => 'published'
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("❌ Erro ao colocar produto na prateleira", [
                'shelf_id' => $shelf->id,
                'product_id' => $product['product_id'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 🎯 NOVO: Log de falha do produto
     */
    private function logProductFailure(array $product, string $reason, string $category): void
    {
        Log::warning("❌ PRODUTO FALHOU", [
            'category' => $category,
            'product_id' => $product['product_id'],
            'product_name' => $product['product']['name'] ?? 'N/A',
            'facing_required' => $product['intelligent_facing'] ?? 1,
            'width_cm' => $product['product']['width'] ?? 20,
            'total_width_required' => ($product['product']['width'] ?? 20) * ($product['intelligent_facing'] ?? 1),
            'reason' => $reason,
            'policy' => 'NUNCA alterar facing - FALHA em vez de adaptação'
        ]);
    }
}