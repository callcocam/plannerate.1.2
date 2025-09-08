/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

import apiService from './api';

/**
 * Configurações do motor de planograma automático
 */
export interface EngineConfig {
  default_weights: {
    quantity: number;
    value: number;
    margin: number;
  };
  abc_bonuses: {
    class_a: number;
    class_b: number;
    class_c: number;
  };
  stock_penalties: {
    deficit: number;
    excess: number;
  };
  confidence_flags: Record<string, string>;
}

/**
 * Pesos personalizados para cálculo de score
 */
export interface ScoreWeights {
  quantity?: number;
  value?: number;
  margin?: number;
}

/**
 * Parâmetros para cálculo de scores
 */
export interface CalculateScoresParams {
    gondola_id: string;
    weights?: ScoreWeights;
    start_date?: string;
    end_date?: string;
    store_id?: number;
    auto_distribute?: boolean;
}

/**
 * Score calculado para um produto
 */
export interface ProductScore {
  product_id: string;
  product_name: string;
  category: string;
  base_score: number;
  abc_bonus: number;
  stock_penalty: number;
  final_score: number;
  abc_class: 'A' | 'B' | 'C';
  confidence_flag: string;
  metrics: {
    quantity: number;
    value: number;
    margin: number;
    current_stock: number;
  };
  normalized_scores: {
    quantity: number;
    value: number;
    margin: number;
  };
}

/**
 * Resumo estatístico dos scores
 */
export interface ScoresSummary {
  total_products: number;
  average_score: number;
  min_score: number;
  max_score: number;
  score_distribution: {
    high: number;
    medium: number;
    low: number;
  };
  abc_distribution: Record<string, number>;
  confidence_flags: Record<string, number>;
}

/**
 * Resultado da distribuição automática
 */
export interface DistributionResult {
    products_placed: number;
    segments_used: number;
    placement_by_class: {
        A?: { placed: number; segments_used: number; total_products: number };
        B?: { placed: number; segments_used: number; total_products: number };
        C?: { placed: number; segments_used: number; total_products: number };
    };
    gondola_structure: any;
}

/**
 * Resposta do cálculo de scores
 */
export interface CalculateScoresResponse {
    success: boolean;
    message: string;
    data: {
        gondola: {
            id: string;
            name: string;
            planogram_id: string;
        };
        calculation_info: {
            products_analyzed: number;
            products_scored: number;
            calculation_date: string;
            period: {
                start_date?: string;
                end_date?: string;
            };
            weights_used: ScoreWeights;
        };
        scores: ProductScore[];
        summary: ScoresSummary;
        distribution?: DistributionResult;
    };
}

/**
 * Parâmetros para aplicação de scores
 */
export interface ApplyScoresParams {
  gondola_id: string;
  scores: Array<{
    product_id: string;
    final_score: number;
    abc_class: 'A' | 'B' | 'C';
    confidence_flag: string;
  }>;
}

/**
 * Resposta da aplicação de scores
 */
export interface ApplyScoresResponse {
  success: boolean;
  message: string;
  data: {
    gondola_id: string;
    segments_updated: number;
    applied_at: string;
  };
}

/**
 * Service para interação com o Motor de Planograma Automático
 */
export class EngineService {
  private baseUrl = 'auto-planogram';

  /**
   * Obtém as configurações padrão do motor automático
   */
  async getConfig(): Promise<EngineConfig> {
    try {
      const response = await apiService.get(`${this.baseUrl}/config`);
      
      if (response.success) {
        return response.data;
      }
      
      throw new Error(response.message || 'Erro ao obter configurações do motor');
    } catch (error: any) {
      console.error('Erro ao obter configurações do motor:', error);
      throw new Error(error.response?.data?.message || 'Erro de comunicação com o servidor');
    }
  }

