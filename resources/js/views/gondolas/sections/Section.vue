<template>
    <ContextMenu>
        <ContextMenuTrigger>
            <div class="bg-gray-800" :style="sectionStyle" :data-section-id="section.id"
                @dragover.prevent="handleSectionDragOver" @drop.prevent="handleSectionDrop"
                @dragleave="handleSectionDragLeave" ref="sectionRef">
                <!-- Conteúdo da Seção (Prateleiras) -->
                <ShelfComponent v-for="shelf in section.shelves" :key="shelf.id" :shelf="shelf" :gondola-id="gondolaId"
                    :scale-factor="scaleFactor" :section-width="section.width" :section-height="section.height"
                    :base-height="baseHeight" :sections-container="sectionsContainer" :section-index="sectionIndex"
                    @drop-product="handleProductDropOnShelf" @drop-layer-copy="handleLayerCopy"
                    @drag-shelf="handleShelfDragStart" @drop-layer="updateLayer" />
            </div>
        </ContextMenuTrigger>
        <ContextMenuContent class="w-64">
            <ContextMenuRadioGroup model-value="modulos">
                <ContextMenuLabel inset> Modulos </ContextMenuLabel>
                <ContextMenuSeparator />
                <ContextMenuItem inset @click="editSection">
                    Editar
                    <ContextMenuShortcut>⌘E</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem inset @click="addShelf">
                    Adicionar prateleira
                    <ContextMenuShortcut>⌘A</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem inset @click="inverterModule">
                    Inverter ordem
                    <ContextMenuShortcut>⌘I</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuSeparator />
                <ContextMenuSub>
                    <ContextMenuSubTrigger inset> Alinhamento </ContextMenuSubTrigger>
                    <ContextMenuSubContent class="w-48">
                        <ContextMenuItem inset @click="() => justifyModule('left')">
                            à esquerda
                            <ContextMenuShortcut>⌘⇧L</ContextMenuShortcut>
                        </ContextMenuItem>
                        <ContextMenuItem inset @click="() => justifyModule('justify')">
                            ao centro
                            <ContextMenuShortcut>⌘⇧C</ContextMenuShortcut>
                        </ContextMenuItem>
                        <ContextMenuItem inset @click="() => justifyModule('right')">
                            à direita
                            <ContextMenuShortcut>⌘⇧R</ContextMenuShortcut>
                        </ContextMenuItem>
                    </ContextMenuSubContent>
                </ContextMenuSub>
                <ContextMenuSeparator />
                <ContextMenuItem inset disabled>
                    Excluir
                    <ContextMenuShortcut>⌘D</ContextMenuShortcut>
                </ContextMenuItem>
            </ContextMenuRadioGroup>
        </ContextMenuContent>
    </ContextMenu>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { useProductStore } from '@plannerate/store/product';
import { useSectionStore } from '@plannerate/store/section';
import { useShelvesStore } from '@plannerate/store/shelves';
import { useEditorStore } from '@plannerate/store/editor';
import { type Shelf as ShelfType } from '@plannerate/types/shelves';
import { Section } from '@plannerate/types/sections';
import { Layer, Product, Segment } from '@plannerate/types/segment';
import ShelfComponent from './Shelf.vue';
import { useToast } from '@plannerate/components/ui/toast';

// ------- PROPS & EMITS -------
const props = defineProps<{
    gondolaId: string | undefined;
    section: Section;
    scaleFactor: number;
    selectedCategory: any;
    sectionsContainer: HTMLElement | null;
    sectionIndex: number;
}>();

const emit = defineEmits(['update:segments']);

// ------- DESTRUCTURED PROPS FOR BETTER PERFORMANCE -------
// Previne acesso repetido às props nos computed
const { gondolaId, section, scaleFactor } = props;

// ------- STORES & SERVICES -------
const productStore = useProductStore();
const shelvesStore = useShelvesStore();
const sectionStore = useSectionStore();
const editorStore = useEditorStore();
const { toast } = useToast();

// ------- REFS -------
const dropTargetActive = ref(false);
const draggingShelf = ref<ShelfType | null>(null);
const sectionRef = ref<HTMLElement | null>(null);

