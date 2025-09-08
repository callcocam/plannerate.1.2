<template>
    <div v-if="layer.product" class="layer group flex cursor-pointer" :style="layerStyle" :class="layerClasses"
        @click="handleLayerClick" @keydown="handleKeyDown">
        <ProductNormal v-for="index in layer.quantity" :key="index" :product="layer.product" :scale-factor="scaleFactor"
            :index="index" :shelf-depth="shelfDepth" :layer="layer">
            <template #depth-count v-if="index === 1">
                <slot name="depth-count"></slot>
            </template>
        </ProductNormal>
    </div>
</template>

<script setup lang="ts">
import { computed, CSSProperties, onMounted, onUnmounted, ref } from 'vue';
import { useEditorStore } from '@plannerate/store/editor';
import ProductNormal from '@plannerate/views/gondolas/sections/shelves/Product.vue';
import { Layer as LayerType, Segment as SegmentType } from '@/types/segment';
import { Shelf } from '@plannerate/types/shelves';
import { validateShelfWidth } from '@plannerate/utils/validation';
import { toast } from 'vue-sonner';

// Props
const props = defineProps<{
    layer: LayerType;
    segment: SegmentType;
    shelf: Shelf;
    scaleFactor: number;
    sectionWidth: number;
    shelfDepth: number;
}>();

// Emits
const emit = defineEmits<{
    (e: 'increase', layer: LayerType): void;
    (e: 'decrease', layer: LayerType): void;
    (e: 'tab-navigation', data: { isLast: boolean, direction: 'next' | 'prev', currentTabIndex: number }): void;
    (e: 'layer-click', layer: LayerType): void;
    (e: 'update-layer-quantity', layer: LayerType): void;
}>();

// Store
const editorStore = useEditorStore();

// Reactive state
const layerQuantity = ref(props.layer.quantity || 1);
const segmentQuantity = ref(props.segment.quantity || 1);
const debounceTimer = ref<ReturnType<typeof setTimeout> | null>(null);
const segmentSelected = ref(false);

// Computed properties
const editorGondola = computed(() => editorStore.getCurrentGondola);
const currentSectionId = computed(() => props.shelf.section_id);

const isSelected = computed(() => {
    const layerId = String(props.layer.id);
    return editorStore.isSelectedLayer(layerId);
});

const layerClasses = computed(() => ({
    'layer--selected': isSelected.value,
    'layer--focused': !isSelected.value
}));

const layerStyle = computed((): CSSProperties => {
    if (!props.layer?.product) {
        return {
            width: '0px',
            height: '0px',
            zIndex: '0',
        };
    }

    const { product, quantity = 1 } = props.layer;
    const layerHeight = product.height || 0;
    const productWidth = (product.width || 0) * props.scaleFactor;
    const alignment = editorStore.getCurrentGondola?.alignment;

    let layerWidthFinal: string;

    switch (alignment) {
        case 'left':
        case 'right':
        case 'center':
            layerWidthFinal = `${productWidth * quantity}px`;
            break;
        case 'justify':
        default:
            layerWidthFinal = '100%';
            break;
    }

    return {
        width: layerWidthFinal,
        height: `${layerHeight * props.scaleFactor}px`,
        zIndex: '0',
        display: 'flex',
        justifyContent: 'space-around',
        position: 'relative',
    };
});

// Layer selection methods
const handleLayerClick = (event: MouseEvent) => {
    const { product, id: layerId } = props.layer;
    const productId = product?.id;

    if (!productId) return;

    const isCtrlOrMetaPressed = event.ctrlKey || event.metaKey;
    handleSelectedLayer(isCtrlOrMetaPressed, productId, layerId);
};

const handleSelectedLayer = (isCtrlOrMetaPressed: boolean, productId: string, layerId: string) => {
    if (!productId) return;

    const layerIdAsString = String(layerId);

    if (isCtrlOrMetaPressed) {
        segmentSelected.value = !segmentSelected.value;
        editorStore.toggleLayerSelection(layerIdAsString);
    } else {
        const isCurrentlySelected = editorStore.isSelectedLayer(layerIdAsString);
        const selectionSize = editorStore.getSelectedLayerIds.size;

        if (isCurrentlySelected && selectionSize === 1) {
            editorStore.clearLayerSelection();
            segmentSelected.value = false;
        } else {
            editorStore.clearLayerSelection();
            editorStore.selectLayer(layerIdAsString);
            segmentSelected.value = true;
        }
    }
};

