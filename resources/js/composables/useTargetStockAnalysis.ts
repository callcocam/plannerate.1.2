import { useTargetStockResultStore } from '@plannerate/store/editor/targetStockResult';
import { useEditorStore } from '@plannerate/store/editor';
import { useAnalysisService } from '@plannerate/services/analysisService';
import { useTargetStock, type ServiceLevel, type Replenishment } from '@plannerate/composables/useTargetStock';
import { useAnalysisResultStore } from '@plannerate/store/editor/analysisResult';
import { useProductAggregationStore, type ProductAggregation, type ProductPlacement } from '@plannerate/store/editor/productAggregation';
import { watch } from 'vue';
import { currentGondola } from '@plannerate/store/editor/state';

export function useTargetStockAnalysis() {
    const targetStockResultStore = useTargetStockResultStore();
    const analysisResultStore = useAnalysisResultStore();
    const editorStore = useEditorStore();
    const analysisService = useAnalysisService();
    const productAggregationStore = useProductAggregationStore();

    function calculateProductCapacity(segment: any, shelf: any, product: any): number {
        const segmentQuantity = segment.layer?.quantity || 1;
        const layerQuantity = segment.quantity || 1;

        // Calcular itens em profundidade
        let depthItems = 1;
        if (product.dimensions?.depth && shelf.shelf_depth) {
            depthItems = Math.floor(shelf.shelf_depth / product.dimensions.depth);
        }

        return segmentQuantity * layerQuantity * depthItems;
    }

    // Função para recalcular agregações localmente (sem chamada API)
    function recalculateAggregations() {
        // Limpar agregações anteriores
        productAggregationStore.clear();

        const analysisResult = analysisResultStore.result;
        const aggregationMap = new Map<string, ProductAggregation>();

        // Iterar por toda a gôndola e agregar produtos por EAN
        currentGondola.value?.sections.forEach(section => {
            section.shelves.forEach(shelf => {
                shelf.segments.forEach(segment => {
                    if (!segment.layer || !segment.layer.product) {
                        return;
                    }

                    const product = segment.layer.product as any;
                    const ean = product.ean;

                    // Calcular capacidade neste local
                    const capacityAtLocation = calculateProductCapacity(segment, shelf, product);

                    // Criar placement
                    const placement: ProductPlacement = {
                        sectionId: section.id,
                        shelfId: shelf.id,
                        segmentId: segment.id,
                        capacityAtLocation,
                        segmentQuantity: segment.layer?.quantity || 1,
                        layerQuantity: segment.quantity || 1,
                    };

                    // Agregar por EAN
                    if (aggregationMap.has(ean)) {
                        const existing = aggregationMap.get(ean)!;
                        existing.totalCapacity += capacityAtLocation;
                        existing.placements.push(placement);
                    } else {
                        // Obter classificação do resultado da análise
                        const classification = analysisResult?.find((p: any) => p.id === ean);
                        const { abcClass } = classification || { abcClass: 'B' };

                        aggregationMap.set(ean, {
                            id: product.id,
                            ean: ean,
                            name: product.name,
                            classification: abcClass,
                            totalCapacity: capacityAtLocation,
                            placements: [placement],
                        });
                    }
                });
            });
        });

        // Armazenar agregações no store
        aggregationMap.forEach((aggregation, ean) => {
            productAggregationStore.setAggregation(ean, aggregation);
        });
    }

    // Watcher para detectar mudanças na gôndola e recalcular agregações
    watch(
        () => currentGondola.value,
        (newGondola) => {
            if (newGondola && analysisResultStore.result.length > 0) {
                // Só recalcula se já houver resultados de análise
                recalculateAggregations();
            }
        },
        { deep: true }
    );

    async function executeTargetStockAnalysisWithParams(
        serviceLevels: ServiceLevel[],
        replenishmentParams: Replenishment[],
        sourceType: 'monthly' | 'daily' = 'monthly'
    ) {
        targetStockResultStore.loading = true;

        // Limpar agregações anteriores
        productAggregationStore.clear();

        const analysisResult = analysisResultStore.result;
        const aggregationMap = new Map<string, ProductAggregation>();

        // Iterar por toda a gôndola e agregar produtos por EAN
        editorStore.getCurrentGondola?.sections.forEach(section => {
            section.shelves.forEach(shelf => {
                shelf.segments.forEach(segment => {
                    if (!segment.layer || !segment.layer.product) {
                        return;
                    }

                    const product = segment.layer.product as any;
                    const ean = product.ean;

                    // Calcular capacidade neste local
                    const capacityAtLocation = calculateProductCapacity(segment, shelf, product);

                    // Criar placement
                    const placement: ProductPlacement = {
                        sectionId: section.id,
                        shelfId: shelf.id,
                        segmentId: segment.id,
                        capacityAtLocation,
                        segmentQuantity: segment.layer?.quantity || 1,
                        layerQuantity: segment.quantity || 1,
                    };

                    // Agregar por EAN
                    if (aggregationMap.has(ean)) {
                        const existing = aggregationMap.get(ean)!;
                        existing.totalCapacity += capacityAtLocation;
                        existing.placements.push(placement);
                    } else {
                        // Obter classificação do resultado da análise
                        const classification = analysisResult?.find((p: any) => p.id === ean);
                        const { abcClass } = classification || { abcClass: 'B' };

                        aggregationMap.set(ean, {
                            id: product.id,
                            ean: ean,
                            name: product.name,
                            classification: abcClass,
                            totalCapacity: capacityAtLocation,
                            placements: [placement],
                        });
                    }
                });
            });
        });

        // Armazenar agregações no store
        aggregationMap.forEach((aggregation, ean) => {
            productAggregationStore.setAggregation(ean, aggregation);
        });

        // Extrair lista de produtos únicos para a chamada API
        const products = Array.from(aggregationMap.values()).map(agg => ({
            id: agg.id,
            ean: agg.ean,
            name: agg.name,
            classification: agg.classification,
        }));

        try {
            if (products.length > 0) {
                const sales = await analysisService.getTargetStockData(
                    products.map(p => Number(p.id)),
                    {
                        planogram: editorStore.currentState?.id,
                        sourceType: sourceType
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
                        sales: productSales ? Object.values(productSales.sales_by_day).map(Number) : []
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
        recalculateAggregations,
        targetStockResultStore,
        analysisResultStore,
        editorStore,
        analysisService,
        productAggregationStore
    };
}
