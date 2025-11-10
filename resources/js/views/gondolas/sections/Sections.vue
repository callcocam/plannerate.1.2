<template>
    <div class="flex flex-col md:flex-row">
        <slot name="flow-direction" />

        <div id="planogram-container-full" class="mt-28 flex md:flex-row" ref="sectionsContainer">
            <draggable v-model="draggableSections" item-key="id" handle=".drag-handle" class="flex md:flex-row"
                :animation="200" :disabled="!canReorder">
                <template #item="{ element: section, index }">
                    <SectionWrapper :key="section.id" :section="section" :index="index" :scale-factor="scaleFactor"
                        :sections-container="sectionsContainer" :gondola="gondola" @delete="handleDeleteSection"
                        @edit="handleEditSection" @invert-shelves="handleInvertShelves"
                        @segment-select="$emit('segment-select', $event)" />
                </template>
            </draggable>

            <!-- Última cremalheira -->
            <LastRack v-if="lastSection" :section="lastSection" :scale-factor="scaleFactor" :index="gondola.sections.length" />
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import draggable from 'vuedraggable';
import { toast } from 'vue-sonner';
import type { Gondola } from '@plannerate/types/gondola';
import type { Section as SectionType } from '@plannerate/types/sections';
import { useEditorStore } from '@plannerate/store/editor';
import { getActiveSections } from '@plannerate/store/editor/actions/section';
import SectionWrapper from '@plannerate/views/gondolas/components/sections/SectionWrapper.vue';
import LastRack from '@plannerate/views/gondolas/sections/LastRack.vue';

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
const canReorder = computed(() => {
    const activeSections = getActiveSections(props.gondola?.sections || []);
    return props.gondola?.id && activeSections.length > 1;
});

const draggableSections = computed<SectionType[]>({
    get: () => {
        const allSections = props.gondola?.sections || [];
        // Retorna apenas seções ativas para exibição e arraste
        return getActiveSections(allSections);
    },
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
    const activeSections = getActiveSections(props.gondola?.sections || []);
    return activeSections.length > 0 ? activeSections[activeSections.length - 1] : null;
});

// ===== Methods =====
const handleDeleteSection = (section: SectionType) => {
    if (!props.gondola?.id) {
        toast.error('Erro', {
            description: 'Não é possível deletar a seção: Gôndola não identificada.'
        });
        return;
    }

    // Confirmação adicional se for a última seção ativa
    const activeSections = getActiveSections(props.gondola.sections || []);
    if (activeSections.length === 1) {
        toast.error('Aviso', {
            description: 'Não é possível deletar a última seção ativa da gôndola.'
        });
        return;
    }

    editorStore.removeSectionFromGondola(props.gondola.id, section.id);
    toast.success('Seção removida', {
        description: 'A seção foi removida com sucesso.'
    });
};

const handleEditSection = (section: SectionType) => {
    editorStore.setSelectedSection(section);
    
    editorStore.clearLayerSelection(); // Limpa seleção de camadas ao selecionar prateleira    
    editorStore.clearSelectedShelf(); // Limpa seleção de prateleira ao selecionar produto
};

const handleInvertShelves = (section: SectionType) => {
    if (!section || !props.gondola?.id) {
        toast.error('Erro', {
            description: 'Não foi possível inverter as prateleiras.'
        });
        return;
    }

    try {
        editorStore.invertShelvesInSection(props.gondola.id, section.id);
        toast.success('Prateleiras invertidas', {
            description: `${section.shelves.length} prateleiras foram invertidas com sucesso.`
        });
    } catch (error) {
        console.error('Erro ao inverter prateleiras:', error);
        toast.error('Erro', {
            description: 'Ocorreu um erro ao inverter as prateleiras.'
        });
    }
};
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