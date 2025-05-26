<?php

namespace Callcocam\Plannerate\Services\Analysis;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * @return array
     */
    public function analyze(
        array $productIds,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null,
        ?float $marketShare = 0.1
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
            $marketShare
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
    ): Collection {
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

        return $query->get();
    }

    /**
     * Calcula o crescimento e participação de mercado
     */
    protected function calculateGrowthAndMarketShare(
        Collection $products,
        Collection $currentSales,
        Collection $previousSales,
        float $marketShare
    ): array {
        $result = [];

        // Calcula o total de vendas do mercado

        foreach ($products as $product) {

            $category = data_get($product->category_level, 'id');

            $level = data_get($product->category_level, 'level');

            $parent = data_get($product->category_level, 'parent');

            $productIds = Product::where($level, $category)
            ->whereNull($parent)
            ->pluck('id');
            
            $totalMarketSales = Sale::query()->whereIn('product_id', $productIds)->sum('sale_value');
            // Vendas do produto no período atual
            $currentProductSales = $product->sales->sum('sale_value');
 
            // Vendas do produto no período anterior
            $previousProductSales = $previousSales->where('product_id', $product->id)->avg('sale_value');

            // Taxa de crescimento
            $growthRate = $previousProductSales > 0
                ? (($currentProductSales - $previousProductSales) / $previousProductSales)
                : 0;

            // Participação no mercado
            $marketSharePercent = $totalMarketSales > 0
                ? ($currentProductSales / $totalMarketSales)
                : 0;

            // Classificação BCG
            $classification = $this->classifyBCG($growthRate, $marketSharePercent, $marketShare);

            $result[] = [
                'product_id' => $product->id,
                'ean' => $product->ean,
                'category' => $product->category_name,
                'current_sales' => $currentProductSales,
                'previous_sales' => $previousProductSales,
                'growth_rate' => round($growthRate * 100, 2),
                'market_share' => round($marketSharePercent * 100, 2),
                'classification' => $classification
            ];
        }

        return $result;
    }

    /**
     * Classifica o produto na matriz BCG
     */
    protected function classifyBCG(float $growthRate, float $marketShare, float $marketShareThreshold): string
    {
        if ($growthRate >= 0.1 && $marketShare >= $marketShareThreshold) {
            return 'STAR'; // Alta participação, alto crescimento
        } elseif ($growthRate >= 0.1 && $marketShare < $marketShareThreshold) {
            return 'QUESTION_MARK'; // Baixa participação, alto crescimento
        } elseif ($growthRate < 0.1 && $marketShare >= $marketShareThreshold) {
            return 'CASH_COW'; // Alta participação, baixo crescimento
        } else {
            return 'DOG'; // Baixa participação, baixo crescimento
        }
    }
}
