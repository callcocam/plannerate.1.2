<template>
    <div
        class="flex h-full w-full items-center justify-center text-center text-xs text-gray-100 dark:text-gray-700"
        :class="{ 
            'border border-dashed border-blue-500 dark:border-blue-400': isSelected,
        }"
        @dragover.prevent="handleDragOver"
        @drop.prevent="handleDrop"
        @dragleave="handleDragLeave"  
    >
        {{ shelftext }}
    </div>
</template>

<script setup lang="ts">
import { defineEmits, defineProps, ref, watch, computed } from 'vue'; 
import { useShelvesStore } from '../../../store/shelves';
import { Shelf } from '../../../types/shelves';

// Definir Props
const props = defineProps<{
    shelf: Shelf;
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

const isSelected = computed(() => {
    // Verifica se a prateleira está selecionada
    return shelvesStore.isShelfSelected(props.shelf.id);
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
    padding: 0 0 30px 0;
    /* Adicionar um efeito de escala */
    cursor: grab;
    z-index: 9999;
    transform: translateY(-50%);
}
</style>
