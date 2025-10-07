import { useTargetStockResultStore } from '@plannerate/store/editor/targetStockResult';
import { useEditorStore } from '@plannerate/store/editor';
import { useAnalysisService } from '@plannerate/services/analysisService';
import { useTargetStock, type ServiceLevel, type Replenishment } from '@plannerate/composables/useTargetStock';
import { useAnalysisResultStore } from '@plannerate/store/editor/analysisResult';

export function useTargetStockAnalysis() {
    const targetStockResultStore = useTargetStockResultStore();
    const analysisResultStore = useAnalysisResultStore();
    const editorStore = useEditorStore();
    const analysisService = useAnalysisService();

    async function executeTargetStockAnalysisWithParams(serviceLevels: ServiceLevel[], replenishmentParams: Replenishment[]) {
        targetStockResultStore.loading = true;
        const products: any[] = [];
        const analysisResult = analysisResultStore.result;
        editorStore.getCurrentGondola?.sections.forEach(section => {
            section.shelves.forEach(shelf => {
                shelf.segments.forEach(segment => {
                    if (!segment.layer || !segment.layer.product) {
                        return;
                    }
                    const product = segment.layer.product as any;
                    const classification = analysisResult?.find((p: any) => p.id === product.ean);
                    const { abcClass } = classification || { abcClass: 'B' };
                    product.classification = abcClass;
                    if (product) {
                        products.push({
                            id: product.id,
                            ean: product.ean,
                            name: product.name,
                            classification: product.classification || 'B',
                        });
                    }
                });
            });
        });

        try {
            if (products.length > 0) {
                const sales = await analysisService.getTargetStockData(
                    products.map(p => p.id),
                    {
                        planogram: editorStore.currentState?.id
                    }
                ) as any;

                // Transformar os dados de vendas no formato esperado
                const productsWithSales = products.map(product => {
                    const productSales = sales.find((sale: any) => sale.product_id === product.id);
                    return {
                        ...product,
                        standard_deviation: productSales?.standard_deviation,
                        average_sales: productSales?.average_sales,
                        currentStock: productSales?.currentStock,
                        variability: productSales?.variability,
                        sales: productSales ? Object.values(productSales.sales_by_day) : []
                    };
                });

                const analyzed = useTargetStock(
                    productsWithSales,
                    serviceLevels,
                    replenishmentParams
                );

                // Atualizar o store com os resultados
                targetStockResultStore.setResult(analyzed, replenishmentParams);
            } else {
                console.log('Nenhum produto encontrado na gôndola para análise de estoque alvo.');
            }
        } catch (error) {
            console.error('Erro ao executar Análise de Estoque Alvo:', error);
        } finally {
            targetStockResultStore.loading = false;
        }
    }

    return {
        executeTargetStockAnalysisWithParams,
        targetStockResultStore,
        analysisResultStore,
        editorStore,
        analysisService
    };
}
