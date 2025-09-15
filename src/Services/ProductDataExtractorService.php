<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Services;

use Illuminate\Support\Facades\Log;

/**
 * Serviço responsável por extrair e processar dados de produtos
 * Centraliza toda a lógica de manipulação de dados de produtos no planograma
 */
class ProductDataExtractorService
{
    /**
     * Extrai largura do produto (sem fallback - deve ter dimensões válidas)
     */
    public function getProductWidth(array $productData): float
    {
        if (!isset($productData['width']) || $productData['width'] <= 0) {
            throw new \InvalidArgumentException("Produto deve ter largura válida > 0");
        }
        return floatval($productData['width']);
    }

    /**
     * Calcula largura média dos produtos (apenas produtos com dimensões válidas)
     */
    public function calculateAverageProductWidth(array $products): float
    {
        if (empty($products)) {
            throw new \InvalidArgumentException("Lista de produtos não pode estar vazia");
        }
        
        $totalWidth = 0;
        $validWidths = 0;
        
        foreach ($products as $product) {
            $productData = $product['product'] ?? [];
            try {
                $width = $this->getProductWidth($productData);
                $totalWidth += $width;
                $validWidths++;
            } catch (\InvalidArgumentException $e) {
                // Pular produtos sem dimensões válidas
                continue;
            }
        }
        
        if ($validWidths === 0) {
            throw new \InvalidArgumentException("Nenhum produto com dimensões válidas encontrado");
        }
        
        $avgWidth = $totalWidth / $validWidths;
        
        Log::info("Largura média calculada", [
            'total_products' => count($products),
            'valid_widths' => $validWidths,
            'avg_width' => round($avgWidth, 2),
            'total_width_sum' => $totalWidth
        ]);
        
        return $avgWidth;
    }

    /**
     * Enricha scores com dados dos produtos (incluindo dimensões)
     */
    public function enrichScoresWithProductData(array $scores, array $productsData): array
    {
        // Criar mapa de produtos por ID para acesso rápido
        $productMap = collect($productsData)->keyBy('id');
        
        $enrichedScores = [];
        foreach ($scores as $scoreData) {
            // Suportar tanto 'product_id' quanto 'id' para compatibilidade
            $productId = $scoreData['product_id'] ?? $scoreData['id'];
            $product = $productMap->get($productId);
            
            if ($product) {
                // Verificar se o produto tem dimensões válidas
                if (!isset($product['dimensions']) || 
                    !isset($product['dimensions']['width']) || 
                    !$product['dimensions']['width'] || 
                    $product['dimensions']['width'] <= 0) {
                    
                    Log::warning("❌ Produto ignorado - sem dimensões válidas", [
                        'product_id' => $productId,
                        'product_name' => $product['name'] ?? 'Sem nome',
                        'dimensions' => $product['dimensions'] ?? 'null'
                    ]);
                    continue; // Pular produtos sem dimensões válidas
                }
                
                // Adicionar dados do produto ao score (apenas com dimensões reais)
                $scoreData['product_id'] = $productId;
                $scoreData['product'] = [
                    'id' => $product['id'],
                    'name' => $product['name'] ?? 'Produto sem nome',
                    'ean' => $product['ean'] ?? '',
                    'width' => (float) $product['dimensions']['width'],
                    'height' => (float) $product['dimensions']['height'],
                    'depth' => (float) $product['dimensions']['depth'],
                ];
                
                Log::info("Produto enrichado com dimensões", [
                    'product_id' => $productId,
                    'width' => $scoreData['product']['width'],
                    'has_dimensions' => isset($product['dimensions']) && $product['dimensions'] !== null
                ]);
            } else {
                // Produto não encontrado - pular completamente
                Log::warning("❌ Produto não encontrado - ignorando", [
                    'product_id' => $productId
                ]);
                continue; // Pular produtos não encontrados
            }
            
            $enrichedScores[] = $scoreData;
        }
        
        Log::info("Scores enrichados com sucesso", [
            'total_scores' => count($enrichedScores),
            'avg_width' => collect($enrichedScores)->avg('product.width'),
            'products_with_real_dimensions' => collect($enrichedScores)->filter(function($score) {
                return isset($score['product']['width']) && $score['product']['width'] > 0; // Tem dimensões válidas
            })->count()
        ]);
        
        return $enrichedScores;
    }

