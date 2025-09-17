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
    
    /**
     * 🎯 FUNÇÃO PRINCIPAL: Calcula facing inteligente baseado nos resultados das análises ABC e Target Stock
     * 
     * Esta função integra os resultados dos serviços existentes:
     * - ABCAnalysisService: fornece classificação ABC (A, B, C)  
     * - TargetStockAnalysis: fornece estoque alvo necessário
     * - Dimensões do produto: calcula capacidade por facing
     * 
     * @param array $productData Dados do produto com dimensões
     * @param array $abcResult Resultado da análise ABC para este produto
     * @param array $targetStockResult Resultado da análise de estoque alvo 
     * @param array $shelfData Dados da prateleira (altura, profundidade)
     * @return array Dados completos do facing calculado
     */
    public function calculateIntelligentFacing(
        array $productData, 
        array $abcResult, 
        array $targetStockResult, 
        array $shelfData
    ): array {
        // 1. EXTRAIR DADOS NECESSÁRIOS
        $targetStock = $targetStockResult['target_stock'] ?? 1;
        $currentStock = $targetStockResult['current_stock'] ?? 0;
        $abcClass = $abcResult['abc_class'] ?? 'C';
        $urgency = $targetStockResult['urgency'] ?? 'NORMAL';
        
        $productHeight = data_get($productData, 'dimensions.height', 0) ?: data_get($productData, 'height', 0);
        $productDepth = data_get($productData, 'dimensions.depth', 0) ?: data_get($productData, 'depth', 0);
        $productWidth = data_get($productData, 'dimensions.width', 0) ?: data_get($productData, 'width', 0);
        
        $shelfHeight = $shelfData['height'] ?? 40;
        $shelfDepth = $shelfData['depth'] ?? 40;

        // 2. VALIDAÇÕES E FALLBACKS
        if ($targetStock <= 0) {
            return [
                'facing' => 1,
                'target_stock' => $targetStock,
                'units_per_facing' => 1,
                'coverage_efficiency' => 100,
                'abc_class' => $abcClass,
                'urgency' => $urgency,
                'reason' => 'Estoque alvo zero ou negativo'
            ];
        }

        if ($productHeight <= 0 || $productDepth <= 0) {
            Log::warning("⚠️ Produto sem dimensões válidas - usando facing baseado em classe ABC", [
                'product_name' => $productData['name'] ?? 'N/A',
                'abc_class' => $abcClass,
                'height' => $productHeight,
                'depth' => $productDepth
            ]);
            
            // Fallback baseado apenas na classe ABC
            return [
                'facing' => $this->getFacingByABCClass($abcClass),
                'target_stock' => $targetStock,
                'units_per_facing' => 1,
                'coverage_efficiency' => 0,
                'abc_class' => $abcClass,
                'urgency' => $urgency,
                'reason' => 'Dimensões inválidas - usando facing por classe ABC'
            ];
        }

        // 3. CALCULAR CAPACIDADE POR FACING
        $unitsPerVerticalLayer = max(1, floor($shelfHeight / $productHeight));
        $layersOfDepth = max(1, floor($shelfDepth / $productDepth));
        $unitsPerFacing = $unitsPerVerticalLayer * $layersOfDepth;

        // 4. CALCULAR FACING NECESSÁRIO PARA ATINGIR ESTOQUE ALVO
        // 🎯 LÓGICA CORRIGIDA: Sempre calcular baseado na capacidade real necessária
        $facingByTarget = max(1, ceil($targetStock / $unitsPerFacing));
        $facingMethod = "Optimized capacity calculation";
        
        // Log para debug
        Log::info("🔧 Facing calculation", [
            'product_name' => $productData['name'] ?? 'N/A',
            'target_stock' => $targetStock,
            'units_per_facing' => $unitsPerFacing,
            'facing_calculated' => $facingByTarget,
            'total_capacity' => $facingByTarget * $unitsPerFacing
        ]);
        
        // 5. 🎯 USAR FACING OTIMIZADO SEM AJUSTES ARBITRÁRIOS
        // Priorizar eficiência de espaço baseada no target stock real
        $facing = $facingByTarget; // Usar apenas o facing necessário para o estoque alvo
        
        Log::info("🎯 Facing otimizado aplicado", [
            'product_name' => $productData['name'] ?? 'N/A',
            'target_stock' => $targetStock,
            'facing_optimal' => $facingByTarget,
            'facing_applied' => $facing,
            'space_efficiency' => 'Optimized (no ABC forcing)'
        ]);
        
        // 6. CALCULAR EFICIÊNCIA DE COBERTURA
        $totalUnitsWithFacing = $facing * $unitsPerFacing;
        $coverageEfficiency = ($totalUnitsWithFacing > 0) ? 
            min(100, round(($targetStock / $totalUnitsWithFacing) * 100, 1)) : 0;

        $result = [
            'facing' => max(1, $facing),
            'target_stock' => $targetStock,
            'current_stock' => $currentStock,
            'units_per_facing' => $unitsPerFacing,
            'total_capacity' => $totalUnitsWithFacing,
            'coverage_efficiency' => $coverageEfficiency,
            'abc_class' => $abcClass,
            'urgency' => $urgency,
            'dimensions' => [
                'product_height' => $productHeight,
                'product_depth' => $productDepth,
                'product_width' => $productWidth,
                'shelf_height' => $shelfHeight,
                'shelf_depth' => $shelfDepth,
                'units_vertical' => $unitsPerVerticalLayer,
                'layers_depth' => $layersOfDepth
            ],
            'reason' => 'Cálculo inteligente ABC + Target Stock + Dimensões'
        ];

        Log::info("🧠 Facing Inteligente Calculado", [
            'product' => $productData['name'] ?? 'N/A',
            'abc_class' => $abcClass,
            'urgency' => $urgency,
            'target_stock' => $targetStock,
            'current_stock' => $currentStock,
            'facing_calculated' => $facingByTarget,
            'facing_final' => $facing,
            'units_per_facing' => $unitsPerFacing,
            'coverage_efficiency' => $coverageEfficiency . '%',
            'total_capacity' => $totalUnitsWithFacing,
            'facing_method' => $facingMethod,
            'space_optimization' => 'Enabled (target-based only)'
        ]);

        return $result;
    }

    /**
     * 🔄 FUNÇÃO LEGADA: Mantida para compatibilidade (usa a nova função internamente)
     */
    public function calculateFacingFromTargetStock(array $productData, int $targetStock, array $shelfData): int
    {
        // Simular dados para usar a nova função
        $abcResult = ['abc_class' => 'B']; // Classe média como padrão
        $targetStockResult = [
            'target_stock' => $targetStock,
            'current_stock' => 0,
            'urgency' => 'NORMAL'
        ];
        
        $result = $this->calculateIntelligentFacing($productData, $abcResult, $targetStockResult, $shelfData);
        
        return $result['facing'];
    }

    /**
     * 🎨 Ajusta facing baseado no contexto ABC + urgência + situação do estoque
     */
    protected function adjustFacingByContext(int $baseFacing, string $abcClass, string $urgency, int $currentStock, int $targetStock): int
    {
        $adjustedFacing = $baseFacing;
        
        // 1. AJUSTE POR CLASSE ABC
        $abcMultiplier = match($abcClass) {
            'A' => 1.2, // Produtos A: +20% facing
            'B' => 1.0, // Produtos B: manter facing  
            'C' => 0.8, // Produtos C: -20% facing
            default => 1.0
        };
        
        // 2. AJUSTE POR URGÊNCIA DE REPOSIÇÃO
        $urgencyMultiplier = match($urgency) {
            'CRÍTICO' => 1.5, // Urgência crítica: +50% facing
            'BAIXO' => 1.2,   // Estoque baixo: +20% facing
            'NORMAL' => 1.0,  // Normal: manter
            'ALTO' => 0.8,    // Estoque alto: -20% facing
            default => 1.0
        };
        
        // 3. APLICAR MULTIPLICADORES
        $adjustedFacing = ceil($baseFacing * $abcMultiplier * $urgencyMultiplier);
        
        // 4. LIMITES MÍNIMOS E MÁXIMOS POR CLASSE
        $limits = match($abcClass) {
            'A' => ['min' => 2, 'max' => 8], // Classe A: 2-8 facings
            'B' => ['min' => 1, 'max' => 5], // Classe B: 1-5 facings  
            'C' => ['min' => 1, 'max' => 3], // Classe C: 1-3 facings
            default => ['min' => 1, 'max' => 2]
        };
        
        return max($limits['min'], min($limits['max'], $adjustedFacing));
    }

    /**
     * 📊 Facing baseado apenas na classe ABC (fallback)
     */
    protected function getFacingByABCClass(string $abcClass): int
    {
        return match($abcClass) {
            'A' => 3, // Produtos A: 3 facings por padrão
            'B' => 2, // Produtos B: 2 facings por padrão
            'C' => 1, // Produtos C: 1 facing por padrão
            default => 1
        };
    }
}
