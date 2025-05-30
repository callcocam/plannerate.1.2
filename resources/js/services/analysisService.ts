import { apiService } from './api';

interface ABCAnalysisParams {
    planogram?: any;
    storeId?: number;
    weights?: {
        quantity: number;
        value: number;
        margin: number;
    };
    thresholds?: {
        a: number;
        b: number;
    };
}

interface TargetStockParams {
    planogram?: any;
    storeId?: number;
    period?: number; // período em dias para análise
}

interface BCGAnalysisParams {
    planogram?: any;
    storeId?: number;
    marketShare?: number; // percentual mínimo de participação no mercado
    xAxis?: string;
    yAxis?: string;
}

export const useAnalysisService = () => {
    /**
     * Obtém dados para análise ABC
     * Retorna dados de quantidade, valor e margem dos produtos
     */
    const getABCAnalysisData = async (products: number[], params: ABCAnalysisParams = {}) => {
        const response = await apiService.get('/analysis/abc', {
            params: {
                products,
                planogram: params.planogram,
                storeId: params.storeId,
                weights: params.weights,
                thresholds: params.thresholds
            }
        });
        return response;
    }

    /**
     * Obtém dados para cálculo de estoque alvo
     * Retorna dados de vendas por período para cada produto
     */
    const getTargetStockData = async (products: number[], params: TargetStockParams = {}) => {
        const response = await apiService.get('/analysis/target-stock', {
            params: {
                products,
                planogram: params.planogram,
                storeId: params.storeId,
                period: params.period
            }
        });
        return response;
    }

    /**
     * Obtém dados para análise BCG
     * Retorna dados de crescimento e participação de mercado
     */
    const getBCGAnalysisData = async (products: number[], params: BCGAnalysisParams = {}) => {
        const response = await apiService.get('/analysis/bcg', {
            params: {
                products,
                planogram: params.planogram,
                storeId: params.storeId,
                marketShare: params.marketShare,
                xAxis: params.xAxis,
                yAxis: params.yAxis
            }
        });
        return response;
    }

    return {
        getABCAnalysisData,
        getTargetStockData,
        getBCGAnalysisData
    }
} 