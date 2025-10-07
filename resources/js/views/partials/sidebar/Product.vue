<template>
    <div>
        <div class="flex-1 p-3" v-if="selectedLayers.length">
            <div v-if="!isLoadingDetails" class="group rounded-md bg-white p-3 shadow-sm dark:bg-gray-700">
                <p class="text-gray-800 dark:text-gray-200">{{ selectedLayers.length }} produto(s) selecionado(s)</p>
                <div v-for="layer in selectedLayers" :key="layer.id" class="relative mb-2 flex items-center gap-2">
                    <template v-if="layer.product">
                        <div class="flex flex-col w-full">
                            <div class="flex flex-col items-center gap-3 p-1">
                                <!-- Imagem do produto -->
                                <img :src="layer.product.image_url" alt=""
                                    class="h-20 w-20 rounded-lg border object-contain shadow-sm dark:border-gray-600"
                                    @error="(e) => handleImageError(e, layer.product)" />

                                <!-- Informações básicas do produto -->
                                <div class="flex flex-col text-center">
                                    <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-1">{{
                                        layer.product.name }}
                                    </h4>
                                    <div class="grid grid-cols-2 gap-2 text-xs text-gray-500 dark:text-gray-400 mb-3">
                                        <div><span class="font-medium">EAN:</span> {{ layer.product.ean }}</div>
                                        <div><span class="font-medium">Frentes:</span> {{ layer.quantity || 0 }}</div>
                                    </div>
                                </div>
                                <div v-if="getStockInfo(layer.product?.ean) || getProductAnalysis(layer.product?.ean)"
                                    class="w-full bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                                    <h5
                                        class="text-sm font-semibold text-green-700 dark:text-green-300 mb-3 text-center flex items-center justify-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                        Análise de Estoque
                                    </h5>

                                    <div class="grid grid-cols-2 gap-3">
                                        <!-- Classificação ABC -->
                                        <div v-if="getProductAnalysis(layer.product?.ean)"
                                            class="bg-white dark:bg-gray-800 rounded-md p-3 text-center">
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Classificação
                                            </div>
                                            <div class="text-lg font-bold"
                                                :class="getAbcClassColor(getProductAnalysis(layer.product?.ean)?.abcClass)">
                                                {{ getProductAnalysis(layer.product?.ean)?.abcClass }}
                                            </div>
                                        </div>

                                        <!-- Estoque Alvo -->
                                        <div v-if="getStockInfo(layer.product?.ean)"
                                            class="bg-white dark:bg-gray-800 rounded-md p-3 text-center">
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Estoque Alvo
                                            </div>
                                            <div class="text-lg font-bold text-blue-600 dark:text-blue-400">
                                                {{ getStockInfo(layer.product?.ean)?.targetStock }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">unidades</div>
                                        </div>
                                    </div>

                                    <!-- Informações adicionais de estoque -->
                                    <div v-if="getStockInfo(layer.product?.ean)" class="mt-3 grid grid-cols-3 gap-2">
                                        <div class="bg-white dark:bg-gray-800 rounded-md p-2 text-center">
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Estoque Atual</div>
                                            <div class="text-sm font-bold text-gray-700 dark:text-gray-300">
                                                {{ getStockInfo(layer.product?.ean)?.currentStock || 0 }}
                                            </div>
                                        </div>
                                        <div class="bg-white dark:bg-gray-800 rounded-md p-2 text-center">
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Estoque Mínimo</div>
                                            <div class="text-sm font-bold text-orange-600 dark:text-orange-400">
                                                {{ getStockInfo(layer.product?.ean)?.minimumStock || 0 }}
                                            </div>
                                        </div>
                                        <div class="bg-white dark:bg-gray-800 rounded-md p-2 text-center">
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Estoque Segurança
                                            </div>
                                            <div class="text-sm font-bold text-purple-600 dark:text-purple-400">
                                                {{ getStockInfo(layer.product?.ean)?.safetyStock || 0 }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Indicadores de Performance -->
                                    <div v-if="getStockInfo(layer.product?.ean)" class="mt-3 grid grid-cols-2 gap-2">
                                        <div class="bg-white dark:bg-gray-800 rounded-md p-2 text-center">
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Nível de Serviço</div>
                                            <div class="text-sm font-bold text-green-600 dark:text-green-400">
                                                {{ ((getStockInfo(layer.product?.ean)?.serviceLevel || 0) *
                                                    100).toFixed(1) }}%
                                            </div>
                                        </div>
                                        <div class="bg-white dark:bg-gray-800 rounded-md p-2 text-center">
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Variabilidade</div>
                                            <div class="text-sm font-bold"
                                                :class="getStockInfo(layer.product?.ean)?.highVariability ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'">
                                                {{ ((getStockInfo(layer.product?.ean)?.variability || 0) *
                                                    100).toFixed(1) }}%
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Dimensões -->
                                <div class="w-full bg-gray-50 dark:bg-gray-800 rounded-lg p-3 mb-3" v-else>
                                    <h5 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 text-center">
                                        Dimensões</h5>
                                    <div class="grid grid-cols-3 gap-2 text-xs">
                                        <div class="text-center">
                                            <div class="font-medium text-gray-600 dark:text-gray-400">Altura</div>
                                            <div class="text-gray-800 dark:text-gray-200">{{ layer.product.height }}
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <div class="font-medium text-gray-600 dark:text-gray-400">Largura</div>
                                            <div class="text-gray-800 dark:text-gray-200">{{ layer.product.width }}
                                            </div>
                                        </div>
                                        <div class="text-center">
                                            <div class="font-medium text-gray-600 dark:text-gray-400">Profundidade</div>
                                            <div class="text-gray-800 dark:text-gray-200">{{ layer.product.depth }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Summary - Informações Financeiras -->
                                <div v-if="layer.product.summary"
                                    class="w-full bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                                    <h5
                                        class="text-sm font-semibold text-blue-700 dark:text-blue-300 mb-3 text-center flex items-center justify-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                        Resumo Financeiro
                                    </h5>

                                    <!-- Vendas -->
                                    <div class="mb-4">
                                        <h6
                                            class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wide">
                                            Vendas</h6>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div class="bg-white dark:bg-gray-800 rounded-md p-2 text-center">
                                                <div class="text-xs text-gray-500 dark:text-gray-400">Valor Total</div>
                                                <div class="text-sm font-bold text-green-600 dark:text-green-400">
                                                    R$ {{ formatCurrency(layer.product.summary.sales_total_value || 0)
                                                    }}
                                                </div>
                                            </div>
                                            <div class="bg-white dark:bg-gray-800 rounded-md p-2 text-center">
                                                <div class="text-xs text-gray-500 dark:text-gray-400">Quantidade</div>
                                                <div class="text-sm font-bold text-blue-600 dark:text-blue-400">
                                                    {{ layer.product.summary.sales_total_quantity || 0 }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Compras e Preços -->
                                    <div class="grid grid-cols-2 gap-3 mb-4">
                                        <div class="bg-white dark:bg-gray-800 rounded-md p-2 text-center">
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Qtd. Comprada</div>
                                            <div class="text-sm font-bold text-purple-600 dark:text-purple-400">
                                                {{ layer.product.summary.purchases_total_quantity || 0 }}
                                            </div>
                                        </div>
                                        <div class="bg-white dark:bg-gray-800 rounded-md p-2 text-center">
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Preço Médio</div>
                                            <div class="text-sm font-bold text-gray-700 dark:text-gray-300">
                                                R$ {{ formatCurrency(layer.product.summary.preco_medio || 0) }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Custos e Margem -->
                                    <div class="grid grid-cols-3 gap-2">
                                        <div class="bg-white dark:bg-gray-800 rounded-md p-2 text-center">
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Impostos</div>
                                            <div class="text-sm font-bold text-orange-600 dark:text-orange-400">
                                                R$ {{ formatCurrency(layer.product.summary.impostos_medio || 0) }}
                                            </div>
                                        </div>
                                        <div class="bg-white dark:bg-gray-800 rounded-md p-2 text-center">
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Custo Médio</div>
                                            <div class="text-sm font-bold text-red-600 dark:text-red-400">
                                                R$ {{ formatCurrency(layer.product.summary.custo_medio || 0) }}
                                            </div>
                                        </div>
                                        <div class="bg-white dark:bg-gray-800 rounded-md p-2 text-center">
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Margem</div>
                                            <div class="text-sm font-bold"
                                                :class="getMarginColor(layer.product.summary.margem_percentual || 0)">
                                                {{ (layer.product.summary.margem_percentual || 0).toFixed(1) }}%
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Análise de Estoque e Classificação -->


                                <!-- Botões de ação -->
                                <div
                                    class="absolute top-2 right-2 flex items-center justify-end rounded-md p-2 flex-row gap-2 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm">
                                    <AlertConfirm title="Excluir produto"
                                        message="Tem certeza que deseja excluir este produto? Esta ação não pode ser desfeita."
                                        confirmButtonText="Excluir" cancelButtonText="Cancelar" :isDangerous="true"
                                        @confirm="confirmDelete" @cancel="cancelDelete" :record="layer">
                                        <TrashIcon class="h-4 w-4 cursor-pointer text-red-500 hover:text-red-700" />
                                    </AlertConfirm>
                                    <EditProduct :product="getProductForEdit(layer.product)"
                                        @update:product="handleLayerUpdate">
                                        <EditIcon
                                            class="h-4 w-4 cursor-pointer no-remove-properties text-blue-500 hover:text-blue-700" />
                                    </EditProduct>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div v-else class="text-xs text-red-500">Dados do produto indisponíveis</div>
                </div>
            </div>
            <div v-else class="p-3 text-center text-gray-500">
                Carregando detalhes...
            </div>
        </div>
        <div v-else class="p-3 text-center text-gray-500">
            Nenhum produto selecionado.
        </div>
    </div>
</template>

<script setup lang="ts">
import { EditIcon, TrashIcon } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { apiService } from '@plannerate/services';
import { Layer } from '@/types/segment';
import { Product } from '@plannerate/types/segment';
import { useEditorStore } from '@plannerate/store/editor';
import EditProduct from './EditProduct.vue';
import { useTargetStockAnalysis } from '@plannerate/composables/useTargetStockAnalysis';
import { useAnalysisResultStore } from '@plannerate/store/editor/analysisResult';

const editorStore = useEditorStore();

const selectedLayers = ref<Layer[]>([]);
const isLoadingDetails = ref(false);
const emit = defineEmits<{
    (e: 'remove-layer', layer: Layer): void;
}>();
const { targetStockResultStore } = useTargetStockAnalysis();
const analysisResultStore = useAnalysisResultStore();
console.log('editorStore ', editorStore.currentState);
const start_date = editorStore?.currentState?.start_date || '';
const end_date = editorStore?.currentState?.end_date || '';
watch(
    editorStore.getSelectedLayerIds,
    async (newIdsSet) => {
        const idsToFetch = Array.from(newIdsSet);

        if (idsToFetch.length === 0) {
            selectedLayers.value = [];
            return;
        }

        isLoadingDetails.value = true;
        try {
            // Replace with correct source for start_date and end_date  
            const productDetailsPromises = idsToFetch.map((productId) => {
                return apiService.get<Layer>(`layers/${productId}`, {
                    params: {
                        start_date,
                        end_date,
                    },
                });
            });
            const fetchedProducts = await Promise.all(productDetailsPromises);
            selectedLayers.value = fetchedProducts.filter((p): p is Layer => !!p);
        } catch (error) {
            console.error('Erro ao buscar detalhes dos produtos selecionados:', error);
            selectedLayers.value = [];
        } finally {
            isLoadingDetails.value = false;
        }
    },
    { deep: true, immediate: true },
);


// Função para obter informações de estoque de um produto específico
const getStockInfo = (ean: string | undefined) => {
    if (!targetStockResultStore.result || !ean) {
        return null;
    }
    return targetStockResultStore.result.find(item => item.ean === ean);
};
const showDeleteConfirm = ref(false);
const confirmDelete = (record: any) => {
    if (!record) {
        console.error('No layer selected');
        return;
    }
    let sectionId = null;
    let shelfId = null;
    let segmentId = null;
    if (editorStore.getCurrentGondola) {
        editorStore.getCurrentGondola?.sections.forEach(section => {
            section.shelves.forEach(shelf => {
                shelf.segments.forEach(segment => {
                    if (segment.id === record.segment_id) {
                        sectionId = section.id;
                        shelfId = shelf.id;
                        segmentId = segment.id;
                    }
                });
            });
        });
        if (sectionId && shelfId && segmentId) {
            editorStore.removeSegmentFromShelf(editorStore.getCurrentGondola?.id, sectionId, shelfId, segmentId);
        }
    }
    emit('remove-layer', record);
};

const cancelDelete = () => {
    showDeleteConfirm.value = false;
};

const handleImageError = (event: Event, product: Product) => {
    const target = event.target as HTMLImageElement;
    const initials = product.name
        .split(' ')
        .map(word => word.charAt(0).toUpperCase())
        .join('')
        .slice(0, 2);
    target.src = `https://placehold.co/400x600?text=${initials}`;
}

const getProductForEdit = (product: Product) => {
    return {
        ...product,
        mercadologico_nivel: product.mercadologico_nivel || {
            mercadologico_nivel_1: null,
            mercadologico_nivel_2: null,
            mercadologico_nivel_3: null,
            mercadologico_nivel_4: null,
            mercadologico_nivel_5: null,
            mercadologico_nivel_6: null,
        }
    } as any;
}

const handleLayerUpdate = (updatedProduct: Product) => {
    console.log('handleLayerUpdate', selectedLayers.value);
    if (selectedLayers.value) {
        selectedLayers.value.forEach(layer => {
            if (layer.product?.id === updatedProduct.id) {
                layer.product = updatedProduct;
            }
        });
    }
}

// Funções auxiliares para formatação
const formatCurrency = (value: number): string => {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value);
}

const getMarginColor = (margin: number): string => {
    if (margin >= 20) return 'text-green-600 dark:text-green-400';
    if (margin >= 10) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
}

const getProductAnalysis = (ean: string | undefined) => {
    if (!analysisResultStore.result || !ean) return null;
    return analysisResultStore.result.find((item: any) => item.id === ean);
}

const getAbcClassColor = (abcClass: string | undefined): string => {
    switch (abcClass) {
        case 'A':
            return 'text-green-600 dark:text-green-400';
        case 'B':
            return 'text-yellow-600 dark:text-yellow-400';
        case 'C':
            return 'text-red-600 dark:text-red-400';
        default:
            return 'text-gray-600 dark:text-gray-400';
    }
}
</script>