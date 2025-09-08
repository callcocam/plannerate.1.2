<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Services\Engine;

use App\Models\Product;
use App\Models\Sale;
use Callcocam\Plannerate\Services\Analysis\ABCAnalysisService;
use Callcocam\Plannerate\Services\Analysis\TargetStockAnalysisService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Motor de pontuação para planogramas automáticos
 * 
 * Implementa o sistema de Score Engine conforme especificação do README.MD:
 * Score = (W_qty * Qty_normalized) + (W_val * Value_normalized) + (W_margin * Margin_normalized) 
 *         + ABC_bonus + Stock_penalty + Anti_churn_penalty
 */
class ScoreEngineService
{
    protected ABCAnalysisService $abcService;
    protected TargetStockAnalysisService $targetStockService;
    
    // Configurações carregadas do arquivo de config
    protected array $config;
    
    protected function getConfig(): array
    {
        if (!isset($this->config)) {
            $this->config = config('plannerate.score_engine', []);
        }
        return $this->config;
    }
    
    protected function getDefaultWeights(): array
    {
        return $this->getConfig()['default_weights'] ?? [
            'quantity' => 0.30,
            'value' => 0.30, 
            'margin' => 0.40,
        ];
    }
    
    protected function getAbcBonuses(): array
    {
        return $this->getConfig()['abc_bonuses'] ?? [
            'class_a' => 0.20,
            'class_b' => 0.00,
            'class_c' => -0.10,
        ];
    }
    
    protected function getStockPenalties(): array
    {
        return $this->getConfig()['stock_penalties'] ?? [
            'deficit' => -0.15,
            'excess' => -0.05,
        ];
    }
    
    protected function getAntiChurnConfig(): array
    {
        return $this->getConfig()['anti_churn'] ?? [
            'position_change' => -0.10,
            'facing_change' => -0.05,
            'minimum_improvement' => 0.15,
        ];
    }

    public function __construct(
        ABCAnalysisService $abcService,
        TargetStockAnalysisService $targetStockService
    ) {
        $this->abcService = $abcService;
        $this->targetStockService = $targetStockService;
    }

    /**
     * Calcula o score automático para uma lista de produtos
     * 
     * @param array $productIds Lista de IDs dos produtos
     * @param array $weights Pesos personalizados (opcional)
     * @param string|null $startDate Data inicial para análise
     * @param string|null $endDate Data final para análise  
     * @param int|null $storeId ID da loja (opcional)
     * @return array Array com scores calculados por produto
     */
    public function calculateScores(
        array $productIds,
        array $weights = [],
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null
    ): array {
        Log::info('ScoreEngine: Iniciando cálculo de scores', [
            'produtos_count' => count($productIds),
            'periodo' => $startDate ? "{$startDate} até {$endDate}" : 'Período completo',
            'loja_id' => $storeId
        ]);

        // Mesclar pesos personalizados com padrões
        $finalWeights = array_merge($this->getDefaultWeights(), $weights);
        
        // Validar pesos (devem somar 1.0)
        $weightSum = array_sum($finalWeights);
        if (abs($weightSum - 1.0) > 0.01) {
            Log::warning('ScoreEngine: Pesos não somam 1.0, normalizando', [
                'soma_original' => $weightSum,
                'pesos' => $finalWeights
            ]);
            
            // Normalizar pesos
            $finalWeights = array_map(fn($weight) => $weight / $weightSum, $finalWeights);
        }

        try {
            // Obter análise ABC dos produtos
            $abcAnalysis = $this->abcService->analyze($productIds, $startDate, $endDate, $storeId);
            
            // Obter análise de estoque-alvo
            $stockAnalysis = $this->targetStockService->analyze($productIds, $startDate, $endDate, $storeId);
            
            // Calcular scores por produto
            $scores = $this->processProductScores($abcAnalysis, $stockAnalysis, $finalWeights);
            
            Log::info('ScoreEngine: Cálculo concluído com sucesso', [
                'produtos_processados' => count($scores),
                'score_medio' => count($scores) > 0 ? array_sum(array_column($scores, 'final_score')) / count($scores) : 0
            ]);
            
            return $scores;
            
        } catch (\Exception $e) {
            Log::error('ScoreEngine: Erro no cálculo de scores', [
                'erro' => $e->getMessage(),
                'linha' => $e->getLine(),
                'arquivo' => $e->getFile()
            ]);
            
            throw new \RuntimeException('Erro no cálculo automático de scores: ' . $e->getMessage());
        }
    }

