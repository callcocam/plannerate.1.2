<template>
    <div class=" w-full flex items-center justify-center text-center text-xs text-gray-100 bg-transparent p-5 rounded-md "
        :style="shelfContentStyle" @dragover.prevent="handleDragOver" @drop.prevent="handleDrop"
        @dragleave="handleDragLeave">
        <!-- Quero alinhar o texto no centro da prateleira  -->
        <span class="text-center text-gray-800 dark:text-gray-200 translate-y-1/2" v-if="dragShelfActive"> {{ shelftext }}</span>
    </div>
</template>

<script setup lang="ts">
import { defineEmits, defineProps, ref, watch, computed, CSSProperties } from 'vue';
import { useShelvesStore } from '@plannerate/store/shelves';
import { type Shelf } from '@plannerate/types/shelves';
import { Section } from '@/types/sections';

// Definir Props
const props = defineProps<{
    shelf: Shelf;
    scaleFactor: number;
    sortedShelves: Shelf[];
    index: number;
    section: Section;
}>();
const dragShelfActive = ref(false); // Estado para rastrear se a prateleira está sendo arrastada
const shelftext = ref(`Shelf (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`); // Texto da prateleira
// Definir Emits
const emit = defineEmits(['drop-product', 'drop-layer', 'drop-layer-copy']); // Para quando um produto é solto na prateleira

const shelvesStore = useShelvesStore(); // Instanciar o shelves store

watch(dragShelfActive, (newValue) => {
    if (newValue) {
        // Adicionar lógica para quando a prateleira está sendo arrastada
        shelftext.value = `Arrastando Prateleira (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`;
    } else {
        // Adicionar lógica para quando a prateleira não está mais sendo arrastada
        shelftext.value = `Shelf (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`;
    }
});
 

const shelfContentStyle = computed((): CSSProperties => {
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
        heightPx = Math.max(0, heightPx - bottomPaddingPx);
        otherStyles = {
            transform: `translateY(-${heightPx}px)`
        }
    } else {
        // Para as demais, aplica padding no topo e embaixo
        topPx += topPaddingPx; // Desce o topo um pouco
        heightPx = Math.max(0, heightPx - topPaddingPx - bottomPaddingPx); // Reduz altura pelos dois paddings
    }


    // Debug logs
    console.log(`Shelf ${currentIndex} (Pos ${currentShelf.shelf_position.toFixed(1)}): TopPx=${topPx.toFixed(1)}, HeightPx=${heightPx.toFixed(1)}`);

    return {
        width: '100%',
        height: `${heightPx}px`,
        top: `${topPx}px`,
        left: '0',
        position: 'absolute',
        transition: 'all 0.2s ease',
        zIndex: dragShelfActive.value ? '9999' : '1',
        ...otherStyles,
        // Adicione outros estilos se necessário (background, borda para debug, etc.)
        // Ex: backgroundColor: 'rgba(255, 0, 0, 0.3)',
    };
});
// --- Lógica de Drag and Drop (para produtos) ---
const handleDragOver = (event: DragEvent) => {
    // Permite que itens sejam soltos aqui
    event.preventDefault();
    dragShelfActive.value = true; // Ativa o estado de arrastar a prateleira
    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'copy'; // Ou 'move' se for o caso
    }
    // TODO: Adicionar feedback visual (ex: mudar borda)
    if (event.currentTarget) {
        (event.currentTarget as HTMLElement).classList.add('drag-over');
    }
};

const handleDragLeave = (event: DragEvent) => {
    // Remove o feedback visual quando o item não está mais sobre a prateleira
    if (event.currentTarget) {
        (event.currentTarget as HTMLElement).classList.remove('drag-over');
    }
    dragShelfActive.value = false; // Desativa o estado de arrastar a prateleira
};

const handleDrop = (event: DragEvent) => {
    event.preventDefault();
    if (event.dataTransfer) {
        const layerData = event.dataTransfer.getData('text/layer');
        const layerDataCopy = event.dataTransfer.getData('text/layer/copy');
        const productData = event.dataTransfer.getData('text/product');
        if (productData) {
            const product = JSON.parse(productData);
            // Emitir evento para o componente pai (Section) lidar com a adição
            emit('drop-product', product, props.shelf, { x: event.offsetX, y: event.offsetY });
        } else if (layerData) {
            const layer = JSON.parse(layerData);
            // Emitir evento para o componente pai (Section) lidar com a adição
            emit('drop-layer', layer, props.shelf, { x: event.offsetX, y: event.offsetY });
        } else if (layerDataCopy) {
            const layer = JSON.parse(layerDataCopy);
            // Emitir evento para o componente pai (Section) lidar com a adição 
            emit('drop-layer-copy', layer, props.shelf, { x: event.offsetX, y: event.offsetY });
        }
        // TODO: Remover feedback visual
        if (event.currentTarget) {
            (event.currentTarget as HTMLElement).classList.remove('drag-over');
        }
    }
    dragShelfActive.value = false; // Desativa o estado de arrastar a prateleira
};

</script>

<style scoped>
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
    /* Adicionar um efeito de escala */
    cursor: grab;
    z-index: 9999;
}
</style>
