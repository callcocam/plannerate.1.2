import { Product } from '@/types/segment';
import { ref, computed } from 'vue';

// Tipos para configuração hierárquica
export interface BCGConfiguration {
    classifyBy: string;
    displayBy: string;
    xAxis: string;
    yAxis: string;
    rule?: string;
    isValid: boolean;
}

// Tipos para dados do service (atualizados)
export interface BCGServiceData {
    product_id: number | string;
    ean: string;
    category: string;
    display_group: string;
    classify_group: string;
    current_sales: number;
    x_axis_value: number;
    y_axis_value: number;
    x_axis_label: string;
    y_axis_label: string;
    configuration?: {
        classify_by: string;
        display_by: string;
        group_size?: number;
    };
}

// Tipos para dados processados
export interface BCGData {
    ean: string;
    description: string;
    category: string;
    displayGroup: string;
    classifyGroup: string;
    product_id: string;
    yValue: number;
    xValue: number;
    groupSize?: number;
}

export type BCGClassification =
    | 'Alto valor - manutenção'
    | 'Incentivo - volume'
    | 'Incentivo - lucro'
    | 'Incentivo - valor'
    | 'Baixo valor - avaliar';

export interface BCGResult extends BCGData {
    classification: BCGClassification;
    color: string;
}

interface ProductWithCategory extends Product {
    id: string;
    ean: string;
    description: string;
    product_id: string;
    category: string;
}

