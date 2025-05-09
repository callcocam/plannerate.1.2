<template>
    <ContextMenu>
        <ContextMenuTrigger>
            <ShelfContent :shelf="shelf" :sorted-shelves="sortedShelves" :index="index" :scale-factor="scaleFactor"
                :section="section"
                @drop-product="(product: Product, shelf: Shelf) => $emit('drop-product', product, shelf)"
                @drop-segment-copy="(segment: SegmentType, shelf: Shelf) => $emit('drop-segment-copy', segment, shelf)"
                @drop-segment="(segment: SegmentType, oldShelf: Shelf) => $emit('drop-segment', segment, oldShelf)" />
            <div class="shelf relative flex flex-col items-center text-gray-50 shadow-md" 
                 :class="{
                    'border-2 border-blue-800 border-dashed bg-gray-500': isSelected, // Selecionado
                    'border-2 border-transparent dark:border-gray-700 border-dashed': !isSelected, // Não selecionado - Borda transparente
                    // Fundo para não selecionado (sempre normal neste componente):
                    'bg-gray-400 dark:bg-gray-700': !isSelected && shelf.product_type === 'hook',
                    'bg-gray-700 dark:bg-gray-800': !isSelected && shelf.product_type === 'normal'
                 }" 
                 :style="shelfStyle" 
                 ref="shelfElement"> 
                <draggable v-model="sortableSegments" item-key="id" handle=".drag-segment-handle"
                    class="relative flex w-full" :class="{
                        'items-start': shelf.product_type === 'hook',
                        'items-end': shelf.product_type !== 'hook',
                        'justify-center': alignment === 'center',
                        'justify-start': alignment === 'left',
                        'justify-end': alignment === 'right',
                        'justify-between': alignment === 'justify',
                    }" :style="segmentsContainerStyle">
                    <template #item="{ element: segment }"> 
                         <Segment 
                            :key="segment.id" 
                            :shelf="shelf" 
                            :segment="segment" 
                            :scale-factor="scaleFactor"
                            :section-width="sectionWidth" 
                            :gondola="gondola" />
                    </template>
                </draggable>
                <ShelfControls :shelf="shelf" :scale-factor="scaleFactor" :section-width="sectionWidth"
                    :section-height="sectionHeight" :shelf-element="shelfElement" :base-height="baseHeight"
                    :sections-container="sectionsContainer" :section-index="sectionIndex" :hole-width="holeWidth"  :index="index" :totalItems="sortedShelves.length"/>
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
import { computed, defineEmits, defineProps, onMounted, onUnmounted, ref, type CSSProperties } from 'vue';
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

const gondolaId = computed(() => props.gondola.id);
const holeWidth = computed(() => props.section.hole_width); 
// Definir Emits
const emit = defineEmits(['drop-product', 'drop-segment-copy', 'drop-segment']);
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
        zIndex: '1',
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

const selectShelfClick = (event: MouseEvent) => {
    event.stopPropagation(); // Impede que o clique se propague para elementos pais
    editorStore.setSelectedShelf(props.shelf);
    editorStore.setIsShelfEditing(true);
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

onMounted(() => {
    if (shelfElement.value) {
        shelfElement.value.addEventListener('click', selectShelfClick);
    }

    document.addEventListener('keydown', globalKeyHandler);
});

onUnmounted(() => {
    if (shelfElement.value) {
        shelfElement.value.removeEventListener('click', selectShelfClick);
    }

    document.removeEventListener('keydown', globalKeyHandler);
});
</script>

<style scoped>
.shelf-container {
    transition: border-color 0.2s ease-in-out;
}

.shelf-container.drag-over {
    border-color:  rgba(59, 130, 246, 0.5);
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
</style>