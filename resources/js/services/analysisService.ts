import { apiService } from './api';

// ===== INTERFACES PARA ANÁLISE ABC =====
interface ABCAnalysisParams {
    planogram?: any;
    storeId?: number;
    sourceType?: 'monthly' | 'daily';
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

interface ABCAnalysisResponse {
    data: Array<{
        product_id: number;
        ean: string;
        name: string;
        category: string;
        quantity_value: number;
        sales_value: number;
        margin_value: number;
        abc_classification: 'A' | 'B' | 'C';
        cumulative_percentage: number;
    }>;
    metadata: {
        total_items: number;
        thresholds: {
            a: number;
            b: number;
        };
        weights: {
            quantity: number;
            value: number;
            margin: number;
        };
    };
}

// ===== INTERFACES PARA ANÁLISE DE ESTOQUE ALVO =====
interface TargetStockParams {
    planogram?: any;
    storeId?: number;
    sourceType?: 'monthly' | 'daily';
    period?: number; // período em dias para análise
    safetyStock?: number; // estoque de segurança em percentual
    leadTime?: number; // tempo de reposição em dias
}

interface TargetStockResponse {
    data: Array<{
        product_id: number;
        ean: string;
        name: string;
        category: string;
        current_stock: number;
        average_daily_sales: number;
        target_stock: number;
        stock_days: number;
        reorder_point: number;
        stock_status: 'overstock' | 'normal' | 'understock' | 'stockout';
    }>;
    metadata: {
        analysis_period: number;
        safety_stock_percentage: number;
        lead_time_days: number;
        total_products: number;
    };
}

// ===== INTERFACES PARA ANÁLISE BCG =====
interface BCGAnalysisParams {
    products: string[] | number[];
    planogram?: string;
    storeId?: number;
    sourceType?: 'monthly' | 'daily';
    xAxis?: string;
    yAxis?: string;
    classifyBy?: string;
    displayBy?: string;
    configuration?: {
        rule?: string;
        isValid: boolean;
    };
    // Parâmetros legacy (mantidos para compatibilidade)
    marketShare?: number;
}

interface BCGServiceData {
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

interface BCGAnalysisResponse {
    data: BCGServiceData[];
    metadata: {
        configuration: {
            classify_by: string;
            display_by: string;
            x_axis: string;
            y_axis: string;
            period: {
                start_date: string;
                end_date: string;
            };
        };
        summary: {
            total_items: number;
            aggregation_level: string;
            classification_level: string;
        };
    };
}

interface BCGConfigurationOption {
    classify_by: string;
    display_by: string;
    label: string;
    hierarchy_level: number;
}

interface BCGConfigurationsResponse {
    configurations: BCGConfigurationOption[];
    levels: string[];
    axis_options: string[];
}

interface BCGValidationResponse {
    is_valid: boolean;
    classify_by: string;
    display_by: string;
    label?: string;
    available_display_options: string[];
    message: string;
}

// ===== CLASSE PRINCIPAL DO SERVICE =====
class AnalysisService {

    // ===== ANÁLISE ABC =====

    /**
     * Executa análise ABC dos produtos
     * Classifica produtos em categorias A, B, C baseado em critérios de valor
     */
    async getABCAnalysis(products: number[], params: ABCAnalysisParams = {}): Promise<ABCAnalysisResponse> {
        try {
            const response = await apiService.post('/analysis/abc', {
                products,
                planogram: params.planogram,
                storeId: params.storeId,
                sourceType: params.sourceType || 'monthly',
                weights: params.weights || {
                    quantity: 0.3,
                    value: 0.5,
                    margin: 0.2
                },
                thresholds: params.thresholds || {
                    a: 80,
                    b: 95
                }
            }); 
            return response;
        } catch (error) {
            console.error('Erro ao executar análise ABC:', error);
            throw this.handleError(error);
        }
    }

    /**
     * Método legacy - mantido para compatibilidade
     * @deprecated Use getABCAnalysis
     */
    async getABCAnalysisData(products: number[], params: ABCAnalysisParams = {}) {
        return this.getABCAnalysis(products, params);
    }

    // ===== ANÁLISE DE ESTOQUE ALVO =====

    /**
     * Executa análise de estoque alvo
     * Calcula níveis ideais de estoque baseado em histórico de vendas
     */
    async getTargetStockAnalysis(products: number[], params: TargetStockParams = {}): Promise<TargetStockResponse> {
        try {
            const response = await apiService.post('/analysis/target-stock', {
                products,
                planogram: params.planogram,
                storeId: params.storeId,
                sourceType: params.sourceType || 'monthly',
                period: params.period || 30,
                safetyStock: params.safetyStock || 20,
                leadTime: params.leadTime || 7
            }); 
            return response;
        } catch (error) {
            console.error('Erro ao executar análise de estoque alvo:', error);
            throw this.handleError(error);
        }
    }

