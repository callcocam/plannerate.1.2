<template>
    <ContextMenu>
        <ContextMenuTrigger>
            <ShelfContent :shelf="shelf" :sorted-shelves="sortedShelves" :index="index" :scale-factor="scaleFactor"
                :section="section" :segment-dragging="segmentDragging" :dragging-segment="draggingSegment"
                />

            <!-- Overlay para quando um segment está sendo arrastado -->
            <div class="segment-drag-overlay absolute inset-0 pointer-events-none"
                :class="{ 'active': segmentDragging }" :style="segmentOverlayStyle" v-if="segmentDragging"></div>
            <!-- Texto para quando um segment está sendo arrastado -->
            <span
                class="text-center text-gray-800 dark:text-gray-200 pointer-events-none font-bold absolute inset-0 flex items-center justify-center z-20 text-xs segment-drag-text"
                :class="{ 'active': segmentDragging }" :style="segmentOverlayStyle" v-if="segmentDragging">
                {{ segmentDragText }}
            </span>

            <div class="shelf relative flex flex-col items-center text-gray-50 shadow-md" :class=   "{
                'border-2 border-blue-800 border-dashed bg-gray-500': isSelected, // Selecionado
                'border-2 border-transparent dark:border-gray-700 border-dashed': !isSelected, // Não selecionado - Borda transparente
                // Fundo para não selecionado (sempre normal neste componente):
                'bg-gray-400 dark:bg-gray-700': !isSelected && shelf.product_type === 'hook',
                'bg-gray-700 dark:bg-gray-800': !isSelected && shelf.product_type === 'normal'
            }" :style="shelfStyle" ref="shelfElement">
                <draggable v-model="sortableSegments" item-key="id" handle=".drag-segment-handle"
                    class="relative flex w-full" :class="{
                        'items-start': shelf.product_type === 'hook',
                        'items-end': shelf.product_type !== 'hook',
                        'justify-center': alignment === 'center',
                        'justify-start': alignment === 'left',
                        'justify-end': alignment === 'right',
                        'justify-evenly': alignment === 'justify',
                    }" :style="segmentsContainerStyle">
                    <template #item="{ element: segment }">
                        <Segment :key="segment.id" :shelf="shelf" :segment="segment" :scale-factor="scaleFactor"
                            :section-width="sectionWidth" :gondola="gondola"
                            :is-segment-dragging="segmentDragging && draggingSegment?.id === segment.id"
                            @drop-product="(product: Product) => $emit('drop-product', product)"
                            @drop-products-multiple="(products: Product[]) => $emit('drop-products-multiple', products)"
                            @drop-segment-copy="(segment: SegmentType) => $emit('drop-segment-copy', segment)"
                            @drop-segment="(segment: SegmentType) => $emit('drop-segment', segment)"
                            @segment-drag-start="handleSegmentDragStart"
                            @segment-drag-over="handleSegmentDragOver" />
                    </template>
                </draggable>
                <ShelfControls :shelf="shelf" :scale-factor="scaleFactor" :section-width="sectionWidth"
                    :section-height="sectionHeight" :shelf-element="shelfElement" :base-height="baseHeight"
                    :sections-container="sectionsContainer" :section-index="sectionIndex" :hole-width="holeWidth"
                    :index="index" :totalItems="sortedShelves.length" />
            </div>
        </ContextMenuTrigger>
        <ContextMenuContent class="w-64">
            <ContextMenuRadioGroup model-value="modulos">
                <ContextMenuLabel inset> Prateleiras </ContextMenuLabel>
                <ContextMenuSeparator />
                <ContextMenuItem inset @click="selectShelfClick">
                    Editar
                    <ContextMenuShortcut>⌘]</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem inset @click="invertSegments" :disabled="shelf.segments.length < 2">
                    Inverter ({{ shelf.segments.length }})
                    <ContextMenuShortcut>⌘⇧I</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuSeparator />
                <ContextMenuItem inset @click="deleteShelf">
                    Excluir
                    <ContextMenuShortcut>⌘D</ContextMenuShortcut>
                </ContextMenuItem>
            </ContextMenuRadioGroup>
        </ContextMenuContent>
    </ContextMenu>
</template>

