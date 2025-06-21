<template>
    <div class="bg-gray-800" :style="sectionStyle" :data-section-id="section.id"
        @dragover.prevent="handleSectionDragOver" @drop.prevent="handleSectionDrop" @dragleave="handleSectionDragLeave"
        @dragstart="editorStore.disableDragging" @dragend="editorStore.enableDragging" ref="sectionRef">
        <!-- Conteúdo da Seção (Prateleiras) -->
        <slot />
        <template v-for="(shelf, index) in sortedShelves" :key="shelf.id">
            <ShelfComponent :shelf="shelf" :gondola="gondola" :sorted-shelves="sortedShelves" :index="index"
                :section="section" :scale-factor="scaleFactor" :section-width="section.width"
                :section-height="section.height" :base-height="baseHeight" :sections-container="sectionsContainer"
                :section-index="sectionIndex" :holes="holes" :invert-index="invertIndex" @drop-product="handleProductDropOnShelf"
                @drop-segment-copy="handleSegmentCopy" @drop-segment="updateSegment"
                @drag-shelf="handleShelfDragStart" />
        </template>
        <div class="text-black text-xs absolute bottom-5 left-1/2 -translate-x-1/2 p-2 dark:text-white uppercase font-bold" :style="{
            bottom: `${baseHeight / 2}px`
        }">
            Módulo {{ invertIndex + 1 }}
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';

import { useEditorStore } from '@plannerate/store/editor';
import { Section } from '@plannerate/types/sections';
import { Layer, Product, Segment } from '@plannerate/types/segment';
import { type Shelf as ShelfType } from '@plannerate/types/shelves';
import { Gondola } from '@plannerate/types/gondola';
import { validateShelfWidth } from '@plannerate/utils/validation';
import { toast } from 'vue-sonner';
import ShelfComponent from './shelves/Shelf.vue';

// ------- PROPS & EMITS -------
const props = defineProps<{
    gondola: Gondola;
    section: Section;
    scaleFactor: number;
    sectionsContainer: HTMLElement | null;
    sectionIndex: number;
}>();

const emit = defineEmits(['update:segments']);

// ------- DESTRUCTURED PROPS FOR BETTER PERFORMANCE -------
// Previne acesso repetido às props nos computed
const { gondola, section } = props;

// ------- STORES & SERVICES -------
const editorStore = useEditorStore();

const holes = computed(() => {
    if (!section.settings) return [];
    return section.settings.holes;
});
const invertIndex = computed(() =>{ 
    if (props.gondola.flow === 'left_to_right') {   
        const inverted = props.sectionIndex;
        return inverted;
    } else {
        const inverted = props.gondola.sections.length - 1 - props.sectionIndex;
        return inverted;
    }  
});
// Ordena as prateleiras por posição para garantir o cálculo correto
const sortedShelves = computed(() => {
    if (!props.section.shelves || props.section.shelves.length === 0) {
        return [];
    }
    return [...props.section.shelves].sort((a, b) => a.shelf_position - b.shelf_position);
});
// ------- REFS -------
const dropTargetActive = ref(false);
const draggingShelf = ref<ShelfType | null>(null);
const sectionRef = ref<HTMLElement | null>(null);

// ------- COMPUTED -------
const baseHeight = computed(() => {
    const baseHeightCm = section.base_height || 0;
    return baseHeightCm <= 0 ? 0 : baseHeightCm * props.scaleFactor;
});

// Estilo da seção com CSS transformado via computed para melhorar performance
const sectionStyle = computed(() => {
    const isActive = dropTargetActive.value;
    return {
        width: `${section.width * props.scaleFactor}px`,
        height: `${section.height * props.scaleFactor}px`,
        position: 'relative' as const,
        borderWidth: '2px',
        borderStyle: isActive ? 'dashed' : 'solid',
        borderColor: isActive ? 'rgba(59, 130, 246, 0.5)' : 'transparent',
        backgroundColor: isActive ? 'rgba(59, 130, 246, 0.1)' : 'transparent',
        overflow: 'visible' as const,
        transition: 'border-color 0.2s ease-in-out, background-color 0.2s ease-in-out',
        willChange: isActive ? 'border-color, background-color' : 'auto',
    };
});

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
        tabindex: (shelf.segments?.length || 0) + 1,
        layer: {
            id: layerId,
            product_id: product.id,
            product: product,
            quantity: layerQuantity || 1,
            status: 'published',
            height: product.height,
            segment_id: segmentId,
            tabindex: 0,
        },
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
        const newPosition = mouseY / props.scaleFactor;
        const shelfHeight = draggingShelf.value?.shelf_height || 0;

        // Verifica se a posição é válida
        if (newPosition >= 0 && newPosition <= section.height - shelfHeight) {
            if (!gondola.id) {
                toast.error('Erro Interno', { description: 'Contexto da gôndola não encontrado.' });
                draggingShelf.value = null;
                dropTargetActive.value = false;
                return;
            }

            // Atualiza a posição da prateleira
            editorStore.setShelfPosition(gondola.id, section.id, shelf.id, {
                shelf_position: newPosition,
                shelf_x_position: -4,
            });
        } else {
            toast.error('Aviso', {
                description: `Posição de prateleira inválida. A posição deve estar entre 0 e ${section.height - shelfHeight}cm.`,
            });
        }
    } catch (e) {
        console.error('Erro ao processar dados da prateleira no drop:', e);
        toast.error('Erro', {
            description: 'Falha ao mover prateleira.',
        });
    } finally {
        draggingShelf.value = null;
        dropTargetActive.value = false;
    }
};