    /**
     * Método legacy - mantido para compatibilidade
     * @deprecated Use getTargetStockAnalysis
     */
    async getTargetStockData(products: number[], params: TargetStockParams = {}) {
        return this.getTargetStockAnalysis(products, params);
    }

    // ===== ANÁLISE BCG =====

    /**
     * Executa análise BCG com configuração hierárquica
     * Suporta diferentes níveis de agrupamento e classificação
     */
    async getBCGAnalysis(params: BCGAnalysisParams): Promise<BCGAnalysisResponse> {
        try {
            // Validar parâmetros obrigatórios
            if (!params.products || params.products.length === 0) {
                throw new Error('Lista de produtos é obrigatória');
            }

            const response = await apiService.post('/analysis/bcg', {
                products: params.products,
                planogram: params.planogram || '',
                storeId: params.storeId,
                sourceType: params.sourceType || 'monthly',
                xAxis: params.xAxis || 'VALOR DE VENDA',
                yAxis: params.yAxis || 'MARGEM DE CONTRIBUIÇÃO',
                classifyBy: params.classifyBy || 'categoria',
                displayBy: params.displayBy || 'produto',
                configuration: params.configuration || { isValid: true }
            });

            return response;
        } catch (error) {
            console.error('Erro ao executar análise BCG:', error);
            throw this.handleError(error);
        }
    }

    /**
     * Método legacy para análise BCG básica
     * Mantido para compatibilidade com código existente
     * @deprecated Use getBCGAnalysis com parâmetros completos
     */
    async getBCGAnalysisData(products: number[], params: Omit<BCGAnalysisParams, 'products'> = {}) {
        return this.getBCGAnalysis({
            ...params,
            products: products.map(p => p.toString()),
            planogram: params.planogram || '',
            classifyBy: 'categoria',
            displayBy: 'produto'
        });
    }

    // ===== CONFIGURAÇÕES BCG =====

    /**
     * Obtém todas as configurações válidas para análise BCG
     */
    async getBCGConfigurations(): Promise<BCGConfigurationsResponse> {
        try {
            const response = await apiService.get('/analysis/bcg/configurations');
            return response.data;
        } catch (error) {
            console.error('Erro ao obter configurações BCG:', error);
            throw this.handleError(error);
        }
    }

    /**
     * Valida uma configuração específica no servidor
     */
    async validateBCGConfiguration(classifyBy: string, displayBy: string): Promise<BCGValidationResponse> {
        try {
            const response = await apiService.post('/analysis/bcg/validate', {
                classifyBy,
                displayBy
            });

            return response.data;
        } catch (error) {
            console.error('Erro ao validar configuração BCG:', error);
            throw this.handleError(error);
        }
    }

    // ===== VALIDAÇÕES LOCAIS =====

    /**
     * Validação local de configuração BCG (sem requisição ao servidor)
     */
    isValidBCGConfigurationLocal(classifyBy: string, displayBy: string): boolean {
        const validCombinations: Record<string, string[]> = {
            'segmento_varejista': ['departamento', 'subdepartamento', 'categoria', 'subcategoria', 'produto'],
            'departamento': ['subdepartamento', 'categoria', 'produto'],
            'subdepartamento': ['categoria', 'produto'],
            'categoria': ['subcategoria', 'produto'],
            'subcategoria': ['produto']
        };

        return validCombinations[classifyBy]?.includes(displayBy) || false;
    }

    /**
     * Obtém opções válidas de exibição para um nível de classificação
     */
    getValidDisplayOptions(classifyBy: string): string[] {
        const validCombinations: Record<string, string[]> = {
            'segmento_varejista': ['departamento', 'subdepartamento', 'categoria', 'subcategoria', 'produto'],
            'departamento': ['subdepartamento', 'categoria', 'produto'],
            'subdepartamento': ['categoria', 'produto'],
            'categoria': ['subcategoria', 'produto'],
            'subcategoria': ['produto']
        };

        return validCombinations[classifyBy] || [];
    }

    /**
     * Obtém opções válidas de classificação para um nível de exibição
     */
    getValidClassifyOptions(displayBy: string): string[] {
        const validOptions: string[] = [];
        const validCombinations: Record<string, string[]> = {
            'segmento_varejista': ['departamento', 'subdepartamento', 'categoria', 'subcategoria', 'produto'],
            'departamento': ['subdepartamento', 'categoria', 'produto'],
            'subdepartamento': ['categoria', 'produto'],
            'categoria': ['subcategoria', 'produto'],
            'subcategoria': ['produto']
        };

        for (const [classify, displays] of Object.entries(validCombinations)) {
            if (displays.includes(displayBy)) {
                validOptions.push(classify);
            }
        }

        return validOptions;
    }

    // ===== UTILITÁRIOS =====