    /**
     * Obtém IDs dos produtos já usados na gôndola atual
     * Para filtrar apenas produtos "unused" (igual à sidebar)
     */
    public function getProductIdsInGondola($gondola): array
    {
        $productIds = [];
        
        foreach ($gondola->sections as $section) {
            foreach ($section->shelves as $shelf) {
                foreach ($shelf->segments as $segment) {
                    foreach ($segment->layers as $layer) {
                        if ($layer->product_id) {
                            $productIds[] = $layer->product_id;
                        }
                    }
                }
            }
        }
        
        return array_unique($productIds);
    }

    /**
     * Aplica filtros dinâmicos vindos do modal AutoGenerateModal.vue
     */
    public function applyDynamicFilters($productsQuery, array $filters, $gondola): void
    {
        Log::info("🎛️ Aplicando filtros dinâmicos do modal", [
            'filters_received' => $filters,
            'gondola_id' => $gondola->id
        ]);
        
        // Contar produtos antes dos filtros
        $countBefore = $productsQuery->count();
        Log::info("📊 Produtos antes dos filtros dinâmicos: {$countBefore}");
        
        // FILTRO 1: Produtos com dimensões (dimension)
        if ($filters['dimension'] ?? true) {
            $productsQuery->whereHas('dimensions');
            $countAfterDimensions = $productsQuery->count();
            Log::info("📊 Após filtro dimensões: {$countAfterDimensions}");
        }
        
        // FILTRO 2: Produtos não utilizados na gôndola (unusedOnly)  
        if ($filters['unusedOnly'] ?? true) {
            $productIdsInGondola = $this->getProductIdsInGondola($gondola);
            Log::info("📊 Produtos já na gôndola: " . count($productIdsInGondola));
            if (!empty($productIdsInGondola)) {
                $productsQuery->whereNotIn('id', $productIdsInGondola);
                $countAfterUnused = $productsQuery->count();
                Log::info("📊 Após filtro não utilizados: {$countAfterUnused}");
            }
        }
        
        // FILTRO 3: Produtos com histórico de vendas (sales)
        if ($filters['sales'] ?? true) {
            // Filtrar produtos com status de vendas ativo ou nulo (padrão)
            $productsQuery->where(function($query) {
                $query->where('sales_status', 'active')
                      ->orWhereNull('sales_status');
            });
            $countAfterSales = $productsQuery->count();
            Log::info("📊 Após filtro vendas: {$countAfterSales}");
        }
        
        // FILTRO 4: Produtos penduráveis (hangable)
        if ($filters['hangable'] ?? false) {
            $productsQuery->where('hangable', true);
        } else {
            // Se não incluir penduráveis, excluir eles
            $productsQuery->where('hangable', false);
        }
        $countAfterHangable = $productsQuery->count();
        Log::info("📊 Após filtro penduráveis: {$countAfterHangable}");
        
        // FILTRO 5: Produtos empilháveis (stackable)
        if ($filters['stackable'] ?? false) {
            $productsQuery->where('stackable', true);
        } else {
            // Se não incluir empilháveis, excluir eles
            $productsQuery->where('stackable', false);
        }
        $countAfterStackable = $productsQuery->count();
        Log::info("📊 Após filtro empilháveis: {$countAfterStackable}");
        
        Log::info("✅ Filtros dinâmicos aplicados com sucesso");
        
        // Filtros dinâmicos aplicados com sucesso
    }

    /**
     * Obtém todos os descendentes de uma categoria com limite de profundidade
     * Copiado do ProductController para manter consistência
     */
    public function getCategoryDescendants($category, int $maxDepth = 2, int $currentDepth = 0): array
    {
        $descendants = [];

        // Limitar profundidade para evitar busca excessiva
        if ($currentDepth >= $maxDepth) {
            Log::info("🛑 Limite de profundidade atingido: {$currentDepth}/{$maxDepth}");
            return $descendants;
        }

        // Busca filhos diretos
        if (is_string($category)) {
            $children = \App\Models\Category::where('category_id', $category)->get();
        } else {
            $children = \App\Models\Category::where('category_id', $category->id)->get();
        }

        Log::info("🔍 Profundidade {$currentDepth}: Encontradas " . $children->count() . " categorias filhas");

        foreach ($children as $child) {
            $descendants[] = $child->id;
            // Recursivamente busca descendentes dos filhos (com limite de profundidade)
            $descendants = array_merge($descendants, $this->getCategoryDescendants($child, $maxDepth, $currentDepth + 1));
        }

        return $descendants;
    }
}
