// Info.vue - Componente Principal
<script setup lang="ts">
// Imports de Bibliotecas Externas
import {
    AlignCenter,
    AlignJustify,
    AlignLeft,
    AlignRight,
    ArrowLeftRight,
    Grid,
    Minus,
    Plus,
    Trash2,
    SaveIcon,
    Undo2Icon,
    Redo2Icon,
    PrinterIcon,
    NutIcon,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

// Imports Internos 
import { useEditorStore } from '@plannerate/store/editor';
import Category from './Category.vue';
import type { Gondola } from '@plannerate/types/gondola';
import ABCParamsPopover from '@plannerate/components/ABCParamsPopover.vue';
import AnalysisResultModal from '@plannerate/components/AnalysisResultModal.vue';
import TargetStockParamsPopover from '@plannerate/components/TargetStockParamsPopover.vue';
import BCGParamsPopover from '@plannerate/components/BCGParamsPopover.vue';
import TargetStockResultModal from '@plannerate/components/TargetStockResultModal.vue';
import { useTargetStock, type StockAnalysis, type Replenishment } from '@plannerate/composables/useTargetStock';
import { useAnalysisService } from '@plannerate/services/analysisService';
import { useAssortmentStatus, type Weights, type Thresholds } from '@plannerate/composables/useSortimentoStatus';
import { useAnalysisResultStore } from '@plannerate/store/editor/analysisResult';
import { useTargetStockResultStore } from '@plannerate/store/editor/targetStockResult';
// Definição das Props usando sintaxe padrão
const props = defineProps({
    gondola: {
        type: Object as () => Gondola | undefined, // Usar Object como tipo base
        required: false, // Ou true, dependendo da lógica pai
        // Não precisa de default se for undefined
    },
    categories: {
        type: Array as () => any[], // Usar any[] como tipo base genérico
        default: () => [], // Default padrão para array
    },
});

// Definição dos Emits
/**
 * Eventos emitidos pelo componente.
 * @event update:invertOrder - Emitido ao clicar para inverter a ordem das seções, com o ID da gôndola.
 * @event update:category - Emitido ao selecionar/limpar uma categoria de filtro.
 */
const emit = defineEmits(['update:invertOrder', 'update:category']);

// Hooks e Stores
const router = useRouter();
const editorStore = useEditorStore(); // Instanciar editorStore

// Estado Local
/** Filtros aplicados localmente (ex: categoria). */
const filters = ref({
    category: null as any | null, // Tipar o filtro de categoria
});

// Propriedades Computadas (Ligadas aos Stores)
/** Fator de escala atual do editor. */
const scaleFactor = computed(() => editorStore.currentScaleFactor);
/** Visibilidade da grade no editor. */
const showGrid = computed(() => editorStore.isGridVisible);
/** Seções da gôndola atual (agora lendo da prop). */
const sections = computed(() => (props.gondola as Gondola | undefined)?.sections || []); // Cast para usar

const shelfSelected = computed(() => {
    // Verifica se há prateleiras selecionadas
    return editorStore.getSelectedShelf;
});

// Adicionar Computed Props do Editor
const hasChanges = computed(() => editorStore.hasChanges);
const canUndo = computed(() => editorStore.canUndo);
const canRedo = computed(() => editorStore.canRedo);

const showResultModal = ref(false);


// *** NOVA Computed para a gôndola reativa do editorStore ***
const alignment = computed(() => {
    // Busca a gôndola correspondente no estado atual do editor
    const gondolaStore = props.gondola;
    let alignment = gondolaStore?.alignment;
    return alignment;
});

// Estado para o Popover e campos do cálculo ABC
const showCalculos = ref(false);
const showABCParams = ref(false); 
watch(showABCParams, (newVal) => {
    if (!newVal) {
        showCalculos.value = false;
    }
});
const abcParams = ref({
    weights: {
        quantity: 0.30,
        value: 0.30,
        margin: 0.40,
    },
    thresholds: {
        a: 0.8,
        b: 0.85,
    },
});

// Estado para o Popover e campos do cálculo Estoque Alvo
const showTargetStockParams = ref(false);
watch(showTargetStockParams, (newVal) => {
    if (!newVal) {
        showCalculos.value = false;
    }
});
const targetStockParams = ref({
    serviceLevels: [
        { classification: 'A', level: 0.7 },
        { classification: 'B', level: 0.8 },
        { classification: 'C', level: 0.9 }
    ],
    replenishmentParams: [
        { classification: 'A', coverageDays: 2 },
        { classification: 'B', coverageDays: 5 },
        { classification: 'C', coverageDays: 7 }
    ],
});

// Estado para o Popover e campos do cálculo BCG
const showBCGParams = ref(false);
watch(showBCGParams, (newVal) => {
    if (!newVal) {
        showCalculos.value = false;
    }
});
const bcgParams = ref({
    marketShare: 0.1,
    growthRate: 0.1
});

// Estado para o resultado do cálculo de Estoque Alvo
const showTargetStockResultModal = ref(false);
const targetStockResultData = ref<StockAnalysis[]>([]);
const targetStockReplenishmentParams = ref<Replenishment[]>([
    { classification: 'A', coverageDays: 2 },
    { classification: 'B', coverageDays: 5 },
    { classification: 'C', coverageDays: 7 }
]);

const analysisService = useAnalysisService();
const analysisResultStore = useAnalysisResultStore();
const targetStockResultStore = useTargetStockResultStore();
// Função para executar a Análise ABC
async function executeABCAnalysis() {
    const products: any[] = [];
    editorStore.getCurrentGondola?.sections.forEach(section => {
        section.shelves.forEach(shelf => {
            shelf.segments.forEach(segment => {
                const product = segment.layer.product as any;
                if (product) {
                    products.push({
                        id: product.id,
                        ean: product.ean,
                        name: product.name,
                        classification: product.classification,
                        currentStock: product.current_stock || 0
                    });
                }
            });
        });
    });

    try {
        if (products.length > 0) {
            const analysisData = await analysisService.getABCAnalysisData(
                products.map(p => p.id),
                {
                    // período padrão dos últimos 30 dias
                    startDate: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
                    endDate: new Date().toISOString().split('T')[0],
                    weights: abcParams.value.weights as { quantity: number; value: number; margin: number },
                    thresholds: abcParams.value.thresholds as { a: number; b: number }
                }
            );

            const analyzed = useAssortmentStatus(
                analysisData,
                abcParams.value.weights as Weights,
                abcParams.value.thresholds as Thresholds
            );

            analysisResultStore.setResult(analyzed);

            // Abrir modal de resultado e fechar popover
            showResultModal.value = true;
            showABCParams.value = false;
        }
    } catch (error) {
        console.error('Erro ao executar Análise ABC:', error);
        // Optionally set an error state in the store
    } finally {
        analysisResultStore.loading = false;
    }
}

// Métodos
/**
 * Atualiza o fator de escala no store.
 * @param {number} newScale - O novo valor da escala.
 */
const updateScale = (newScale: number) => {
    const clampedScale = Math.max(2, Math.min(10, newScale));
    // Atualiza a escala no editorStore
    editorStore.setScaleFactor(clampedScale);
};

/** Alterna a visibilidade da grade no store. */
const toggleGrid = () => {
    editorStore.toggleGrid();
};

/** Emite evento para inverter a ordem das seções da gôndola pai. */
const invertSectionOrder = () => {
    const currentGondola = props.gondola as Gondola | undefined;
    if (currentGondola?.id) {
        editorStore.invertGondolaSectionOrder(currentGondola.id);
    } else {
        console.warn('Não é possível inverter a ordem: Gôndola atual não definida.');
    }
};

/**
 * Atualiza a categoria selecionada no filtro local e emite o evento.
 * @param {number | null} categoryId - O ID da categoria selecionada ou null.
 */
const selectCategory = (categoryId: number | null) => {
    filters.value.category = categoryId;
    emit('update:category', categoryId);
};

/** Limpa o filtro de categoria localmente e emite o evento. */
const clearCategoryFilter = () => {
    filters.value.category = null;
    emit('update:category', null);
};

/** Navega para a tela de edição/adição de seção para a gôndola atual. */
const navigateToAddSection = () => {
    const currentGondola = props.gondola as Gondola | undefined;
    if (currentGondola) {
        router.push({
            name: 'plannerate.gondola.add_section',
            params: {
                id: currentGondola.planogram_id,
                gondolaId: currentGondola.id,
            },
        });
    }
};

/**
 * Confirma e remove a gôndola atual.
 * Atualiza o store, chama a API e redireciona.
 */
const confirmDeleteGondola = async () => {
    const currentGondola = props.gondola as Gondola | undefined;
    if (!currentGondola) return;
    try {
        const planogramId = currentGondola.planogram_id;
        editorStore.removeGondola(currentGondola.id, () => {
            const editorStore = useEditorStore();
            const gondolas = editorStore.currentState?.gondolas;
            if (gondolas?.length) {
                const gondola = gondolas[0];
                router.push({
                    name: 'gondola.view',
                    params: { id: planogramId, gondolaId: gondola.id },
                });
            } else {
                router.push({
                    name: 'plannerate.create',
                    params: { id: planogramId },
                });
            }
        });
    } catch (error) {
        console.error('Erro ao remover gôndola:', error);
    }
};

/**
 * Confirma a remoção da prateleira selecionada.
 * Atualiza o store, chama a API e redireciona.
 */
// Define interface for modal types
interface DeleteConfirmItem {
    shelf?: boolean;
    gondola?: boolean;
}

const showDeleteConfirm = ref<DeleteConfirmItem[]>([]); // Estado para controle do modal de confirmação
const confirmRemoveShelf = async () => {
    showDeleteConfirm.value.push({
        shelf: true,
    }); // Abre o modal de confirmação
};

/**
 * Confirma a exclusão da prateleira selecionada.
 * Atualiza o store, chama a API e redireciona.
 */
const confirmDeleteShelf = async () => {
    // Adiciona verificação se a gôndola existe
    if (!shelfSelected.value) return;
    try {
        // Chamar o método de exclusão da prateleira no gondolaStore
        if (props.gondola) {
            await editorStore.removeShelfFromSection(props.gondola.id, shelfSelected.value.section_id, shelfSelected.value.id);
        }
    } catch (error) {
        console.error('Erro ao excluir prateleira:', error);
    }
};

/**
 * Confirma a exclusão da gôndola selecionada.
 * Atualiza o store, chama a API e redireciona.
 */
const confirmRemoveGondola = () => {
    showDeleteConfirm.value.push({
        gondola: true,
    }); // Abre o modal de confirmação
};
const cancelDelete = () => {
    showDeleteConfirm.value = [];
};

/**
 * Define o alinhamento padrão da gôndola atual, atualizando o estado via editorStore.
 */
const setGondolaAlignmentHandler = (alignment: string | null = null) => {
    const gondolaId = (props.gondola as Gondola | undefined)?.id;
    if (!gondolaId) {
        console.error("setGondolaAlignmentHandler: ID da gôndola atual não encontrado.");
        return;
    }
    try {
        editorStore.setGondolaAlignment(gondolaId, alignment);
    } catch (error) {
        console.error('Erro ao definir alinhamento da gôndola:', error);
    }
};

// Adicionar Métodos do Editor
const undo = () => editorStore.undo();
const redo = () => editorStore.redo();
const saveChanges = () => editorStore.saveChanges();

// Métodos para os cálculos
const calcularABC = () => {
    // Aqui você pode usar params para enviar os parâmetros 
    showABCParams.value = true;
};
const calcularBCG = () => {
    // Placeholder para lógica do cálculo Matriz BCG
    console.log('Cálculo Matriz BCG acionado');
};
const closeResultModal = () => {
    showResultModal.value = false;
};

// Função para executar a Análise de Estoque Alvo
async function executeTargetStockAnalysis() {
    const products: any[] = [];
    editorStore.getCurrentGondola?.sections.forEach(section => {
        section.shelves.forEach(shelf => {
            shelf.segments.forEach(segment => {
                const product = segment.layer.product as any;
                if (product) {
                    products.push({
                        id: product.id,
                        ean: product.ean,
                        name: product.name,
                        classification: product.classification || 'C',
                        currentStock: product.current_stock || 0
                    });
                }
            });
        });
    });

    if (products.length > 0) {
        // Definir loading no store antes de buscar os dados
        targetStockResultStore.loading = true;
        try {
            const sales = await analysisService.getTargetStockData(
                products.map(p => p.id),
                {
                    period: 30 // período padrão de 30 dias
                }
            );

            // Transformar os dados de vendas no formato esperado
            const productsWithSales = products.map(product => {
                const productSales = sales.find((sale: any) => sale.product_id === product.id);
                return {
                    ...product,
                    sales: productSales ? Object.values(productSales.sales_by_day) : []
                };
            });

            const analyzed = useTargetStock(
                productsWithSales,
                targetStockParams.value.serviceLevels,
                targetStockParams.value.replenishmentParams
            );

            // Atualizar o store com os resultados
            targetStockResultStore.setResult(analyzed, targetStockParams.value.replenishmentParams);

        } catch (error) {
            console.error('Erro ao executar Análise de Estoque Alvo:', error);
            // Optionally set an error state in the store
        } finally {
            // Remover loading no store após a conclusão
            targetStockResultStore.loading = false;
        }
    }
}

function openTargetStockResultModal(result: StockAnalysis[], params: Replenishment[]) {
    targetStockResultData.value = result;
    targetStockReplenishmentParams.value = params;
    showTargetStockResultModal.value = true;
    showTargetStockParams.value = false; // Fecha o popover
}

// Observar ações do store para recalculo ABC
analysisResultStore.$onAction(({ name, store, args, after, onError }) => {
    if (name === 'requestRecalculation') {
        after(() => {
            executeABCAnalysis();
        });
    }

});

// Observar ações do store para recalculo Estoque Alvo
targetStockResultStore.$onAction(({ name, store, args, after, onError }) => {
    if (name === 'requestRecalculation') {
        after(() => {
            executeTargetStockAnalysis(); // Chamar a nova função de cálculo
        });
    }
});

function removeFromGondola(selectedItemId: string | null) {
    if (selectedItemId) {
        const record = editorStore.getCurrentGondola?.sections.flatMap(section => section.shelves.flatMap(shelf => shelf.segments.flatMap(segment => segment.layer.product))).find(product => product?.ean === selectedItemId);
        if (record) {
            let sectionId = null;
            let shelfId = null;
            let segmentId = null;
            if (editorStore.getCurrentGondola) {
                editorStore.getCurrentGondola?.sections.forEach(section => {
                    section.shelves.forEach(shelf => {
                        shelf.segments.forEach(segment => {
                            if (segment.layer.product?.ean === selectedItemId) {
                                sectionId = section.id;
                                shelfId = shelf.id;
                                segmentId = segment.id;
                            }
                        });
                    });
                });
                if (sectionId && shelfId && segmentId) {
                    editorStore.removeSegmentFromShelf(editorStore.getCurrentGondola?.id, sectionId, shelfId, segmentId);
                    analysisResultStore.requestRecalculation();
                }
            }
        }
    }
}
</script>

<template>
    <!-- Cabeçalho Fixo com Controles -->
    <div class="sticky top-0 z-50 border-b bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <div class="flex items-center justify-between">
                <!-- Grupo Esquerda: Controles de Visualização e Filtros -->
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <!-- Label Gôndola -->
                    <h3 class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300">
                        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                        </svg>
                        {{ (props.gondola as Gondola | undefined)?.name || 'Gôndola' }}
                    </h3>
                    <!-- Controle de Escala -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm text-gray-600 dark:text-gray-400">Escala:</label>
                        <div class="flex items-center space-x-2">
                            <Button type="button" variant="outline" size="sm" :disabled="scaleFactor <= 2"
                                @click="updateScale(scaleFactor - 1)">
                                <Minus class="h-4 w-4" />
                            </Button>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ scaleFactor }}x
                            </span>
                            <Button type="button" variant="outline" size="sm" :disabled="scaleFactor >= 10"
                                @click="updateScale(scaleFactor + 1)">
                                <Plus class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                    <!-- Botão de Grade -->
                    <Button type="button" variant="outline" size="sm" @click="toggleGrid"
                        :class="{ 'bg-accent text-accent-foreground': showGrid }">
                        <Grid class="h-4 w-4" />
                    </Button>
                    <!-- Botões de Justificação -->
                    <div class="flex items-center space-x-1">
                        <Button type="button" :variant="alignment === 'justify' ? 'default' : 'outline'" size="sm"
                            @click="setGondolaAlignmentHandler('justify')">
                            <AlignJustify class="h-4 w-4" />
                        </Button>
                        <Button type="button" :variant="alignment === 'left' ? 'default' : 'outline'" size="sm"
                            @click="setGondolaAlignmentHandler('left')" title="Alinhar à Esquerda">
                            <AlignLeft class="h-4 w-4" />
                        </Button>
                        <Button type="button" :variant="alignment === 'center' ? 'default' : 'outline'" size="sm"
                            @click="setGondolaAlignmentHandler('center')" title="Centralizar">
                            <AlignCenter class="h-4 w-4" />
                        </Button>
                        <Button type="button" :variant="alignment === 'right' ? 'default' : 'outline'" size="sm"
                            @click="setGondolaAlignmentHandler('right')" title="Alinhar à Direita">
                            <AlignRight class="h-4 w-4" />
                        </Button>
                    </div>
                    <!-- Filtro de Categoria -->
                    <div class="flex items-center space-x-2" v-if="props.categories && props.categories.length > 0">
                        <label class="text-sm text-gray-600 dark:text-gray-400">Filtros:</label>
                        <Popover @clear-filters="clearCategoryFilter" :has-active-filters="!!filters.category">
                            <Category class="w-full" :categories="props.categories" v-model="filters.category"
                                @update:model-value="selectCategory" :clearable="true" />
                        </Popover>
                    </div>
                </div>

                <!-- Grupo Direita: Botões de Ação -->
                <div class="flex items-center gap-x-3 gap-y-2" v-if="props.gondola">
                    <!-- Grupo Ações Gôndola/Seção -->
                    <div class="flex items-center gap-2">
                        <Button v-if="shelfSelected" type="button" variant="outline" size="icon"
                            @click="confirmRemoveShelf" title="Remover Prateleira">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                        <Button type="button" variant="secondary" size="sm" v-if="sections.length > 1"
                            @click="invertSectionOrder" title="Inverter Ordem Seções">
                            <ArrowLeftRight class="mr-1 h-4 w-4" /> <span class="hidden md:inline">Inverter</span>
                        </Button>
                        <Button type="button" variant="secondary" size="sm" @click="navigateToAddSection"
                            title="Adicionar Seção">
                            <Plus class="mr-1 h-4 w-4" /> <span class="hidden md:inline">Seção</span>
                        </Button>
                        <Button type="button" variant="destructive" size="sm" @click="confirmRemoveGondola"
                            title="Remover Gôndola">
                            <Trash2 class="mr-1 h-4 w-4" /> <span class="hidden md:inline">Gôndola</span>
                        </Button>
                    </div>

                    <!-- Divisor Vertical -->
                    <div class="h-6 w-px bg-gray-300 dark:bg-gray-600 mx-1"></div>

                    <!-- Grupo Histórico/Salvar --->
                    <div class="flex items-center gap-2">
                        <Button variant="outline" size="sm" @click="undo" :disabled="!canUndo"
                            class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 disabled:opacity-50">
                            <Undo2Icon class="mr-2 h-4 w-4" />
                            Desfazer
                        </Button>
                        <Button variant="outline" size="sm" @click="redo" :disabled="!canRedo"
                            class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 disabled:opacity-50">
                            <Redo2Icon class="mr-2 h-4 w-4" />
                            Refazer
                        </Button>
                        <div class="flex items-center gap-1">

                            <Button size="sm" @click="saveChanges" :disabled="!hasChanges"
                                class="dark:hover:bg-primary-800 disabled:opacity-50"
                                :variant="hasChanges ? 'default' : 'outline'">
                                <SaveIcon class="mr-2 h-4 w-4" />
                                Salvar
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex gap-2 m-2 justify-between">
            <div class="flex gap-2">



            </div>
            <div class="flex gap-2">
                <Button v-if="analysisResultStore.result" @click="analysisResultStore.setResult(null);" variant="destructive">Limpar Resultado</Button>
                <Popover v-model:open="showCalculos">
                    <PopoverTrigger as-child>
                        <Button variant="outline" size="sm" @click="showCalculos = true">
                            <NutIcon class="h-4 w-4" />
                            Calculos
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent class="w-auto max-w-lg z-[1000]">
                        <div class="flex flex-col gap-2">
                            <Button variant="outline" @click="showABCParams = true">Calculos ABC</Button>
                            <Button variant="outline" @click="showTargetStockParams = true">Calculos Estoque Alvo
                                Prateleira</Button>
                            <Button variant="outline" @click="showBCGParams = true">Calculos Matriz BCG</Button>
                        </div>
                    </PopoverContent>
                </Popover>
                <Button variant="outline" size="sm">
                    <PrinterIcon class="h-4 w-4" />
                    Imprimir
                </Button>
                <Popover v-model:open="showABCParams">
                    <PopoverTrigger as-child>
                        <!-- Botão movido para o popover principal -->
                        <span></span>
                    </PopoverTrigger>
                    <PopoverContent class="w-auto max-w-lg z-[1000]" align="end">
                        <ABCParamsPopover :weights="abcParams.weights" :thresholds="abcParams.thresholds"
                            @update:weights="calcularABC" @update:thresholds="calcularABC"
                            @show-result-modal="showResultModal = true" />
                    </PopoverContent>
                </Popover>
                <Popover v-model:open="showTargetStockParams">
                    <PopoverTrigger as-child>
                        <!-- Botão movido para o popover principal -->
                        <span></span>
                    </PopoverTrigger>
                    <PopoverContent class="w-auto max-w-lg z-[1000]" align="end">
                        <TargetStockParamsPopover :service-levels="targetStockParams.serviceLevels"
                            :replenishment-params="targetStockParams.replenishmentParams"
                            @show-result-modal="openTargetStockResultModal($event.result, $event.replenishmentParams)" />
                    </PopoverContent>
                </Popover>
                <Popover v-model:open="showBCGParams">
                    <PopoverTrigger as-child>
                        <!-- Botão movido para o popover principal -->
                        <span></span>
                    </PopoverTrigger>
                    <PopoverContent class="w-auto max-w-lg z-[1000]" align="end">
                        <BCGParamsPopover :market-share="bcgParams.marketShare" :growth-rate="bcgParams.growthRate"
                            @update:market-share="calcularBCG" @update:growth-rate="calcularBCG"
                            @show-result-modal="showResultModal = true" />
                    </PopoverContent>
                </Popover>
            </div>
        </div>
        <ConfirmModal :isOpen="showDeleteConfirm.some((item) => item.gondola)"
            @update:isOpen="(isOpen: boolean) => !isOpen && (showDeleteConfirm = [])" title="Excluir gondola"
            message="Tem certeza que deseja a gondola? Esta ação não pode ser desfeita." confirmButtonText="Excluir"
            cancelButtonText="Cancelar" :isDangerous="true" @confirm="confirmDeleteGondola" @cancel="cancelDelete" />

        <ConfirmModal :isOpen="showDeleteConfirm.some((item) => item.shelf)"
            @update:isOpen="(isOpen: boolean) => !isOpen && (showDeleteConfirm = [])" title="Excluir produto"
            message="Tem certeza que deseja excluir este produto? Esta ação não pode ser desfeita."
            confirmButtonText="Excluir" cancelButtonText="Cancelar" :isDangerous="true" @confirm="confirmDeleteShelf"
            @cancel="cancelDelete" />
        <AnalysisResultModal :open="showResultModal" @close="closeResultModal"
            @remove-from-gondola="removeFromGondola" />
        <TargetStockResultModal :open="showTargetStockResultModal" @close="showTargetStockResultModal = false" />
    </div>
</template>
