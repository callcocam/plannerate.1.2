<template>
    <div class="flex flex-col md:flex-row">
        <div class="mt-28 flex px-10 md:flex-row" ref="sectionsContainer">
            <draggable v-model="sortableSections" item-key="id" handle=".drag-handle" @end="onDragEnd" class="flex md:flex-row">
                <template #item="{ element: section, index }">
                    <div :key="section.id">
                        <div class="flex items-center">
                            <Cremalheira :section="section" :scale-factor="scaleFactor" @delete-section="deleteSection" @edit-section="editSection">
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
                            <SectionComponent
                                :key="section.id"
                                :gondola-id="gondola?.id"
                                :section-index="index"
                                :section="section"
                                :scale-factor="scaleFactor"
                                :selected-category="selectedCategory"
                                :sections-container="sectionsContainer" 
                                @segment-select="$emit('segment-select', $event)"   
                                @delete-section="deleteSection"
                            />
                        </div>
                    </div>
                </template>
            </draggable>
            <div v-if="lastSectionData" class="flex items-center">
                <Cremalheira
                    :section="lastSectionData"
                    :scale-factor="scaleFactor"
                    :is-last-section="true"
                    :key="`rack-end-${lastSectionData.id}`"
                    @edit-section="editSection"
                />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { MoveIcon } from 'lucide-vue-next';
import { computed, ref, watch, toRefs } from 'vue';
import draggable from 'vuedraggable';

import type { Gondola } from '@plannerate/types/gondola';
import type { Section as SectionType } from '@plannerate/types/sections';

import Cremalheira from '@plannerate/views/gondolas/sections/Cremalheira.vue';
import SectionComponent from '@plannerate/views/gondolas/sections/Section.vue'; 
import { useSectionStore } from '@plannerate/store/section'; 
import { useEditorStore } from '@plannerate/store/editor';

interface Category {
    id: string | number;
    name: string;
}

const props = defineProps({
    gondola: {
        type: Object as () => Gondola | undefined,
        required: true,
    },
    selectedCategory: {
        type: Object as () => Category | null,
        default: null,
    },
    scaleFactor: {
        type: Number,
        default: 3,
    },
});

const { gondola, selectedCategory, scaleFactor } = toRefs(props);

const sectionsContainer = ref<HTMLElement | null>(null);

const emit = defineEmits(['sections-reordered', 'shelves-updated', 'move-shelf-to-section', 'segment-select']);
 
const sectionStore = useSectionStore(); 
const editorStore = useEditorStore();

const sortableSections = ref<SectionType[]>([...(gondola.value?.sections || [])]);

watch(
    () => gondola.value?.sections,
    (newSections) => {
        if (newSections && JSON.stringify(newSections) !== JSON.stringify(sortableSections.value)) {
            console.log('Prop sections mudou, atualizando sortableSections');
            sortableSections.value = [...newSections];
        }
    },
    { deep: true },
);

const lastSectionData = computed(() => {
    const sections = sortableSections.value;
    return sections.length > 0 ? sections[sections.length - 1] : null;
});

const onDragEnd = () => {
    if (!gondola.value) {
        console.warn('Tentativa de reordenar seções sem uma gôndola definida na prop.');
        return;
    }
    editorStore.setGondolaSectionOrder(gondola.value.id, sortableSections.value);

    // emit('sections-reordered', sortableSections.value, gondola.value.id);
};

const deleteSection = (sectionToDelete: SectionType) => {
    if (!gondola.value) {
        console.warn('Não é possível deletar seção: Gôndola não definida.');
        return;
    }
    editorStore.removeSectionFromGondola(gondola.value.id, sectionToDelete.id);
};
 

const editSection = (section: SectionType) => {
    sectionStore.setSelectedSection(section);
    sectionStore.startEditing();
};
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