// ------- COMPUTED -------
const baseHeight = computed(() => {
    const baseHeightCm = section.base_height || 0;
    return baseHeightCm <= 0 ? 0 : baseHeightCm * scaleFactor;
});

// Estilo da seção com CSS transformado via computed para melhorar performance
const sectionStyle = computed(() => {
    const isActive = dropTargetActive.value;
    return {
        width: `${section.width * scaleFactor}px`,
        height: `${section.height * scaleFactor}px`,
        position: 'relative' as const,
        borderWidth: '2px',
        borderStyle: isActive ? 'dashed' : 'solid',
        borderColor: isActive ? 'rgba(59, 130, 246, 0.5)' : 'transparent',
        backgroundColor: isActive ? 'rgba(59, 130, 246, 0.1)' : 'transparent',
        overflow: 'visible' as const,
        transition: 'border-color 0.2s ease-in-out, background-color 0.2s ease-in-out',
        willChange: isActive ? 'border-color, background-color' : 'auto'
    };
});

// ------- MÉTODOS - AÇÕES DE CONTEXTO -------
/**
 * Abre o modal de edição da seção
 */
const editSection = () => {
    sectionStore.setSelectedSection(section);
    sectionStore.startEditing();
};

/**
 * Adiciona uma nova prateleira no local do clique
 * @param event Evento do mouse
 */
const addShelf = (event: MouseEvent) => {
    shelvesStore.handleDoubleClick({
        shelf_position: event.offsetY / scaleFactor,
        section_id: section.id,
    });
    event.stopPropagation();
};

/**
 * Define o alinhamento do módulo
 * @param alignment Tipo de alinhamento ('left', 'justify', 'right')
 */
const justifyModule = (alignment: string) => {
    if (!gondolaId) {
        toast({
            title: 'Aviso',
            description: 'Não é possível justificar módulo: gondolaId não fornecido.',
            variant: 'default'
        });
        return;
    }
    editorStore.setSectionAlignment(gondolaId, section.id, alignment);
};

/**
 * Inverte a ordem das prateleiras no módulo
 */
const inverterModule = () => {
    if (!gondolaId) {
        toast({
            title: 'Aviso',
            description: 'Não é possível inverter prateleiras: gondolaId não fornecido.',
            variant: 'default'
        });
        return;
    }
    editorStore.invertShelvesInSection(gondolaId, section.id);
};

// ------- MÉTODOS - HELPERS -------
/**
 * Cria um novo segmento a partir de um produto
 * @param product Produto para criar o segmento
 * @param shelf Prateleira onde o segmento será adicionado
 * @param layerQuantity Quantidade de camadas
 * @returns Novo objeto Segment
 */
const createSegmentFromProduct = (product: Product, shelf: ShelfType, layerQuantity: number): Segment => {
    const timestamp = Date.now();
    const segmentId = `segment-${timestamp}-${shelf.segments?.length || 0}`;
    const layerId = `layer-${timestamp}-${product.id}`;

    return {
        id: segmentId,
        user_id: null,
        tenant_id: '',
        width: parseInt(section.width.toString()),
        ordering: (shelf.segments?.length || 0) + 1,
        quantity: 1,
        shelf_id: shelf.id,
        spacing: 0,
        position: 0,
        alignment: '',
        settings: null,
        status: 'published',
        layer: {
            id: layerId,
            product_id: product.id,
            product: product,
            quantity: layerQuantity || 1,
            status: 'published',
            height: product.height,
            segment_id: segmentId,
        }
    };
};

// ------- MÉTODOS - DRAG & DROP PRATELEIRAS -------
/**
 * Inicia o arrasto de uma prateleira
 * @param shelf Prateleira sendo arrastada
 */
const handleShelfDragStart = (shelf: ShelfType) => {
    draggingShelf.value = shelf;
};

/**
 * Gerencia o evento dragover na seção
 * @param event Evento de arrasto
 */
const handleSectionDragOver = (event: DragEvent) => {
    if (!event.dataTransfer) return;

    // Verifica se o que está sendo arrastado é uma prateleira
    const isShelf = event.dataTransfer.types.includes('text/shelf');

    if (isShelf) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        dropTargetActive.value = true;
    }
};

/**
 * Gerencia a saída do cursor da área de drop
 */
