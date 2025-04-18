<template>
    <ContextMenu>
        <ContextMenuTrigger>
            <div class="shelf relative flex flex-col items-center justify-around border-y border-gray-400 bg-gray-700 text-gray-50 dark:bg-gray-800"
                :style="shelfStyle" ref="shelfElement">
                <!-- TODO: Renderizar Segmentos/Produtos aqui -->
                <draggable v-model="sortableSegments" item-key="id" handle=".drag-segment-handle"
                    class="relative flex w-full items-end" :class="{
                        'justify-center': alignment === 'center',
                        'justify-start': alignment === 'left',
                        'justify-end': alignment === 'right',
                        'justify-around': alignment === 'justify',
                    }" :style="segmentsContainerStyle">
                    <template #item="{ element: segment }">
                        <Segment :key="segment.id" :shelf="shelf" :segment="segment" :scale-factor="scaleFactor"
                            :section-width="sectionWidth" :gondola="gondola" />
                    </template>
                </draggable>
                <ShelfControls :shelf="shelf" :scale-factor="scaleFactor" :section-width="sectionWidth"
                    :section-height="sectionHeight" :shelf-element="shelfElement" :base-height="baseHeight"
                    :sections-container="sectionsContainer" :section-index="sectionIndex" />
                <!-- <div class="absolute inset-0 bottom-0 z-0 flex h-full w-full items-center justify-center"> -->
                 
                <!-- </div> -->
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
                <ContextMenuItem inset @click="invertSegments">
                    Inverter
                    <ContextMenuShortcut>⌘⇧I</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuSeparator />
                <ContextMenuLabel inset> Alinhamento </ContextMenuLabel>
                <ContextMenuSeparator />
                <ContextMenuItem inset @click="setAlignmentJustify">
                    Justificado
                    <ContextMenuShortcut>⌘⇧J</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem inset @click="setAlignmentLeft" :disabled="alignment === 'left'">
                    Alinhado à esquerda
                    <ContextMenuShortcut>⌘⇧L</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem inset @click="setAlignmentCenter" :disabled="alignment === 'center'">
                    Alinhado ao centro
                    <ContextMenuShortcut>⌘⇧C</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem inset @click="setAlignmentRight" :disabled="alignment === 'right'">
                    Alinhado à direita
                    <ContextMenuShortcut>⌘⇧R</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem inset @click="setAlignmentNone" :disabled="!alignment || alignment === 'justify'">
                    Não alinhar
                    <ContextMenuShortcut>⌘⇧N</ContextMenuShortcut>
                </ContextMenuItem>
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
import { computed, defineEmits, defineProps, onMounted, onUnmounted, ref, type CSSProperties } from 'vue';
import draggable from 'vuedraggable';
import { useShelvesStore } from '@plannerate/store/shelves';
import { useEditorStore } from '@plannerate/store/editor';
import { type Layer, type Product, type Segment as SegmentType } from '@plannerate/types/segment';
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
// Definir Emits
const emit = defineEmits(['drop-product', 'drop-layer-copy', 'drop-layer']);
const shelvesStore = useShelvesStore();
const editorStore = useEditorStore();
const alignment = computed(() => {
    let alignment = props.gondola?.alignment;
    props.gondola?.sections.map((section: Section) => {
        if (section.id === props.shelf.section_id) {
            if (section.alignment) {
                alignment = section.alignment;
            }
            section.shelves.map((shelf: Shelf) => {
                if (shelf.alignment) {
                    alignment = shelf.alignment;
                }
            });
        }
    });
    return alignment;
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
    const topPosition = closestHolePosition * props.scaleFactor;

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
        top: `${topPosition}px`, // <-- Usar a posição calculada com snap
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
            console.error('sortableSegments.set: IDs faltando (gondola, section, ou shelf).');
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
    shelvesStore.setSelectedShelf(props.shelf);
    shelvesStore.startEditing();
    event.stopPropagation();
    const isCtrlOrMetaPressed = event.ctrlKey || event.metaKey;
    if (isCtrlOrMetaPressed) {
        shelvesStore.setSelectedShelfIds(props.shelf.id);
    } else {
        const isCurrentlySelected = shelvesStore.isShelfSelected(props.shelf.id);
        const selectionSize = shelvesStore.selectedShelfIds.size;
        if (isCurrentlySelected && selectionSize === 1) {
            shelvesStore.clearSelection();
            shelvesStore.clearSelectedShelfIds();
        } else {
            shelvesStore.clearSelectedShelfIds();
            shelvesStore.setSelectedShelfIds(props.shelf.id);
        }
    }
};
const controlDeleteShelf = (event: KeyboardEvent) => {
    if ((event.key === 'Delete' || event.key === 'Backspace') && event.ctrlKey) {
        event.preventDefault();
        shelvesStore.deleteSelectedShelf();
    }
};

const globalKeyHandler = (event: KeyboardEvent) => {
    if (shelvesStore.selectedShelf && shelvesStore.selectedShelf.id === props.shelf.id) {
        controlDeleteShelf(event);
    }
};

// Função auxiliar para chamar a action do editorStore
const updateAlignment = (alignment: string | null) => {
    if (!gondolaId.value || !props.shelf.section_id || !props.shelf.id) {
        console.error('updateAlignment: IDs faltando (gondola, section, ou shelf).');
        // Adicionar toast de erro?
        return;
    }
    editorStore.setShelfAlignment(gondolaId.value, props.shelf.section_id, props.shelf.id, alignment);
}

const setAlignmentLeft = () => {
    // REMOVIDO: shelvesStore.setSectionAlignment(props.shelf.id, 'left');
    updateAlignment('left');
};

const setAlignmentCenter = () => {
    // REMOVIDO: shelvesStore.setSectionAlignment(props.shelf.id, 'center');
    updateAlignment('center');
};

const setAlignmentRight = () => {
    // REMOVIDO: shelvesStore.setSectionAlignment(props.shelf.id, 'right');
    updateAlignment('right');
};
const setAlignmentJustify = () => {
    // REMOVIDO: shelvesStore.setSectionAlignment(props.shelf.id, 'justify');
    updateAlignment('justify');
};
const setAlignmentNone = () => {
    // REMOVIDO: shelvesStore.setSectionAlignment(props.shelf.id, null);
    updateAlignment(null);
};

const invertSegments = () => {
    const invertedSegments = [...sortableSegments.value].reverse();
    sortableSegments.value = invertedSegments;
    console.warn("InvertSegments agora usa setShelfSegmentsOrder via computed.");
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
    border-color: theme('colors.blue.500');
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
