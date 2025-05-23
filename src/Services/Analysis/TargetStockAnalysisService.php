<?php

namespace Callcocam\Plannerate\Services\Analysis;

use App\Models\Product;
use App\Models\Purchase;
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

        $currentStock = $this->getCurrentStock($productIds, $startDate, $endDate, $storeId);

        // Calcula as estatísticas
        $statistics = $this->calculateStatistics($dailySales, $period, $currentStock);

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

        // if ($startDate) {
        //     $query->where('date', '>=', $startDate);
        // }

        // if ($endDate) {
        //     $query->where('date', '<=', $endDate);
        // }

        // if ($storeId) {
        //     $query->where('store_id', $storeId);
        // }

        return $query->get();
    }

    /**
     * Agrupa as vendas por produto e dia
     */
    protected function groupDailySales(Collection $sales): array
    {
        $grouped = [];

        foreach ($sales as $sale) { 
            $date = $sale->sale_date;
            $productId = $sale->product_id;

            if (!isset($grouped[$productId])) {
                $grouped[$productId] = [];
            }

            if (!isset($grouped[$productId][$date])) {
                $grouped[$productId][$date] = 0;
            }

            $grouped[$productId][$date] += $sale->sale_quantity;
        }

        return $grouped;
    }

    protected function getCurrentStock(array $productIds, ?string $startDate, ?string $endDate, ?string $storeId): int
    {
        $query = Purchase::whereIn('product_id', $productIds)
            ->orderBy('entry_date', 'desc');

        if ($startDate) {
            $query->where('entry_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('entry_date', '<=', $endDate);
        }

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $currentStock = $query->first();


        return data_get($currentStock, 'current_stock', 0);
    }

    /**
     * Calcula as estatísticas de vendas
     */
    protected function calculateStatistics(array $dailySales, int $period, int $currentStock): array
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
                'currentStock' => $currentStock,
                'sales_by_day' => $sales,
            ];
        }

        return $result;
    }
} 