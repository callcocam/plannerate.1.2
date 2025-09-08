<template>
    <div class="w-full flex items-center justify-center text-center text-xs text-gray-100 bg-transparent p-5 rounded-md"
        :style="shelfContentStyle" :class="{ 'drag-over': dragShelfActive }" @dragenter.prevent="handleDragEnter"
        @dragover.prevent="handleDragOver" @dragleave="handleDragLeave" @drop.prevent="handleDrop"
        ref="shelfContentRef">
        <span v-if="dragShelfActive"
            class="text-center text-gray-800 dark:text-gray-200 pointer-events-none font-bold absolute inset-0 flex items-center justify-center">
            {{ shelfText }}
        </span>
    </div>
</template>

<script setup lang="ts">
import { defineEmits, defineProps, ref, watch, computed, CSSProperties } from 'vue';
import { type Shelf } from '@plannerate/types/shelves';
import { Section } from '@/types/sections';
import type { Product, Layer } from '@plannerate/types/segment';

// Props
const props = defineProps<{
    shelf: Shelf;
    scaleFactor: number;
    sortedShelves: Shelf[];
    index: number;
    section: Section;
    segmentDragging?: boolean;
    draggingSegment?: any;
}>();

// Emits
const emit = defineEmits<{
    'drop-product': [product: Product, shelf: Shelf, position: { x: number; y: number }];
    'drop-products-multiple': [products: Product[], shelf: Shelf, position: { x: number; y: number }];
    'drop-segment': [segment: Layer, shelf: Shelf, position: { x: number; y: number }];
    'drop-segment-copy': [segment: Layer, shelf: Shelf, position: { x: number; y: number }];
}>();

// Reactive state
const dragShelfActive = ref(false);
const dragEnterCount = ref(0);
const segmentDragOverActive = ref(false);
const segmentDragOverCount = ref(0);
const shelfContentRef = ref<HTMLElement | null>(null);
const isCtrlPressed = ref(false);

// Computed properties
const baseShelfText = computed(() => `Shelf (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`);

const shelfText = ref(baseShelfText.value);

const shelfContentStyle = computed((): CSSProperties => {
    const { shelf, index, sortedShelves, scaleFactor, section } = props;

    // Visual padding constants
    const verticalPaddingPx = 8;
    const topPaddingPx = verticalPaddingPx / 2;
    const bottomPaddingPx = verticalPaddingPx / 2;
    const minTopHeightPx = 120;

    // Calculate position and height in CM
    let topPositionCm: number;
    let rawHeightCm: number;

    if (index === 0) {
        topPositionCm = 0;
        rawHeightCm = Math.max(0, shelf.shelf_position);
    } else {
        const previousShelf = sortedShelves[index - 1];
        topPositionCm = Math.max(0, previousShelf.shelf_position);
        rawHeightCm = Math.max(0, shelf.shelf_position - previousShelf.shelf_position);
    }

    // Ensure it doesn't exceed section height
    if (topPositionCm + rawHeightCm > section.height) {
        rawHeightCm = Math.max(0, section.height - topPositionCm);
    }

    // Convert to pixels
    let topPx = topPositionCm * scaleFactor + shelf.shelf_height * scaleFactor;
    let heightPx = rawHeightCm * scaleFactor - shelf.shelf_height * scaleFactor;

    // Apply padding and minimum height adjustments
    let otherStyles = {};

    if (index === 0) {
        heightPx = Math.max(minTopHeightPx, heightPx);
        heightPx = Math.max(shelf.shelf_position, heightPx - bottomPaddingPx);
        otherStyles = {
            transform: `translateY(-${heightPx}px)`
        };
        topPx = shelf.shelf_position * scaleFactor;
    } else {
        topPx += topPaddingPx;
        heightPx = Math.max(0, heightPx - topPaddingPx - bottomPaddingPx);
    }

    // Ensure minimum height when segment is being dragged
    const finalHeightPx = props.segmentDragging ? Math.max(heightPx, 50) : heightPx;

    return {
        width: '100%',
        height: `${finalHeightPx}px`,
        top: `${topPx}px`,
        left: '0',
        position: 'absolute',
        zIndex: dragShelfActive.value ? 9999 : 0,
        ...otherStyles,
    };
});

