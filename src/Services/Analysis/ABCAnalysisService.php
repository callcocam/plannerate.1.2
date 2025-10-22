<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Services\Analysis;

use App\Models\Product;
use App\Models\SaleSummary;
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
            // Usar dados sumarizados para performance
            $summaryQuery = SaleSummary::where('product_id', $product->id)
                ->where('period_type', 'monthly')
                ->when($startDate, fn($q) => $q->where('period_start', '>=', $startDate))
                ->when($endDate, fn($q) => $q->where('period_end', '<=', $endDate))
                ->when($storeId, fn($q) => $q->where('store_id', $storeId));

            // Agregar dados já sumarizados
            $summary = $summaryQuery->selectRaw('
                SUM(total_quantity) as total_quantity,
                SUM(total_value) as total_value,
                SUM(total_profit) as total_profit,
                SUM(total_cost) as total_cost
            ')->first();

            $quantity = $summary->total_quantity ?? 0;
            $value = $summary->total_value ?? 0;
            $totalProfit = $summary->total_profit ?? 0;

            // Buscar estoque atual (purchases não tem sumarização)
            $productPurchases = $product->purchases()
                ->when($startDate, fn($q) => $q->where('entry_date', '>=', $startDate))
                ->when($endDate, fn($q) => $q->where('entry_date', '<=', $endDate))
                ->when($storeId, fn($q) => $q->where('store_id', $storeId));

            $currentPurchases = $productPurchases->orderBy('entry_date', 'desc')->first();
            $currentStock = 0;
            $lastPurchase = null;
            
            if ($currentPurchases) {
                $lastPurchase = $currentPurchases->entry_date;
                $currentStock = $currentPurchases->current_stock;
            }

            // Buscar última venda (da summary)
            $lastSaleRecord = SaleSummary::where('product_id', $product->id)
                ->where('period_type', 'monthly')
                ->when($storeId, fn($q) => $q->where('store_id', $storeId))
                ->orderBy('period_end', 'desc')
                ->first();
            
            $lastSale = $lastSaleRecord?->period_end;

            $result[] = [
                'id' => $product->ean,
                'name' => $product->name,
                // SUPERMERCADO > MERCEARIA TRADICIONAL > FARINÁCEOS > FARINHA > DE MILHO > MÉDIA pegar os 5 primeiros níveis
                'category' => $product->category 
                    ? implode(' > ', array_slice(explode(' > ', $product->category->full_path), 0, 5))
                    : 'Sem Categoria',
                'quantity' => $quantity,
                'value' => $value,
                'margin' => round($totalProfit, 2),
                'currentStock' => $currentStock,
                'lastPurchase' => $lastPurchase,
                'lastSale' => $lastSale,
            ];
        }

        return $result;
    }
}
