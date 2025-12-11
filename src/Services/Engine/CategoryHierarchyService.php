<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Services\Engine;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de Hierarquia de Categorias
 * 
 * Responsável por gerenciar a hierarquia mercadológica e buscar produtos
 * por categoria, incluindo todas as subcategorias descendentes.
 */
class CategoryHierarchyService
{
    /**
     * Extrai o nome da categoria mercadológica do produto
     * Busca categorias baseado no nível hierárquico configurado no planograma
     * Ex: "REFRIGERANTE", "SUCO", "ÁGUA", "CAFÉ", "AÇÚCAR"
     * 
     * @param array $product - Produto com category_id
     * @param string|null $mercadologicoLevel - Nível mercadológico (padrão: 'categoria')
     * @return string - Nome da categoria
     */
    public function extractCategoryFromProduct(array $product, ?string $mercadologicoLevel = 'categoria'): string
    {
        // Buscar pela category_id
        if (isset($product['category_id'])) {
            $category = Category::find($product['category_id']);
            
            if ($category) {
                // Buscar categoria baseado no nível mercadológico configurado
                $targetCategory = $this->getCategoryByLevel($category, $mercadologicoLevel);
                
                Log::info("📁 Categoria mercadológica extraída", [
                    'product_id' => $product['id'],
                    'product_name' => $product['name'] ?? 'N/A',
                    'original_category' => $category->name,
                    'target_category' => $targetCategory->name,
                    'target_level' => $targetCategory->level_name ?? 'N/A',
                    'requested_level' => $mercadologicoLevel
                ]);
                
                return $targetCategory->name;
            }
        }
        
        // Se não encontrou nada, retorna categoria padrão
        Log::warning("⚠️ Produto sem categoria identificável", [
            'product_id' => $product['id'],
            'product_name' => $product['name'] ?? 'N/A'
        ]);
        
        return 'SEM_CATEGORIA';
    }

    /**
     * Extrai o ID da categoria genérica de um produto
     * 
     * @param array $product - Produto
     * @return string|null - ID da categoria genérica
     */
    public function extractGenericCategoryId(array $product): ?string
    {
        // Se não tem categoria, retornar null
        if (empty($product['category_id'])) {
            return null;
        }
        
        // Buscar a categoria do produto
        $category = Category::find($product['category_id']);
        
        if (!$category) {
            return null;
        }
        
        // Buscar a categoria genérica (level_name = 'categoria')
        $genericCategory = $this->getGenericCategory($category);
        
        return $genericCategory->id;
    }

    /**
     * Busca a categoria genérica (level_name = 'categoria' - REFRI, SUCO, AÇUCAR, etc.)
     * 
     * @param Category $category - Categoria do produto
     * @return Category - Categoria genérica
     */
    protected function getGenericCategory(Category $category): Category
    {
        return $this->getCategoryByLevel($category, 'categoria');
    }
    
    /**
     * Busca categoria baseado no nível mercadológico configurado
     * 
     * @param Category $category - Categoria do produto
     * @param string $targetLevel - Nível mercadológico desejado (ex: 'categoria', 'subcategoria', 'departamento')
     * @return Category - Categoria do nível especificado
     */
    protected function getCategoryByLevel(Category $category, string $targetLevel): Category
    {
        // Se já está no nível desejado, retornar ela mesma
        if ($category->level_name === $targetLevel) {
            return $category;
        }
        
        // Subir na hierarquia até encontrar o nível desejado
        $current = $category;
        $maxIterations = 10; // Prevenir loop infinito
        $iterations = 0;
        
        while ($current->category_id && $iterations < $maxIterations) {
            $parent = Category::find($current->category_id);
            
            if (!$parent) {
                // Não tem mais pai, retornar a categoria atual
                break;
            }
            
            // Se encontrou o nível desejado, retornar
            if ($parent->level_name === $targetLevel) {
                return $parent;
            }
            
            $current = $parent;
            $iterations++;
        }
        
        // Se não encontrou o nível desejado, retornar a categoria original
        return $category;
    }

    /**
     * Extrai o tamanho/volume do nome do produto
     * 
     * @param string $productName - Nome do produto
     * @return float - Volume em litros (para comparação)
     */
    public function extractVolumeFromName(string $productName): float
    {
        // Regex para capturar número + unidade (2L, 3.5Lt, 500ML, 5KG, etc.)
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(L|LT|ML|G|KG|UN)/i', $productName, $matches)) {
            $value = (float) str_replace(',', '.', $matches[1]);
            $unit = strtoupper($matches[2]);
            
            // Converter tudo para litros (ou kg) para comparação
            switch ($unit) {
                case 'L':
                case 'LT':
                    return $value;
                case 'ML':
                    return $value / 1000; // 500ml = 0.5L
                case 'KG':
                    return $value;
                case 'G':
                    return $value / 1000; // 500g = 0.5kg
                case 'UN':
                    return $value;
                default:
                    return 0;
            }
        }
        
