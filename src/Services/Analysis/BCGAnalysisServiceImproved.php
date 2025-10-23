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

class BCGAnalysisServiceImproved
{
    // Mapeamento dos níveis hierárquicos para campos do banco
    private const HIERARCHY_MAPPING = [
        'segmento_varejista' => 'retail_segment',
        'departamento' => 'department',
        'subdepartamento' => 'subdepartment',
        'categoria' => 'category_name',
        'subcategoria' => 'subcategory',
        'produto' => 'id'
    ];

    /**
     * Realiza análise BCG dos produtos com configuração hierárquica
     */
    public function analyze(
        array $productIds,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $xAxis = null,
        ?string $yAxis = null,
        ?string $clientId = null,
        ?string $storeId = null,
        ?string $classifyBy = 'categoria',
        ?string $displayBy = 'produto'
    ): array {

        // Validar configuração
        if (!$this->isValidConfiguration($classifyBy, $displayBy)) {
            throw new \InvalidArgumentException("Configuração inválida: classificar por {$classifyBy} e exibir por {$displayBy}");
        }

        // Buscar produtos
        $products = Product::whereIn('id', $productIds)->get();

        // Buscar vendas
        $currentSales = $this->getSales($startDate, $endDate, $clientId, $storeId);

        // Calcular dados agregados baseado na configuração
        $aggregatedData = $this->calculateAggregatedData(
            $products,
            $currentSales,
            $classifyBy,
            $displayBy,
            $xAxis,
            $yAxis
        );

        return $aggregatedData;
    }

    /**
     * Calcula dados agregados baseado na configuração hierárquica
     */
    private function calculateAggregatedData(
        Collection $products,
        Builder $currentSales,
        string $classifyBy,
        string $displayBy,
        ?string $xAxis,
        ?string $yAxis
    ): array {
        $result = [];

        // Agrupar produtos por nível de classificação
        $classifyField = self::HIERARCHY_MAPPING[$classifyBy];
        $displayField = self::HIERARCHY_MAPPING[$displayBy];

        // Se estamos agrupando por produto, mantemos lógica individual
        if ($displayBy === 'produto') {
            return $this->calculateProductLevel($products, $currentSales, $classifyBy, $xAxis, $yAxis);
        }

        // Agrupar por nível de exibição
        $groupedProducts = $products->groupBy($displayField);

        foreach ($groupedProducts as $displayValue => $groupProducts) {
            // Determinar o grupo de classificação (para calcular médias)
            $classifyValue = $groupProducts->first()->{$classifyField} ?? 'Sem Classificação';

            // Agregar vendas do grupo
            $aggregatedSales = $this->aggregateGroupSales($groupProducts, $currentSales);

            if ($aggregatedSales['total_sales'] <= 0) {
                continue; // Pular grupos sem vendas
            }

            // Calcular valores dos eixos
            $xValue = $this->calculateAxisValue($xAxis, $aggregatedSales);
            $yValue = $this->calculateAxisValue($yAxis, $aggregatedSales);

            $result[] = [
                'product_id' => 'group_' . md5($displayValue), // ID único para o grupo
                'ean' => $this->generateGroupEAN($groupProducts),
                'category' => $classifyValue, // Usado para calcular médias
                'display_group' => $displayValue,
                'classify_group' => $classifyValue,
                'current_sales' => $aggregatedSales['total_sales'],
                'x_axis_value' => round($xValue, 2),
                'y_axis_value' => round($yValue, 2),
                'x_axis_label' => $xAxis ?: 'VALOR DE VENDA',
                'y_axis_label' => $yAxis ?: 'MARGEM DE CONTRIBUIÇÃO',
                'configuration' => [
                    'classify_by' => $classifyBy,
                    'display_by' => $displayBy,
                    'group_size' => $groupProducts->count()
                ]
            ];
        }

        return $result;
    }

