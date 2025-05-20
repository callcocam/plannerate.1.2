<?php

namespace Callcocam\Plannerate\Services\Analysis;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TargetStockAnalysisService
{
    /**
     * Realiza análise de estoque alvo dos produtos
     * 
     * @param array $productIds
     * @param string|null $startDate
     * @param string|null $endDate
     * @param int|null $storeId
     * @param int|null $period
     * @return array
     */
    public function analyze(
        array $productIds,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null,
        ?int $period = 30
    ): array {
        // Busca os produtos
        $products = Product::whereIn('id', $productIds)->get();

        // Busca as vendas no período
        $sales = $this->getSales($productIds, $startDate, $endDate, $storeId);

        // Agrupa as vendas por produto e dia
        $dailySales = $this->groupDailySales($sales);

        // Calcula as estatísticas
        $statistics = $this->calculateStatistics($dailySales, $period);

        return $statistics;
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
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->get();
    }

    /**
     * Agrupa as vendas por produto e dia
     */
    protected function groupDailySales(Collection $sales): array
    {
        $grouped = [];

        foreach ($sales as $sale) {
            $date = $sale->date->format('Y-m-d');
            $productId = $sale->product_id;

            if (!isset($grouped[$productId])) {
                $grouped[$productId] = [];
            }

            if (!isset($grouped[$productId][$date])) {
                $grouped[$productId][$date] = 0;
            }

            $grouped[$productId][$date] += $sale->quantity;
        }

        return $grouped;
    }

    /**
     * Calcula as estatísticas de vendas
     */
    protected function calculateStatistics(array $dailySales, int $period): array
    {
        $result = [];

        foreach ($dailySales as $productId => $sales) {
            $quantities = array_values($sales);
            $count = count($quantities);

            if ($count === 0) {
                continue;
            }

            // Média diária
            $average = array_sum($quantities) / $count;

            // Desvio padrão
            $variance = 0;
            foreach ($quantities as $quantity) {
                $variance += pow($quantity - $average, 2);
            }
            $standardDeviation = sqrt($variance / $count);

            // Variabilidade
            $variability = $average > 0 ? $standardDeviation / $average : 0;

            $result[] = [
                'product_id' => $productId,
                'average_sales' => round($average, 2),
                'standard_deviation' => round($standardDeviation, 2),
                'variability' => round($variability, 2),
                'sales_by_day' => $sales
            ];
        }

        return $result;
    }
} 