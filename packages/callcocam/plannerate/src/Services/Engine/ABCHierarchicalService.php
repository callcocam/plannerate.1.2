<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Services\Engine;

use Callcocam\Plannerate\Services\Analysis\ABCAnalysisService;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de Análise ABC Hierárquica
 * 
 * Responsável por executar ABC Global e ABC Local por categoria,
 * priorizando categorias pelo maior score A.
 */
class ABCHierarchicalService
{
    public function __construct(
        private ABCAnalysisService $abcService,
        private CategoryHierarchyService $categoryService
    ) {}

    /**
     * Executa análise ABC de TODOS os produtos (ABC Global)
     * 
     * @param array $allProducts - Lista de todos os produtos
     * @param array $weights - Pesos ABC (quantity, value, margin)
     * @param string|null $startDate - Data inicial
     * @param string|null $endDate - Data final
     * @param int|null $storeId - ID da loja
     * @return array - Resultado do ABC global
     */
    public function executeGlobalABC(
        array $allProducts,
        array $weights,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null
    ): array {
        $productIds = array_column($allProducts, 'id');
        
        Log::info("🌍 Executando ABC GLOBAL", [
            'total_products' => count($productIds),
            'weights' => $weights,
            'date_range' => [$startDate, $endDate]
        ]);
        
        // Usar ABCAnalysisService existente
        $abcResults = $this->abcService->analyze(
            $productIds,
            $startDate,
            $endDate,
            $storeId,
            $weights
        );
        
        $classA = count(array_filter($abcResults, fn($p) => ($p['abc_class'] ?? '') === 'A'));
        $classB = count(array_filter($abcResults, fn($p) => ($p['abc_class'] ?? '') === 'B'));
        $classC = count(array_filter($abcResults, fn($p) => ($p['abc_class'] ?? '') === 'C'));
        
        Log::info("✅ ABC Global concluído", [
            'products_analyzed' => count($abcResults),
            'class_A' => $classA,
            'class_B' => $classB,
            'class_C' => $classC,
            'class_A_percentage' => round(($classA / count($abcResults)) * 100, 1) . '%'
        ]);
        
        return $abcResults;
    }

    /**
     * Identifica categorias e ordena pelo maior score A de cada
     * 
     * @param array $allProducts - Lista de produtos
     * @param array $abcGlobal - Resultado do ABC global
     * @return array - ['FARINHA' => 'cat-456', 'ARROZ' => 'cat-789', ...]
     */
    public function extractCategoriesPriority(array $allProducts, array $abcGlobal): array
    {
        Log::info("📊 Extraindo prioridade de categorias pelo ABC Global");
        
        // 1. Agrupar produtos por categoria
        $categoriesMap = [];
        
        foreach ($allProducts as $product) {
            $categoryName = $this->categoryService->extractCategoryFromProduct($product);
            
            if (!isset($categoriesMap[$categoryName])) {
                $categoriesMap[$categoryName] = [
                    'category_id' => $product['category_id'] ?? null,
                    'category_name' => $categoryName,
                    'products' => [],
                    'max_score' => 0,
                    'total_score' => 0,
                    'count_A' => 0
                ];
            }
            
            $categoriesMap[$categoryName]['products'][] = $product;
        }
        
        // 2. Para cada categoria, encontrar o maior score A e contabilizar
        foreach ($categoriesMap as $categoryName => &$data) {
            $maxScore = 0;
            $totalScore = 0;
            $countA = 0;
            
            foreach ($data['products'] as $product) {
                $abcData = collect($abcGlobal)->firstWhere('product_id', $product['id']);
                
                if ($abcData) {
                    $score = $abcData['composite_score'] ?? 0;
                    $totalScore += $score;
                    
                    if (($abcData['abc_class'] ?? '') === 'A') {
                        $countA++;
                        if ($score > $maxScore) {
                            $maxScore = $score;
                        }
                    }
                }
            }
            
            $data['max_score'] = $maxScore;
            $data['total_score'] = $totalScore;
            $data['count_A'] = $countA;
            $data['avg_score'] = count($data['products']) > 0 ? $totalScore / count($data['products']) : 0;
        }
        
        // 3. Ordenar categorias por maior score A (decrescente)
        uasort($categoriesMap, function($a, $b) {
            // Prioriza por max_score, depois por count_A, depois por avg_score
            if ($b['max_score'] !== $a['max_score']) {
                return $b['max_score'] <=> $a['max_score'];
            }
            if ($b['count_A'] !== $a['count_A']) {
                return $b['count_A'] <=> $a['count_A'];
            }
            return $b['avg_score'] <=> $a['avg_score'];
        });
        
        // 4. Retornar apenas nome => category_id
        $result = [];
        foreach ($categoriesMap as $categoryName => $data) {
            $result[$categoryName] = $data['category_id'];
            
            Log::info("📦 Categoria priorizada", [
                'category' => $categoryName,
                'category_id' => $data['category_id'],
                'max_score_A' => round($data['max_score'], 3),
                'count_A' => $data['count_A'],
                'avg_score' => round($data['avg_score'], 3),
                'total_products' => count($data['products'])
            ]);
        }
        
        Log::info("✅ Categorias priorizadas", [
            'categories_count' => count($result),
            'priority_order' => array_keys($result)
        ]);
        
        return $result;
    }

    /**
     * Executa ABC apenas dos produtos de uma categoria (ABC Local)
     * 
     * @param array $categoryProducts - Produtos da categoria
     * @param array $weights - Pesos ABC
     * @param string|null $startDate - Data inicial
     * @param string|null $endDate - Data final
     * @param int|null $storeId - ID da loja
     * @return array - Resultado do ABC local
     */
    public function executeLocalABC(
        array $categoryProducts,
        array $weights,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null
    ): array {
        $productIds = array_column($categoryProducts, 'id');
        
        Log::info("📍 Executando ABC LOCAL", [
            'products_count' => count($productIds),
            'weights' => $weights
        ]);
        
        // Usar ABCAnalysisService existente
        $abcResults = $this->abcService->analyze(
            $productIds,
            $startDate,
            $endDate,
            $storeId,
            $weights
        );
        
        $classA = count(array_filter($abcResults, fn($p) => ($p['abc_class'] ?? '') === 'A'));
        $classB = count(array_filter($abcResults, fn($p) => ($p['abc_class'] ?? '') === 'B'));
        $classC = count(array_filter($abcResults, fn($p) => ($p['abc_class'] ?? '') === 'C'));
        
        Log::info("✅ ABC Local concluído", [
            'products_analyzed' => count($abcResults),
            'class_A' => $classA,
            'class_B' => $classB,
            'class_C' => $classC
        ]);
        
        return $abcResults;
    }

    /**
     * Ordena produtos por score ABC (decrescente)
     * 
     * @param array $products - Lista de produtos
     * @param array $abcResults - Resultados do ABC
     * @return array - Produtos ordenados
     */
    public function sortProductsByABC(array $products, array $abcResults): array
    {
        usort($products, function($a, $b) use ($abcResults) {
            $scoreA = collect($abcResults)->firstWhere('product_id', $a['id'])['composite_score'] ?? 0;
            $scoreB = collect($abcResults)->firstWhere('product_id', $b['id'])['composite_score'] ?? 0;
            return $scoreB <=> $scoreA;
        });
        
        return $products;
    }
}