<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Services\Engine;

use Illuminate\Support\Facades\Log;

/**
 * Serviço de Cálculo de Facing
 * 
 * Calcula o número de frentes (facings) necessárias para um produto
 * baseado no estoque alvo (target stock) e nas dimensões da prateleira.
 */
class FacingCalculatorService
{
    /**
     * Calcula o facing de um produto baseado no target stock
     * 
     * Exemplo:
     * - Target Stock: 96 unidades
     * - Profundidade produto: 7 cm
     * - Profundidade prateleira: 40 cm
     * 
     * Resultado:
     * - Unidades por fileira: 40 / 7 = 5,71 → 5 unidades
     * - Fileiras necessárias: 96 / 5 = 19,2 → 20 fileiras
     * - Facing = 20 frentes
     * 
     * @param array $product - Produto com dimensões (width, height, depth)
     * @param int $targetStock - Estoque alvo calculado
     * @param float $shelfDepth - Profundidade da prateleira (cm), padrão 40cm
     * @return int - Número de facings (frentes)
     */
    public function calculateFacing(
        array $product,
        int $targetStock,
        float $shelfDepth = 40
    ): int {
        $productDepth = $product['depth'] ?? 1;
        $productName = $product['name'] ?? 'Produto sem nome';
        
        // Validações
        if ($productDepth <= 0) {
            Log::warning("⚠️ Produto com profundidade inválida", [
                'product_id' => $product['id'] ?? 'N/A',
                'product_name' => $productName,
                'depth' => $productDepth
            ]);
            return 1; // Mínimo 1 facing
        }
        
        if ($targetStock <= 0) {
            Log::warning("⚠️ Target stock inválido ou zero", [
                'product_id' => $product['id'] ?? 'N/A',
                'product_name' => $productName,
                'target_stock' => $targetStock
            ]);
            return 1; // Mínimo 1 facing
        }
        
        // Quantas unidades cabem na profundidade da prateleira?
        $unitsPerRow = floor($shelfDepth / $productDepth);
        
        if ($unitsPerRow === 0) {
            Log::warning("⚠️ Produto não cabe na profundidade da prateleira", [
                'product_id' => $product['id'] ?? 'N/A',
                'product_name' => $productName,
                'product_depth' => $productDepth,
                'shelf_depth' => $shelfDepth
            ]);
            return 1; // Mínimo 1 facing
        }
        
        // Quantas fileiras (facings) precisamos?
        $facing = ceil($targetStock / $unitsPerRow);
        
        Log::info("🔢 Facing calculado", [
            'product_id' => $product['id'] ?? 'N/A',
            'product_name' => $productName,
            'product_depth' => $productDepth . 'cm',
            'shelf_depth' => $shelfDepth . 'cm',
            'target_stock' => $targetStock,
            'units_per_row' => $unitsPerRow,
            'facing_calculated' => $facing
        ]);
        
        return max(1, $facing); // Mínimo 1 facing
    }

    /**
     * Calcula a largura total ocupada pelo produto com facing
     * 
     * @param array $product - Produto com largura
     * @param int $facing - Número de facings
     * @return float - Largura total em cm
     */
    public function calculateTotalWidth(array $product, int $facing): float
    {
        $productWidth = $product['width'] ?? 0;
        return $productWidth * $facing;
    }

    /**
     * Valida se o produto cabe na prateleira com o facing calculado
     * 
     * @param array $product - Produto
     * @param int $facing - Número de facings
     * @param float $shelfWidth - Largura da prateleira
     * @return bool - true se cabe, false se não cabe
     */
    public function validateFacingFits(
        array $product,
        int $facing,
        float $shelfWidth
    ): bool {
        $totalWidth = $this->calculateTotalWidth($product, $facing);
        return $totalWidth <= $shelfWidth;
    }

    /**
     * Ajusta o facing para caber na largura disponível
     * 
     * @param array $product - Produto
     * @param int $idealFacing - Facing ideal calculado
     * @param float $availableWidth - Largura disponível na prateleira
     * @return int - Facing ajustado
     */
    public function adjustFacingToFit(
        array $product,
        int $idealFacing,
        float $availableWidth
    ): int {
        $productWidth = $product['width'] ?? 0;
        
        if ($productWidth <= 0) {
            return 1;
        }
        
        // Quantos facings cabem na largura disponível?
        $maxFacing = floor($availableWidth / $productWidth);
        
        // Retorna o menor entre o ideal e o máximo que cabe
        $adjustedFacing = min($idealFacing, $maxFacing);
        
        if ($adjustedFacing < $idealFacing) {
            Log::warning("⚠️ Facing ajustado para caber na prateleira", [
                'product_id' => $product['id'] ?? 'N/A',
                'product_name' => $product['name'] ?? 'N/A',
                'ideal_facing' => $idealFacing,
                'adjusted_facing' => $adjustedFacing,
                'available_width' => $availableWidth,
                'product_width' => $productWidth
            ]);
        }
        
        return max(1, $adjustedFacing);
    }
}
