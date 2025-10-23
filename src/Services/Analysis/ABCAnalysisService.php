<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Services\Analysis;

use App\Models\Product;
use App\Services\OptimizedSummarySales;
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
        ?int $storeId = null
    ): array {
        // Busca os produtos
        $products = Product::whereIn('id', $productIds)->get();

        // Classifica os produtos
        $classified = $this->classifyProducts($products, $startDate, $endDate, $storeId);

        return $classified;
    }



    /**
     * Calcula os totais de quantidade, valor e margem
     */
    protected function calculateTotals(Collection $sales, Collection $purchases): array
    {

        // Retorna os totais
        //acquisition_cost -> Custo de aquisição do produto
        //sale_price -> Preço de venda do produto
        //total_profit_margin -> Margem de lucro unitária
        //sale_date -> Data da venda
        //promotion -> Promoção
        //total_sale_quantity -> Quantidade vendida
        //total_sale_value -> Valor total da venda
        //current_stock -> Estoque atual
        return [
            'quantity' => $sales->sum('total_sale_quantity'), // Quantidade vendida
            'value' => $sales->sum('sale_price'), // Valor total da venda
            'margin' => $sales->sum('total_profit_margin'), // Margem de lucro unitária
            'current_stock' => $purchases->sum('current_stock') // Estoque atual
        ];
    }

    /**
     * Classifica os produtos em A, B ou C
     */
    protected function classifyProducts(
        Collection $products,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $storeId = null
    ): array {
        $result = [];

        foreach ($products as $product) {
            // 7896508200041
            $productSales = $product->sales()->when($startDate, function ($query) use ($startDate) {
                $query->where('sale_date', '>=', $startDate);
            })->when($endDate, function ($query) use ($endDate) {
                $query->where('sale_date', '<=', $endDate);
            })->when($storeId, function ($query) use ($storeId) {
                $query->where('store_id', $storeId);
            });

            $quantity = $productSales->sum('total_sale_quantity');
            $value = $productSales->sum('total_sale_value');
            $margin = $productSales->sum('total_profit_margin');
            $salesWithAccessors = $productSales->get();
            $totalCustoMedio = 0;
            $totalImpostos = 0;
            foreach ($salesWithAccessors as $sale) {
                // Usar os accessors do modelo Sale
                $totalCustoMedio += $sale->custo_medio_loja;
                $totalImpostos += $sale->impostos_sale;
            }
            $totalMargem = round($value - $totalImpostos - $totalCustoMedio, 2);

            $margemAbsoluta = round($value - $totalImpostos - $totalCustoMedio, 2);

            $productPurchases = $product->purchases()->when($startDate, function ($query) use ($startDate) {
                $query->where('entry_date', '>=', $startDate);
            })->when($endDate, function ($query) use ($endDate) {
                $query->where('entry_date', '<=', $endDate);
            })->when($storeId, function ($query) use ($storeId) {
                $query->where('store_id', $storeId);
            });
            $currentPurchases = $productPurchases->orderBy('entry_date', 'desc')->first();
            $currentStock = 0;
            $lastPurchase = null;
            $lastSale = null;
            if ($currentPurchases) {
                $lastPurchase = $currentPurchases->entry_date;
                $currentStock = $currentPurchases->current_stock;
            }
            if ($saleDate = $productSales->orderBy('sale_date', 'desc')->first()) {
                $lastSale = $saleDate->sale_date;
            }
            $result[] = [
                'id' => $product->ean,
                'name' => $product->name,
                // SUPERMERCADO > MERCEARIA TRADICIONAL > FARINÁCEOS > FARINHA > DE MILHO > MÉDIA pegar os 5 primeiros níveis
                // 'category' =>  $product->category->full_path, //Atributo analise de sortimento<?php
                // ...existing code...
                // SUPERMERCADO > MERCEARIA TRADICIONAL > FARINÁCEOS > FARINHA > DE MILHO > MÉDIA pegar os 5 primeiros níveis
                'category' => implode(' > ', array_slice(explode(' > ', $product->category->full_path), 0, 5)), //Atributo analise de sortimento
                // ...existing code...
                'quantity' => $quantity,
                'value' => $value,
                'margin' => $totalMargem,
                'currentStock' => $currentStock,
                'lastPurchase' => $lastPurchase,
                'lastSale' => $lastSale,
            ];
        }

        return $result;
    }
}