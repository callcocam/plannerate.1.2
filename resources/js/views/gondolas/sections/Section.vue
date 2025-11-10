<template>
    <div ref="sectionRef" class="bg-gray-800 section-container" :style="sectionStyle" :data-section-id="section.id"
        @dragover.prevent="handleDragOver" @drop.prevent="handleDrop" @dragleave="handleDragLeave">
        <!-- Prateleiras -->
        <ShelfComponent v-for="(shelf, index) in sortedShelves" :key="shelf.id" :shelf="shelf" :gondola="gondola"
            :sorted-shelves="sortedShelves" :index="index" :section="section" :scale-factor="scaleFactor"
            :section-width="section.width" :section-height="section.height" :base-height="baseHeightPx"
            :sections-container="sectionsContainer" :section-index="sectionIndex" :holes="holes"
            :invert-index="moduleNumber" @drop-product="handleProductDrop"
            @drop-products-multiple="handleMultipleProductsDrop" @drop-segment-copy="handleSegmentCopy"
            @drop-segment="handleSegmentMove" @drag-shelf="draggingShelf = $event" />

        <!-- Label do Módulo -->
        <ModuleLabel :module-number="moduleNumber" :base-height="baseHeightPx" :scale-factor="scaleFactor" />
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { ulid } from 'ulid';
import { useEditorStore } from '@plannerate/store/editor';
import { getActiveShelves } from '@plannerate/store/editor/actions/shelf';
import { Section } from '@plannerate/types/sections';
import { Product, Segment } from '@plannerate/types/segment';
import { type Shelf as ShelfType } from '@plannerate/types/shelves';
import { Gondola } from '@plannerate/types/gondola';
import { validateShelfWidth } from '@plannerate/utils/validation';
import { toast } from 'vue-sonner';
import ShelfComponent from './shelves/Shelf.vue';

