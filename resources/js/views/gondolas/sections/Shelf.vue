<template>
    <ContextMenu>
        <ContextMenuTrigger>
            <div
                class="shelf relative flex flex-col items-center justify-around border-y border-gray-400 bg-gray-700 text-gray-50 dark:bg-gray-800"
                :style="shelfStyle"
                ref="shelfElement"
            >
                <!-- TODO: Renderizar Segmentos/Produtos aqui -->
                <draggable
                    v-model="sortableSegments"
                    item-key="id"
                    handle=".drag-segment-handle"
                    class="relative flex w-full items-end"
                    :class="{
                        'justify-center': alignment === 'center',
                        'justify-start': alignment === 'left',
                        'justify-end': alignment === 'right',
                        'justify-around': alignment === 'justify',
                    }"
                    :style="segmentsContainerStyle"
                >
                    <template #item="{ element: segment }">
                        <Segment :key="segment.id" :shelf="shelf" :segment="segment" :scale-factor="scaleFactor" :section-width="sectionWidth" />
                    </template>
                </draggable>
                <ShelfControls
                    :shelf="shelf"
                    :scale-factor="scaleFactor"
                    :section-width="sectionWidth"
                    :section-height="sectionHeight"
                    :shelf-element="shelfElement"
                    :base-height="baseHeight"
                    :sections-container="sectionsContainer"
                    :section-index="sectionIndex"
                />
                <!-- <div class="absolute inset-0 bottom-0 z-0 flex h-full w-full items-center justify-center"> -->
                <ShelfContent
                    :shelf="shelf"
                    @drop-product="(product: Product, shelf: Shelf, dropPosition: any) => $emit('drop-product', product, shelf, dropPosition)"
                    @drop-layer-copy="(product: Product, shelf: Shelf, dropPosition: any) => $emit('drop-layer-copy', product, shelf, dropPosition)"
                    @drop-layer="(layer: Layer, shelf: Shelf) => updateLayer(layer, shelf)"
                />
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
                <ContextMenuItem inset @click="setAlignmentLeft">
                    Alinhado à esquerda
                    <ContextMenuShortcut>⌘⇧L</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem inset @click="setAlignmentCenter">
                    Alinhado ao centro
                    <ContextMenuShortcut>⌘⇧C</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem inset @click="setAlignmentRight">
                    Alinhado à direita
                    <ContextMenuShortcut>⌘⇧R</ContextMenuShortcut>
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

// Definir Props
const props = defineProps<{
    gondolaId: string | undefined;
    shelf: Shelf;
    scaleFactor: number;
    sectionWidth: number;
    sectionHeight: number;
    baseHeight: number;
    sectionsContainer: HTMLElement | null;
    sectionIndex: number;
}>();

const shelfElement = ref<HTMLElement | null>(null);

// Definir Emits
const emit = defineEmits(['drop-product', 'drop-layer-copy']);
const shelvesStore = useShelvesStore(); 
const editorStore = useEditorStore();

const alignment = computed(() => {
    if (props.shelf?.alignment) {
        return props.shelf?.alignment;
    }
    if (props.shelf?.section?.alignment) {
        return props.shelf?.section?.alignment;
    }
    if (props.shelf?.section?.gondola?.alignment) {
        return props.shelf?.section?.gondola?.alignment;
    }
    // Verifica se a prateleira está alinhada à esquerda ou direita
    return 'justify';
});
// --- Computeds para Estilos ---
const shelfStyle = computed(() => {
    const topPosition = props.shelf.shelf_position * props.scaleFactor;
    const moveStyle: Record<string, string> = {};
    if (props.shelf?.shelf_x_position !== undefined) {
        const leftPosition = props.shelf.shelf_x_position;
        moveStyle['left'] = `${leftPosition}px`;
    }

    return {
        position: 'absolute' as const,
        left: '-4px',
        width: `${props.sectionWidth * props.scaleFactor + 4}px`,
        height: `${props.shelf.shelf_height * props.scaleFactor}px`,
        top: `${topPosition}px`,
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
        if (!props.gondolaId || !props.shelf.section_id || !props.shelf.id) {
            console.error('sortableSegments.set: IDs faltando (gondola, section, ou shelf).');
            return;
        }

        const reorderedSegments = newSegments.map((segment, index) => ({
            ...segment,
            ordering: index + 1,
        }));
        
        editorStore.setShelfSegmentsOrder(
            props.gondolaId,
            props.shelf.section_id,
            props.shelf.id,
            reorderedSegments
        );
    },
});

const updateLayer = (layer: Layer, targetShelf: Shelf) => {
    // 1. Obter IDs e dados necessários
    const segmentToMove = layer.segment;
    if (!segmentToMove) {
        console.error('updateLayer: Objeto segment não encontrado na layer.');
        return;
    }
    const segmentId = segmentToMove.id;
    const oldShelfId = segmentToMove.shelf_id; // ID da prateleira de origem
    const oldSectionId = props.shelf.section_id; // ID da seção de origem (da prateleira atual)
    
    const newShelfId = targetShelf.id; // ID da prateleira de destino
    const newSectionId = targetShelf.section_id; // ID da seção de destino
    
    const gondolaId = props.gondolaId; // ID da gôndola

    // 2. Verificar se todos os IDs essenciais foram obtidos
    if (!gondolaId || !oldSectionId || !oldShelfId || !newSectionId || !newShelfId || !segmentId) {
        console.error('updateLayer: IDs faltando para realizar a transferência.', 
            { gondolaId, oldSectionId, oldShelfId, newSectionId, newShelfId, segmentId }
        );
        // Adicionar um toast para o usuário seria bom aqui
        return;
    }

    // 3. Evitar auto-transferência (opcional)
    if (oldShelfId === newShelfId) {
        console.log('updateLayer: Tentativa de transferir layer para a mesma prateleira. Ignorando.');
        return;
    }

    // 4. Chamar a action do editorStore
    console.log(`Chamando transferSegment: ${segmentId} de ${oldShelfId} para ${newShelfId}`);
    editorStore.transferSegmentBetweenShelves(
        gondolaId,
        oldSectionId,
        oldShelfId,
        newSectionId,
        newShelfId,
        segmentId
        // Passar newPositionX ou newOrdering se forem calculados aqui
    );

    // 5. Remover chamada antiga
    // segmentStore.transferLayer(layer.segment_id, layer.segment.shelf_id, shelf.id, 0);
};
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
const updateAlignment = (alignment: string) => {
    if (!props.gondolaId || !props.shelf.section_id || !props.shelf.id) {
        console.error('updateAlignment: IDs faltando (gondola, section, ou shelf).');
        // Adicionar toast de erro?
        return;
    }
    editorStore.setShelfAlignment(props.gondolaId, props.shelf.section_id, props.shelf.id, alignment);
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
