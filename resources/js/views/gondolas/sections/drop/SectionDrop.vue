<template>
    <div class="relative w-full h-full bg-slate-400" :style="sectionStyle">
        <!-- Renderiza cada prateleira com altura baseada na diferença de posição -->
        <div v-for="(shelf, index) in sortedShelves" :key="shelf.id"
            :class="['shelf-item absolute w-full', { 'active-shelf': activeShelf === shelf.id }]"
            :style="shelfStyle(shelf, index)">
            <!-- Componente de drop para a prateleira -->
            <ShelfDropArea v-for="(shelf, index) in sortedShelves" :key="shelf.id"
                :accept-types="['text/product', 'text/layer', 'text/layer-copy']"
                :default-message="'Soltar produto aqui'"
                @drop="(dropData, position) => handleDrop(dropData, position, shelf)"
                @drag-state-change="(isDragging, type) => handleDragStateChange(shelf.id, isDragging, type)">
                <template #default="{ isDraggingOver }">
                    <!-- Conteúdo normal da prateleira -->
                    <div class="shelf-content w-full h-full" :class="{ 'faded': isDraggingOver }">
                        <div class="p-1 flex justify-between items-center">
                            <p class="text-white text-xs">Pos: {{ shelf.shelf_position.toFixed(1) }}</p>
                            <p class="text-white text-xs" v-if="index < sortedShelves.length - 1">
                                {{ getShelfHeight(shelf, index).toFixed(1) }}cm
                            </p>
                        </div>
                        <!-- Área para produtos/segmentos da prateleira (slot) -->
                        <slot name="shelf-content" :shelf="shelf"></slot>
                    </div>
                </template>
            </ShelfDropArea>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Layer, Product } from '@/types/segment';
import { Shelf } from '@/types/shelves';
import { type Section } from '@plannerate/types/sections';
import { computed, CSSProperties, ref } from 'vue';
import ShelfDropArea from './ShelfDropArea.vue';

const props = defineProps<{
    section: Section;
    scaleFactor: number;
}>();

// Estado para rastrear qual prateleira está ativa
const activeShelf = ref<string | null>(null);

const emit = defineEmits<{
    (e: 'drop-product', product: Product, shelf: Shelf, position: { x: number, y: number }): void;
    (e: 'drop-layer', layer: Layer, shelf: Shelf, position: { x: number, y: number }): void;
    (e: 'drop-layer-copy', layer: Layer, shelf: Shelf, position: { x: number, y: number }): void;
}>();

// Ordena as prateleiras por posição para garantir o cálculo correto
const sortedShelves = computed(() => {
    if (!props.section.shelves || props.section.shelves.length === 0) {
        return [];
    }
    return [...props.section.shelves].sort((a, b) => a.shelf_position - b.shelf_position);
});

// Estilo da seção
const sectionStyle = computed(() => {
    return {
        width: `${props.section.width * props.scaleFactor}px`,
        height: `${props.section.height * props.scaleFactor}px`,
        position: 'relative',
        overflow: 'hidden'
    } as CSSProperties;
});

/**
 * Calcula a altura de uma prateleira com base na próxima posição
 */
const getShelfHeight = (shelf: Shelf, index: number) => {
    // Se for a última prateleira, a altura vai até o final da seção
    if (index === sortedShelves.value.length - 1) {
        return props.section.height - shelf.shelf_position;
    }

    // Caso contrário, a altura vai até a próxima prateleira
    const nextShelf = sortedShelves.value[index + 1];
    return nextShelf.shelf_position - shelf.shelf_position;
};

/**
 * Gera o estilo CSS para uma prateleira
 */
const shelfStyle = (shelf: Shelf, index: number) => {
    // Posição absoluta baseada na posição da prateleira
    const topPosition = shelf.shelf_position * props.scaleFactor;

    // Calcula a altura até a próxima prateleira
    const heightInCm = getShelfHeight(shelf, index);
    const height = heightInCm * props.scaleFactor - shelf.shelf_height * props.scaleFactor;

    return {
        width: '100%',
        height: `${height}px`,
        top: `${topPosition + shelf.shelf_height * props.scaleFactor}px`,
        left: '0',
        // Fundo gradiente apenas para visualização 
        transition: 'all 0.2s ease',
        zIndex: activeShelf.value === shelf.id ? '10' : '1'
    };
};

/**
 * Manipula mudanças no estado de arrastar para uma prateleira
 */
const handleDragStateChange = (shelfId: string, isDragging: boolean, type: string | null) => {
    // Atualiza qual prateleira está ativa (para z-index)
    activeShelf.value = isDragging ? shelfId : null;
};

/**
 * Processa o drop de um item em uma prateleira
 */
const handleDrop = (dropData: any, position: { x: number, y: number, relativeX: number, relativeY: number }, shelf: Shelf) => {
    console.log('handleDrop', dropData, position, shelf);
    // Com base no tipo, emite o evento apropriado
    if (dropData.type === 'product') {
        emit('drop-product', dropData.data, shelf, position);
    } else if (dropData.type === 'layer') {
        emit('drop-layer', dropData.data, shelf, position);
    } else if (dropData.type === 'layer-copy') {
        emit('drop-layer-copy', dropData.data, shelf, position);
    }
};
</script>

<style scoped>
.shelf-item {
    transition: all 0.2s ease;
}

.active-shelf {
    z-index: 10;
}

.shelf-content {
    width: 100%;
    height: 100%;
    transition: opacity 0.2s ease;
}

.faded {
    opacity: 0.6;
}
</style>