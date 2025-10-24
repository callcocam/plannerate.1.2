<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Services\Analysis;

use App\Models\Product;
use App\Services\Analysis\SalesDataSourceService;
use Illuminate\Support\Collection;

class ABCAnalysisService
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
        $this->dataSource = new SalesDataSourceService($sourceType ?? 'monthly');
    }

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
            // Usa o dataSource para obter a query (sales ou monthly_sales_summaries)
            $productSales = $this->dataSource->getQueryForProduct($product);
            
            // Aplica filtro de data usando o dataSource
            if ($startDate && $endDate) {
                $productSales = $this->dataSource->applyDateFilter($productSales, $startDate, $endDate);
            }
            
            // Aplica filtros adicionais
            $productSales = $productSales->when($storeId, function ($query) use ($storeId) {
                $query->where('store_id', $storeId);
            });

            $quantity = $productSales->sum('total_sale_quantity');
            $value = $productSales->sum('total_sale_value');
            $margin = $productSales->sum('total_profit_margin');
            $salesWithAccessors = $productSales->get();
            $totalCustoMedio = 0;
            $totalImpostos = 0;
            foreach ($salesWithAccessors as $sale) {
                // Usar os accessors do modelo Sale ou MonthlySalesSummary
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
            
            // Buscar última venda usando o campo de data correto
            $dateField = $this->dataSource->getDateFieldName();
            if ($saleDate = $this->dataSource->getQueryForProduct($product)->orderBy($dateField, 'desc')->first()) {
                $lastSale = $saleDate->{$dateField};
            }
            
            $result[] = [
                'id' => $product->ean,
                'name' => $product->name,
                // SUPERMERCADO > MERCEARIA TRADICIONAL > FARINÁCEOS > FARINHA > DE MILHO > MÉDIA pegar os 5 primeiros níveis
                'category' => implode(' > ', array_slice(explode(' > ', $product->category->full_path), 0, 5)), //Atributo analise de sortimento
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