// Watchers
watch(dragShelfActive, (newValue) => {
    if (newValue) {
        const action = isCtrlPressed.value ? 'Copiar para' : 'Mover para';
        shelfText.value = `${action} (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`;
    } else {
        shelfText.value = baseShelfText.value;
    }
}); 

// Drag and Drop utility functions
const ACCEPTED_TYPES = {
    PRODUCT: 'text/product',
    PRODUCTS_MULTIPLE: 'text/products-multiple',
    SEGMENT: 'text/segment',
    SEGMENT_COPY: 'text/segment/copy'
} as const;

const isAcceptedDataType = (dataTransfer: DataTransfer | null): boolean => {
    if (!dataTransfer) return false;
    const types = dataTransfer.types;
    return Object.values(ACCEPTED_TYPES).some(type => types.includes(type));
};

const isSegmentBeingDragged = (dataTransfer: DataTransfer | null): boolean => {
    if (!dataTransfer) return false;
    const types = dataTransfer.types;
    return types.includes(ACCEPTED_TYPES.SEGMENT) || types.includes(ACCEPTED_TYPES.SEGMENT_COPY);
};

const updateCtrlState = (event: DragEvent): void => {
    isCtrlPressed.value = event.ctrlKey || event.metaKey;
};

const resetVisualState = (): void => {
    dragEnterCount.value = 0;
    segmentDragOverCount.value = 0;

    if (dragShelfActive.value) {
        dragShelfActive.value = false;
    }

    if (segmentDragOverActive.value) {
        segmentDragOverActive.value = false;
    }

    isCtrlPressed.value = false;
};

// Drag event handlers
const handleDragEnter = (event: DragEvent): void => {
    if (!isAcceptedDataType(event.dataTransfer)) return;

    event.preventDefault();
    updateCtrlState(event);
    dragEnterCount.value++;

    if (isSegmentBeingDragged(event.dataTransfer)) {
        segmentDragOverCount.value++;
        if (!segmentDragOverActive.value) {
            segmentDragOverActive.value = true;
        }
    }

    if (!dragShelfActive.value) {
        dragShelfActive.value = true;
        const action = isCtrlPressed.value ? 'Copiar para' : 'Mover para';
        shelfText.value = `${action} (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`;
    }
};

const handleDragOver = (event: DragEvent): void => {
    if (!isAcceptedDataType(event.dataTransfer)) {
        if (event.dataTransfer) event.dataTransfer.dropEffect = 'none';
        if (dragShelfActive.value) {
            resetVisualState();
        }
        return;
    }

    event.preventDefault();
    updateCtrlState(event);

    if (!dragShelfActive.value) {
        dragShelfActive.value = true;
        const action = isCtrlPressed.value ? 'Copiar para' : 'Mover para';
        shelfText.value = `${action} (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`;
    }

    // Update drop effect based on Ctrl key and data type
    if (event.dataTransfer) {
        const types = event.dataTransfer.types;
        let effect: DataTransfer["dropEffect"] = 'move';

        if (isCtrlPressed.value ||
            types.includes(ACCEPTED_TYPES.SEGMENT_COPY) ||
            types.includes(ACCEPTED_TYPES.PRODUCT) ||
            types.includes(ACCEPTED_TYPES.PRODUCTS_MULTIPLE)) {
            effect = 'copy';
        }

        event.dataTransfer.dropEffect = effect;
    }

    // Update text if Ctrl state changed
    const action = isCtrlPressed.value ? 'Copiar para' : 'Mover para';
    shelfText.value = `${action} (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`;
};

