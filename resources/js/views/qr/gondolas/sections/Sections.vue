<template>
    <div class="flex flex-col md:flex-row">
        <slot name="flow-direction" />

        <div class="mt-28 flex md:flex-row" ref="sectionsContainer">
            <draggable v-model="draggableSections" item-key="id" handle=".drag-handle" class="flex md:flex-row"
                :animation="200" :disabled="!canReorder">
                <template #item="{ element: section, index }">
                    <SectionWrapper :key="section.id" :section="section" :index="index" :scale-factor="scaleFactor"
                        :sections-container="sectionsContainer" :gondola="gondola" @segment-select="$emit('segment-select', $event)" />
                </template>
            </draggable>

            <!-- Última cremalheira -->
            <LastRack v-if="lastSection" :section="lastSection" :scale-factor="scaleFactor" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import draggable from 'vuedraggable';
import type { Gondola } from '@plannerate/types/gondola';
import type { Section as SectionType } from '@plannerate/types/sections';
import { useEditorStore } from '@plannerate/store/editor';
import LastRack from '@plannerate/views/qr/gondolas/sections/LastRack.vue';
import SectionWrapper from '@plannerate/views/qr/gondolas/components/sections/SectionWrapper.vue';


// ===== Componentes Internos ===== 
 

// ===== Props & Emits =====
const props = defineProps<{
    gondola: Gondola;
    scaleFactor: number;
}>();

const emit = defineEmits<{
    (e: 'sections-reordered', sections: SectionType[]): void;
    (e: 'shelves-updated'): void;
    (e: 'move-shelf-to-section', data: any): void;
    (e: 'segment-select', segment: any): void;
}>();

// ===== Store & Refs =====
const editorStore = useEditorStore();
const sectionsContainer = ref<HTMLElement | null>(null);

// ===== Computed Properties =====
const canReorder = computed(() => props.gondola?.id && props.gondola.sections.length > 1);

const draggableSections = computed<SectionType[]>({
    get: () => [...(props.gondola?.sections || [])],
    set: (newOrder: SectionType[]) => {
        if (!props.gondola?.id) {
            console.warn('Sections.vue: Não é possível reordenar - ID da gôndola não encontrado');
            return;
        }

        editorStore.setGondolaSectionOrder(props.gondola.id, newOrder);
        emit('sections-reordered', newOrder);
    }
});

const lastSection = computed(() => {
    const sections = props.gondola?.sections || [];
    return sections.length > 0 ? sections[sections.length - 1] : null;
});

</script>

<style scoped>
/* Animação suave para o draggable */
.sortable-ghost {
    opacity: 0.5;
}

.sortable-drag {
    opacity: 0.8;
    transform: scale(1.02);
}

/* Melhor feedback visual no dark mode */
@media (prefers-color-scheme: dark) {
    .drag-handle:hover {
        background-color: rgb(55 65 81);
    }
}
</style>