import { Product } from '@/types/segment';
import { ref } from 'vue';

// Tipos para dados vindos do service
export interface BCGServiceData {
  product_id: number;
  category: string;
  current_sales: number;
  previous_sales: number;
  growth_rate: number;
  market_share: number;
  classification: 'STAR' | 'QUESTION_MARK' | 'CASH_COW' | 'DOG';
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
  | 'Baixo valor - descontinuar';

export interface BCGResult extends BCGData {
  classification: BCGClassification;
  color: string; 
}

interface CategoryStats {
  sumY: number;
  sumX: number;
  count: number;
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
  const categories = ref<Map<string, CategoryStats>>(new Map());

  // Cores para cada classificação
  const classificationColors = {
    'Alto valor - manutenção': '#00B050', // Verde
    'Incentivo - volume': '#00B0F0',      // Azul claro
    'Incentivo - lucro': '#BF90FF',       // Roxo claro
    'Baixo valor - descontinuar': '#FF6347' // Vermelho claro
  };

  // Mapeamento das classificações do service para o frontend
  const mapServiceClassification = (serviceClassification: string): BCGClassification => {
    const mapping: Record<string, BCGClassification> = {
      'STAR': 'Alto valor - manutenção',           // Alta participação, alto crescimento
      'CASH_COW': 'Incentivo - volume',            // Alta participação, baixo crescimento
      'QUESTION_MARK': 'Incentivo - lucro',        // Baixa participação, alto crescimento
      'DOG': 'Baixo valor - descontinuar'          // Baixa participação, baixo crescimento
    };
    return mapping[serviceClassification] || 'Baixo valor - descontinuar';
  };

  // Calcular médias por categoria
  const calculateCategoryAverages = (data: BCGData[]) => {
    categories.value.clear();
    
    // Primeira passagem: acumular somas e contagens
    data.forEach(item => {
      if (!categories.value.has(item.category)) {
        categories.value.set(item.category, { sumY: 0, sumX: 0, count: 0 });
      }
      
      const stats = categories.value.get(item.category)!;
      stats.sumY += item.yValue;
      stats.sumX += item.xValue;
      stats.count += 1;
    });
  };

  // Classificar um item baseado nas médias da categoria 

  // Função principal para processar os dados vindos do service
  const processData = (serviceData: BCGServiceData[], products: ProductWithCategory[]) => {
    try {
      // Mapear dados do service para o formato esperado pelo frontend
      const mappedData: BCGData[] = serviceData.map((item: BCGServiceData) => {
        const product = products.find(p => p.id === item.product_id.toString());
        
        return {
          ean: product?.ean || '',
          description: product?.name || product?.description || '',
          category: item.category,
          product_id: item.product_id.toString(),
          yValue: item.growth_rate,      // Taxa de crescimento (EIXO Y - Vertical)
          xValue: item.market_share      // Participação de mercado (EIXO X - Horizontal)
        };
      });

      // Calcular médias por categoria
      calculateCategoryAverages(mappedData);

      // Processar cada item com classificação do service
      results.value = serviceData.map((serviceItem: BCGServiceData) => {
        const product = products.find(p => p.id === serviceItem.product_id.toString());
        const classification = mapServiceClassification(serviceItem.classification);
        
        return {
          ean: product?.ean || '',
          description: product?.name || product?.description || '',
          category: serviceItem.category,
          product_id: serviceItem.product_id.toString(),
          yValue: serviceItem.growth_rate,
          xValue: serviceItem.market_share,
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
  const getCategoryStats = (category: string) => {
    return categories.value.get(category);
  };

  const getResultsByClassification = (classification: BCGClassification) => {
    return results.value.filter(result => result.classification === classification);
  };

  const getResultsByCategory = (category: string) => {
    return results.value.filter(result => result.category === category);
  };

  return {
    results,
    processData,
    getCategoryStats,
    getResultsByClassification,
    getResultsByCategory,
    classificationColors
  };
} 