const handleDragLeave = (event: DragEvent): void => {
    if (dragEnterCount.value > 0) {
        dragEnterCount.value--;

        if (dragEnterCount.value === 0) {
            resetVisualState();
        }
    }
};

const handleDrop = (event: DragEvent): void => {
    event.preventDefault();
    updateCtrlState(event);

    if (!isAcceptedDataType(event.dataTransfer) || !event.dataTransfer) {
        resetVisualState();
        return;
    }

    try {
        const types = event.dataTransfer.types;
        const position = { x: event.offsetX, y: event.offsetY };

        if (types.includes(ACCEPTED_TYPES.PRODUCTS_MULTIPLE)) {
            handleProductsMultipleDrop(event.dataTransfer, position);
        } else if (types.includes(ACCEPTED_TYPES.PRODUCT)) {
            handleProductDrop(event.dataTransfer, position);
        } else if (types.includes(ACCEPTED_TYPES.SEGMENT)) {
            handleSegmentDrop(event.dataTransfer, position);
        } else if (types.includes(ACCEPTED_TYPES.SEGMENT_COPY)) {
            handleSegmentCopyDrop(event.dataTransfer, position);
        }
    } catch (error) {
        console.error("handleDrop: Error processing dropped data:", error);
    } finally {
        resetVisualState();
    }
};

// Drop handlers for different data types
const handleProductsMultipleDrop = (dataTransfer: DataTransfer, position: { x: number; y: number }): void => {
    const productsData = dataTransfer.getData(ACCEPTED_TYPES.PRODUCTS_MULTIPLE);
    if (!productsData) return;

    const products = JSON.parse(productsData) as Product[];
    emit('drop-products-multiple', products, props.shelf, position);
};

const handleProductDrop = (dataTransfer: DataTransfer, position: { x: number; y: number }): void => {
    const productData = dataTransfer.getData(ACCEPTED_TYPES.PRODUCT);
    if (!productData) return;

    const product = JSON.parse(productData) as Product;
    emit('drop-product', product, props.shelf, position);
};

const handleSegmentDrop = (dataTransfer: DataTransfer, position: { x: number; y: number }): void => {
    const segmentDataString = dataTransfer.getData(ACCEPTED_TYPES.SEGMENT);
    if (!segmentDataString) return;

    const segmentData = JSON.parse(segmentDataString) as Layer & { segment?: { shelf_id?: string } };
    const originShelfId = segmentData?.segment?.shelf_id;

    // If Ctrl is pressed, force copy behavior instead of move
    if (isCtrlPressed.value) {
        emit('drop-segment-copy', segmentData, props.shelf, position);
        return;
    }

    // Original move logic - only if not same shelf
    if (originShelfId && originShelfId !== props.shelf.id) {
        emit('drop-segment', segmentData, props.shelf, position);
    } else if (!originShelfId) {
        emit('drop-segment', segmentData, props.shelf, position);
    }
    // If same shelf and no Ctrl, do nothing (prevent unwanted moves)
};

const handleSegmentCopyDrop = (dataTransfer: DataTransfer, position: { x: number; y: number }): void => {
    const segmentDataCopy = dataTransfer.getData(ACCEPTED_TYPES.SEGMENT_COPY);
    if (!segmentDataCopy) return;

    const segment = JSON.parse(segmentDataCopy) as Layer;
    emit('drop-segment-copy', segment, props.shelf, position);
};
</script>

<style scoped>
.drag-over {
    background-color: rgba(2, 16, 39, 0.1);
    border-color: rgba(13, 65, 150, 0.5);
    border-width: 2px;
    border-style: dashed;
    border-radius: 4px;
    box-shadow: 0 0 10px rgba(59, 130, 246, 0.5);
    transition: border-color 0.2s ease-in-out, background-color 0.2s ease-in-out;
    cursor: grab;
    z-index: 9999 !important;
}

.debug-shelf-content {
    background-color: rgba(0, 255, 0, 0.3) !important;
    border: 2px solid green !important;
}
</style>