export function useBCGMatrixImproved() {
    const results = ref<BCGResult[]>([]);
    const configuration = ref<BCGConfiguration>({
        classifyBy: 'categoria',
        displayBy: 'produto',
        xAxis: 'VALOR DE VENDA',
        yAxis: 'MARGEM DE CONTRIBUIÇÃO',
        isValid: true
    });

    const axisLabels = ref<{ x: string; y: string }>({
        x: 'VALOR DE VENDA',
        y: 'MARGEM DE CONTRIBUIÇÃO'
    });

    // Cores para cada classificação
    const classificationColors = {
        'Alto valor - manutenção': '#00B050', // Verde
        'Incentivo - volume': '#00B0F0',      // Azul claro
        'Incentivo - lucro': '#BF90FF',       // Roxo claro
        'Incentivo - valor': '#FF6347',       // Vermelho claro
        'Baixo valor - avaliar': '#FF6347' // Vermelho claro
    };

    // Classificação otimizada
    const classifyProduct = (
        xValue: number,
        yValue: number,
        xAverage: number,
        yAverage: number
    ): BCGClassification => {
        const xLabel = axisLabels.value.x;
        const yLabel = axisLabels.value.y;

        // Determinar quadrante
        const highX = xValue > xAverage;
        const highY = yValue > yAverage;
        console.log(xValue, xAverage, yValue, yAverage, highX, highY);
        // Alto valor (ambos acima da média)
        if (highX && highY) {
            return 'Alto valor - manutenção';
        }

        // Mapear combinações específicas de métricas
        const getIncentiveType = (): BCGClassification => {
            // Combinações que indicam incentivo de volume
            if ((xLabel.includes('QUANTIDADE') && yLabel.includes('MARGEM')) ||
                (xLabel.includes('MARGEM') && yLabel.includes('QUANTIDADE'))) {
                return 'Incentivo - volume';
            }

            // Combinações que indicam incentivo de valor
            if ((xLabel.includes('VALOR') && yLabel.includes('QUANTIDADE')) ||
                (xLabel.includes('QUANTIDADE') && yLabel.includes('VALOR'))) {
                return 'Incentivo - valor';
            }

            // Combinações que indicam incentivo de lucro
            if ((xLabel.includes('VALOR') && yLabel.includes('MARGEM')) ||
                (xLabel.includes('MARGEM') && yLabel.includes('VALOR'))) {
                return 'Incentivo - lucro';
            }

            return 'Incentivo - valor'; // Padrão
        };

        // Para outros quadrantes, determinar tipo de incentivo baseado nas métricas
        if (highX || highY) {
            return getIncentiveType();
        }

        // Baixo valor (ambos abaixo da média)
        return 'Baixo valor - avaliar';
    };

    // Calcular médias por grupo de classificação
    const calculateGroupAverages = (data: BCGData[]): Map<string, { xAverage: number; yAverage: number }> => {
        const groupStats = new Map<string, { sumX: number; sumY: number; count: number }>();

        // Agrupar por categoria (grupo de classificação)
        data.forEach(item => {
            const groupKey = item.classifyGroup || item.category;

            if (!groupStats.has(groupKey)) {
                groupStats.set(groupKey, { sumX: 0, sumY: 0, count: 0 });
            }

            const stats = groupStats.get(groupKey)!;
            if (typeof item.xValue === 'number' && typeof item.yValue === 'number') {
                stats.sumX += item.xValue;
                stats.sumY += item.yValue;
                stats.count += 1;
            }
        });

        // Calcular médias
        const averages = new Map<string, { xAverage: number; yAverage: number }>();
        groupStats.forEach((stats, groupKey) => {
            averages.set(groupKey, {
                xAverage: stats.count > 0 ? stats.sumX / stats.count : 0,
                yAverage: stats.count > 0 ? stats.sumY / stats.count : 0
            });
        });

        return averages;
    };

    // Função principal para processar dados do service
    const processData = (serviceData: BCGServiceData[], products: ProductWithCategory[]) => {
        try {
            // Mapear dados do service
            const mappedData: BCGData[] = serviceData.map((item: BCGServiceData) => {
                const product = products.find(p => p.id === item.product_id.toString());

                return {
                    ean: item.ean || product?.ean || '',
                    description: product?.name || product?.description || item.display_group || '',
                    category: item.category,
                    displayGroup: item.display_group,
                    classifyGroup: item.classify_group,
                    product_id: item.product_id.toString(),
                    yValue: item.y_axis_value,
                    xValue: item.x_axis_value,
                    groupSize: item.configuration?.group_size
                };
            });

            // Atualizar configuração se disponível
            if (serviceData.length > 0) {
                const firstItem = serviceData[0];
                axisLabels.value = {
                    x: firstItem.x_axis_label,
                    y: firstItem.y_axis_label
                };

                if (firstItem.configuration) {
                    configuration.value = {
                        ...configuration.value,
                        classifyBy: firstItem.configuration.classify_by,
                        displayBy: firstItem.configuration.display_by,
                        xAxis: firstItem.x_axis_label,
                        yAxis: firstItem.y_axis_label
                    };
                }
            }

            // Calcular médias por grupo
            const groupAverages = calculateGroupAverages(mappedData);

            // Processar resultados
            results.value = mappedData.map((item: BCGData) => {
                const groupKey = item.classifyGroup || item.category;
                const averages = groupAverages.get(groupKey);
                const xAverage = averages?.xAverage || 0;
                const yAverage = averages?.yAverage || 0; 
                const classification = classifyProduct(item.xValue, item.yValue, xAverage, yAverage);

                return {
                    ...item,
                    classification,
                    color: classificationColors[classification],
                };
            });

            return results.value;
        } catch (error) {
            console.error('Erro ao processar dados BCG:', error);
            throw error;
        }
    };

    // Funções para análise dos resultados
    const getResultsByClassification = (classification: BCGClassification) => {
        return results.value.filter(result => result.classification === classification);
    };

    const getResultsByGroup = (groupType: 'classify' | 'display', groupValue: string) => {
        return results.value.filter(result =>
            groupType === 'classify' ? result.classifyGroup === groupValue : result.displayGroup === groupValue
        );
    };

    const getClassificationSummary = computed(() => {
        const summary = {
            'Alto valor - manutenção': 0,
            'Incentivo - volume': 0,
            'Incentivo - lucro': 0,
            'Incentivo - valor': 0,
            'Baixo valor - avaliar': 0
        };

        results.value.forEach(result => {
            summary[result.classification]++;
        });

        return summary;
    });

    const getGroupSummary = computed(() => {
        const classifyGroups = new Set(results.value.map(r => r.classifyGroup));
        const displayGroups = new Set(results.value.map(r => r.displayGroup));

        return {
            totalClassifyGroups: classifyGroups.size,
            totalDisplayGroups: displayGroups.size,
            totalItems: results.value.length,
            aggregationLevel: configuration.value.displayBy,
            classificationLevel: configuration.value.classifyBy
        };
    });

    // Função para drill-down (navegar entre níveis)
    const drillDown = (groupValue: string, newDisplayBy: string) => {
        // Esta função seria implementada para permitir navegação entre níveis
        // Por exemplo, clicar em uma categoria para ver produtos dessa categoria
        console.log(`Drill down para ${groupValue} com exibição por ${newDisplayBy}`);
    };

    return {
        results,
        configuration,
        axisLabels,
        processData,
        getResultsByClassification,
        getResultsByGroup,
        getClassificationSummary,
        getGroupSummary,
        classificationColors,
        drillDown
    };
}