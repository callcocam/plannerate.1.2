// services/autoplanogramService.ts - Service para geração inteligente de planogramas
import { apiService } from './api';

interface IntelligentGenerationRequest {
  gondola_id: string;
  filters: any;
  abc_params: any;
  target_stock_params: any;
  facing_limits: any;
}

interface IntelligentGenerationResponse {
  success: boolean;
  data: {
    gondola: any;
    placed_products: any[];
    unplaced_products: any[];
    stats: {
      total_processed: number;
      successfully_placed: number;
      failed_to_place: number;
      placement_rate: number;
    };
  };
  metadata: {
    abc_analysis: any;
    target_stock_analysis: any;
    processing_time_ms: number;
  };
}

class AutoPlanogramService {
  
  /**
   * 🧠 Geração inteligente com ABC + Target Stock
   */
  async generateIntelligent(params: IntelligentGenerationRequest): Promise<IntelligentGenerationResponse> {
    try {
      const response = await apiService.post('/auto-planogram/generate-intelligent', {
        gondola_id: params.gondola_id,
        filters: params.filters,
        abc_params: params.abc_params,
        target_stock_params: params.target_stock_params,
        facing_limits: params.facing_limits,
        auto_distribute: true
      });
      
      return response.data;
    } catch (error) {
      console.error('❌ Erro na geração inteligente:', error);
      throw this.handleError(error);
    }
  }
  
  private handleError(error: any): Error {
    if (error.response?.data) {
      const { message, error: errorType } = error.response.data;
      return new Error(message || errorType || 'Erro desconhecido na API');
    }
    return new Error(error.message || 'Erro desconhecido');
  }
}

export const autoplanogramService = new AutoPlanogramService();
export default autoplanogramService;