    /**
     * Processa os scores individuais dos produtos
     */
    protected function processProductScores(
        array $abcAnalysis,
        array $stockAnalysis,
        array $weights
    ): array {
        $scores = [];
        
        // Converter análises em arrays indexados por produto_id para lookup rápido
        $abcLookup = $this->createProductLookup($abcAnalysis, 'id');
        $stockLookup = $this->createProductLookup($stockAnalysis, 'product_id');
        
        // Obter valores máximos para normalização
        $maxValues = $this->calculateMaxValues($abcAnalysis);
        
        foreach ($abcAnalysis as $product) {
            $productEan = $product['id']; // Este é o EAN vindo do ABCAnalysisService
            
            // Converter EAN para ID real do produto
            $productModel = Product::where('ean', $productEan)->first();
            if (!$productModel) {
                Log::warning('ScoreEngine: Produto não encontrado pelo EAN', [
                    'ean' => $productEan,
                    'product_name' => $product['name'] ?? 'N/A'
                ]);
                continue; // Pular este produto se não encontrar
            }
            
            $productId = $productModel->id; // ID real do produto
            $stockData = $stockLookup[$productEan] ?? null;
            
            // Calcular componentes do score
            $normalizedScores = $this->calculateNormalizedScores($product, $maxValues);
            $baseScore = $this->calculateBaseScore($normalizedScores, $weights);
            $abcBonus = $this->calculateABCBonus($product);
            $stockPenalty = $this->calculateStockPenalty($product, $stockData);
            
            // Score final
            $finalScore = $baseScore + $abcBonus + $stockPenalty;
            
            // Determinar classificação ABC
            $abcClass = $this->determineABCClass($product);
            
            // Flags de confiabilidade
            $confidenceFlag = $this->calculateConfidenceFlag($product, $stockData);
            
            $scores[] = [
                'product_id' => $productId,
                'product_name' => $product['name'],
                'category' => $product['category'] ?? 'Sem categoria',
                'base_score' => round($baseScore, 4),
                'abc_bonus' => round($abcBonus, 4),
                'stock_penalty' => round($stockPenalty, 4),
                'final_score' => round($finalScore, 4),
                'abc_class' => $abcClass,
                'confidence_flag' => $confidenceFlag,
                'metrics' => [
                    'quantity' => $product['quantity'] ?? 0,
                    'value' => $product['value'] ?? 0,
                    'margin' => $product['margin'] ?? 0,
                    'current_stock' => $product['currentStock'] ?? 0,
                ],
                'normalized_scores' => $normalizedScores,
            ];
        }
        
        // Ordenar por score final (maior primeiro)
        usort($scores, fn($a, $b) => $b['final_score'] <=> $a['final_score']);
        
        return $scores;
    }

    /**
     * Calcula scores normalizados (0-1) para cada métrica
     */
    protected function calculateNormalizedScores(array $product, array $maxValues): array
    {
        return [
            'quantity' => $maxValues['quantity'] > 0 ? ($product['quantity'] ?? 0) / $maxValues['quantity'] : 0,
            'value' => $maxValues['value'] > 0 ? ($product['value'] ?? 0) / $maxValues['value'] : 0,
            'margin' => $maxValues['margin'] > 0 ? ($product['margin'] ?? 0) / $maxValues['margin'] : 0,
        ];
    }

    /**
     * Calcula score base usando pesos configurados
     */
    protected function calculateBaseScore(array $normalizedScores, array $weights): float
    {
        return ($weights['quantity'] * $normalizedScores['quantity']) +
               ($weights['value'] * $normalizedScores['value']) +
               ($weights['margin'] * $normalizedScores['margin']);
    }

    /**
     * Calcula bônus baseado na classificação ABC
     */
    protected function calculateABCBonus(array $product): float
    {
        $abcClass = $this->determineABCClass($product);
        $bonuses = $this->getAbcBonuses();
        
        switch ($abcClass) {
            case 'A':
                return $bonuses['class_a'];
            case 'B':
                return $bonuses['class_b'];
            case 'C':
                return $bonuses['class_c'];
            default:
                return 0;
        }
    }

    /**
     * Calcula penalidade baseada no estoque
     */
    protected function calculateStockPenalty(array $product, ?array $stockData): float
    {
        if (!$stockData) {
            return 0; // Sem dados de estoque, sem penalidade
        }
        
        $currentStock = $product['currentStock'] ?? 0;
        $averageSales = $stockData['average_sales'] ?? 0;
        
        if ($averageSales <= 0) {
            return 0; // Sem vendas médias, sem penalidade
        }
        
        // Calcular dias de estoque atual
        $stockDays = $currentStock / $averageSales;
        
        // Penalidades baseadas em dias de estoque
        $penalties = $this->getStockPenalties();
        
        if ($stockDays < 7) { // Menos de 7 dias = déficit
            return $penalties['deficit'];
        } elseif ($stockDays > 30) { // Mais de 30 dias = excesso
            return $penalties['excess'];
        }
        
        return 0; // Estoque adequado
    }

    /**
     * Determina a classificação ABC do produto
     */
    protected function determineABCClass(array $product): string
    {
        $value = $product['value'] ?? 0;
        $quantity = $product['quantity'] ?? 0;
        
        // Lógica simplificada - será aprimorada com integração ABC completa
        $totalScore = $value + $quantity;
        
        if ($totalScore > 1000) return 'A';
        if ($totalScore > 100) return 'B';
        return 'C';
    }

    /**
     * Calcula flags de confiabilidade
     */
    protected function calculateConfidenceFlag(array $product, ?array $stockData): string
    {
        $flags = [];
        
        // Verificar variabilidade alta (CV > 1)
        if ($stockData && ($stockData['variability'] ?? 0) > 1.0) {
            $flags[] = 'HIGH_VARIABILITY';
        }
        
        // Verificar série curta (menos de 30 dias de dados)
        if ($stockData && count($stockData['sales_by_day'] ?? []) < 30) {
            $flags[] = 'SHORT_SERIES';
        }
        
        // Verificar estoque zero
        if (($product['currentStock'] ?? 0) <= 0) {
            $flags[] = 'ZERO_STOCK';
        }
        
        return implode(',', $flags) ?: 'OK';
    }

    /**
     * Cria array de lookup indexado por chave
     */
    protected function createProductLookup(array $data, string $keyField): array
    {
        $lookup = [];
        foreach ($data as $item) {
            if (isset($item[$keyField])) {
                $lookup[$item[$keyField]] = $item;
            }
        }
        return $lookup;
    }

    /**
     * Calcula valores máximos para normalização
     */
    protected function calculateMaxValues(array $data): array
    {
        return [
            'quantity' => max(array_column($data, 'quantity')),
            'value' => max(array_column($data, 'value')),
            'margin' => max(array_column($data, 'margin')),
        ];
    }
}
