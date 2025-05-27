import { Product } from '@/types/segment';
import { ref } from 'vue';

// Tipos para dados vindos do service
export interface BCGServiceData {
  product_id: number;
  ean: string;
  category: string;
  current_sales: number;
  previous_sales: number;
  growth_rate: number;
  market_share: number;
  x_axis_value: number;
  y_axis_value: number;
  x_axis_label: string;
  y_axis_label: string;
  classification: string; // Dados brutos do service, classificação será feita no frontend
}

// Tipos para dados processados
export interface BCGData {
  ean: string;
  description: string;
  category: string;
  product_id: string;
  yValue: number;
  xValue: number;
}

export type BCGClassification =
  | 'Alto valor - manutenção'
  | 'Incentivo - volume'
  | 'Incentivo - lucro'
  | 'Incentivo - valor'
  | 'Baixo valor - descontinuar';

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

export function useBCGMatrix() {
  const results = ref<BCGResult[]>([]);
  const axisLabels = ref<{ x: string; y: string }>({ x: 'VALOR DE VENDA', y: 'MARGEM DE CONTRIBUIÇÃO' });

  // Cores para cada classificação (mapeadas para o frontend)
  const classificationColors = {
    'Alto valor - manutenção': '#00B050', // Verde
    'Incentivo - volume': '#00B0F0',      // Azul claro
    'Incentivo - lucro': '#BF90FF',       // Roxo claro
    'Incentivo - valor': '#FF6347',       // Vermelho claro
    'Baixo valor - descontinuar': '#FF6347' // Vermelho claro
  };

  // Classificar produto baseado nas médias da categoria (seguindo lógica do VBA)
  const classifyProduct = (xValue: number, yValue: number, xAverage: number, yAverage: number): BCGClassification => {

    const xLabel = axisLabels.value.x;
    const yLabel = axisLabels.value.y;

    console.log('xValue', xValue, 'yValue', yValue, 'xAverage', xAverage, 'yAverage', yAverage, 'xLabel', xLabel, 'yLabel', yLabel);

    if (xValue > xAverage && yValue > yAverage) {
      return 'Alto valor - manutenção'; // Verde
    } 
    
    else if (yLabel === 'VENDA EM QUANTIDADE' && yValue <= yAverage && xLabel === 'MARGEM DE CONTRIBUIÇÃO' && xValue > xAverage) {
      return 'Incentivo - volume'; // Azul claro
    } else if (yLabel === 'MARGEM DE CONTRIBUIÇÃO' && yValue > yAverage && xLabel === 'VENDA EM QUANTIDADE' && xValue <= xAverage) {
      return 'Incentivo - volume'; // Roxo claro
    } else if (yLabel === 'VENDA EM QUANTIDADE' && yValue > yAverage && xLabel === 'MARGEM DE CONTRIBUIÇÃO' && xValue <= xAverage) {
      return 'Incentivo - volume'; // Azul claro
    } else if (yLabel === 'MARGEM DE CONTRIBUIÇÃO' && yValue <= yAverage && xLabel === 'VENDA EM QUANTIDADE' && xValue > xAverage) {
      return 'Incentivo - volume'; // Roxo claro
    } 
    
    else if (yLabel === 'VALOR DE VENDA' && yValue <= yAverage && xLabel === 'VENDA EM QUANTIDADE' && xValue > xAverage) {
      return 'Incentivo - valor'; // Azul claro
    } else if (yLabel === 'VENDA EM QUANTIDADE' && yValue > yAverage && xLabel === 'VALOR DE VENDA' && xValue <=  xAverage) {
      return 'Incentivo - valor'; // Azul claro
    } else if (yLabel === 'VALOR DE VENDA' && yValue > yAverage && xLabel === 'VENDA EM QUANTIDADE' && xValue <= xAverage) {
      return 'Incentivo - valor'; // Azul claro
    } else if (yLabel === 'VENDA EM QUANTIDADE' && yValue <= yAverage && xLabel === 'VALOR DE VENDA' && xValue >  xAverage) {
      return 'Incentivo - valor'; // Azul claro
    }
    
    else if (yLabel === 'MARGEM DE CONTRIBUIÇÃO' && yValue <= yAverage && xLabel === 'VALOR DE VENDA' && xValue > xAverage) {
      return 'Incentivo - lucro'; // Azul claro
    } else if (yLabel === 'VALOR DE VENDA' && yValue > yAverage && xLabel === 'MARGEM DE CONTRIBUIÇÃO' && xValue <= xAverage) {
      return 'Incentivo - lucro'; // Azul claro
    }  else if (yLabel === 'MARGEM DE CONTRIBUIÇÃO' && yValue > yAverage && xLabel === 'VALOR DE VENDA' && xValue <= xAverage) {
      return 'Incentivo - lucro'; // Azul claro
    } else if (yLabel === 'VALOR DE VENDA' && yValue <= yAverage && xLabel === 'MARGEM DE CONTRIBUIÇÃO' && xValue > xAverage) {
      return 'Incentivo - lucro'; // Azul claro
    } 
    
    else {
      return 'Baixo valor - descontinuar'; // Vermelho claro
    }
  };

  // Calcular médias por categoria (seguindo lógica do VBA)
  const calculateCategoryAverages = (data: BCGData[]): Map<string, { xAverage: number; yAverage: number }> => {
    const categoryStats = new Map<string, { sumX: number; sumY: number; count: number }>();

    // Primeira passagem: acumular somas e contagens por categoria
    data.forEach(item => {
      if (!categoryStats.has(item.category)) {
        categoryStats.set(item.category, { sumX: 0, sumY: 0, count: 0 });
      }

      const stats = categoryStats.get(item.category)!;
      if (typeof item.xValue === 'number' && typeof item.yValue === 'number') {
        stats.sumX += item.xValue;
        stats.sumY += item.yValue;
        stats.count += 1;
      }
    });

    // Segunda passagem: calcular médias
    const averages = new Map<string, { xAverage: number; yAverage: number }>();
    categoryStats.forEach((stats, category) => {
      averages.set(category, {
        xAverage: stats.count > 0 ? stats.sumX / stats.count : 0,
        yAverage: stats.count > 0 ? stats.sumY / stats.count : 0
      });
    });

    return averages;
  };

  // Classificar um item baseado nas médias da categoria 

  // Função principal para processar os dados vindos do service (seguindo lógica do VBA)
  const processData = (serviceData: BCGServiceData[], products: ProductWithCategory[]) => {
    try {
      // Mapear dados do service para o formato esperado pelo frontend
      const mappedData: BCGData[] = serviceData.map((item: BCGServiceData) => {
        const product = products.find(p => p.id === item.product_id.toString());

        return {
          ean: item.ean || product?.ean || '',
          description: product?.name || product?.description || '',
          category: item.category,
          product_id: item.product_id.toString(),
          yValue: item.y_axis_value,     // Valor do eixo Y selecionado
          xValue: item.x_axis_value      // Valor do eixo X selecionado
        };
      });


      // Atualizar labels dos eixos
      if (serviceData.length > 0) {
        axisLabels.value = {
          x: serviceData[0].x_axis_label,
          y: serviceData[0].y_axis_label
        };
      }
      // Calcular médias por categoria (seguindo lógica do VBA)
      const categoryAverages = calculateCategoryAverages(mappedData);

      // Processar cada item e classificar baseado nas médias da categoria
      results.value = mappedData.map((item: BCGData) => {
        const averages = categoryAverages.get(item.category);
        const xAverage = averages?.xAverage || 0;
        const yAverage = averages?.yAverage || 0;

        // Classificar produto baseado nas médias da categoria
        const classification = classifyProduct(item.xValue, item.yValue, xAverage, yAverage);

        return {
          ean: item.ean,
          description: item.description,
          category: item.category,
          product_id: item.product_id,
          yValue: item.yValue,
          xValue: item.xValue,
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

  // Funções auxiliares para análise
  const getResultsByClassification = (classification: BCGClassification) => {
    return results.value.filter(result => result.classification === classification);
  };

  const getResultsByCategory = (category: string) => {
    return results.value.filter(result => result.category === category);
  };

  return {
    results,
    axisLabels,
    processData,
    getResultsByClassification,
    getResultsByCategory,
    classificationColors
  };
} 