  /**
   * Calcula scores automáticos para produtos de uma gôndola
   */
  async calculateScores(params: CalculateScoresParams): Promise<CalculateScoresResponse> {
    try {
      console.log('EngineService: Calculando scores automáticos', {
        gondola_id: params.gondola_id,
        weights: params.weights,
        period: params.start_date ? `${params.start_date} - ${params.end_date}` : 'Período completo'
      });

      const response = await apiService.post(`${this.baseUrl}/calculate-scores`, params);
      
      if (response.success) {
        console.log('EngineService: Scores calculados com sucesso', {
          produtos_analisados: response.data.calculation_info.products_analyzed,
          produtos_com_score: response.data.calculation_info.products_scored,
          score_medio: response.data.summary.average_score
        });
        
        return response;
      }
      
      throw new Error(response.message || 'Erro no cálculo de scores');
    } catch (error: any) {
      console.error('EngineService: Erro no cálculo de scores', error);
      
      if (error.response?.status === 422) {
        const validationErrors = error.response.data?.errors || {};
        const errorMessages = Object.values(validationErrors).flat().join(', ');
        throw new Error(`Dados inválidos: ${errorMessages}`);
      }
      
      throw new Error(error.response?.data?.message || 'Erro de comunicação com o servidor');
    }
  }

  /**
   * Aplica scores calculados aos segmentos da gôndola
   */
  async applyScores(params: ApplyScoresParams): Promise<ApplyScoresResponse> {
    try {
      console.log('EngineService: Aplicando scores aos segmentos', {
        gondola_id: params.gondola_id,
        scores_count: params.scores.length
      });

      const response = await apiService.post(`${this.baseUrl}/apply-scores`, params);
      
      if (response.success) {
        console.log('EngineService: Scores aplicados com sucesso', {
          segmentos_atualizados: response.data.segments_updated
        });
        
        return response;
      }
      
      throw new Error(response.message || 'Erro na aplicação de scores');
    } catch (error: any) {
      console.error('EngineService: Erro na aplicação de scores', error);
      throw new Error(error.response?.data?.message || 'Erro de comunicação com o servidor');
    }
  }

  /**
   * Valida se os pesos fornecidos são válidos (devem somar próximo a 1.0)
   */
  validateWeights(weights: ScoreWeights): { valid: boolean; message?: string } {
    const { quantity = 0, value = 0, margin = 0 } = weights;
    const sum = quantity + value + margin;
    
    if (Math.abs(sum - 1.0) > 0.01) {
      return {
        valid: false,
        message: `Pesos devem somar 1.0. Soma atual: ${sum.toFixed(3)}`
      };
    }
    
    if (quantity < 0 || value < 0 || margin < 0) {
      return {
        valid: false,
        message: 'Pesos não podem ser negativos'
      };
    }
    
    return { valid: true };
  }

  /**
   * Normaliza pesos para somarem 1.0
   */
  normalizeWeights(weights: ScoreWeights): ScoreWeights {
    const { quantity = 0, value = 0, margin = 0 } = weights;
    const sum = quantity + value + margin;
    
    if (sum === 0) {
      // Se todos os pesos são zero, usar pesos padrão
      return { quantity: 0.33, value: 0.33, margin: 0.34 };
    }
    
    return {
      quantity: quantity / sum,
      value: value / sum,
      margin: margin / sum,
    };
  }

  /**
   * Converte flag de confiança para descrição amigável
   */
  getConfidenceFlagDescription(flag: string, config?: EngineConfig): string {
    if (!config) {
      return flag;
    }
    
    // Se a flag contém múltiplas flags separadas por vírgula
    if (flag.includes(',')) {
      const flags = flag.split(',');
      return flags.map(f => config.confidence_flags[f] || f).join(', ');
    }
    
    return config.confidence_flags[flag] || flag;
  }

  /**
   * Categoriza score em faixas de qualidade
   */
  getScoreCategory(score: number): { category: 'high' | 'medium' | 'low'; label: string; color: string } {
    if (score > 0.7) {
      return { category: 'high', label: 'Alto', color: 'text-green-600' };
    } else if (score >= 0.3) {
      return { category: 'medium', label: 'Médio', color: 'text-yellow-600' };
    } else {
      return { category: 'low', label: 'Baixo', color: 'text-red-600' };
    }
  }

  /**
   * Formata score para exibição
   */
  formatScore(score: number): string {
    return (score * 100).toFixed(1) + '%';
  }
}

// Instância singleton do serviço
export const engineService = new EngineService();

/**
 * Composable para usar o Engine Service
 */
export function useEngineService() {
  return engineService;
}