        return 0; // Sem tamanho identificado
    }

    /**
     * Busca TODOS os produtos de uma categoria (incluindo subcategorias)
     * 
     * @param string $categoryId - ID da categoria
     * @return array - Lista de produtos com dimensões válidas
     */
    public function getAllProductsFromCategory(string $categoryId): array
    {
        // 1. Buscar categoria
        $category = Category::find($categoryId);
        
        if (!$category) {
            Log::warning("⚠️ Categoria não encontrada", [
                'category_id' => $categoryId
            ]);
            return [];
        }
        
        // 2. Buscar descendentes (subcategorias) usando método existente do model
        $descendants = $category->getAllDescendantIds();
        
        // Incluir a categoria pai também
        $allCategoryIds = array_merge([$categoryId], $descendants);
        
        Log::info("🔍 Buscando produtos da categoria", [
            'category_name' => $category->name,
            'category_id' => $categoryId,
            'descendants_count' => count($descendants),
            'total_category_ids' => count($allCategoryIds)
        ]);
        
        // 3. Buscar produtos com dimensões válidas
        $products = Product::whereIn('category_id', $allCategoryIds)
            ->where('status', 'published') // Status correto no banco
            ->whereHas('dimensions', function($q) {
                $q->where('width', '>', 0)
                  ->where('height', '>', 0)
                  ->where('depth', '>', 0);
            })
            ->with('dimensions')
            ->get()
            ->map(function ($product) {
                $array = $product->toArray();
                // Garantir que os acessors de dimensão estejam disponíveis
                $array['width'] = $product->width;
                $array['height'] = $product->height;
                $array['depth'] = $product->depth;
                return $array;
            })
            ->toArray();
        
        Log::info("✅ Produtos da categoria obtidos", [
            'category_name' => $category->name,
            'category_id' => $categoryId,
            'products_found' => count($products),
            'category_ids_searched' => count($allCategoryIds)
        ]);
        
        return $products;
    }

    /**
     * Busca recursivamente todas as subcategorias (HELPER)
     * 
     * NOTA: Este método é um helper interno. O método getAllDescendantIds()
     * do model Category já faz isso de forma otimizada com cache.
     * 
     * @param string $categoryId - ID da categoria pai
     * @return array - IDs de todas as subcategorias
     */
    protected function getCategoryDescendants(string $categoryId): array
    {
        $descendants = [];
        
        // Buscar filhos diretos
        $children = Category::where('category_id', $categoryId)->get();
        
        foreach ($children as $child) {
            $descendants[] = $child->id;
            
            // Buscar descendentes dos filhos (recursivo)
            $childDescendants = $this->getCategoryDescendants($child->id);
            $descendants = array_merge($descendants, $childDescendants);
        }
        
        return array_unique($descendants);
    }

    /**
     * Busca a categoria principal de um produto (nível mais alto da hierarquia)
     * 
     * @param array $product - Produto
     * @return Category|null - Categoria raiz ou null se não encontrada
     */
    public function getRootCategory(array $product): ?Category
    {
        if (!isset($product['category_id'])) {
            return null;
        }
        
        $category = Category::find($product['category_id']);
        
        if (!$category) {
            return null;
        }
        
        // Subir na hierarquia até encontrar a raiz
        $current = $category;
        while ($current->parent) {
            $current = $current->parent;
        }
        
        return $current;
    }

    /**
     * Agrupa produtos por categoria mercadológica
     * 
     * @param array $products - Lista de produtos
     * @return array - ['CATEGORIA_NAME' => ['id' => 'cat-123', 'products' => [...]]]
     */
    public function groupProductsByCategory(array $products): array
    {
        $grouped = [];
        
        foreach ($products as $product) {
            $categoryName = $this->extractCategoryFromProduct($product);
            $categoryId = $product['category_id'] ?? null;
            
            if (!isset($grouped[$categoryName])) {
                $grouped[$categoryName] = [
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                    'products' => []
                ];
            }
            
            $grouped[$categoryName]['products'][] = $product;
        }
        
        Log::info("📊 Produtos agrupados por categoria", [
            'total_categories' => count($grouped),
            'categories' => array_keys($grouped),
            'products_per_category' => array_map(fn($g) => count($g['products']), $grouped)
        ]);
        
        return $grouped;
    }
}
