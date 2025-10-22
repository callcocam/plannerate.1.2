<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */
namespace Callcocam\Plannerate\Services\Analysis;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\SaleSummary;
use Carbon\Carbon;
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
     * @return array
     */
    public function analyze(
        array $productIds,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null, 
    ): array {
        // Busca os produtos
        // $products = Product::whereIn('id', $productIds)->get();

        // Busca as vendas no período
        $sales = $this->getSales($productIds, $startDate, $endDate, $storeId);

        // Agrupa as vendas por produto e dia
        $dailySales = $this->groupDailySales($sales); 

        // Calcula as estatísticas
        $statistics = $this->calculateStatistics($dailySales);

        return $statistics;
    }

    /**
     * Busca as vendas dos produtos no período usando dados sumarizados
     */
    protected function getSales(
        array $productIds,
        ?string $startDate,
        ?string $endDate,
        ?int $storeId
    ): Collection {
        $query = SaleSummary::whereIn('product_id', $productIds)
            ->where('period_type', 'monthly');

        if ($startDate) {
            $query->where('period_start', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('period_end', '<=', $endDate);
        }

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->get();
    }

    /**
     * Agrupa as vendas por produto e período (mensal)
     * Nota: Dados já vêm sumarizados mensalmente, convertemos para formato esperado
     */
    protected function groupDailySales(Collection $sales): array
    {
        $grouped = [];

        foreach ($sales as $summary) { 
            // Usar period_start como referência de período
            $date = $summary->period_start instanceof Carbon 
                ? $summary->period_start->format('Y-m-d') 
                : $summary->period_start;
            $productId = $summary->product_id;

            if (!isset($grouped[$productId])) {
                $grouped[$productId] = [];
            }

            if (!isset($grouped[$productId][$date])) {
                $grouped[$productId][$date] = 0;
            }

            // total_quantity já é a soma do período
            $grouped[$productId][$date] += $summary->total_quantity;
        }

        return $grouped;
    }

    protected function getCurrentStock(string $productId, ?string $startDate, ?string $endDate, ?string $storeId): int
    {
        $query = Purchase::where('product_id', $productId)
            ->orderBy('entry_date', 'asc');

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
    protected function calculateStatistics(array $dailySales): array
    {
        $result = [];

        foreach ($dailySales as $productId => $sales) {
            $currentStock = $this->getCurrentStock($productId, null, null, null);
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