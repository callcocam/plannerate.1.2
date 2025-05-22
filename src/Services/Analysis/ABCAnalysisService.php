<?php

namespace Callcocam\Plannerate\Services\Analysis;

use App\Models\Product;
use App\Models\Purchase;
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
     * @param array|null $weights
     * @param array|null $thresholds
     * @return array
     */
    public function analyze(
        array $productIds,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null,
        ?array $weights = null,
        ?array $thresholds = null
    ): array {
        // Busca os produtos
        $products = Product::whereIn('id', $productIds)->get();



        // Classifica os produtos
        $classified = $this->classifyProducts($products, $weights, $thresholds);

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
     * Busca as compras dos produtos no período
     */
    protected function getPurchases(
        array $productIds,
        ?string $startDate,
        ?string $endDate,
        ?int $storeId
    ): Collection {
        $query = Purchase::whereIn('product_id', $productIds);

        if ($startDate) {
            $query->where('entry_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('entry_date', '<=', $endDate);
        }

        return $query->get();
    }

    /**
     * Calcula os totais de quantidade, valor e margem
     */
    protected function calculateTotals(Collection $sales, Collection $purchases): array
    {
        return [
            'quantity' => $sales->sum('sale_quantity'),
            'value' => $sales->sum('sale_value'),
            'margin' => $sales->sum('unit_contribution_margin'),
            'current_stock' => $purchases->sum('current_stock')
        ];
    }

    /**
     * Classifica os produtos em A, B ou C
     */
    protected function classifyProducts(
        Collection $products,
        array $weights,
        array $thresholds
    ): array {
        $result = [];

        foreach ($products as $product) {
            $productSales = $product->sales;
            $quantity = $productSales->sum('sale_quantity');
            $value = $productSales->sum('sale_value');
            $margin = $productSales->sum('unit_contribution_margin');
            $productPurchases = $product->purchases;
            $currentStock = $productPurchases->first()->current_stock;
            // $totals = $this->calculateTotals($productSales, $productPurchases);
 
            // $margin = $marginPonderada + $valuePonderada + $quantityPonderada;
            $lastPurchase = null;
            $lastSale = null;
            if ($entryDate = $productPurchases->first()) {
                $lastPurchase = $entryDate->entry_date;
            }
            if ($saleDate = $productSales->first()) {
                $lastSale = $saleDate->sale_date;
            }
            $result[] = [
                'id' => $product->id,
                'product_id' => $product->id,
                'ean' => $product->ean,
                'name' => $product->name,
                'category' => $product->category_name, //Atributo analise de sortimento
                'quantity' => $quantity,
                'value' => number_format($value),
                'margin' => floatval($margin),
                'currentStock' => $currentStock,
                'lastPurchase' => $lastPurchase,
                'lastSale' => $lastSale,
            ];
        }

        return $result;
    }
}
