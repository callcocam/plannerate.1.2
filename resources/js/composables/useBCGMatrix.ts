import { ref, computed } from 'vue';

// Tipos
export interface BCGData {
  ean: string;
  description: string;
  category: string;
  yValue: number;
  xValue: number;
}

export type BCGClassification = 
  | 'Alto valor – manutenção'
  | 'Incentivo – volume'
  | 'Incentivo – lucro'
  | 'Baixo valor – descontinuar';

export interface BCGResult extends BCGData {
  classification: BCGClassification;
  color: string;
}

interface CategoryStats {
  sumY: number;
  sumX: number;
  count: number;
}

export function useBCGMatrix() {
  const results = ref<BCGResult[]>([]);
  const categories = ref<Map<string, CategoryStats>>(new Map());

  // Cores para cada classificação
  const classificationColors = {
    'Alto valor – manutenção': '#00B050', // Verde
    'Incentivo – volume': '#00B0F0',      // Azul claro
    'Incentivo – lucro': '#BF90FF',       // Roxo claro
    'Baixo valor – descontinuar': '#FF6347' // Vermelho claro
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
  const classifyItem = (item: BCGData): BCGClassification => {
    const stats = categories.value.get(item.category);
    if (!stats || stats.count === 0) {
      throw new Error(`Categoria ${item.category} não encontrada ou sem dados`);
    }

    const avgY = stats.sumY / stats.count;
    const avgX = stats.sumX / stats.count;

    if (item.xValue >= avgX && item.yValue >= avgY) {
      return 'Alto valor – manutenção';
    } else if (item.xValue >= avgX && item.yValue < avgY) {
      return 'Incentivo – volume';
    } else if (item.xValue < avgX && item.yValue >= avgY) {
      return 'Incentivo – lucro';
    } else {
      return 'Baixo valor – descontinuar';
    }
  };

  // Função principal para processar os dados
  const processData = (data: BCGData[]) => {
    try {
      // Calcular médias por categoria
      calculateCategoryAverages(data);

      // Classificar cada item
      results.value = data.map(item => {
        const classification = classifyItem(item);
        return {
          ...item,
          classification,
          color: classificationColors[classification]
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