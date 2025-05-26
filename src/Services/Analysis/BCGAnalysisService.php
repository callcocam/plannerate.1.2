<?php

namespace Callcocam\Plannerate\Services\Analysis;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BCGAnalysisService
{
    /**
     * Realiza análise BCG dos produtos
     * 
     * @param array $productIds
     * @param string|null $startDate
     * @param string|null $endDate
     * @param int|null $storeId
     * @param float|null $marketShare
     * @param string|null $xAxis
     * @param string|null $yAxis
     * @return array
     */
    public function analyze(
        array $productIds,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null,
        ?float $marketShare = 0.1,
        ?string $xAxis = null,
        ?string $yAxis = null
    ): array {

        // Busca os produtos
        $products = Product::whereIn('id', $productIds)->get();

        // Busca as vendas no período atual
        $currentSales = $this->getSales($productIds, $startDate, $endDate, $storeId);

        // Busca as vendas no período anterior
        $previousStartDate = $startDate ? date('Y-m-d', strtotime($startDate . ' -1 year')) : null;
        $previousEndDate = $endDate ? date('Y-m-d', strtotime($endDate . ' -1 year')) : null;
        $previousSales = $this->getSales($productIds, $previousStartDate, $previousEndDate, $storeId);

        // Calcula o crescimento e participação de mercado
        $analysis = $this->calculateGrowthAndMarketShare(
            $products,
            $currentSales,
            $previousSales,
            $marketShare,
            $xAxis,
            $yAxis,
            $startDate,
            $endDate,
            $storeId
        );

        return $analysis;
    }

