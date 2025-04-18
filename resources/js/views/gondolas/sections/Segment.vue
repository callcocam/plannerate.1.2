<template>
    <div class="segment drag-segment-handle group relative flex items-center border-2" :style="segmentStyle"
        :class="{ 
            'justify': alignment === 'justify',
            'left': alignment === 'left',
            'center': alignment === 'center',
            'right': alignment === 'right'
        }"
    >
        <LayerComponent v-for="(_, index) in segmentQuantity" :key="index" :shelf="shelf" :segment="segment"
            :layer="segment.layer" :scale-factor="scaleFactor" :section-width="sectionWidth"
            @increase="onIncreaseQuantity" @decrease="onDecreaseQuantity" />
    </div>
</template>
<script setup lang="ts">
import {
    computed,
    defineProps,
    type CSSProperties,
} from 'vue';
import { useGondolaStore } from '@plannerate/store/gondola'; // <-- CORRIGIR PATH
import { useEditorStore } from '@plannerate/store/editor'; // <-- ADICIONAR
import type { Segment } from '@plannerate/types/segment';
import type { Shelf } from '@plannerate/types/shelves';
import type { Section } from '@plannerate/types/sections'; // <-- ADICIONAR IMPORT
import LayerComponent from './Layer.vue';

// Definir Props
const props = defineProps<{
    gondolaId: string | undefined;
    segment: Segment;
    shelf: Shelf;
    scaleFactor: number;
    sectionWidth: number;
}>();

const gondolaStore = useGondolaStore(); // Manter para sectionId
const editorStore = useEditorStore(); // <-- INSTANCIAR EDITOR STORE

const currentSectionId = computed(() => props.shelf.section_id);

/** Segment quantity (number of layers) */
const segmentQuantity = computed(() => {
    return props.segment.quantity;
});

// Computed para o estilo do segmento
// ----------------------------------------------------
// Computed Properties
// ----------------------------------------------------
/**
 * Calculate segment style based on properties and selection state
 */
const layerWidth = () => {
    let sectionWidth = props.sectionWidth;
    gondolaStore.currentGondola.value?.sections.map((section: Section) => {
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

const alignment = computed(() => {
    let alignment = gondolaStore.currentGondola?.alignment;
    gondolaStore.currentGondola?.sections.map((section: Section) => {
        if (section.id === currentSectionId.value) {
            if (section.alignment) {
                alignment = section.alignment;
            }
            section.shelves.map((shelf: Shelf) => {
                if (shelf.alignment) {
                    alignment = shelf.alignment;
                }
            });
        }
    });
    return alignment;
});

const segmentStyle = computed(() => {
    const layerHeight = props.segment.layer.product.height * props.scaleFactor;
    const productWidth = props.segment.layer.product.width;
    const productQuantity = props.segment.layer.quantity;
    let layerWidthFinal = 0;

    let currentAlignment = alignment.value; 

    console.log('alignment', currentAlignment);

    if (currentAlignment === 'justify') {
        layerWidthFinal = productWidth * productQuantity * props.scaleFactor + layerWidth();
    } else {
        layerWidthFinal = productWidth * productQuantity * props.scaleFactor;
    }

    const totalWidth = layerWidthFinal;
    const selectedStyle = {};

    return {
        height: `${layerHeight}px`,
        width: `${totalWidth}px`,
        marginBottom: `${props.shelf.shelf_height * props.scaleFactor}px`,
        ...selectedStyle,
    } as CSSProperties;
});

// Funções para ajustar a quantidade
const onIncreaseQuantity = (/* REMOVER: layer: Layer */) => {
    if (!props.gondolaId || !currentSectionId.value || !props.shelf.id || !props.segment.id || !props.segment.layer?.product?.id) {
        console.error("onIncreaseQuantity: IDs faltando para atualizar quantidade.");
        return;
    }
    const currentQuantity = props.segment.layer?.quantity ?? 0;
    const newQuantity = currentQuantity + 1;

    editorStore.updateLayerQuantity(
        props.gondolaId,
        currentSectionId.value,
        props.shelf.id,
        props.segment.id,
        props.segment.layer.product.id, // layerId
        newQuantity
    );

    // REMOVER: productStore.updateLayerQuantity(layer, newQuantity);
};

const onDecreaseQuantity = (/* REMOVER: layer: Layer */) => {
    if (!props.gondolaId || !currentSectionId.value || !props.shelf.id || !props.segment.id || !props.segment.layer?.product?.id) {
        console.error("onDecreaseQuantity: IDs faltando para atualizar quantidade.");
        return;
    }
    const currentQuantity = props.segment.layer?.quantity ?? 0;
    if (currentQuantity > 0) {
        const newQuantity = currentQuantity - 1;
        editorStore.updateLayerQuantity(
            props.gondolaId,
            currentSectionId.value,
            props.shelf.id,
            props.segment.id,
            props.segment.layer.product.id, // layerId
            newQuantity
        );
        // REMOVER: productStore.updateLayerQuantity(layer, newQuantity);
    }
};
</script>

<style scoped>
.segment {
    position: relative;
    transition: all 0.3s ease;
}
</style>
