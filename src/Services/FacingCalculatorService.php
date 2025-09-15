<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Services;

use Illuminate\Support\Facades\Log;
use Callcocam\Plannerate\Services\StepLogger;

/**
 * Serviço responsável por calcular facing de produtos no planograma
 * Centraliza toda a lógica de cálculo de facing (otimizado, conservador, etc.)
 */
class FacingCalculatorService
{
    /**
     * Calcula facing otimizado baseado no espaço disponível
     * Prioriza garantir que o produto SEMPRE cabe, mesmo que com facing menor
     */
    public function calculateOptimalFacing(array $product, float $availableWidth): int
    {
        $productData = $product['product'] ?? [];
        $productWidth = $this->getProductWidth($productData);
        $abcClass = $product['abc_class'] ?? 'C';
        $finalScore = floatval($product['final_score'] ?? 0);
        
        // Cálculo de facing realista
        
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
        
        StepLogger::logFacingCalculation($product, $desiredFacing, $finalFacing, 
            "Classe $abcClass, Eficiência: {$widthEfficiency}%, Score: " . 
            ($normalizedScore > 0.7 ? '+1' : ($normalizedScore < 0.3 ? '-1' : '0')));
        
        return $finalFacing;
    }

    /**
     * Calcula facing conservador para distribuição em cascata
     */
    public function calculateConservativeFacing(array $product): int
    {
        $abcClass = $product['abc_class'] ?? 'C';
        
        // Facing muito conservador para cascata (garantir que cabe)
        $conservativeFacing = match($abcClass) {
            'A' => 2, // Classe A: apenas 2 facing na cascata
            'B' => 1, // Classe B: apenas 1 facing na cascata
            'C' => 1, // Classe C: apenas 1 facing na cascata
            default => 1
        };
        
        // Facing conservador para cascata calculado
        
        return $conservativeFacing;
    }

    /**
     * Calcula facing total baseado na classe ABC e score do produto
     * Usado na verticalização por section
     */
    public function calculateTotalFacingForSection(array $product): int
    {
        $abcClass = $product['abc_class'] ?? 'C';
        $score = $product['final_score'] ?? 0;
        
        // Facing base por classe ABC
        $facingTotal = match($abcClass) {
            'A' => 6, // Produtos A: facing alto
            'B' => 4, // Produtos B: facing médio  
            'C' => 2, // Produtos C: facing baixo
            default => 1
        };
        
        // Ajustar baseado no score dentro da classe
        if ($score > 0.5) {
            $facingTotal = ceil($facingTotal * 1.5); // Score alto = +50%
        } elseif ($score > 0.3) {
            $facingTotal = ceil($facingTotal * 1.2); // Score médio = +20%
        }
        
        $facingTotal = min($facingTotal, 10); // Máximo 10 faces total
        
        return $facingTotal;
    }

    /**
     * Extrai largura do produto com fallback seguro
     */
    protected function getProductWidth(array $productData): float
    {
        if (!isset($productData['width']) || $productData['width'] <= 0) {
            throw new \InvalidArgumentException("Produto deve ter largura válida > 0");
        }
        return floatval($productData['width']);
    }

    /**
     * Calcula facing adaptativo baseado no espaço disponível
     * Reduz facing até caber ou chegar a 1
     */
    public function calculateAdaptiveFacing(array $product, float $availableWidth, int $requestedFacing): array
    {
        $productData = $product['product'] ?? [];
        $productWidth = $this->getProductWidth($productData);
        $requiredWidth = $productWidth * $requestedFacing;
        
        // FACING ADAPTATIVO: Reduzir facing se não cabe
        $adaptedFacing = $requestedFacing;
        $adaptedRequiredWidth = $requiredWidth;
        
        // Tentar reduzir facing até caber ou chegar a 1
        while ($adaptedFacing > 0 && $adaptedRequiredWidth > $availableWidth) {
            $adaptedFacing--;
            $adaptedRequiredWidth = $productWidth * $adaptedFacing;
        }
        
        // Facing adaptativo aplicado
        
        return [
            'facing' => $adaptedFacing,
            'required_width' => $adaptedRequiredWidth,
            'fits' => $adaptedFacing > 0 && $adaptedRequiredWidth <= $availableWidth,
            'optimization' => $requestedFacing > $adaptedFacing ? 'REDUZIDO' : 'MANTIDO'
        ];
    }
}
