// stores/bcgResultStore.ts
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { useAnalysisService, type BCGAnalysisParams } from '@plannerate/services/analysisService';

// ===== TIPOS LOCAIS =====
export interface BCGConfiguration {
  classifyBy: string;
  displayBy: string;
  xAxis: string;
  yAxis: string;
  isValid: boolean;
  rule?: string;
}

export type BCGClassification =
  | 'Alto valor - manutenção'
  | 'Incentivo - volume'
  | 'Incentivo - lucro'
  | 'Incentivo - valor'
  | 'Baixo valor - avaliar';

export interface BCGResult {
  ean: string;
  description: string;
  category: string;
  displayGroup: string;
  classifyGroup: string;
  product_id: string;
  yValue: number;
  xValue: number;
  groupSize?: number;
  classification: BCGClassification;
  color: string;
}

export interface BCGMetadata {
  total_items: number;
  aggregation_level: string;
  classification_level: string;
  period: {
    start_date: string;
    end_date: string;
  };
}

export const useBCGResultStore = defineStore('bcgResult', () => {
  // ===== ESTADO =====
  const result = ref<BCGResult[] | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);
  const configuration = ref<BCGConfiguration>({
    classifyBy: 'categoria',
    displayBy: 'produto',
    xAxis: 'VALOR DE VENDA',
    yAxis: 'MARGEM DE CONTRIBUIÇÃO',
    isValid: true
  });

  const metadata = ref<BCGMetadata | null>(null);

  // ===== SERVICE =====
  const analysisService = useAnalysisService();

  // ===== COMPUTED PROPERTIES =====
  const hasResults = computed(() => result.value && result.value.length > 0);

  const classificationSummary = computed(() => {
    if (!result.value) return null;

    const summary = {
      'Alto valor - manutenção': 0,
      'Incentivo - volume': 0,
      'Incentivo - lucro': 0,
      'Incentivo - valor': 0,
      'Baixo valor - avaliar': 0
    };

    result.value.forEach(item => {
      summary[item.classification]++;
    });

    return summary;
  });

  const groupSummary = computed(() => {
    if (!result.value) return null;

    const classifyGroups = new Set(result.value.map(r => r.classifyGroup));
    const displayGroups = new Set(result.value.map(r => r.displayGroup));

    return {
      totalClassifyGroups: classifyGroups.size,
      totalDisplayGroups: displayGroups.size,
      totalItems: result.value.length,
      aggregationLevel: configuration.value.displayBy,
      classificationLevel: configuration.value.classifyBy
    };
  });

  // ===== MUTATIONS =====
  const setResult = (newResult: BCGResult[] | null) => {
    result.value = newResult;
    error.value = null;
  };

  const setConfiguration = (newConfig: Partial<BCGConfiguration>) => {
    configuration.value = { ...configuration.value, ...newConfig };
  };

  const setMetadata = (newMetadata: BCGMetadata | null) => {
    metadata.value = newMetadata;
  };

  const setError = (errorMessage: string) => {
    error.value = errorMessage;
    loading.value = false;
  };

  const clearError = () => {
    error.value = null;
  };

  const clearResults = () => {
    result.value = null;
    metadata.value = null;
    error.value = null;
  };

  // ===== CLASSIFICAÇÃO COLORS =====
  const classificationColors = {
    'Alto valor - manutenção': '#00B050', // Verde
    'Incentivo - volume': '#00B0F0',      // Azul claro
    'Incentivo - lucro': '#BF90FF',       // Roxo claro
    'Incentivo - valor': '#FF8C00',       // Laranja
    'Baixo valor - avaliar': '#FF6347' // Vermelho
  };

  // ===== CLASSIFICAÇÃO LOGIC =====
  const classifyProduct = (
    xValue: number,
    yValue: number,
    xAverage: number,
    yAverage: number,
    xLabel: string,
    yLabel: string
  ): BCGClassification => {
    // Determinar quadrante
    const highX = xValue > xAverage;
    const highY = yValue > yAverage;

    // Alto valor (ambos acima da média)
    if (highX && highY) {
      return 'Alto valor - manutenção';
    }

    // Mapear combinações específicas de métricas
    const getIncentiveType = (): BCGClassification => {
      // Combinações que indicam incentivo de volume
      if (xLabel.includes('QUANTIDADE') && yLabel.includes('MARGEM')) {
        if (highX && !highY) {
          return 'Incentivo - lucro';
        }
        if (!highX && highY) {
          return 'Incentivo - volume';
        }
      }

      if (xLabel.includes('MARGEM') && yLabel.includes('QUANTIDADE')) {
        if (highX && !highY) {
          return 'Incentivo - volume';
        }
        if (!highX && highY) {
          return 'Incentivo - lucro';
        }
      }

      // Combinações que indicam incentivo de valor
      if (xLabel.includes('VALOR') && yLabel.includes('QUANTIDADE')) {
        if (highX && !highY) {
          return 'Incentivo - volume';
        }
        if (!highX && highY) {
          return 'Incentivo - valor';
        }
      }

      if (xLabel.includes('QUANTIDADE') && yLabel.includes('VALOR')) {
        if (highX && !highY) {
          return 'Incentivo - valor';
        }
        if (!highX && highY) {
          return 'Incentivo - volume';
        }
      }

      // Combinações que indicam incentivo de lucro
      if (xLabel.includes('VALOR') && yLabel.includes('MARGEM')) {
        if (highX && !highY) {
          return 'Incentivo - lucro';
        }
        if (!highX && highY) {
          return 'Incentivo - valor';
        }
      }

      if (xLabel.includes('MARGEM') && yLabel.includes('VALOR')) {
        if (highX && !highY) {
          return 'Incentivo - valor';
        }
        if (!highX && highY) {
          return 'Incentivo - lucro';
        }
      }

      return 'Incentivo - valor'; // Padrão
    };

    // Para outros quadrantes, determinar tipo de incentivo
    if (highX || highY) {
      return getIncentiveType();
    }

    // Baixo valor (ambos abaixo da média)
    return 'Baixo valor - avaliar';
  };

  // ===== PROCESSAMENTO DE DADOS =====
  const processResponseData = (responseData: any[]): BCGResult[] => {
    if (!responseData || responseData.length === 0) return [];

    // Calcular médias por grupo de classificação
    const groupStats = new Map<string, { sumX: number; sumY: number; count: number }>();

    responseData.forEach(item => {
      const groupKey = item.classify_group || item.category;

      if (!groupStats.has(groupKey)) {
        groupStats.set(groupKey, { sumX: 0, sumY: 0, count: 0 });
      }

      const stats = groupStats.get(groupKey)!;
      if (typeof item.x_axis_value === 'number' && typeof item.y_axis_value === 'number') {
        stats.sumX += item.x_axis_value;
        stats.sumY += item.y_axis_value;
        stats.count += 1;
      }
    });

    // Calcular médias
    const groupAverages = new Map<string, { xAverage: number; yAverage: number }>();
    groupStats.forEach((stats, groupKey) => {
      groupAverages.set(groupKey, {
        xAverage: stats.count > 0 ? stats.sumX / stats.count : 0,
        yAverage: stats.count > 0 ? stats.sumY / stats.count : 0
      });
    });

    // Processar cada item
    return responseData.map(item => {
      const groupKey = item.classify_group || item.category;
      const averages = groupAverages.get(groupKey);
      const xAverage = averages?.xAverage || 0;
      const yAverage = averages?.yAverage || 0;

      const classification = classifyProduct(
        item.x_axis_value,
        item.y_axis_value,
        xAverage,
        yAverage,
        item.x_axis_label,
        item.y_axis_label
      );

      return {
        ean: item.ean || '',
        description: item.display_group || '',
        category: item.category || '',
        displayGroup: item.display_group || '',
        classifyGroup: item.classify_group || item.category || '',
        product_id: item.product_id?.toString() || '',
        yValue: item.y_axis_value || 0,
        xValue: item.x_axis_value || 0,
        groupSize: item.configuration?.group_size,
        classification,
        color: classificationColors[classification]
      };
    });
  };

  // ===== ACTIONS =====

  /**
   * Ação principal para executar análise BCG
   */
  const executeBCGAnalysis = async (params: BCGAnalysisParams) => {
    loading.value = true;
    error.value = null;

    try {
      const response = await analysisService.getBCGAnalysis(params);
      // Atualizar configuração baseada na resposta
      setConfiguration({
        classifyBy: response.metadata.configuration.classify_by,
        displayBy: response.metadata.configuration.display_by,
        xAxis: response.metadata.configuration.x_axis,
        yAxis: response.metadata.configuration.y_axis,
        isValid: true
      });

      // Atualizar metadados
      setMetadata({
        total_items: response.metadata.summary.total_items,
        aggregation_level: response.metadata.summary.aggregation_level,
        classification_level: response.metadata.summary.classification_level,
        period: response.metadata.configuration.period
      });

      // Processar e classificar dados
      const processedResults = processResponseData(response.data);
      setResult(processedResults);

      return processedResults;
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Erro desconhecido';
      setError(errorMessage);
      throw err;
    } finally {
      loading.value = false;
    }
  };

  /**
   * Ação para validar configuração
   */
  const validateConfiguration = async (classifyBy: string, displayBy: string) => {
    try {
      const validation = await analysisService.validateBCGConfiguration(classifyBy, displayBy);

      setConfiguration({
        classifyBy,
        displayBy,
        isValid: validation.is_valid
      });

      return validation;
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Erro ao validar configuração';
      setError(errorMessage);
      throw err;
    }
  };

  // ===== FILTROS E BUSCAS =====

  const getResultsByClassification = (classification: BCGClassification) => {
    return result.value?.filter(item => item.classification === classification) || [];
  };

  const getResultsByGroup = (groupType: 'classify' | 'display', groupValue: string) => {
    return result.value?.filter(item =>
      groupType === 'classify' ? item.classifyGroup === groupValue : item.displayGroup === groupValue
    ) || [];
  };

  const searchResults = (searchTerm: string) => {
    if (!result.value || !searchTerm.trim()) return result.value;

    const term = searchTerm.toLowerCase();
    return result.value.filter(item =>
      item.ean.toLowerCase().includes(term) ||
      item.description.toLowerCase().includes(term) ||
      item.category.toLowerCase().includes(term) ||
      item.displayGroup.toLowerCase().includes(term) ||
      item.classifyGroup.toLowerCase().includes(term)
    );
  };

  /**
   * Drill-down para navegar entre níveis
   */
  const drillDown = async (groupValue: string, newDisplayBy: string, baseParams: Omit<BCGAnalysisParams, 'displayBy'>) => {
    const newParams: BCGAnalysisParams = {
      ...baseParams,
      displayBy: newDisplayBy
      // TODO: Implementar filtro por grupo específico
    };

    return await executeBCGAnalysis(newParams);
  };

  /**
   * Exportar dados para Excel
   */
  const exportToExcel = () => {
    if (!result.value) {
      console.warn('Nenhum resultado para exportar');
      return;
    }

    // Implementação básica - pode ser expandida
    const csvContent = "data:text/csv;charset=utf-8," +
      "EAN,Descrição,Categoria,Grupo Classificação,Grupo Exibição,Eixo X,Eixo Y,Classificação\n" +
      result.value.map(item =>
        `${item.ean},"${item.description}","${item.category}","${item.classifyGroup}","${item.displayGroup}",${item.xValue},${item.yValue},"${item.classification}"`
      ).join("\n");

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `bcg_analysis_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    console.log('Exportando para CSV...', result.value.length, 'itens');
  };

  /**
   * Reset completo da store
   */
  const $reset = () => {
    result.value = null;
    loading.value = false;
    error.value = null;
    metadata.value = null;
    configuration.value = {
      classifyBy: 'categoria',
      displayBy: 'produto',
      xAxis: 'VALOR DE VENDA',
      yAxis: 'MARGEM DE CONTRIBUIÇÃO',
      isValid: true
    };
  };

  // ===== RETURN =====
  return {
    // Estado
    result,
    loading,
    error,
    configuration,
    metadata,

    // Getters
    hasResults,
    classificationSummary,
    groupSummary,

    // Actions
    setResult,
    setConfiguration,
    setMetadata,
    setError,
    clearError,
    clearResults,
    executeBCGAnalysis,
    validateConfiguration,
    getResultsByClassification,
    getResultsByGroup,
    searchResults,
    drillDown,
    exportToExcel,
    $reset,

    // Utilitários
    classificationColors
  };
});