const handleSectionDragLeave = () => {
    dropTargetActive.value = false;
};

/**
 * Gerencia o drop de uma prateleira na seção
 * @param event Evento de drop
 */
const handleSectionDrop = async (event: DragEvent) => {
    if (!event.dataTransfer) return;
    const shelfData = event.dataTransfer.getData('text/shelf');

    if (!shelfData) {
        dropTargetActive.value = false;
        return;
    }

    try {
        const shelf = JSON.parse(shelfData) as ShelfType;
        const mouseY = event.offsetY;
        const newPosition = mouseY / scaleFactor;
        const shelfHeight = draggingShelf.value?.shelf_height || 0;

        // Verifica se a posição é válida
        if (newPosition >= 0 && newPosition <= section.height - shelfHeight) {
            if (!gondolaId) {
                toast({
                    title: 'Erro Interno',
                    description: 'Contexto da gôndola não encontrado.',
                    variant: 'destructive'
                });
                draggingShelf.value = null;
                dropTargetActive.value = false;
                return;
            }

            // Atualiza a posição da prateleira
            editorStore.setShelfPosition(gondolaId, section.id, shelf.id, {
                shelf_position: newPosition,
                shelf_x_position: -4
            });
        } else {
            toast({
                title: 'Aviso',
                description: 'Posição de prateleira inválida',
                variant: 'default',
            });
        }
    } catch (e) {
        console.error('Erro ao processar dados da prateleira no drop:', e);
        toast({
            title: 'Erro',
            description: 'Falha ao mover prateleira.',
            variant: 'destructive'
        });
    } finally {
        draggingShelf.value = null;
        dropTargetActive.value = false;
    }
};

// ------- MÉTODOS - EVENTOS DE PRODUTOS -------
/**
 * Gerencia o drop de um produto em uma prateleira
 * @param product Produto que foi solto
 * @param shelf Prateleira alvo
 */
const handleProductDropOnShelf = async (product: Product, shelf: ShelfType) => {
    // Verifica se o gondolaId está disponível
    if (!gondolaId) {
        toast({
            title: 'Erro Interno',
            description: 'Contexto da gôndola não encontrado.',
            variant: 'destructive'
        });
        return;
    }

    // Cria o novo segmento com ID temporário
    const newSegment = createSegmentFromProduct(product, shelf, 1);

    try {
        // Adiciona o segmento à prateleira
        editorStore.addSegmentToShelf(gondolaId, section.id, shelf.id, newSegment);
    } catch (error) {
        console.error('Erro ao adicionar produto/segmento ao editorStore:', error);
        const errorDesc = (error instanceof Error) ? error.message : 'Falha ao atualizar o estado do editor.';
        toast({
            title: 'Erro Interno',
            description: errorDesc,
            variant: 'destructive'
        });
    }
};

/**
 * Gerencia a cópia de uma camada para uma prateleira
 * @param layer Camada sendo copiada
 * @param shelf Prateleira alvo
 */
const handleLayerCopy = async (layer: Layer, shelf: ShelfType) => {
    // Verifica se o gondolaId está disponível
    if (!gondolaId) {
        toast({
            title: 'Erro Interno',
            description: 'Contexto da gôndola não encontrado.',
            variant: 'destructive'
        });
        return;
    }

    // Cria o novo segmento baseado na layer copiada
    const newSegment = createSegmentFromProduct(layer.product, shelf, layer.quantity);

    try {
        // Adiciona o segmento copiado à prateleira
        editorStore.addSegmentToShelf(gondolaId, section.id, shelf.id, newSegment);
    } catch (error) {
        console.error('Erro ao copiar camada/segmento para o editorStore:', error);
        const errorDesc = (error instanceof Error) ? error.message : 'Falha ao atualizar o estado do editor.';
        toast({
            title: 'Erro Interno',
            description: errorDesc,
            variant: 'destructive'
        });
    }
};

/**
 * Atualiza uma camada movendo-a para outra prateleira
 * @param layer Camada sendo movida
 * @param targetShelf Prateleira alvo
 */
