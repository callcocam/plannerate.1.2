<template>
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
</template>

<script setup lang="ts">
import { computed, defineEmits, defineProps, onMounted, onUnmounted, ref } from 'vue';
import draggable from 'vuedraggable';
import { useSegmentStore } from '../../../store/segment';
import { useShelvesStore } from '../../../store/shelves';
import Segment from './Segment.vue';
import ShelfContent from './ShelfContent.vue';
import ShelfControls from './ShelfControls.vue'; // Importar o componente ShelfControls
import { Layer, Product, Segment as SegmentType, Shelf } from './types';

// Definir Props
const props = defineProps<{
    shelf: Shelf;
    scaleFactor: number;
    sectionWidth: number;
    sectionHeight: number;
    baseHeight: number;
    rackWidth: number; // Nova prop para a largura da cremalheira
    sectionsContainer: HTMLElement | null; // Referência ao container das seções
    sectionIndex: number; // Índice da seção atual
}>();

const shelfElement = ref<HTMLElement | null>(null);

// Definir Emits
const emit = defineEmits(['drop-product', 'drop-layer-copy']); // Para quando um produto é solto na prateleira
const shelvesStore = useShelvesStore();
const segmentStore = useSegmentStore(); // Instanciar o segment store

const alignment = computed(() => { 
    if (props.shelf?.alignment) {
        return props.shelf?.alignment;
    }
    console.log('alignment', props.shelf.section?.alignment);
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
    // Convertemos a posição da prateleira para pixels usando o fator de escala
    const topPosition = props.shelf.shelf_position * props.scaleFactor;
    const moveStyle = {};
    if (props.shelf?.shelf_x_position !== undefined) {
        const leftPosition = props.shelf.shelf_x_position;
        // Aplicamos a posição sem o sinal negativo para corrigir a direção do movimento
        moveStyle['left'] = `${leftPosition}px`;
    }

    // Retornamos o estilo final com tipagem correta (as CSSProperties)
    return {
        position: 'absolute' as const, // Use 'as const' para tipar corretamente
        left: '-4px',
        width: `${props.sectionWidth * props.scaleFactor + 4}px`,
        height: `${props.shelf.shelf_height * props.scaleFactor}px`,
        top: `${topPosition}px`,
        zIndex: '1',
        ...moveStyle,
    };
});

// --- Lógica de Drag and Drop (para produtos) ---
/**
 * Referência local aos segmentos para o draggable
 * Aplica ordenamento e garante IDs para todos os segmentos
 */
const sortableSegments = computed<SegmentType[]>({
    get() {
        // Garantir que todos os segmentos tenham IDs
        return props.shelf.segments;
    },
    set(newSegments: SegmentType[]) {
        // Garantir que a ordenação está atualizada antes de emitir o evento
        const reorderedSegments = newSegments.map((segment, index) => ({
            ...segment,
            ordering: index + 1,
        }));
        // Emitir evento para o componente pai (Section) lidar com a atualização
        shelvesStore.updateShelf(props.shelf.id, {
            segments: reorderedSegments,
        });
    },
});

const updateLayer = (layer: Layer, shelf: Shelf) => {
    // Emitir evento para o componente pai (Section) lidar com a atualização
    segmentStore.transferLayer(layer.segment_id, layer.segment.shelf_id, shelf.id, 0);
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
    // Emitir evento para o componente pai (Section) lidar com o clique
    shelvesStore.setSelectedShelf(props.shelf);
    shelvesStore.startEditing();
    // Emitir evento para o componente pai (Section) lidar com o clique
    event.stopPropagation(); // Impede que o evento de clique se propague para outros elementos
    // Verifica se a tecla Ctrl ou Meta está pressionada
    const isCtrlOrMetaPressed = event.ctrlKey || event.metaKey;
    if (isCtrlOrMetaPressed) {
        // Se a tecla Ctrl ou Meta estiver pressionada, alterna a seleção
        shelvesStore.setSelectedShelfIds(props.shelf.id);
    } else {
        // Caso contrário, seleciona apenas a prateleira atual
        const isCurrentlySelected = shelvesStore.isShelfSelected(props.shelf.id);
        const selectionSize = shelvesStore.selectedShelfIds.size;
        if (isCurrentlySelected && selectionSize === 1) {
            // Se a prateleira já estiver selecionada e for a única selecionada, desmarque-a
            shelvesStore.clearSelection();
            shelvesStore.clearSelectedShelfIds();
        } else {
            // Caso contrário, selecione apenas a prateleira atual
            shelvesStore.clearSelectedShelfIds();
            shelvesStore.setSelectedShelfIds(props.shelf.id);
        }
    }
};
const controlDeleteShelf = (event: KeyboardEvent) => {
    // Verificar se Ctrl+Delete foi pressionado
    if ((event.key === 'Delete' || event.key === 'Backspace') && event.ctrlKey) {
        event.preventDefault();
        shelvesStore.deleteSelectedShelf();
    }
};

// Handler global para capturar Ctrl+Delete em qualquer parte da aplicação
const globalKeyHandler = (event: KeyboardEvent) => {
    if (shelvesStore.selectedShelf && shelvesStore.selectedShelf.id === props.shelf.id) {
        controlDeleteShelf(event);
    }
};

onMounted(() => {
    // Adicionar lógica para quando a prateleira é montada
    if (shelfElement.value) {
        shelfElement.value.addEventListener('click', selectShelfClick);
    }

    // Adicionar listener global para capturar Ctrl+Delete
    document.addEventListener('keydown', globalKeyHandler);
});

onUnmounted(() => {
    // Remover os listeners quando o componente for desmontado
    if (shelfElement.value) {
        shelfElement.value.removeEventListener('click', selectShelfClick);
    }

    // Remover listener global
    document.removeEventListener('keydown', globalKeyHandler);
});
</script>

<style scoped>
.shelf-container {
    /* Adicionar transições se houver feedback visual no dragover */
    transition: border-color 0.2s ease-in-out;
}

/* Estilo para feedback visual ao arrastar sobre */
.shelf-container.drag-over {
    border-color: theme('colors.blue.500');
    /* background-color: theme('colors.blue.50 / 50%'); */
}

.drag-over {
    background-color: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.5);
    border-width: 2px;
    border-style: dashed;
    border-radius: 4px;
    /* Adicionar sombra se necessário */
    box-shadow: 0 0 10px rgba(59, 130, 246, 0.5);
    /* Adicionar transição suave */
    transition:
        border-color 0.2s ease-in-out,
        background-color 0.2s ease-in-out;
    /* Aumentar a area de drop */
    padding: 0 0 30px 0;
    /* Adicionar um efeito de escala */
}
</style>