// Quantity management methods
const validateQuantityIncrease = (newQuantity: number): boolean => {
    const validation = validateShelfWidth(
        props.shelf,
        props.sectionWidth,
        props.segment.layer.product.id,
        newQuantity,
        null
    );

    if (!validation.isValid) {
        toast.error('Limite de Largura Excedido', {
            description: `A largura total (${validation.totalWidth.toFixed(1)}cm) excederia a largura da seção (${validation.sectionWidth}cm).`,
        });
        return false;
    }

    return true;
};

const onUpdateQuantity = (quantity: number) => {
    if (editorStore.getSelectedLayerIds.size > 1) return;

    emit('update-layer-quantity', {
        ...props.layer,
        quantity,
    });
};

const onIncreaseQuantity = () => {
    if (editorStore.getSelectedLayerIds.size > 1) return;

    const newQuantity = layerQuantity.value + 1;

    if (!validateQuantityIncrease(newQuantity)) {
        return;
    }

    layerQuantity.value = newQuantity;
    emit('increase', {
        ...props.layer,
        quantity: newQuantity,
    });
};

const onDecreaseQuantity = () => {
    if (editorStore.getSelectedLayerIds.size > 1 || layerQuantity.value <= 1) return;

    const newQuantity = layerQuantity.value - 1;
    if (newQuantity === 0) return;

    layerQuantity.value = newQuantity;
    emit('decrease', {
        ...props.layer,
        quantity: newQuantity,
    });
};

// Segment quantity methods
const validateSegmentOperation = (): boolean => {
    return !!(editorGondola.value?.id && currentSectionId.value && props.shelf?.id && props.segment?.id);
};

const onIncreaseSegmentQuantity = () => {
    if (!validateSegmentOperation()) return;

    segmentQuantity.value += 1;
    editorStore.updateSegmentQuantity(
        editorGondola.value!.id,
        currentSectionId.value!,
        props.shelf.id,
        props.segment.id!,
        segmentQuantity.value
    );
};

const onDecreaseSegmentQuantity = () => {
    if (!validateSegmentOperation() || segmentQuantity.value <= 1) return;

    segmentQuantity.value -= 1;
    editorStore.updateSegmentQuantity(
        editorGondola.value!.id,
        currentSectionId.value!,
        props.shelf.id,
        props.segment.id!,
        segmentQuantity.value
    );
};

// Segment removal method
const removeSegment = () => {
    if (!editorGondola.value) return;

    let sectionId: string | null = null;
    let shelfId: string | null = null;
    let segmentId: string | null = null;

    editorGondola.value.sections.forEach(section => {
        section.shelves.forEach(shelf => {
            shelf.segments.forEach(segment => {
                if (segment.id === props.segment.id) {
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
};

// Keyboard event handler
const handleKeyDown = (event: KeyboardEvent) => {
    if (!isSelected.value) return;

    const target = event.target as HTMLElement;
    const isInput = target.tagName === 'INPUT' || target.tagName === 'TEXTAREA';

    if (isInput) return;

    event.preventDefault();

    if (/^[1-9]$/.test(event.key)) {
        onUpdateQuantity(parseInt(event.key));
    } else {
        switch (event.key) {
            case 'ArrowRight':
                onIncreaseQuantity();
                break;
            case 'ArrowLeft':
                onDecreaseQuantity();
                break;
            case 'ArrowUp':
                onIncreaseSegmentQuantity();
                break;
            case 'ArrowDown':
                onDecreaseSegmentQuantity();
                break;
            case 'Delete':
            case 'Backspace':
                removeSegment();
                break;
        }
    }
};

// Lifecycle hooks
onMounted(() => {
    document.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    if (debounceTimer.value) {
        clearTimeout(debounceTimer.value);
    }
    document.removeEventListener('keydown', handleKeyDown);
});
</script>

<style scoped>
.layer {
    border: 2px solid transparent;
}

.layer--selected {
    border: 2px solid blue;
    box-shadow: 0 0 5px rgba(0, 0, 255, 0.5);
    box-sizing: border-box;
}

.layer--focused {
    outline: 1px solid transparent;
    outline-offset: 2px;
}

.layer--focused:focus {
    outline: 1px solid blue;
    outline-offset: 2px;
}
</style>