    /**
     * Calcula dados no nível de produto individual
     */
    private function calculateProductLevel(
        Collection $products,
        Builder $currentSales,
        string $classifyBy,
        ?string $xAxis,
        ?string $yAxis
    ): array {
        $result = [];
        $classifyField = self::HIERARCHY_MAPPING[$classifyBy];

        foreach ($products as $product) {
            $cloneCurrentSales = clone $currentSales;

            // Vendas do produto
            $productSales = $cloneCurrentSales->where('product_id', $product->id);
            $currentProductSales = $productSales->sum('total_sale_value');
            $currentProductQuantity = $productSales->sum('total_sale_quantity');
            $currentProductMargin = $productSales->sum('total_profit_margin');

            // Valores dos eixos
            $xValue = $this->calculateAxisValue($xAxis, [
                'total_sales' => $currentProductSales,
                'total_quantity' => $currentProductQuantity,
                'total_margin' => $currentProductMargin
            ]);

            $yValue = $this->calculateAxisValue($yAxis, [
                'total_sales' => $currentProductSales,
                'total_quantity' => $currentProductQuantity,
                'total_margin' => $currentProductMargin
            ]);

            if ($xValue <= 0 && $yValue <= 0) {
                continue;
            }

            $classifyValue = $product->{$classifyField} ?? 'Sem Classificação';

            $result[] = [
                'product_id' => $product->id,
                'ean' => $product->ean,
                'category' => $classifyValue, // Usado para calcular médias
                'display_group' => $product->name,
                'classify_group' => $classifyValue,
                'current_sales' => round($currentProductSales, 2),
                'x_axis_value' => round($xValue, 2),
                'y_axis_value' => round($yValue, 2),
                'x_axis_label' => $xAxis ?: 'VALOR DE VENDA',
                'y_axis_label' => $yAxis ?: 'MARGEM DE CONTRIBUIÇÃO',
                'configuration' => [
                    'classify_by' => $classifyBy,
                    'display_by' => 'produto'
                ]
            ];
        }

        return $result;
    }

    /**
     * Agrega vendas de um grupo de produtos
     */
    private function aggregateGroupSales(Collection $products, Builder $currentSales): array
    {
        $productIds = $products->pluck('id')->toArray();
        $cloneCurrentSales = clone $currentSales;

        $groupSales = $cloneCurrentSales->whereIn('product_id', $productIds);

        return [
            'total_sales' => $groupSales->sum('total_sale_value'),
            'total_quantity' => $groupSales->sum('total_sale_quantity'),
            'total_margin' => $groupSales->sum('total_profit_margin')
        ];
    }

    /**
     * Calcula valor do eixo baseado nos dados agregados
     */
    private function calculateAxisValue(?string $axis, array $aggregatedData): float
    {
        switch ($axis) {
            case 'VENDA EM QUANTIDADE':
                return $aggregatedData['total_quantity'] ?? 0;
            case 'VALOR DE VENDA':
                return $aggregatedData['total_sales'] ?? 0;
            case 'MARGEM DE CONTRIBUIÇÃO':
                return $aggregatedData['total_margin'] ?? 0;
            default:
                return $aggregatedData['total_sales'] ?? 0;
        }
    }

    /**
     * Gera EAN representativo para um grupo
     */
    private function generateGroupEAN(Collection $products): string
    {
        if ($products->count() === 1) {
            return $products->first()->ean;
        }

        return "GRUPO_" . $products->count() . "_ITENS";
    }

    /**
     * Valida se a configuração é permitida
     */
    private function isValidConfiguration(string $classifyBy, string $displayBy): bool
    {
        $validCombinations = [
            'segmento_varejista' => ['departamento', 'subdepartamento', 'categoria', 'subcategoria', 'produto'],
            'departamento' => ['subdepartamento', 'categoria', 'produto'],
            'subdepartamento' => ['categoria', 'produto'],
            'categoria' => ['subcategoria', 'produto'],
            'subcategoria' => ['produto']
        ];

        return isset($validCombinations[$classifyBy]) &&
            in_array($displayBy, $validCombinations[$classifyBy]);
    }

    /**
     * Busca as vendas dos produtos no período
     */
    private function getSales(
        ?string $startDate,
        ?string $endDate,
        ?string $clientId = null,
        ?string $storeId = null
    ): Builder {
        $query = SaleSummary::query();

        if ($startDate) {
            $query->where('period_start', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('period_end', '<=', $endDate);
        }

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        return $query;
    }
}