// ===== Componente Interno =====
const ModuleLabel = {
    props: ['moduleNumber', 'baseHeight', 'scaleFactor'],
    template: `
        <div 
            class="module-label text-black text-xs absolute left-1/2 -translate-x-1/2 p-2 dark:text-white uppercase font-bold" 
            :style="{ 
                bottom: baseHeight / -45 + 'rem', 
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
const draggingShelf = ref<ShelfType | null>(null);
const isProcessingDrop = ref(false);

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

const createSegment = (product: Product, shelf: ShelfType, quantity = 1): Segment => {
    const ordering = (shelf.segments?.length || 0) + 1;
    const segmentId = ulid();
    return {
        id: segmentId,
        user_id: null,
        tenant_id: '',
        width: props.section.width,
        ordering,
        quantity: 1,
        shelf_id: shelf.id,
        spacing: 0,
        position: 0,
        alignment: '',
        settings: null,
        status: 'published',
        tabindex: ordering,
        layer: {
            id: ulid(),
            product_id: product.id,
            product: product,
            quantity,
            status: 'published',
            height: product.height,
            segment_id: segmentId,
            tabindex: 0,
        },
    };
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

const sortedShelves = computed(() => {
    const allShelves = props.section.shelves || [];
    // Filtrar apenas prateleiras ativas e depois ordenar por posição
    const activeShelves = getActiveShelves(allShelves);
    return activeShelves.sort((a, b) => a.shelf_position - b.shelf_position);
});

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

// ===== Drag & Drop Methods =====
const handleDragOver = (event: DragEvent) => {
    if (!event.dataTransfer?.types.includes('text/shelf')) return;

    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    isDragOver.value = true;
};

const handleDragLeave = () => {
    isDragOver.value = false;
};

const handleDrop = async (event: DragEvent) => {
    isDragOver.value = false;

    const shelfData = event.dataTransfer?.getData('text/shelf');
    if (!shelfData || !props.gondola.id) return;

    try {
        const shelf = JSON.parse(shelfData) as ShelfType;
        const newPosition = Math.round(event.offsetY / props.scaleFactor);
        const maxPosition = props.section.height - (shelf.shelf_height || 0);

        if (newPosition < 0 || newPosition > maxPosition) {
            toast.error('Posição inválida', {
                description: `A posição deve estar entre 0 e ${maxPosition}cm.`,
            });
            return;
        }

        editorStore.setShelfPosition(props.gondola.id, props.section.id, shelf.id, {
            shelf_position: newPosition,
            shelf_x_position: -4,
        });
    } catch (error) {
        console.error('Erro ao processar drop da prateleira:', error);
        toast.error('Erro ao mover prateleira');
    }
};

// ===== Product Drop Handlers =====
const validateAndAddProduct = (product: Product, shelf: ShelfType, quantity = 1) => {
    const tempLayer = {
        id: ulid(),
        product_id: product.id,
        product: product,
        quantity,
        status: 'temp' as const,
        height: product.height,
        segment_id: 'temp',
        spacing: 0,
        tabindex: 0,
    };

    const validation = validateShelfWidth(shelf, props.section.width, null, 0, tempLayer);

    if (!validation.isValid) {
        toast.error('Limite de largura excedido', {
            description: `Largura total: ${validation.totalWidth.toFixed(1)}cm (máximo: ${props.section.width}cm)`,
        });
        return null;
    }

    return createSegment(product, shelf, quantity);
};

const handleProductDrop = async (product: Product, shelf: ShelfType, quantity = 1) => {
    if (!props.gondola.id || isProcessingDrop.value) return;

    isProcessingDrop.value = true;

    try {
        const newSegment = validateAndAddProduct(product, shelf, quantity);
        if (newSegment) {
            editorStore.addSegmentToShelf(
                props.gondola.id,
                props.section.id,
                shelf.id,
                newSegment
            );
        }
    } catch (error) {
        console.error('Erro ao adicionar produto:', error);
        toast.error('Erro ao adicionar produto');
    } finally {
        isProcessingDrop.value = false;
    }
};

const handleMultipleProductsDrop = async (products: Product[], shelf: ShelfType) => {
    if (!props.gondola.id || isProcessingDrop.value) return;

    isProcessingDrop.value = true;

    try {
        // Remover duplicados
        const uniqueProducts = Array.from(
            new Map(products.map(p => [p.id, p])).values()
        );

        // Validar todos antes de adicionar
        const segments: Segment[] = [];
        for (const product of uniqueProducts) {
            const segment = validateAndAddProduct(product, shelf);
            if (!segment) {
                isProcessingDrop.value = false;
                return;
            }
            segments.push(segment);
        }

        // Adicionar todos de uma vez
        const gondola = editorStore.getCurrentGondola;
        const targetShelf = gondola?.sections
            .find(s => s.id === props.section.id)?.shelves
            .find(sh => sh.id === shelf.id);

        if (targetShelf) {
            targetShelf.segments.push(...segments as any);
            editorStore.recordChange(true);

            toast.success('Produtos adicionados', {
                description: `${segments.length} produtos foram adicionados.`,
            });
        }
    } catch (error) {
        console.error('Erro ao adicionar múltiplos produtos:', error);
        toast.error('Erro ao adicionar produtos');
    } finally {
        isProcessingDrop.value = false;
    }
};

const handleSegmentCopy = async (segment: Segment, shelf: ShelfType) => {
    if (!props.gondola.id || !segment.layer?.product) return;

    handleProductDrop(segment.layer.product, shelf, segment.layer.quantity || 1);
};

const handleSegmentMove = (segment: Segment, targetShelf: ShelfType) => {
    console.log('handleSegmentMove: segment:', segment, 'targetShelf:', targetShelf);
    if (!props.gondola.id || segment.shelf_id === targetShelf.id) return;

    // Encontrar seção de destino
    const targetSection = editorStore.currentState?.gondolas
        .find(g => g.id === props.gondola.id)?.sections
        .find(s => s.id === targetShelf.section_id);

    if (!targetSection) {
        toast.error('Seção de destino não encontrada');
        return;
    }

    // Validar largura
    const validation = validateShelfWidth(
        targetShelf,
        targetSection.width,
        null,
        0,
        segment.layer
    );

    if (!validation.isValid) {
        toast.error('Limite de largura excedido no destino');
        return;
    }

    // Encontrar seção de origem
    let sourceSection = props.section;
    if (segment.shelf_id !== targetShelf.id) {
        const gondola = editorStore.currentState?.gondolas.find(g => g.id === props.gondola.id);
        sourceSection = gondola?.sections.find(s =>
            s.shelves.some(sh => sh.id === segment.shelf_id)
        ) || props.section;
    }

    editorStore.transferSegmentBetweenShelves(
        props.gondola.id,
        sourceSection.id,
        segment.shelf_id,
        targetShelf.section_id,
        targetShelf.id,
        segment?.id  || ''
    );
};

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