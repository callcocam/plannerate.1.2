<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Services\Analysis;

use App\Models\Product;
use App\Models\Sale;
use App\Models\MonthlySalesSummary;
use App\Services\Analysis\SalesDataSourceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BCGAnalysisService
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
     * Realiza análise BCG dos produtos
     * 
     * @param array $productIds
     * @param string|null $startDate
     * @param string|null $endDate
     * @param string|null $xAxis
     * @param string|null $yAxis
     * @param int|null $storeId 
     * @return array
     */
    public function analyze(
        array $productIds,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $xAxis = null,
        ?string $yAxis = null,
        ?int $clientId = null,
        ?int $storeId = null
    ): array {

        // Busca os produtos
        $products = Product::whereIn('id', $productIds)->get();

        // Busca as vendas no período atual
        $currentSales = $this->getSales($startDate, $endDate, $clientId, $storeId);

        // Calcula o crescimento e participação de mercado
        $analysis = $this->calculateGrowthAndMarketShare(
            $products,
            $currentSales,
            $xAxis,
            $yAxis
        );

        return $analysis;
    }

    /**
     * Busca as vendas dos produtos no período
     */
    protected function getSales(
        ?string $startDate,
        ?string $endDate,
        ?int $clientId = null,
        ?int $storeId = null
    ): Collection | Builder {
        // Usa o modelo correto baseado no sourceType
        $query = $this->dataSource->getSourceType() === 'monthly' 
            ? MonthlySalesSummary::query() 
            : Sale::query();

        // Aplica filtro de data usando o dataSource se datas forem fornecidas
        if ($startDate && $endDate) {
            $query = $this->dataSource->applyDateFilter($query, $startDate, $endDate);
        }

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query;
    }

    /**
     * Busca dados brutos dos produtos para análise BCG
     */
    protected function calculateGrowthAndMarketShare(
        Collection $products,
        Builder $currentSales,
        ?string $xAxis = null,
        ?string $yAxis = null
    ): array {
        $result = [];
        foreach ($products as $product) {
            $cloneCurrentSales = clone $currentSales;
            // Vendas do produto no período atual (filtradas)
            $currentProductSales = $cloneCurrentSales->where('product_id', $product->id)->sum('total_sale_value');
            $currentProductQuantity = $cloneCurrentSales->where('product_id', $product->id)->sum('total_sale_quantity');
            $currentProductMargin = $cloneCurrentSales->where('product_id', $product->id)->sum('total_profit_margin');

            // Calcular valores para os eixos baseado na seleção do usuário
            $xValue = $this->calculateAxisValue($xAxis, $currentProductSales, $currentProductQuantity, $currentProductMargin);
            $yValue = $this->calculateAxisValue($yAxis, $currentProductSales, $currentProductQuantity, $currentProductMargin);

            // Filtrar apenas produtos que têm vendas no período (valores > 0)
            if ($xValue <= 0 && $yValue <= 0) {
                continue; // Pula produtos sem vendas
            }

            $category = $product->category_name ?: 'Sem Categoria';

            // Log para debug (apenas para os primeiros produtos)
            if (count($result) < 3) {
                Log::info("BCG Debug - Produto {$product->ean}:", [
                    'category' => $category,
                    'xAxis' => $xAxis,
                    'yAxis' => $yAxis,
                    'xValue' => $xValue,
                    'yValue' => $yValue,
                    'currentProductSales' => $currentProductSales,
                    'currentProductQuantity' => $currentProductQuantity,
                    'currentProductMargin' => $currentProductMargin
                ]);
            }

            // Retornar apenas dados brutos - classificação será feita no frontend
            $result[] = [
                'product_id' => $product->id,
                'ean' => $product->ean,
                'category' => $category,
                'current_sales' => $currentProductSales,
                'x_axis_value' => round($xValue, 2),
                'y_axis_value' => round($yValue, 2),
                'x_axis_label' => $xAxis ?: 'VALOR DE VENDA',
                'y_axis_label' => $yAxis ?: 'MARGEM DE CONTRIBUIÇÃO',
                'classification' => '' // Será calculado no frontend
            ];
        }

        return $result;
    }

    /**
     * Calcula o valor do eixo baseado na métrica selecionada
     */
    protected function calculateAxisValue(?string $axis, float $sales, float $quantity, float $margin): float
    {
        switch ($axis) {
            case 'VENDA EM QUANTIDADE':
                return $quantity;
            case 'VALOR DE VENDA':
                return $sales;
            case 'MARGEM DE CONTRIBUIÇÃO':
                return $margin;
            default:
                return $sales; // Valor padrão
        }
    }
}
