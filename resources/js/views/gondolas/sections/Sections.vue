<template>
    <div class="flex flex-col md:flex-row">
        <div class="mt-28 flex px-10 md:flex-row" ref="sectionsContainer">
            <draggable v-model="sortableSections" item-key="id" handle=".drag-handle" @end="onDragEnd" class="flex md:flex-row">
                <template #item="{ element: section, index }">
                    <div :key="section.id">
                        <div class="flex items-center">
                            <Cremalheira :section="section" :scale-factor="scaleFactor" @delete-section="deleteSection">
                                <template #actions>
                                    <Button
                                        size="sm"
                                        class="drag-handle h-6 w-6 cursor-move p-0 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                                        variant="secondary"
                                    >
                                        <MoveIcon class="h-3 w-3" />
                                    </Button>
                                </template>
                            </Cremalheira>
                            <Section
                                :key="section.id"
                                :section-index="index"
                                :section="section"
                                :scale-factor="scaleFactor"
                                :selected-category="selectedCategory"
                                :sections-container="sectionsContainer"
                                @move-shelf-to-section="handleMoveShelfToSection"
                                @segment-select="$emit('segment-select', $event)"
                                @update-shelves="handleMoveSegmentToSection"
                                @update:quantity="updateSegmentQuantity"
                                @update:segments="handleMoveSegmentToSection"
                                @delete-section="deleteSection"
                            />
                        </div>
                    </div>
                </template>
            </draggable>

            <div v-if="lastSectionData" class="flex items-center">
                <Cremalheira :section="lastSectionData" :scale-factor="scaleFactor" :is-last-section="true" :key="`rack-end-${lastSectionData.id}`" />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { MoveIcon } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Cremalheira from './Cremalheira.vue';
import Section from './Section.vue';
// @ts-ignore
import { Button } from '@/components/ui/button';
// @ts-ignore
// import {VueDraggableNext } from 'vue-draggable-next'
import draggable from 'vuedraggable';
import { apiService } from '../../../services';
import { useEditorStore } from '../../../store/editor';
import { useGondolaStore } from '../../../store/gondola';

interface Category {
    id: string | number;
    name: string;
}

const props = defineProps({
    selectedCategory: {
        type: Object as () => Category | null,
        default: null,
    },
});

const sectionsContainer = ref<HTMLElement | null>(null);

const emit = defineEmits(['sections-reordered', 'shelves-updated', 'move-shelf-to-section', 'segment-select']);

const editorStore = useEditorStore();
const gondolaStore = useGondolaStore();

const scaleFactor = computed(() => {
    return editorStore.scaleFactor;
});

const gondolaSections = computed(() => gondolaStore.currentGondola?.sections || []);

const sortableSections = ref([...gondolaSections.value]);
watch(
    gondolaSections,
    (newSections) => {
        if (JSON.stringify(newSections) !== JSON.stringify(sortableSections.value)) {
            sortableSections.value = [...(newSections || [])];
        }
    },
    { deep: true },
);

const lastSectionData = computed(() => {
    const sections = sortableSections.value;
    return sections.length > 0 ? sections[sections.length - 1] : null;
});

const onDragEnd = () => {
    const currentGondola = gondolaStore.currentGondola;
    if (!currentGondola) {
        console.warn('Tentativa de reordenar seções sem uma gôndola carregada.');
        return;
    }
    const orderedIds = sortableSections.value.map((s) => s.id);
    emit('sections-reordered', sortableSections.value, currentGondola.id);
    // TODO: Chamar API para salvar a nova ordem das seções
    // console.log(`Chamando API para reordenar seções da gôndola ${currentGondola.id}`, orderedIds);
    apiService.post(`gondolas/${currentGondola.id}/sections/reorder`, { sectionIds: orderedIds });
};

const deleteSection = (sectionToDelete: any) => {
    sortableSections.value = sortableSections.value.filter((s: any) => s.id !== sectionToDelete.id);
    console.warn(`Seção ${sectionToDelete.id} removida visualmente. Implementar chamada API e atualização do store.`);

    apiService
        .delete(`sections/${sectionToDelete.id}`)
        .then(() => {
            emit('shelves-updated', sortableSections.value);
            console.log(`Seção ${sectionToDelete.id} removida com sucesso.`);
        })
        .catch((error) => {
            console.error(`Erro ao remover seção ${sectionToDelete.id}:`, error);
        });
};

const handleMoveShelfToSection = (shelf: any, sectionId: number) => {};

const handleMoveSegmentToSection = (segment: any, sectionId: number) => {};

const updateSegmentQuantity = (segment: any) => {};
</script>

<style scoped>
/* Estilos para o modo escuro específicos do componente Sections, se necessário */
@media (prefers-color-scheme: dark) {
    .drag-handle {
        /* Ajustes adicionais para o ícone de arrastar no modo escuro, se necessário */
        filter: brightness(1.1);
    }
}
</style>