<script setup lang="ts">
import { computed, defineEmits, defineProps, onMounted, onUnmounted, ref, nextTick, type CSSProperties } from 'vue';
import draggable from 'vuedraggable';
import { useEditorStore } from '@plannerate/store/editor';
import { type Product, type Segment as SegmentType } from '@plannerate/types/segment';
import { type Shelf } from '@plannerate/types/shelves';
import Segment from './Segment.vue';
import ShelfContent from './ShelfContent.vue';
import ShelfControls from './ShelfControls.vue';
import { Section } from '@/types/sections';
import { Gondola } from '@plannerate/types/gondola';

// Definir Props
const props = defineProps<{
    gondola: Gondola;
    shelf: Shelf;
    sortedShelves: Shelf[];
    index: number;
    section: Section;
    scaleFactor: number;
    sectionWidth: number;
    sectionHeight: number;
    baseHeight: number;
    sectionsContainer: HTMLElement | null;
    sectionIndex: number;
    holes: { position: number;[key: string]: any }[];
}>();

const shelfElement = ref<HTMLElement | null>(null);

// Variáveis para controlar o drag do segment
const segmentDragging = ref(false);
const draggingSegment = ref<SegmentType | null>(null);

// Variáveis para o overlay do segment
const segmentDragText = ref(`Arrastando Prateleira (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`);

// Estilo para o overlay do segment
const segmentOverlayStyle = computed(() => {
    const currentShelf = props.shelf;
    const currentIndex = props.index;
    const sortedShelves = props.sortedShelves;
    const scaleFactor = props.scaleFactor;
    const sectionHeight = props.section.height;

    // --- Definir Padding Visual (em pixels) ---
    const verticalPaddingPx = 8; // Ex: 4px total (2px topo, 2px baixo)
    const topPaddingPx = verticalPaddingPx / 2;
    const bottomPaddingPx = verticalPaddingPx / 2;
    const minTopHeightPx = 120; // Altura mínima para a primeira prateleira

    // --- Calcular Posição e Altura em CM ---
    let topPositionCm: number;
    let rawHeightCm: number; // Altura bruta do espaço

    if (currentIndex === 0) {
        topPositionCm = 0;
        rawHeightCm = Math.max(0, currentShelf.shelf_position);
    } else {
        const previousShelf = sortedShelves[currentIndex - 1];
        topPositionCm = Math.max(0, previousShelf.shelf_position);
        rawHeightCm = Math.max(0, currentShelf.shelf_position - previousShelf.shelf_position);
    }

    // Garante que não ultrapasse a altura da seção
    if (topPositionCm + rawHeightCm > sectionHeight) {
        rawHeightCm = Math.max(0, sectionHeight - topPositionCm);
    }

    // --- Converter para Pixels ---
    let topPx = topPositionCm * scaleFactor + props.shelf.shelf_height * scaleFactor;
    let heightPx = rawHeightCm * scaleFactor - props.shelf.shelf_height * scaleFactor;

    // --- Aplicar Ajustes de Padding e Altura Mínima ---
    let otherStyles = {}
    // 1. Altura mínima para a primeira prateleira
    if (currentIndex === 0) {
        heightPx = Math.max(minTopHeightPx, heightPx);
        // Para a primeira, o padding inferior é aplicado, mas o topo começa em 0
        heightPx = Math.max(props.shelf.shelf_position, heightPx - bottomPaddingPx);
        otherStyles = {
            transform: `translateY(-${heightPx}px)`
        }
        topPx = props.shelf.shelf_position * scaleFactor;
    } else {
        // Para as demais, aplica padding no topo e embaixo
        topPx += topPaddingPx; // Desce o topo um pouco
        heightPx = Math.max(0, heightPx - topPaddingPx - bottomPaddingPx); // Reduz altura pelos dois paddings
    }

    // Garantir altura mínima quando segment está sendo arrastado
    const finalHeightPx = segmentDragging.value ? Math.max(heightPx, 50) : heightPx;

    return {
        width: '100%',
        height: `${finalHeightPx}px`,
        top: `${topPx}px`,
        left: '0',
        position: 'absolute',
        zIndex: 999999,
        pointerEvents: 'none',
        ...otherStyles,
    } as CSSProperties;
});

const gondolaId = computed(() => props.gondola.id);
const holeWidth = computed(() => props.section.hole_width);
// Definir Emits
const emit = defineEmits(['drop-product', 'drop-products-multiple', 'drop-segment-copy', 'drop-segment']);
const editorStore = useEditorStore();

