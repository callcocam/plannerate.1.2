<template>
    <div ref="sectionRef" class="bg-gray-800 section-container" :style="sectionStyle" :data-section-id="section.id">
        <!-- Prateleiras -->
        <ShelfComponent v-for="(shelf, index) in sortedShelves" :key="shelf.id" :shelf="shelf" :gondola="gondola"
            :sorted-shelves="sortedShelves" :index="index" :section="section" :scale-factor="scaleFactor"
            :section-width="section.width" :section-height="section.height" :base-height="baseHeightPx"
            :sections-container="sectionsContainer" :section-index="sectionIndex" :holes="holes"
            :invert-index="moduleNumber"/>

        <!-- Label do Módulo -->
        <ModuleLabel :module-number="moduleNumber" :base-height="baseHeightPx" :scale-factor="scaleFactor" />
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useEditorStore } from '@plannerate/store/editor';
import { Section } from '@plannerate/types/sections';
import { Gondola } from '@plannerate/types/gondola';
import ShelfComponent from './shelves/Shelf.vue';

// ===== Componente Interno =====
const ModuleLabel = {
    props: ['moduleNumber', 'baseHeight', 'scaleFactor'],
    template: `
        <div 
            class="module-label text-black text-xs absolute left-1/2 -translate-x-1/2 p-2 dark:text-white uppercase font-bold" 
            :style="{ 
                bottom: baseHeight / 3 + 'px', 
                fontSize: scaleFactor * 3.5 + 'px' 
            }"
        >
            Módulo {{ moduleNumber + 1 }}
        </div>
    `
};

// ===== Props & Emits =====
const props = defineProps<{
    gondola: Gondola;
    section: Section;
    scaleFactor: number;
    sectionsContainer: HTMLElement | null;
    sectionIndex: number;
}>();

const emit = defineEmits(['update:segments']);

// ===== Store & State =====
const editorStore = useEditorStore();
const sectionRef = ref<HTMLElement | null>(null);
const isDragOver = ref(false);

// ===== Helpers =====
const calculateHoles = (section: Section) => {
    const { height, hole_height, hole_width, hole_spacing, base_height } = section;
    const availableHeight = height - base_height;
    const totalSpaceNeeded = hole_height + hole_spacing;
    const holeCount = Math.floor(availableHeight / totalSpaceNeeded);
    const remainingSpace = availableHeight - holeCount * hole_height - (holeCount - 1) * hole_spacing;
    const marginTop = remainingSpace / 2;

    return Array.from({ length: holeCount }, (_, i) => ({
        width: hole_width,
        height: hole_height,
        spacing: hole_spacing,
        position: marginTop + i * (hole_height + hole_spacing),
    }));
};


// ===== Computed Properties =====
const baseHeightPx = computed(() =>
    (props.section.base_height || 0) * props.scaleFactor
);

const holes = computed(() => calculateHoles(props.section));

const moduleNumber = computed(() =>
    props.gondola.flow === 'left_to_right'
        ? props.sectionIndex
        : props.gondola.sections.length - 1 - props.sectionIndex
);

const sortedShelves = computed(() =>
    [...(props.section.shelves || [])]
        .sort((a, b) => a.shelf_position - b.shelf_position)
);

const sectionStyle = computed(() => ({
    width: `${props.section.width * props.scaleFactor}px`,
    height: `${props.section.height * props.scaleFactor}px`,
    position: 'relative' as const,
    borderWidth: '2px',
    borderStyle: isDragOver.value ? 'dashed' : 'solid',
    borderColor: isDragOver.value ? 'rgba(59, 130, 246, 0.5)' : 'transparent',
    backgroundColor: isDragOver.value ? 'rgba(59, 130, 246, 0.1)' : 'transparent',
    transition: 'all 0.2s ease-in-out',
}));





// ===== Event Handlers =====
const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        editorStore.clearLayerSelection();
    }
};

const handleClickOutside = (event: MouseEvent) => {
    const target = event.target as HTMLElement;

    const ignoredSelectors = [
        'html', 'body',
        '#section-properties-sidebar',
        '[data-radix-popper-content-wrapper]',
        '[role="listbox"]',
        '[data-dismissable-layer]',
        '[data-state="open"]',
        '.border-destructive',
        '.no-remove-properties'
    ];

    if (ignoredSelectors.some(selector =>
        target.matches(selector) || target.closest(selector)
    )) {
        return;
    }

    if (!target.closest('.layer')) editorStore.clearLayerSelection();
    if (!target.closest('.shelf')) editorStore.clearSelectedShelf();
    if (!target.closest('.section-container')) editorStore.clearSelectedSection();
};

// ===== Lifecycle =====
onMounted(() => {
    window.addEventListener('keydown', handleKeydown, { passive: true });
    document.addEventListener('click', handleClickOutside, true);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    document.removeEventListener('click', handleClickOutside, true);
});
</script>

<style scoped>
.section-container {
    will-change: border-color, background-color;
}

.section-drag-over {
    background-color: rgba(59, 130, 246, 0.05);
    border: 2px dashed rgba(59, 130, 246, 0.5);
}

.module-label {
    pointer-events: none;
    user-select: none;
}
</style>