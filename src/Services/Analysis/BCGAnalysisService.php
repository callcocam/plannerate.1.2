<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */
namespace Callcocam\Plannerate\Services\Analysis;

use App\Models\Product;
use App\Models\SaleSummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection; 
use Illuminate\Support\Facades\Log;

class BCGAnalysisService
{
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
        ?int $storeId = null
    ): array {

        // Busca os produtos
        $products = Product::whereIn('id', $productIds)->get();

        // Busca as vendas no período atual
        $currentSales = $this->getSales($startDate, $endDate, $storeId);

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
     * Busca as vendas dos produtos no período usando dados sumarizados
     */
    protected function getSales( 
        ?string $startDate,
        ?string $endDate,
        ?int $storeId = null
    ): Collection | Builder {
        $query = SaleSummary::where('period_type', 'monthly');

        if ($startDate) {
            $query->where('period_start', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('period_end', '<=', $endDate);
        }

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query;
    }

        /**
     * Busca dados brutos dos produtos para análise BCG usando dados sumarizados
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
            
            // Agregar vendas do produto usando SaleSummary
            $productSummary = $cloneCurrentSales
                ->where('product_id', $product->id)
                ->selectRaw('
                    SUM(total_value) as total_value,
                    SUM(total_quantity) as total_quantity,
                    SUM(total_profit) as total_profit
                ')
                ->first();

            $currentProductSales = $productSummary->total_value ?? 0;
            $currentProductQuantity = $productSummary->total_quantity ?? 0;
            $currentProductMargin = $productSummary->total_profit ?? 0;

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
