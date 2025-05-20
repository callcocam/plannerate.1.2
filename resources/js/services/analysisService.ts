import { apiService } from './api';

interface ABCAnalysisParams {
    startDate?: string;
    endDate?: string;
    storeId?: number;
}

interface TargetStockParams {
    startDate?: string;
    endDate?: string;
    storeId?: number;
    period?: number; // período em dias para análise
}

interface BCGAnalysisParams {
    startDate?: string;
    endDate?: string;
    storeId?: number;
    marketShare?: number; // percentual mínimo de participação no mercado
}

export const useAnalysisService = () => {
    /**
     * Obtém dados para análise ABC
     * Retorna dados de quantidade, valor e margem dos produtos
     */
    const getABCAnalysisData = async (products: number[], params: ABCAnalysisParams = {}) => {
        const response = await apiService.post('/analysis/abc', {
            products,
            startDate: params.startDate,
            endDate: params.endDate,
            storeId: params.storeId
        });
        return response;
    }

    /**
     * Obtém dados para cálculo de estoque alvo
     * Retorna dados de vendas por período para cada produto
     */
    const getTargetStockData = async (products: number[], params: TargetStockParams = {}) => {
        const response = await apiService.post('/analysis/target-stock', {
            products,
            startDate: params.startDate,
            endDate: params.endDate,
            storeId: params.storeId,
            period: params.period
        });
        return response;
    }

    /**
     * Obtém dados para análise BCG
     * Retorna dados de crescimento e participação de mercado
     */
    const getBCGAnalysisData = async (products: number[], params: BCGAnalysisParams = {}) => {
        const response = await apiService.post('/analysis/bcg', {
            products,
            startDate: params.startDate,
            endDate: params.endDate,
            storeId: params.storeId,
            marketShare: params.marketShare
        });
        return response;
    }

    return {
        getABCAnalysisData,
        getTargetStockData,
        getBCGAnalysisData
    }
} 