    /**
     * Busca as vendas dos produtos no período
     */
    protected function getSales(
        array $productIds,
        ?string $startDate,
        ?string $endDate,
        ?int $storeId
    ): Collection | Builder {
        $query = Sale::whereIn('product_id', $productIds);

        if ($startDate) {
            $query->where('sale_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('sale_date', '<=', $endDate);
        }

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query;
    }

    /**
     * Calcula o crescimento e participação de mercado
     */
    protected function calculateGrowthAndMarketShare(
        Collection $products,
        Builder $currentSales,
        Builder $previousSales,
        float $marketShare,
        ?string $xAxis = null,
        ?string $yAxis = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null
    ): array {
        $result = [];
        foreach ($products as $product) {
            // Calcular total do mercado uma vez para todos os produtos
            $category = $product->category_level;
            $categoryId = data_get($category, 'id');
            $level = data_get($category, 'level');
            $parent = data_get($category, 'parent');
            $allProductIds = Product::query()->where($level, $categoryId)->whereNull($parent)->pluck('id')->toArray();
            $totalMarketXValue = $this->calculateTotalMarketValue($xAxis, $allProductIds, $startDate, $endDate, $storeId);
            // Vendas do produto no período atual (filtradas)
            $currentProductSales = $currentSales->where('product_id', $product->id)->sum('sale_value');
            $currentProductQuantity = $currentSales->where('product_id', $product->id)->sum('sale_quantity');
            $currentProductMargin = $currentSales->where('product_id', $product->id)->sum('unit_profit_margin');

            // Vendas do produto no período anterior
            $previousProductSales = $previousSales->where('product_id', $product->id)->sum('sale_value');
            $previousProductQuantity = $previousSales->where('product_id', $product->id)->sum('sale_quantity');
            $previousProductMargin = $previousSales->where('product_id', $product->id)->sum('unit_profit_margin');

            // Calcular valores para os eixos baseado na seleção do usuário
            $xValue = $this->calculateAxisValue($xAxis, $currentProductSales, $currentProductQuantity, $currentProductMargin);
            $yValue = $this->calculateAxisValue($yAxis, $currentProductSales, $currentProductQuantity, $currentProductMargin);

            $previousXValue = $this->calculateAxisValue($xAxis, $previousProductSales, $previousProductQuantity, $previousProductMargin);
            $previousYValue = $this->calculateAxisValue($yAxis, $previousProductSales, $previousProductQuantity, $previousProductMargin);

            // Taxa de crescimento do eixo Y
            $growthRate = $previousYValue > 0
                ? (($yValue - $previousYValue) / $previousYValue)
                : ($yValue > 0 ? 1.0 : 0); // Se não há dados anteriores mas há atuais, considera crescimento de 100%

            // Participação no mercado do eixo X
            $marketSharePercent = $totalMarketXValue > 0
                ? ($xValue / $totalMarketXValue)
                : 0;

            // Classificação BCG
            $classification = $this->classifyBCG($growthRate, $marketSharePercent, $marketShare);

            // Log para debug (apenas para os primeiros produtos)
            if (count($result) < 3) {
                Log::info("BCG Debug - Produto {$product->ean}:", [
                    'xAxis' => $xAxis,
                    'yAxis' => $yAxis,
                    'xValue' => $xValue,
                    'yValue' => $yValue,
                    'previousXValue' => $previousXValue,
                    'previousYValue' => $previousYValue,
                    'growthRate' => $growthRate,
                    'marketSharePercent' => $marketSharePercent,
                    'totalMarketXValue' => $totalMarketXValue,
                    'classification' => $classification
                ]);
            }

            $result[] = [
                'product_id' => $product->id,
                'ean' => $product->ean,
                'category' => $product->category_name,
                'current_sales' => $currentProductSales,
                'previous_sales' => $previousProductSales,
                'growth_rate' => round($growthRate * 100, 2),
                'market_share' => round($marketSharePercent * 100, 2),
                'x_axis_value' => round($xValue, 2),
                'y_axis_value' => round($yValue, 2),
                'x_axis_label' => $xAxis ?: 'VALOR DE VENDA',
                'y_axis_label' => $yAxis ?: 'MARGEM DE CONTRIBUIÇÃO',
                'classification' => $classification
            ];
        }

        return $result;
    }

    /**
     * Calcula o valor do eixo baseado na métrica selecionada
     */
    protected function calculateAxisValue(?string $axis, float $sales, float $quantity, float $margin): float
    {
        switch ($axis) {
            case 'VENDA EM QUANTIDADE':
                return $quantity;
            case 'VALOR DE VENDA':
                return $sales;
            case 'MARGEM DE CONTRIBUIÇÃO':
                return $margin;
            default:
                return $sales; // Valor padrão
        }
    }

    /**
     * Calcula o valor total do mercado para uma métrica específica
     */
    protected function calculateTotalMarketValue(?string $axis, array $productIds, ?string $startDate, ?string $endDate, ?int $storeId): float
    {
        $query = Sale::whereIn('product_id', $productIds);

        if ($startDate) {
            $query->where('sale_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('sale_date', '<=', $endDate);
        }

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        switch ($axis) {
            case 'VENDA EM QUANTIDADE':
                return $query->sum('sale_quantity');
            case 'VALOR DE VENDA':
                return $query->sum('sale_value');
            case 'MARGEM DE CONTRIBUIÇÃO':
                return $query->sum('unit_profit_margin');
            default:
                return $query->sum('sale_value'); // Valor padrão
        }
    }

    /**
     * Classifica o produto na matriz BCG
     */
    protected function classifyBCG(float $growthRate, float $marketShare, float $marketShareThreshold): string
    {
        // A classificação BCG é baseada em:
        // - EIXO Y (Vertical): Taxa de crescimento das métricas selecionadas
        // - EIXO X (Horizontal): Participação de mercado das métricas selecionadas

        // Ajustar thresholds para serem mais realistas
        $growthThreshold = 0.05; // 5% de crescimento
        $adjustedMarketShareThreshold = max($marketShareThreshold, 0.01); // Mínimo 1%

        if ($growthRate >= $growthThreshold && $marketShare >= $adjustedMarketShareThreshold) {
            return 'STAR'; // Alta participação, alto crescimento
        } elseif ($growthRate >= $growthThreshold && $marketShare < $adjustedMarketShareThreshold) {
            return 'QUESTION_MARK'; // Baixa participação, alto crescimento
        } elseif ($growthRate < $growthThreshold && $marketShare >= $adjustedMarketShareThreshold) {
            return 'CASH_COW'; // Alta participação, baixo crescimento
        } else {
            return 'DOG'; // Baixa participação, baixo crescimento
        }
    }
}