    /**
     * Gera rótulo amigável para uma configuração
     */
    generateConfigurationLabel(classifyBy: string, displayBy: string): string {
        const labels: Record<string, string> = {
            'segmento_varejista': 'Segmento Varejista',
            'departamento': 'Departamento',
            'subdepartamento': 'Subdepartamento',
            'categoria': 'Categoria',
            'subcategoria': 'Subcategoria',
            'produto': 'Produto'
        };

        const classifyLabel = labels[classifyBy] || classifyBy;
        const displayLabel = labels[displayBy] || displayBy;

        return `Classificar por ${classifyLabel} → Exibir por ${displayLabel}`;
    }

    /**
     * Obtém todas as combinações válidas como array de objetos
     */
    getAllValidCombinations(): Array<{ classifyBy: string; displayBy: string; label: string }> {
        const combinations: Array<{ classifyBy: string; displayBy: string; label: string }> = [];
        const validCombinations: Record<string, string[]> = {
            'segmento_varejista': ['departamento', 'subdepartamento', 'categoria', 'subcategoria', 'produto'],
            'departamento': ['subdepartamento', 'categoria', 'produto'],
            'subdepartamento': ['categoria', 'produto'],
            'categoria': ['subcategoria', 'produto'],
            'subcategoria': ['produto']
        };

        for (const [classifyBy, displayOptions] of Object.entries(validCombinations)) {
            for (const displayBy of displayOptions) {
                combinations.push({
                    classifyBy,
                    displayBy,
                    label: this.generateConfigurationLabel(classifyBy, displayBy)
                });
            }
        }

        return combinations;
    }

    // ===== TRATAMENTO DE ERROS =====

    /**
     * Tratamento padronizado de erros da API
     */
    private handleError(error: any): Error {
        // Erro de resposta HTTP
        if (error.response?.data) {
            const { message, error: errorType, errors } = error.response.data;

            // Se há erros de validação específicos
            if (errors && typeof errors === 'object') {
                const validationErrors = Object.values(errors).flat().join(', ');
                return new Error(`Erro de validação: ${validationErrors}`);
            }

            return new Error(message || errorType || 'Erro desconhecido na API');
        }

        // Erro de rede
        if (error.request) {
            return new Error('Erro de comunicação com o servidor. Verifique sua conexão.');
        }

        // Erro de configuração ou outro
        return new Error(error.message || 'Erro desconhecido');
    }
}

// ===== INSTÂNCIA SINGLETON =====
const analysisServiceInstance = new AnalysisService();

// ===== COMPOSABLE FUNCTION =====
export const useAnalysisService = () => {
    return {
        // ===== ANÁLISE ABC =====
        getABCAnalysis: analysisServiceInstance.getABCAnalysis.bind(analysisServiceInstance),
        getABCAnalysisData: analysisServiceInstance.getABCAnalysisData.bind(analysisServiceInstance), // Legacy

        // ===== ANÁLISE ESTOQUE ALVO =====
        getTargetStockAnalysis: analysisServiceInstance.getTargetStockAnalysis.bind(analysisServiceInstance),
        getTargetStockData: analysisServiceInstance.getTargetStockData.bind(analysisServiceInstance), // Legacy

        // ===== ANÁLISE BCG =====
        getBCGAnalysis: analysisServiceInstance.getBCGAnalysis.bind(analysisServiceInstance),
        getBCGAnalysisData: analysisServiceInstance.getBCGAnalysisData.bind(analysisServiceInstance), // Legacy

        // ===== CONFIGURAÇÕES BCG =====
        getBCGConfigurations: analysisServiceInstance.getBCGConfigurations.bind(analysisServiceInstance),
        validateBCGConfiguration: analysisServiceInstance.validateBCGConfiguration.bind(analysisServiceInstance),

        // ===== VALIDAÇÕES LOCAIS =====
        isValidBCGConfigurationLocal: analysisServiceInstance.isValidBCGConfigurationLocal.bind(analysisServiceInstance),
        getValidDisplayOptions: analysisServiceInstance.getValidDisplayOptions.bind(analysisServiceInstance),
        getValidClassifyOptions: analysisServiceInstance.getValidClassifyOptions.bind(analysisServiceInstance),

        // ===== UTILITÁRIOS =====
        generateConfigurationLabel: analysisServiceInstance.generateConfigurationLabel.bind(analysisServiceInstance),
        getAllValidCombinations: analysisServiceInstance.getAllValidCombinations.bind(analysisServiceInstance)
    };
};

// ===== EXPORTAÇÕES ADICIONAIS =====
export type {
    ABCAnalysisParams,
    ABCAnalysisResponse,
    TargetStockParams,
    TargetStockResponse,
    BCGAnalysisParams,
    BCGAnalysisResponse,
    BCGServiceData,
    BCGConfigurationOption,
    BCGConfigurationsResponse,
    BCGValidationResponse
};

export default analysisServiceInstance;