// ------- MÉTODOS - DRAG & DROP PRODUTOS/CAMADAS -------
/**
 * Gerencia o drop de um produto em uma prateleira
 * @param product Produto sendo dropado
 * @param shelf Prateleira alvo
 * @param dropPosition Posição do drop (pode ser usado para calcular a posição X inicial)
 */
const handleProductDropOnShelf = async (product: Product, shelf: ShelfType) => {
    if (!gondola.id) {
        toast.error('Erro Interno', {
            description: 'Contexto da gôndola não encontrado.',
        });
        return;
    }

    // Criar camada temporária para validação
    // Usar spacing padrão 0, pois spacing vem da Layer, não do Product.
    const tempLayer: Layer = {
        id: `temp-layer-${Date.now()}`,
        product_id: product.id,
        product: product,
        quantity: 1, // Validando para quantidade 1
        status: 'temp',
        height: product.height,
        segment_id: 'temp',
        spacing: 0, // <-- Definir spacing padrão 0 aqui
        tabindex: 0,
    };

    // *** Validação ***
    const validation = validateShelfWidth(shelf, section.width, null, 0, tempLayer);

    if (!validation.isValid) {
        toast.error('Limite de Largura Excedido', {
            description: `Adicionar este produto excederia a largura da seção (${section.width}cm). Largura resultante: ${validation.totalWidth.toFixed(1)}cm`,
        });
        return;
    }
    // *** Fim Validação ***

    // Prossegue se válido
    const newSegment = createSegmentFromProduct(product, shelf, 1);
    // TODO: Calcular newSegment.position baseado em dropPosition se necessário
    // TODO: Permitir definir o SPACING da nova layer/segmento aqui?
    //      (newSegment atualmente não define spacing na layer criada)

    try {
        editorStore.addSegmentToShelf(gondola.id, section.id, shelf.id, newSegment);
    } catch (error) {
        console.error('Erro ao adicionar produto/segmento ao editorStore:', error);
        const errorDesc = error instanceof Error ? error.message : 'Falha ao atualizar o estado do editor.';
        toast.error('Erro Interno', {
            description: errorDesc,
        });
    }
};

/**
 * Gerencia a cópia de uma camada para uma prateleira
 * @param segment Segmento sendo copiado
 * @param shelf Prateleira alvo
 */
const handleSegmentCopy = async (segment: Segment, shelf: ShelfType) => {
    if (!gondola.id) {
        toast({
            title: 'Erro Interno',
            description: 'Contexto da gôndola não encontrado.',
            variant: 'destructive',
        });
        return;
    }

    // *** Validação ***
    const validation = validateShelfWidth(shelf, section.width, null, 0, segment.layer);

    if (!validation.isValid) {
        toast.error('Limite de Largura Excedido', {
            description: `Copiar este segmento excederia a largura da seção (${section.width}cm). Largura resultante: ${validation.totalWidth.toFixed(1)}cm`,
        });
        return;
    }
    // *** Fim Validação ***

    // Prossegue se válido
    const newSegment = createSegmentFromProduct(segment.layer.product, shelf, segment.layer.quantity);
    try {
        editorStore.addSegmentToShelf(gondola.id, section.id, shelf.id, newSegment);
    } catch (error) {
        console.error('Erro ao copiar camada/segmento para o editorStore:', error);
        const errorDesc = error instanceof Error ? error.message : 'Falha ao atualizar o estado do editor.';
        toast.error('Erro Interno', {
            description: errorDesc,
        });
    }
};

/**
 * Atualiza uma camada movendo-a para outra prateleira
 * @param Segment Camada sendo movida
 * @param targetShelf Prateleira alvo
 */