// Corrigido: Determina o alinhamento efetivo da prateleira seguindo a hierarquia
const alignment = computed(() => {
    // 1. Prioridade: Alinhamento da própria prateleira   
    if (editorStore.getCurrentGondola?.alignment !== undefined && editorStore.getCurrentGondola?.alignment !== null) {
        return editorStore.getCurrentGondola?.alignment;
    }
    // 2. Padrão: Se nenhum estiver definido (considerando 'justify' como padrão implícito ou ausência de alinhamento)
    return 'justify'; // Ou retorne undefined/null se preferir tratar ausência explicitamente
});

const isSelected = computed(() => {
    return editorStore.getSelectedShelf?.id === props.shelf.id;
});
// --- Computeds para Estilos ---
const shelfStyle = computed(() => {
    // Posição "ideal" ou "arrastada" da prateleira (antes do snap)
    const targetShelfPosition = props.shelf.shelf_position;

    // Encontrar a posição do furo mais próximo
    let closestHolePosition = targetShelfPosition; // Default para a posição original
    if (props.holes && props.holes.length > 0) {
        let minDifference = Infinity;

        for (const hole of props.holes) {
            // Certificar que hole.position é um número antes de calcular
            if (typeof hole.position === 'number') {
                const currentDifference = Math.abs(targetShelfPosition - hole.position);
                if (currentDifference < minDifference) {
                    minDifference = currentDifference;
                    closestHolePosition = hole.position - props.shelf.shelf_height / 4;
                }
            } else {
                console.warn('Invalid hole position detected:', hole);
            }
        }
        // Opcional: Adicionar um console.log para ver o snap
        // console.log(`Shelf ${props.shelf.id} - Target: ${targetShelfPosition.toFixed(1)}, Snapped to Hole: ${closestHolePosition.toFixed(1)}`);
    }

    // Calcular a posição TOP final usando a posição do furo mais próximo
    const snappedTopPosition = closestHolePosition * props.scaleFactor;
    // Não adiciona mais verticalOffset
    const finalTopPosition = snappedTopPosition;

    // Calcular estilo de movimento horizontal (se existir)
    const moveStyle: Record<string, string> = {};
    if (props.shelf?.shelf_x_position !== undefined) {
        const leftPosition = props.shelf.shelf_x_position;
        moveStyle['left'] = `${leftPosition}px`;
    }

    // Retornar o objeto de estilo completo
    return {
        position: 'absolute' as const,
        left: '-4px',
        width: `${props.sectionWidth * props.scaleFactor + 4}px`,
        height: `${props.shelf.shelf_height * props.scaleFactor}px`,
        top: `${finalTopPosition}px`, // Usa a posição sem offset
        zIndex: '0',
        ...moveStyle,
    } as CSSProperties;
});

// --- Lógica de Drag and Drop (para produtos) ---
/**
 * Referência local aos segmentos para o draggable
 * Aplica ordenamento e garante IDs para todos os segmentos
 */
const sortableSegments = computed<SegmentType[]>({
    get() {
        return props.shelf.segments || [];
    },
    set(newSegments: SegmentType[]) {
        if (!gondolaId.value || !props.shelf.section_id || !props.shelf.id) {
            console.log('sortableSegments.set: IDs faltando (gondola, section, ou shelf).');
            return;
        }

        const reorderedSegments = newSegments.map((segment, index) => ({
            ...segment,
            ordering: index + 1,
        }));

        editorStore.setShelfSegmentsOrder(
            gondolaId.value,
            props.shelf.section_id,
            props.shelf.id,
            reorderedSegments
        );
    },
});

/**
 * Computed property para estilo do container de segmentos
 * Define a altura baseada na altura da prateleira
 */
const segmentsContainerStyle = computed(() => {
    return {
        height: `${props.shelf.shelf_height * props.scaleFactor}px`,
    };
});

const selectShelfClick = (event: any) => {
    event.stopPropagation(); // Impede que o clique se propague para elementos pais
    editorStore.clearSelectedSection(); // Limpa seleção de camadas ao selecionar prateleira 
    if (event.target?.hasAttribute('src')) {
        editorStore.clearSelectedShelf(); // Limpa seleção de prateleira ao selecionar produto   
    } else {
        editorStore.setSelectedShelf(props.shelf);
        editorStore.setIsShelfEditing(true);
        editorStore.clearLayerSelection(); // Limpa seleção de camadas ao selecionar prateleira     
    }

};

