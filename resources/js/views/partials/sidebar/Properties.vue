<template>
    <div :class="sidebarClasses">
        <!-- Colapsado: só o ícone -->
        <template v-if="!open">
            <button
                @click="$emit('toggle')"
                aria-label="Expandir propriedades"
                title="Propriedades"
                class="absolute right-0 top-4 flex items-center justify-center w-12 h-12 bg-transparent border-none shadow-none p-0 m-0"
                style="outline: none;"
            >
                <Settings class="h-6 w-6" />
            </button>
        </template>
        <!-- Expandido: conteúdo normal -->
        <div v-if="open" class="flex h-full flex-col">
            <div class="border-b border-gray-200 p-3 dark:border-gray-700 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-800 dark:text-gray-100">Propriedades</h3>
                <div class="flex items-center space-x-2">
                    <Button variant="ghost" size="icon" @click="$emit('toggle')" 
                        aria-label="Colapsar propriedades" title="Colapsar">
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        @click="emit('close')"
                        aria-label="Fechar propriedades"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </Button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto">
                <Product v-if="selectionSize" @remove-layer="handleRemoveLayer" />
                <Section v-else-if="isSectionEditing" />
                <Shelf v-else-if="isShelfEditing" />
                <div v-else class="flex h-full flex-col items-center justify-center p-6 text-center">
                    <div class="rounded-full p-4 dark:bg-gray-700 bg-gray-100">
                        <InfoIcon class="h-12 w-12 text-gray-400 dark:text-gray-500" />
                    </div>
                    <h3 class="mt-4 text-base font-medium text-gray-700 dark:text-gray-300">Nenhuma operação em andamento</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Selecione um produto ou seção para ver suas propriedades</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { InfoIcon, Settings, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue'; 
import { useEditorStore } from '@plannerate/store/editor';
import Product from '@plannerate/views/partials/sidebar/Product.vue';
import Section from '@plannerate/views/partials/sidebar/Section.vue';
import Shelf from '@plannerate/views/partials/sidebar/Shelf.vue';
import { Button } from '@/components/ui/button';
import { Layer } from '@plannerate/types/segment';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'toggle'): void;
}>();

const editorStore = useEditorStore();
const isSectionEditing = computed(() => editorStore.isSectionSelected());
const isShelfEditing = computed(() => editorStore.isShelfSelected());
const selectionSize = computed(() => editorStore.getSelectedLayerIds.size);
const editorGondola = computed(() => editorStore.getCurrentGondola);
 
const handleRemoveLayer = (layer: Layer) => {
    if (editorGondola.value) { 
        let sectionId = null;
        let shelfId = null;
        let segmentId = null;
        editorGondola.value.sections.forEach(section => {
            section.shelves.forEach(shelf => {
                shelf.segments.forEach(segment => {
                    if (segment.id === layer.id) {
                        sectionId = section.id;
                        shelfId = shelf.id;     
                        segmentId = segment.id;
                    }
                });
            });
        });
        if (sectionId && shelfId && segmentId) {
            editorStore.removeSegmentFromShelf(editorGondola.value.id, sectionId, shelfId, segmentId);
        }
    }
};

const sidebarClasses = computed(() => {
    return !props.open
        ? 'w-12 h-screen relative z-40'
        : 'sticky top-0 flex h-screen flex-shrink-0 flex-col overflow-hidden rounded-lg bg-gray-50 dark:bg-gray-800 z-40 transition-all duration-300 ease-in-out w-80 relative';
});
</script>
