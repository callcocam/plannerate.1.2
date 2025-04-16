// Info.vue - Componente Principal
<script setup lang="ts">
// Imports de Bibliotecas Externas
import {
    AlignHorizontalJustifyCenter,
    AlignHorizontalJustifyEnd,
    AlignHorizontalJustifyStart,
    ArrowLeftRight,
    Grid,
    Minus,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';

// Imports Internos
import { apiService } from '../../../services';
import { useEditorStore } from '../../../store/editor';
import { useGondolaStore } from '../../../store/gondola'; // Importar o store da gôndola
import { useShelvesStore } from '../../../store/shelves';
import Category from './Category.vue'; // Assumindo que Category e Popover estão corretos
import Popover from './Popover.vue';

// Definição das Props
/**
 * Props do componente Info.
 * @property {Array} categories - Lista de categorias disponíveis para filtro.
 */
const props = defineProps({
    categories: {
        type: Array as () => any[], // Tipar categoria se possível
        default: () => [],
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
const editorStore = useEditorStore();
const gondolaStore = useGondolaStore(); // Usar o store da gôndola
const shelvesStore = useShelvesStore();

// Estado Local
/** Filtros aplicados localmente (ex: categoria). */
const filters = ref({
    category: null as any | null, // Tipar o filtro de categoria
});

// Propriedades Computadas (Ligadas aos Stores)
/** Fator de escala atual do editor. */
const scaleFactor = computed(() => editorStore.scaleFactor);
/** Visibilidade da grade no editor. */
const showGrid = computed(() => editorStore.showGrid);
/** Gôndola atual do store. */
const currentGondola = computed(() => gondolaStore.currentGondola);
/** Seções da gôndola atual (do store). */
const sections = computed(() => currentGondola.value?.sections || []);
/** Largura da seção (do store). */
const sectionWidth = computed(() => currentGondola.value?.section_width || 0);
/** Altura da seção (do store). */
const sectionHeight = computed(() => currentGondola.value?.section_height || 0);
/** Largura da prateleira (do store). */
const shelfWidth = computed(() => currentGondola.value?.shelf_width || 0);

const shelfSelected = computed(() => {
    // Verifica se há prateleiras selecionadas
    return shelvesStore.selectedShelf;
});

// Métodos
/**
 * Atualiza o fator de escala no store.
 * @param {number} newScale - O novo valor da escala.
 */
const updateScale = (newScale: number) => {
    editorStore.setGondolaId(currentGondola.value?.id); // Atualiza o ID da gôndola no store
    // Adiciona validação de limites se necessário, embora os botões já tenham :disabled
    const clampedScale = Math.max(2, Math.min(10, newScale));
    // Atualiza a escala da gôndola no store
    editorStore.updateScaleFactor(clampedScale);
};

/** Alterna a visibilidade da grade no store. */
const toggleGrid = () => {
    editorStore.toggleGrid();
};

/** Emite evento para inverter a ordem das seções da gôndola pai. */
const invertSectionOrder = () => {
    // Adiciona verificação se a gôndola existe
    if (currentGondola.value) {
        apiService.post(`sections/${currentGondola.value.id}/shelves/reorder`).then((response) => {
            // Emitir evento para o componente pai (Sections) lidar com a atualização
            gondolaStore.invertSectionOrder(response.data);
        });
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
    // Adiciona verificação se a gôndola existe
    if (currentGondola.value) {
        router.push({
            name: 'plannerate.gondola.edit', // Rota para adicionar/editar seção (ajustar se necessário)
            params: {
                id: currentGondola.value.planogram_id, // Usar planogram_id do store
                gondolaId: currentGondola.value.id, // Usar id do store
            },
        });
    }
};

/**
 * Confirma e remove a gôndola atual.
 * Atualiza o store, chama a API e redireciona.
 */
const confirmDeleteGondola = async () => {
    // Adiciona verificação se a gôndola existe
    if (!currentGondola.value) return;

    try {
        const gondolaToRemove = currentGondola.value; // Guarda a referência antes de limpar
        const gondolaId = gondolaToRemove.id;
        const planogramId = gondolaToRemove.planogram_id;

        // Limpa o store localmente *antes* da chamada API (Otimista)
        gondolaStore.clearGondola(); // Limpa a gondola do store

        // Chama a API para deletar
        await apiService.delete(`gondolas/${gondolaId}`);

        // Redireciona após sucesso
        router.push({
            name: 'plannerate.home', // Rota para criar gôndola
            params: { id: planogramId }, // Usar planogram_id do store
        });
    } catch (error) {
        console.error('Erro ao remover gôndola:', error);
        // TODO: Adicionar feedback de erro para o usuário (ex: toast)
        // Em caso de erro, talvez buscar a gôndola novamente ou forçar um reload
        // gondolaStore.fetchGondola(gondolaId); // Tentativa de reverter (complexo)
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
        await shelvesStore.deleteSelectedShelf();
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
 * Justifica os produtos na prateleira selecionada.
 * Atualiza o store, chama a API e redireciona.
 */
const justifyProducts = async (alignment: string) => {
    // Adiciona verificação se a gôndola existe
    if (!currentGondola.value) return;
    // Adiciona verificação se a prateleira existe
    try { 
        // Chamar o método de justificação de produtos no gondolaStore
        await gondolaStore.justifyProducts(alignment);
    } catch (error) {
        console.error('Erro ao justificar produtos:', error);
    }
};
</script>

<template>
    <!-- Cabeçalho Fixo com Controles -->
    <div class="sticky top-0 z-50 border-b bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="p-4">
            <div class="flex items-center justify-between">
                <!-- Controles de Visualização e Filtros -->
                <div class="flex flex-col items-center space-x-2 md:flex-row">
                    <!-- Label Dimensões (Poderia vir do store agora) -->
                    <h3 class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300">
                        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"
                            />
                        </svg>
                        {{ currentGondola?.name || 'Gôndola' }}
                        <!-- Exibe nome da gôndola do store -->
                    </h3>

                    <!-- Controle de Escala -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm text-gray-600 dark:text-gray-400">Escala:</label>
                        <div class="flex items-center space-x-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                @click="updateScale(scaleFactor - 1)"
                                class="h-8 w-8 !p-1 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                                :disabled="scaleFactor <= 2"
                            >
                                <Minus class="h-4 w-4" />
                            </Button>
                            <span class="w-8 text-center text-sm font-medium text-gray-700 dark:text-gray-300">{{ scaleFactor }}x</span>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                @click="updateScale(scaleFactor + 1)"
                                class="h-8 w-8 !p-1 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                                :disabled="scaleFactor >= 10"
                            >
                                <Plus class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- Botão de Toggle Grid -->
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        @click="toggleGrid()"
                        class="ml-4 h-8 w-8 !p-1 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        :class="{ 'bg-gray-100 dark:bg-gray-700': showGrid }"
                        aria-label="Mostrar/Esconder Grade"
                    >
                        <Grid class="h-4 w-4" />
                    </Button>

                    <!-- Botão de Justificação de produtos align-vertical-justify-left-->
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        @click="justifyProducts('left')"
                        class="h-8 w-8 !p-1 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        aria-label="Justificar Produtos Verticalmente"
                    >
                        <AlignHorizontalJustifyStart class="h-4 w-4" />
                    </Button>
                    <!-- Botão de Justificação de produtos align-horizontal-justify-center-->
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        @click="justifyProducts('center')"
                        class="h-8 w-8 !p-1 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        aria-label="Justificar Produtos"
                    >
                        <AlignHorizontalJustifyCenter class="h-4 w-4" />
                    </Button>
                    <!-- Botão de Justificação de produtos align-vertical-justify-right-->
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        @click="justifyProducts('right')"
                        class="h-8 w-8 !p-1 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        aria-label="Justificar Produtos Verticalmente"
                    >
                        <AlignHorizontalJustifyEnd class="h-4 w-4" />
                    </Button>

                    <!-- Filtro de Categoria (condicional) -->
                    <div class="flex items-center space-x-2" v-if="categories.length > 0">
                        <label class="text-sm text-gray-600 dark:text-gray-400">Filtros:</label>
                        <Popover @clear-filters="clearCategoryFilter" :has-active-filters="!!filters.category">
                            <!-- Componente Category para seleção -->
                            <Category
                                class="w-full"
                                :categories="categories"
                                v-model="filters.category"
                                @update:model-value="selectCategory"
                                :clearable="true"
                            />
                        </Popover>
                    </div>
                </div>

                <!-- Botões de Ação (verificar se currentGondola existe para habilitar/mostrar) -->
                <div class="flex items-center space-x-3" v-if="currentGondola">
                    <!-- Botão de Ação (para prateleiras) -->
                    <Button
                        v-if="shelfSelected"
                        type="button"
                        variant="outline"
                        size="icon"
                        @click="confirmRemoveShelf"
                        class="h-8 w-8 !p-1 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        :class="{ 'bg-gray-100 dark:bg-gray-700': shelfSelected }"
                        aria-label="Selecionar Prateleiras"
                    >
                        <Trash2 class="h-4 w-4" />
                        <span class="sr-only">Remover Prateleira</span>
                    </Button>
                    <!-- Botão para inverter ordem das seções -->
                    <Button
                        type="button"
                        variant="secondary"
                        v-if="sections.length > 1"
                        @click="invertSectionOrder"
                        class="flex items-center dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        aria-label="Inverter Ordem das Seções"
                    >
                        <ArrowLeftRight class="mr-1 h-4 w-4" />
                        <span class="hidden md:block">Inverter Ordem</span>
                    </Button>

                    <!-- Botão para adicionar seção/módulo -->
                    <Button
                        type="button"
                        variant="secondary"
                        class="flex items-center dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        @click="navigateToAddSection"
                        aria-label="Adicionar Seção"
                    >
                        <Plus class="mr-1 h-4 w-4" />
                        <span class="hidden md:block">Adicionar Seção</span>
                    </Button>

                    <!-- Botão para remover gôndola -->
                    <Button type="button" variant="destructive" class="flex items-center" @click="confirmRemoveGondola" aria-label="Remover Gôndola">
                        <Trash2 class="mr-1 h-4 w-4" />
                        <span class="hidden md:block">Remover Gôndola</span>
                    </Button>
                </div>
            </div>
        </div>
        <ConfirmModal
            :isOpen="showDeleteConfirm.some((item) => item.gondola)"
            @update:isOpen="(isOpen) => !isOpen && (showDeleteConfirm = [])"
            title="Excluir gondola"
            message="Tem certeza que deseja a gondola? Esta ação não pode ser desfeita."
            confirmButtonText="Excluir"
            cancelButtonText="Cancelar"
            :isDangerous="true"
            @confirm="confirmDeleteGondola"
            @cancel="cancelDelete"
        />

        <ConfirmModal
            :isOpen="showDeleteConfirm.some((item) => item.shelf)"
            @update:isOpen="(isOpen) => !isOpen && (showDeleteConfirm = [])"
            title="Excluir produto"
            message="Tem certeza que deseja excluir este produto? Esta ação não pode ser desfeita."
            confirmButtonText="Excluir"
            cancelButtonText="Cancelar"
            :isDangerous="true"
            @confirm="confirmDeleteShelf"
            @cancel="cancelDelete"
        />
    </div>
</template>
