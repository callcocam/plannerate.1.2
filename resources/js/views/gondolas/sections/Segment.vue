<template>
    <div
        class="segment drag-segment-handle group relative flex items-center" 
        :style="segmentStyle"
    >
        <Layer
            v-for="(_, index) in segmentQuantity"
            :key="index"
            :shelf="shelf"
            :segment="segment"
            :layer="segment.layer"
            :scale-factor="scaleFactor"
            :section-width="sectionWidth"
            @increase="onIncreaseQuantity"
            @decrease="onDecreaseQuantity"
        />
    </div>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue';
import { useGondolaStore } from '@plannerate/store/gondola'; // Corrected relative path
import { useProductStore } from '@plannerate/store/product'; // Corrected relative path 
import Layer from './Layer.vue';
import { Layer as LayerType, Segment } from '@plannerate/types/segment';
import { Section } from '@plannerate/types/sections';
import { Shelf } from '@/types/shelves';

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
 

// Computed para o estilo do segmento
// ----------------------------------------------------
// Computed Properties
// ----------------------------------------------------
/**
 * Calculate segment style based on properties and selection state
 */

const layerWidth = () => {
    let sectionWidth = props.sectionWidth;
    currentGondola.value?.sections.map((section: Section) => {
        section.shelves.map((shelf: Shelf) => {
            if (shelf?.segments?.length > 0) {
                shelf.segments.map((segment: Segment) => {
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
            }
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
    let layerWidthFinal = 0;
    if (gondolaStore.getAligmentCenter()) {
        layerWidthFinal = productWidth * props.scaleFactor;
    } else if (gondolaStore.getAligmentJustify()) {
        layerWidthFinal = layerWidth();
    } else {
        layerWidthFinal = 0;
    }

    // Largura total: largura dos produtos + espaçamento entre eles
    const totalWidth = productWidth * productQuantity * props.scaleFactor + layerWidthFinal;

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
</script>

<style scoped>
.segment {
    position: relative;
    transition: all 0.3s ease;
}
</style>
