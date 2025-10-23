<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Services\Analysis;

use App\Models\Product;
use Illuminate\Support\Collection;

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
        ?string $clientId = null,
        ?string $storeId = null
    ): array {
        // Busca os produtos
        $products = Product::whereIn('id', $productIds)->get();

        // Classifica os produtos
        $classified = $this->classifyProducts($products, $startDate, $endDate, $clientId, $storeId);

        return $classified;
    }



    /**
     * Calcula os totais de quantidade, valor e margem
     */
    protected function calculateTotals(Collection $sales, Collection $purchases): array
    {
        return [
            'quantity' => $sales->sum('total_sale_quantity'),
            'value' => $sales->sum('total_sale_value'),
            'margin' => $sales->sum('total_profit_margin'),
            'current_stock' => $purchases->sum('current_stock')
        ];
    }

    /**
     * Classifica os produtos em A, B ou C
     */
    protected function classifyProducts(
        Collection $products,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $clientId = null,
        ?string $storeId = null
    ): array {
        $result = [];

        foreach ($products as $product) {
            $productSales = $product->saleSummaries()
                ->when($startDate, function ($query) use ($startDate) {
                    $query->where('period_start', '>=', $startDate);
                })->when($endDate, function ($query) use ($endDate) {
                    $query->where('period_end', '<=', $endDate);
                })
                ->when($clientId, function ($query) use ($clientId) {
                    $query->where('client_id', $clientId);
                });
            $quantity = $productSales->sum('total_sale_quantity');
            $value = $productSales->sum('total_sale_value');
            $margin = $productSales->sum('total_profit_margin');
            $productPurchases = $product->purchases()->when($startDate, function ($query) use ($startDate) {
                $query->where('entry_date', '>=', $startDate);
            })->when($endDate, function ($query) use ($endDate) {
                $query->where('entry_date', '<=', $endDate);
            })->when($storeId, function ($query) use ($storeId) {
                $query->where('store_id', $storeId);
            });
            $currentPurchases = $productPurchases->first();
            $currentStock = 0;
            $lastPurchase = null;
            $lastSale = null;
            if ($currentPurchases) {
                $lastPurchase = $currentPurchases->entry_date;
                $currentStock = $currentPurchases->current_stock;
            }
            if ($saleDate = $productSales->first()) {
                $lastSale = $saleDate->period_end;
            }
            $result[] = [
                'id' => $product->ean,
                'name' => $product->name,
                'category' => $product->category_name, //Atributo analise de sortimento
                'quantity' => $quantity,
                'value' => $value,
                'margin' => $margin,
                'currentStock' => $currentStock,
                'lastPurchase' => $lastPurchase,
                'lastSale' => $lastSale,
            ];
        }

        return $result;
    }
}