const updateLayer = (layer: Layer, targetShelf: ShelfType) => {
    // Verifica se o segmento existe na layer
    const segmentToMove = layer.segment;
    if (!segmentToMove) {
        console.error('updateLayer: Objeto segment não encontrado na layer.');
        return;
    }

    // Obtém IDs e dados necessários
    const segmentId = segmentToMove.id;
    const oldShelfId = segmentToMove.shelf_id; // ID da prateleira de origem
    let oldSectionId = targetShelf.section_id; // ID da seção (será atualizado abaixo)

    // Encontra a seção original do segmento
    if (editorStore.currentState?.gondolas) {
        for (const gondola of editorStore.currentState.gondolas) {
            for (const section of gondola.sections) {
                if (section.shelves) {
                    for (const shelf of section.shelves) {
                        if (shelf.id === oldShelfId) {
                            oldSectionId = section.id;
                            break;
                        }
                    }
                }
            }
        }
    }

    const newShelfId = targetShelf.id; // ID da prateleira de destino
    const newSectionId = targetShelf.section_id; // ID da seção de destino

    // Verifica se todos os IDs essenciais foram obtidos
    if (!gondolaId || !oldSectionId || !oldShelfId || !newSectionId || !newShelfId || !segmentId) {
        console.error('updateLayer: IDs faltando para realizar a transferência.',
            { gondolaId, oldSectionId, oldShelfId, newSectionId, newShelfId, segmentId }
        );
        toast({
            title: 'Erro Interno',
            description: 'Dados insuficientes para mover o segmento.',
            variant: 'destructive'
        });
        return;
    }

    // Evita auto-transferência
    if (oldShelfId === newShelfId) {
        return;
    }

    // Transfere o segmento
    editorStore.transferSegmentBetweenShelves(
        gondolaId,
        oldSectionId,
        oldShelfId,
        newSectionId,
        newShelfId,
        segmentId
    );
};

// ------- MÉTODOS - EVENT HANDLERS GLOBAIS -------
/**
 * Gerencia teclas pressionadas globalmente
 * @param event Evento de teclado
 */
const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        productStore.clearSelection();
    }
};

/**
 * Gerencia cliques fora dos elementos selecionáveis
 * @param event Evento de clique
 */
const handleClickOutside = (event: MouseEvent) => {
    const clickedElement = event.target as HTMLElement;

    // Ignora cliques em elementos específicos
    if (clickedElement.closest('.border-destructive')) return;
    if (clickedElement.dataset.state) return;
    if (clickedElement.closest('.no-remove-properties')) return;

    // Limpa seleções com base no elemento clicado
    if (!clickedElement.closest('.layer')) {
        productStore.clearSelection();
    }

    if (!clickedElement.closest('.shelves')) {
        shelvesStore.clearSelection();
        shelvesStore.clearSelectedShelfIds();
    }

    if (!clickedElement.closest('.sections')) {
        sectionStore.setSelectedSection(null);
        sectionStore.clearSelectedSectionIds();
    }
};

/**
 * Gerencia duplo clique para adicionar prateleira
 * @param event Evento de duplo clique
 */
const handleDoubleClick = (event: MouseEvent) => {
    event.stopPropagation();
    shelvesStore.handleDoubleClick({
        shelf_position: event.offsetY / scaleFactor,
        section_id: section.id,
    });
};

// ------- LIFECYCLE HOOKS -------
onMounted(() => {
    // Adiciona event listeners globais
    window.addEventListener('keydown', handleKeydown, { passive: true });
    document.addEventListener('click', handleClickOutside, true);

    // Adiciona evento de duplo clique ao elemento da seção
    if (sectionRef.value) {
        sectionRef.value.addEventListener('dblclick', handleDoubleClick);
    }
});

onUnmounted(() => {
    // Remove event listeners globais
    window.removeEventListener('keydown', handleKeydown);
    document.removeEventListener('click', handleClickOutside, true);

    // Remove evento de duplo clique ao elemento da seção
    if (sectionRef.value) {
        sectionRef.value.removeEventListener('dblclick', handleDoubleClick);
    }
});
</script>

<style scoped>
.section-container>.absolute.bottom-0 {
    z-index: -1;
}

.section-drag-over {
    background-color: rgba(59, 130, 246, 0.05);
    border: 2px dashed rgba(59, 130, 246, 0.5);
}
</style>