const controlDeleteShelf = (event: KeyboardEvent) => {
    if ((event.key === 'Delete' || event.key === 'Backspace')) {
        event.preventDefault();
        editorStore.removeShelfFromSection(gondolaId.value, props.shelf.section_id, props.shelf.id);
    }
};

const globalKeyHandler = (event: KeyboardEvent) => {
    if (editorStore.getSelectedShelf && editorStore.getSelectedShelf?.id === props.shelf.id) {
        if (editorStore.getSelectedLayerIds.size === 0) {
            controlDeleteShelf(event);
        }
    }
};

const deleteShelf = () => {
    editorStore.removeShelfFromSection(gondolaId.value, props.shelf.section_id, props.shelf.id);
};

const invertSegments = () => {
    const invertedSegments = [...sortableSegments.value].reverse();
    sortableSegments.value = invertedSegments;
};

// Handlers para eventos de drag do segment
const handleSegmentDragStart = (segment: SegmentType) => {
    // NÃO ativar overlay na prateleira de origem
    // O overlay só deve aparecer quando estiver sobre outras prateleiras
    segmentDragging.value = false;
    draggingSegment.value = segment;
};


// Handler global para resetar o estado quando qualquer drag termina
const handleGlobalDragEnd = async () => {
    // Resetar sempre que qualquer drag terminar
    segmentDragging.value = false;
    draggingSegment.value = null;

    // Forçar atualização do DOM
    await nextTick();
};

// Handler global para resetar o estado quando qualquer drop ocorrer
const handleGlobalDrop = async () => {
    // Resetar sempre que qualquer drop ocorrer
    segmentDragging.value = false;
    draggingSegment.value = null;

    // Forçar atualização do DOM
    await nextTick();
};

const handleSegmentDragOver = (segment: SegmentType, isOver: boolean) => {
    if (isOver) {
        // Só ativar se não estiver já ativo para evitar múltiplas ativações
        if (!segmentDragging.value) {
            segmentDragging.value = true;
            draggingSegment.value = segment;
        }
    } else {
        // Só resetar se estiver ativo para evitar múltiplos resets
        if (segmentDragging.value) {
            segmentDragging.value = false;
            draggingSegment.value = null;
        }
    }
};

onMounted(() => {
    if (shelfElement.value) {
        shelfElement.value.addEventListener('click', selectShelfClick);
    }

    document.addEventListener('keydown', globalKeyHandler);
    // Listener global para resetar estado de drag quando qualquer drag termina
    document.addEventListener('dragend', handleGlobalDragEnd);
    // Listener global para resetar estado de drag quando qualquer drop ocorrer
    document.addEventListener('drop', handleGlobalDrop);
});

onUnmounted(() => {
    if (shelfElement.value) {
        shelfElement.value.removeEventListener('click', selectShelfClick);
    }

    document.removeEventListener('keydown', globalKeyHandler);
    // Remover listener global de dragend
    document.removeEventListener('dragend', handleGlobalDragEnd);
    // Remover listener global de drop
    document.removeEventListener('drop', handleGlobalDrop);
});
</script>

<style scoped>
.shelf-container {
    transition: border-color 0.2s ease-in-out;
}

.shelf-container.drag-over {
    border-color: rgba(59, 130, 246, 0.5);
}

.drag-over {
    background-color: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.5);
    border-width: 2px;
    border-style: dashed;
    border-radius: 4px;
    box-shadow: 0 0 10px rgba(59, 130, 246, 0.5);
    transition:
        border-color 0.2s ease-in-out,
        background-color 0.2s ease-in-out;
    padding: 0 0 30px 0;
}

/* Overlay para quando um segment está sendo arrastado */
.segment-drag-overlay {
    background-color: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.5);
    border-width: 2px;
    border-style: dashed;
    border-radius: 4px;
    box-shadow: 0 0 10px rgba(59, 130, 246, 0.5);
    transition:
        opacity 0.2s ease-in-out,
        border-color 0.2s ease-in-out,
        background-color 0.2s ease-in-out;
    cursor: grab;
    opacity: 0;
    pointer-events: none;
    z-index: 999999 !important;
    /* Z-index extremamente alto */
}

.segment-drag-overlay.active {
    opacity: 1;
    pointer-events: none;
}

/* Texto durante drag do segment */
.segment-drag-text {
    opacity: 0;
    transition: opacity 0.2s ease-in-out;
}

.segment-drag-text.active {
    opacity: 1;
}
</style>

