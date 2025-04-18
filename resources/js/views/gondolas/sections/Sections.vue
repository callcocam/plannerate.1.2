<template>
    <div class="flex flex-col md:flex-row">
        <div class="mt-28 flex px-10 md:flex-row" ref="sectionsContainer">
            <draggable v-model="draggableSectionsModel" item-key="id" handle=".drag-handle" class="flex md:flex-row">
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
                                :gondola="gondola"
                                :section-index="index"
                                :section="section"
                                :scale-factor="scaleFactor" 
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
import { computed, ref, toRefs } from 'vue';
import draggable from 'vuedraggable';

import type { Gondola } from '@plannerate/types/gondola';
import type { Section as SectionType } from '@plannerate/types/sections';

import Cremalheira from '@plannerate/views/gondolas/sections/Cremalheira.vue';
import SectionComponent from '@plannerate/views/gondolas/sections/Section.vue'; 
import { useSectionStore } from '@plannerate/store/section'; 
import { useEditorStore } from '@plannerate/store/editor';
 

const props = defineProps<{
    gondola: Gondola; 
    scaleFactor: number;
}>(); 

const sectionsContainer = ref<HTMLElement | null>(null);

const emit = defineEmits(['sections-reordered', 'shelves-updated', 'move-shelf-to-section', 'segment-select']);
 
const sectionStore = useSectionStore(); 
const editorStore = useEditorStore();

const draggableSectionsModel = computed<SectionType[]>({
    get() {
        return [...(props.gondola?.sections || [])];
    },
    set(newSectionsOrder: SectionType[]) {
        if (!props.gondola?.id) {
            console.warn('draggableSectionsModel.set: ID da gôndola não encontrado.');
            return;
        }
        editorStore.setGondolaSectionOrder(props.gondola.id, newSectionsOrder);
    }
});

const lastSectionData = computed(() => {
    const sections = props.gondola?.sections || [];
    return sections.length > 0 ? sections[sections.length - 1] : null;
});

const deleteSection = (sectionToDelete: SectionType) => {
    if (!props.gondola?.id) {
        console.warn('Não é possível deletar seção: Gôndola não definida.');
        return;
    }
    editorStore.removeSectionFromGondola(props.gondola.id, sectionToDelete.id);
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
