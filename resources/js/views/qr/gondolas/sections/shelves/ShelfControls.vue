<template>
    <div class="shelf-controls absolute inset-0 flex items-center justify-center">
        <!-- Número da prateleira -->
        <span class="flex z-20 items-center justify-center font-bold text-center" 
        :style="{ fontSize: scaleFactor * 3.5 + 'px' }">
           {{ invertIndex }}   
        </span>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { Shelf } from '@plannerate/types/shelves';

/**
 * Props do componente
 */
const props = defineProps<{
    shelf: Shelf;
    scaleFactor: number;
    sectionWidth: number;
    sectionHeight: number;
    baseHeight: number;
    shelfElement?: HTMLElement | null;
    sectionsContainer: HTMLElement | null;
    index: number;
    totalItems: number;
}>();

const isEmpty = computed(() => !props.shelf.segments || props.shelf.segments.length === 0);

const invertIndex = computed(() =>{
    const inverted = props.totalItems - 1 - props.index;
    if (props.shelf.product_type === 'hook') {
        return `Ganch: ${inverted}`;
    }
    return `Prat: ${inverted + 1}`;
});
</script>

<style scoped>
.shelf-controls {
    pointer-events: none;
    user-select: none;
}
</style>
