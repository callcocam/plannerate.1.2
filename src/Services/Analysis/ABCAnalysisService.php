<?php

namespace Callcocam\Plannerate\Services\Analysis;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ABCAnalysisService
{
    /**
     * Realiza análise ABC dos produtos
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
        ?int $storeId = null
    ): array {
        // Busca os produtos
        $products = Product::whereIn('id', $productIds)->get();

        // Busca as vendas no período
        $sales = $this->getSales($productIds, $startDate, $endDate, $storeId);

        // Calcula os totais
        $totals = $this->calculateTotals($sales);

        // Classifica os produtos
        $classified = $this->classifyProducts($products, $sales, $totals);

        return $classified;
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
     * Calcula os totais de quantidade, valor e margem
     */
    protected function calculateTotals(Collection $sales): array
    {
        return [
            'quantity' => $sales->sum('quantity'),
            'value' => $sales->sum('total_value'),
            'margin' => $sales->sum('margin_value')
        ];
    }

    /**
     * Classifica os produtos em A, B ou C
     */
    protected function classifyProducts(
        Collection $products,
        Collection $sales,
        array $totals
    ): array {
        $result = [];

        foreach ($products as $product) {
            $productSales = $sales->where('product_id', $product->id);
            
            $quantity = $productSales->sum('quantity');
            $value = $productSales->sum('total_value');
            $margin = $productSales->sum('margin_value');

            $result[] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'value' => $value,
                'margin' => $margin,
                'quantity_percent' => $totals['quantity'] > 0 ? ($quantity / $totals['quantity']) * 100 : 0,
                'value_percent' => $totals['value'] > 0 ? ($value / $totals['value']) * 100 : 0,
                'margin_percent' => $totals['margin'] > 0 ? ($margin / $totals['margin']) * 100 : 0
            ];
        }

        return $result;
    }
} 