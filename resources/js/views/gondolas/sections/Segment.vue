<template>
    <div class="segment drag-segment-handle group relative flex items-center justify-around" :style="segmentStyle">
        <Layer
            v-for="(quantity, index) in segmentQuantity"
            :key="index"
            :shelf="shelf"
            :segment="segment"
            :layer="segment.layer"
            :scale-factor="scaleFactor"
            :section-width="sectionWidth"
            @increase="onIncreaseQuantity"
            @decrease="onDecreaseQuantity"
            @spacingIncrease="onSpacingIncrease"
            @spacingDecrease="onSpacingDecrease"
        />
    </div>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue';
import { useGondolaStore } from '../../../store/gondola'; // Corrected relative path
import { useProductStore } from '../../../store/product'; // Corrected relative path
import Layer from './Layer.vue';
import { LayerSegment as LayerType, Segment, Shelf } from './types';

const props = defineProps<{
    segment: Segment & {
        layer: LayerType;
    };
    shelf: Shelf;
    scaleFactor: number;
    sectionWidth: number;
}>();

const segmentSelected = ref(false); // State to track if the segment is selected
/** Segment quantity (number of layers) */
const segmentQuantity = computed(() => {
    return props.segment.quantity;
});

const productStore = useProductStore(); // Instance of the product store
const gondolaStore = useGondolaStore(); // Instance of the gondola store

const layersWithSpacing = ref<LayerType[]>([]); // Array to store layers with spacing

// Computed para o estilo do segmento
// ----------------------------------------------------
// Computed Properties
// ----------------------------------------------------
/**
 * Calculate segment style based on properties and selection state
 */

const layerWidth = () => {
    let sectionWidth = props.sectionWidth;
    currentGondola.value?.sections.map((section) => {
        section.shelves.map((shelf) => {
            shelf.segments.map((segment) => {
                if (segment.id === props.segment.id) {
                    sectionWidth = sectionWidth - segment.layer.product.width;
                    if (shelf.segments.length > 1) {
                        sectionWidth = sectionWidth / shelf.segments.length; 
                    }
                    if (sectionWidth < 0) {
                        sectionWidth = 0;
                    }
                }
            });
        });
    });
    return sectionWidth;
};
const currentGondola = computed(() => {
    return gondolaStore.currentGondola;
});
const segmentStyle = computed(() => {
    // Calculate segment dimensions
    const layerHeight = props.segment.layer.product.height * props.scaleFactor;

    // Cálculo atualizado da largura total, considerando produtos e espaçamento
    const productWidth = props.segment.layer.product.width;
    const productQuantity = props.segment.layer.quantity; 
    const layerWidthFinal = layerWidth();

    // Largura total: largura dos produtos + espaçamento entre eles
    const totalWidth = (productWidth * productQuantity) * props.scaleFactor + layerWidthFinal;

    // Conditional style when segment is selected
    const selectedStyle = segmentSelected.value
        ? {
              border: '2px solid blue',
              boxShadow: '0 0 5px rgba(0, 0, 255, 0.5)',
              outline: 'none',
          }
        : {};
    // Return complete style object
    return {
        height: `${layerHeight}px`,
        width: `${totalWidth}px`,
        marginBottom: `${props.shelf.shelf_height * props.scaleFactor}px`,
        ...selectedStyle,
    };
});

// Function to increase quantity
const onIncreaseQuantity = (layer: LayerType) => {
    productStore.updateLayerQuantity(layer, layer.quantity, {
        ...props.segment,
        layer,
    });
};
// Function to decrease quantity
const onDecreaseQuantity = (layer: LayerType) => {
    productStore.updateLayerQuantity(layer, layer.quantity, {
        ...props.segment,
        layer,
    });
};
// Function to increase spacing
const onSpacingIncrease = (layer: LayerType) => {
    productStore.updateLayerSpacing(layer, layer.spacing, {
        ...props.segment,
        layer,
    });
};
// Function to decrease spacing
const onSpacingDecrease = (layer: LayerType) => {
    productStore.updateLayerSpacing(layer, layer.spacing, {
        ...props.segment,
        layer,
    });
};
</script>

<style scoped>
.segment {
    position: relative;
    transition: all 0.3s ease;
}
</style>
