<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Services\Analysis;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\MonthlySalesSummary;
use App\Services\Analysis\SalesDataSourceService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TargetStockAnalysisService
{
    /**
     * Serviço de fonte de dados de vendas
     */
    protected SalesDataSourceService $dataSource;

    /**
     * Construtor
     * 
     * @param string $sourceType 'daily' ou 'monthly' (padrão: 'daily')
     */
    public function __construct(?string $sourceType = null)
    {
        $this->dataSource = new SalesDataSourceService('daily');
    }

    /**
     * Realiza análise de estoque alvo dos produtos
     * 
     * @param array $productIds
     * @param string|null $startDate
     * @param string|null $endDate
     * @param string|null $clientId 
     * @param string|null $storeId  
     * @return array
     */
    public function analyze(
        array $productIds,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $clientId = null,
        ?string $storeId = null
    ): array {
        // Busca os produtos
        // $products = Product::whereIn('id', $productIds)->get();

        // Busca as vendas no período
        $sales = $this->getSales($productIds, $startDate, $endDate, $clientId, $storeId);

        // Agrupa as vendas por produto e dia (ou mês se usar monthly)
        $dailySales = $this->groupDailySales($sales);

        // Calcula as estatísticas
        $statistics = $this->calculateStatistics($dailySales);

        return $statistics;
    }

    /**
     * Busca as vendas dos produtos no período
     */
    protected function getSales(
        array $productIds,
        ?string $startDate,
        ?string $endDate,
        ?string $clientId = null,
        ?string $storeId = null,
    ): Collection {
        // Usa o modelo correto baseado no sourceType
        $query = $this->dataSource->getSourceType() === 'monthly'
            ? MonthlySalesSummary::whereIn('product_id', $productIds)
            : Sale::whereIn('product_id', $productIds);

        // Aplica filtro de data usando o dataSource se datas forem fornecidas
        if ($startDate && $endDate) {
            $query = $this->dataSource->applyDateFilter($query, $startDate, $endDate);
        }

        if ($clientId && $clientId !== 'all') {
            $query->where('client_id', $clientId);
        }

        if ($storeId && $storeId !== 'all') {
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
            $productId = $sale->product_id;
            if ($this->dataSource->getSourceType() === 'monthly') {
                $date = $sale->sale_month instanceof Carbon ? $sale->sale_month->format('Y-m-d') : $sale->sale_month;
            } else {
                $date = $sale->sale_date instanceof Carbon ? $sale->sale_date->format('Y-m-d') : $sale->sale_date;
            }
            if (!isset($grouped[$productId])) {
                $grouped[$productId] = [];
            }

            if (!isset($grouped[$productId][$date])) {
                $grouped[$productId][$date] = 0;
            }

            $grouped[$productId][$date] += $sale->total_sale_quantity;
        }

        return $grouped;
    }

    protected function getCurrentStock(string $productId, ?string $startDate, ?string $endDate, ?string $clientId, ?string $storeId): int
    {
        $query = Purchase::where('product_id', $productId)
            ->orderBy('entry_date', 'asc');

        if ($startDate) {
            $query->where('entry_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('entry_date', '<=', $endDate);
        }

        if ($clientId) {
            $query->where('client_id', $clientId);
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
            $currentStock = $this->getCurrentStock($productId, null, null, null, null);
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