const updateSegment = (segment: Segment, targetShelf: ShelfType) => {
    const segmentToMove = segment;
    if (!segmentToMove) {
        console.error('updateLayer: Objeto segment não encontrado na layer.');
        return;
    }

    const segmentId = segmentToMove.id;
    const oldShelfId = segmentToMove.shelf_id;
    const newShelfId = targetShelf.id;
    const newSectionId = targetShelf.section_id;

    // Encontrar oldSectionId ... (lógica existente)
    let oldSectionId = targetShelf.section_id;
    if (editorStore.currentState?.gondolas) {
        for (const g of editorStore.currentState.gondolas) {
            if (g.id === gondola.id) {
                for (const s of g.sections) {
                    if (s.shelves?.some((sh) => sh.id === oldShelfId)) {
                        oldSectionId = s.id;
                        break;
                    }
                }
                break;
            }
        }
    }
    // Encontrar seção de destino para obter largura
    const destinationSection = editorStore.currentState?.gondolas.find((g) => g.id === gondola.id)?.sections.find((s) => s.id === newSectionId);

    if (!destinationSection) {
        console.error('updateLayer: Seção de destino não encontrada no editorStore.');
        toast.error('Erro Interno', { description: 'Seção de destino não encontrada.' });
        return;
    }

    if (oldShelfId === newShelfId) return; // Evita auto-transferência

    // *** Validação (na prateleira DESTINO) ***
    const validation = validateShelfWidth(targetShelf, destinationSection.width, null, 0, segmentToMove.layer);

    if (!validation.isValid) {
        toast.error('Limite de Largura Excedido', {
            description: `Mover este segmento excederia a largura da seção destino (${destinationSection.width}cm). Largura resultante: ${validation.totalWidth.toFixed(1)}cm`,
        });
        return;
    }
    // *** Fim Validação ***

    if (!gondola.id || !oldSectionId || !oldShelfId || !newSectionId || !newShelfId || !segmentId) {
        console.error('updateLayer: IDs faltando para realizar a transferência.', {
            gondolaId: gondola.id,
            oldSectionId,
            oldShelfId,
            newSectionId,
            newShelfId,
            segmentId,
        });
        toast({
            title: 'Erro Interno',
            description: 'Dados insuficientes para mover o segmento.',
            variant: 'destructive',
        });
        return;
    }

    editorStore.transferSegmentBetweenShelves(gondola.id, oldSectionId, oldShelfId, newSectionId, newShelfId, segmentId);
};

// ------- MÉTODOS - EVENT HANDLERS GLOBAIS -------
/**
 * Gerencia teclas pressionadas globalmente
 * @param event Evento de teclado
 */
const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        editorStore.clearLayerSelection();
    }
};

/**
 * Gerencia cliques fora dos elementos selecionáveis
 * @param event Evento de clique
 */
const handleClickOutside = (event: MouseEvent) => {
    const clickedElement = event.target as HTMLElement;

    // Ignora cliques diretos no html/body (ex: scrollbar)
    if (clickedElement === document.documentElement || clickedElement === document.body) {
        return;
    }

    // Ignora cliques dentro da sidebar de propriedades da seção
    if (clickedElement.closest('#section-properties-sidebar')) {
        return;
    }

    // Ignora cliques dentro do conteúdo teleportado de componentes Radix/Shadcn (ex: Select, Dropdown)
    if (clickedElement.closest('[data-radix-popper-content-wrapper], [role="listbox"]')) {
        return;
    }

    // Ignora cliques dentro do modal de edição de produto
    if (clickedElement.closest('[data-dismissable-layer]')) {
        return;
    }  
    // Ignora cliques dentro do modal de edição de produto
    if (clickedElement.closest('[data-state="open"]')) {
        return;
    } 

    // Ignora cliques em elementos específicos que não devem limpar seleções
    if (clickedElement.closest('.border-destructive, .no-remove-properties')) {
        return;
    }

    // Limpa seleções com base no elemento clicado
    if (!clickedElement.closest('.layer')) {
        editorStore.clearLayerSelection();
    }

    if (!clickedElement.closest('.shelves')) {
        editorStore.clearSelectedShelf();
    }

    if (!clickedElement.closest('.sections')) {
        editorStore.clearSelectedSection();
    }
};

/**
 * Gerencia duplo clique para adicionar prateleira
 * @param event Evento de duplo clique
 */
// const handleDoubleClick = (event: MouseEvent) => {
//     event.stopPropagation();
//     editorStore.addShelfToSection(gondola.id, section.id, {
//         id: `temp-shelf-${Date.now()}`,
//         shelf_height: 4,
//         shelf_position: event.offsetY / props.scaleFactor,
//         section_id: section.id,
//     } as ShelfType);
// };

// ------- LIFECYCLE HOOKS -------
onMounted(() => {
    // Adiciona event listeners globais
    window.addEventListener('keydown', handleKeydown, { passive: true });
    document.addEventListener('click', handleClickOutside, true);

    // Adiciona evento de duplo clique ao elemento da seção
    // if (sectionRef.value) {
    //     sectionRef.value.addEventListener('dblclick', handleDoubleClick);
    // }
});

onUnmounted(() => {
    // Remove event listeners globais
    window.removeEventListener('keydown', handleKeydown);
    document.removeEventListener('click', handleClickOutside, true);

    // Remove evento de duplo clique ao elemento da seção
    // if (sectionRef.value) {
    //     sectionRef.value.removeEventListener('dblclick', handleDoubleClick);
    // }
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
