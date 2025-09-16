// Info.vue - Componente Principal
<script setup lang="ts">
// Imports de Bibliotecas Externas
import {
    AlignCenter,
    AlignJustify,
    AlignLeft,
    AlignRight,
    ArrowLeftRight,
    Minus,
    Plus,
    Trash2,
    SaveIcon,
    Undo2Icon,
    Redo2Icon,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';

// Imports Internos 
import { useEditorStore } from '@plannerate/store/editor';
import Category from './Category.vue';
import type { Gondola } from '@plannerate/types/gondola'; 
import AnalysisPopover from './AnalysisPopover.vue';
import AutoGenerateModal, { type AutoGenerateFilters, type IntelligentGenerationParams } from './AutoGenerateModal.vue';
import autoplanogramService from '@plannerate/services/autoplanogramService';
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


// *** NOVA Computed para a gôndola reativa do editorStore ***
const alignment = computed(() => {
    // Busca a gôndola correspondente no estado atual do editor
    const gondolaStore = props.gondola;
    const alignment = gondolaStore?.alignment;
    return alignment;
});

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
            router.push({
                name: 'plannerate.index',
                params: { id: planogramId },
            });
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

/**
 * Inicia o processo de geração automática do planograma.
 * Conectado ao Motor de Planograma Automático via API.
 */
const isGeneratingScores = ref(false);

// Estado do modal de geração automática
const showAutoGenerateModal = ref(false);

// Abre o modal de configuração da geração automática
const openAutoGenerateModal = () => {
    const currentGondola = props.gondola as Gondola | undefined;
    if (!currentGondola?.id) {
        console.warn('Não é possível gerar automaticamente: Gôndola atual não definida.');
        alert('⚠️ Gôndola não definida. Não é possível gerar planograma automaticamente.');
        return;
    }
    
    showAutoGenerateModal.value = true;
};

// Executa a geração automática com os filtros selecionados
const executeAutomaticGeneration = async (filters: AutoGenerateFilters) => {
    const currentGondola = props.gondola as Gondola | undefined;
    if (!currentGondola?.id) {
        console.warn('Não é possível gerar automaticamente: Gôndola atual não definida.');
        return;
    }

    // Fechar o modal
    showAutoGenerateModal.value = false;
    isGeneratingScores.value = true;

    try {
        console.log('Iniciando geração automática para a gôndola:', currentGondola.id);
        console.log('Filtros aplicados:', filters);
        
        // Importar dinamicamente o engineService
        const { engineService } = await import('@plannerate/services/engineService');
        
        // Calcular scores e distribuir produtos automaticamente
        const response = await engineService.calculateScores({
            gondola_id: currentGondola.id,
            auto_distribute: true,
            filters: filters
        });

        // Verificar se há produtos para análise
        if (response.data.calculation_info.products_analyzed === 0) {
            alert(`⚠️ ${response.message}`);
            return;
        }

        console.log('Scores calculados:', response.data.summary);
        console.log('Distribuição automática:', response.data.distribution);
        
        // Verificar se a distribuição foi realizada
        const distribution = response.data.distribution;
        let message = `✅ Planograma gerado automaticamente!\n\n`;
        message += `Produtos analisados: ${response.data.calculation_info.products_analyzed}\n`;
        message += `Score médio: ${(response.data.summary.average_score * 100).toFixed(1)}%\n\n`;
        
        if (distribution) {
            message += `📦 Distribuição:\n`;
            message += `• Produtos colocados: ${distribution.products_placed}\n`;
            message += `• Segmentos utilizados: ${distribution.segments_used}\n`;
            message += `• Classe A: ${distribution.placement_by_class?.A?.total_products || 0} produtos\n`;
            message += `• Classe B: ${distribution.placement_by_class?.B?.total_products || 0} produtos\n`;
            message += `• Classe C: ${distribution.placement_by_class?.C?.total_products || 0} produtos`;
        } else {
            message += `Classe A: ${response.data.summary.abc_distribution.A || 0} produtos\n`;
            message += `Classe B: ${response.data.summary.abc_distribution.B || 0} produtos\n`;
            message += `Classe C: ${response.data.summary.abc_distribution.C || 0} produtos`;
        }
        
        alert(message);
        
        // Loggar detalhes para debug
        console.table(response.data.scores.slice(0, 10)); // Mostrar top 10 produtos
        
        // Recarregar a gôndola para mostrar os produtos distribuídos
        if (distribution && distribution.products_placed > 0) {
            window.location.reload();
        }
        
    } catch (error: any) {
        console.error('Erro na geração automática:', error);
        alert(`❌ Erro na geração automática:\n\n${error.message}`);
    } finally {
        isGeneratingScores.value = false;
    }
};

// Novo método para geração inteligente com ABC + Target Stock
const executeIntelligentGeneration = async (params: IntelligentGenerationParams) => {
    const currentGondola = props.gondola as Gondola | undefined;
    if (!currentGondola?.id) {
        console.warn('Não é possível gerar automaticamente: Gôndola atual não definida.');
        return;
    }

    // Fechar o modal
    showAutoGenerateModal.value = false;
    isGeneratingScores.value = true;

    try {
        console.log('🧠 Iniciando geração inteligente...', {
            gondola_id: currentGondola.id,
            abc_params: params.abcParams,
            target_stock_params: params.targetStockParams
        });
        
        const result = await autoplanogramService.generateIntelligent({
            gondola_id: currentGondola.id,
            filters: params.filters,
            abc_params: params.abcParams,
            target_stock_params: params.targetStockParams,
            facing_limits: params.facingLimits
        });
        
        // Aplicar resultado
        if (result.success) {
            // Mostrar estatísticas
            showGenerationStats(result.data.stats, result.metadata);
            
            console.log('✅ Geração inteligente concluída!', result.data.stats);
            
            // Recarregar a gôndola para mostrar os produtos distribuídos
            if (result.data.stats.successfully_placed > 0) {
                window.location.reload();
            }
        }
        
    } catch (error: any) {
        console.error('❌ Erro na geração inteligente:', error);
        alert('❌ Erro na geração inteligente:\n\n' + error.message);
    } finally {
        isGeneratingScores.value = false;
    }
};

const showGenerationStats = (stats: any, metadata: any) => {
    const message = `
🎯 GERAÇÃO INTELIGENTE CONCLUÍDA!

📊 Resultados:
• Produtos processados: ${stats.total_processed}
• Produtos colocados: ${stats.successfully_placed}
• Taxa de sucesso: ${stats.placement_rate.toFixed(1)}%

⏱️ Performance:
• Tempo de processamento: ${metadata.processing_time_ms}ms
• Análise ABC: ${metadata.abc_analysis?.products_analyzed || 0} produtos
• Target Stock: ${metadata.target_stock_analysis?.products_analyzed || 0} produtos
    `;
    
    alert(message);
};

</script>

<template>
    <!-- Cabeçalho Fixo com Controles -->
    <div class="sticky top-0 z-40 border-b border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-2">
            <div class="flex items-center justify-between   gap-2 overflow-x-auto">
                <!-- Grupo Esquerda: Controles de Visualização e Filtros -->
                <div class="flex items-center gap-x-2 gap-y-2">
                    <!-- Label Gôndola -->

                    <!-- Controle de Escala -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm text-gray-600 dark:text-gray-400 hidden xl:block">Escala:</label>
                        <div class="flex items-center space-x-2">
                            <Button type="button" variant="outline" size="sm" :disabled="scaleFactor <= 2"
                                @click="updateScale(scaleFactor - 1)" title="Diminuir escala">
                                <Minus class="h-4 w-4" />
                            </Button>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ scaleFactor }}x
                            </span>
                            <Button type="button" variant="outline" size="sm" :disabled="scaleFactor >= 10"
                                @click="updateScale(scaleFactor + 1)" title="Aumentar escala">
                                <Plus class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                    <!-- Botão de Grade -->
                    <!-- <Button type="button" variant="outline" size="sm" @click="toggleGrid"
                        :class="{ 'bg-accent text-accent-foreground': showGrid }">
                        <Grid class="h-4 w-4" />
                    </Button> -->
                    <!-- Botões de Justificação -->
                    <div class="flex items-center space-x-1">
                        <Button type="button" :variant="alignment === 'justify' ? 'default' : 'outline'" size="sm"
                            @click="setGondolaAlignmentHandler('justify')" title="Justificar">
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
                            <ArrowLeftRight class="mr-1 h-4 w-4" /> <span class="hidden xl:inline">Inverter</span>
                        </Button>
                <Button type="button" variant="default" size="sm"
                    @click="openAutoGenerateModal" :disabled="isGeneratingScores" 
                    title="Gerar Planograma Automático">
                    <template v-if="isGeneratingScores">
                        <svg class="animate-spin mr-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="hidden xl:inline">Calculando...</span>
                    </template>
                    <template v-else>
                        <Zap class="mr-1 h-4 w-4" /> <span class="hidden xl:inline">Gerar Automático</span>
                    </template>
                </Button>
                        <Button type="button" variant="secondary" size="sm" @click="navigateToAddSection"
                            title="Adicionar Seção">
                            <Plus class="mr-1 h-4 w-4" /> <span class="hidden xl:inline">Adicionar novo Modulo</span>
                        </Button>
                        <Button type="button" variant="destructive" size="sm" @click="confirmRemoveGondola"
                            title="Remover Gôndola">
                            <Trash2 class="mr-1 h-4 w-4" /> <span class="hidden xl:inline">Remover Gôndola</span>
                        </Button>
                    </div>

                    <!-- Divisor Vertical -->
                    <div class="h-6 w-px bg-gray-300 dark:bg-gray-600 mx-1"></div>

                    <!-- Grupo Histórico/Salvar --->
                    <div class="flex items-center gap-2">
                        <Button variant="outline" size="sm" @click="undo" :disabled="!canUndo"
                            class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 disabled:opacity-50">
                            <Undo2Icon class="mr-2 h-4 w-4" />
                            <span class="hidden xl:block">Desfazer</span>
                        </Button>
                        <Button variant="outline" size="sm" @click="redo" :disabled="!canRedo"
                            class="dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 disabled:opacity-50">
                            <Redo2Icon class="mr-2 h-4 w-4" />
                            <span class="hidden xl:block">Refazer</span>
                        </Button>
                        <div class="flex items-center gap-1">

                            <Button size="sm" @click="saveChanges" :disabled="!hasChanges"
                                class="dark:hover:bg-primary-800 disabled:opacity-50"
                                :variant="hasChanges ? 'default' : 'outline'">
                                <SaveIcon class="mr-2 h-4 w-4" />
                                <span class="hidden xl:block">Salvar</span>
                            </Button>
                        </div>
                        <div class="flex gap-2">
                            <AnalysisPopover />
                        </div>
                    </div>
                </div>
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

        <!-- Modal de Geração Automática -->
        <AutoGenerateModal 
            v-model:open="showAutoGenerateModal" 
            :is-loading="isGeneratingScores"
            :planogram-category="'Categoria do planograma'"
            @confirm="executeAutomaticGeneration"
            @confirm-intelligent="executeIntelligentGeneration"
        />
    </div>
</template>
