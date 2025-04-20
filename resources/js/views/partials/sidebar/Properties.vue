<template>
    <div
        class="no-remove-properties sticky top-0 flex h-screen w-80 flex-shrink-0 flex-col overflow-hidden rounded-lg border bg-gray-50 dark:border-gray-700 dark:bg-gray-800"
    >
        <div class="border-b border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
            <h3 class="text-center text-lg font-medium text-gray-800 dark:text-gray-100">Propriedades</h3>
        </div>
        <Product v-if="selectionSize" @remove-layer="handleRemoveLayer" />
        <Section v-else-if="isSectionEditing" />
        <Shelf v-else-if="isShelfEditing" />
        <div v-else class="flex h-full flex-col items-center justify-center p-6 text-center">
            <div class="rounded-full bg-gray-100 p-4 dark:bg-gray-700">
                <InfoIcon class="h-12 w-12 text-gray-400 dark:text-gray-500" />
            </div>
            <h3 class="mt-4 text-base font-medium text-gray-700 dark:text-gray-300">Nenhuma operação em andamento</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Selecione um produto ou seção para ver suas propriedades</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { InfoIcon } from 'lucide-vue-next';
import { computed } from 'vue'; 
import { useEditorStore } from '@plannerate/store/editor';
import Product from '@plannerate/views/partials/sidebar/Product.vue';
import Section from '@plannerate/views/partials/sidebar/Section.vue';
import Shelf from '@plannerate/views/partials/sidebar/Shelf.vue';
import { Layer } from '@plannerate/types/segment';
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
</script>
