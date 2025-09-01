<template>
    <div v-if="layer.product" class="layer group flex cursor-pointer" :style="layerStyle" @click="handleLayerClick"
       :class="{ 'layer--selected': isSelected, 'layer--focused': !isSelected }">

        <ProductNormal v-for="index in layer.quantity" :key="index" :product="layer.product" :scale-factor="scaleFactor"
            :index="index" :shelf-depth="props.shelfDepth" :layer="layer">
            <template #depth-count v-if="index === 1">
                <slot name="depth-count"></slot>

            </template>
        </ProductNormal>
    </div>
</template>

<script setup lang="ts">
import { computed, CSSProperties,  ref } from 'vue';
import { useEditorStore } from '@plannerate/store/editor';
import ProductNormal from '@plannerate/views/qr/gondolas/sections/shelves/Product.vue';
import { Layer as LayerType, Segment as SegmentType } from '@/types/segment';
import { Shelf } from '@plannerate/types/shelves';
const props = defineProps<{
    layer: LayerType;
    segment: SegmentType;
    shelf: Shelf;
    scaleFactor: number;
    sectionWidth: number;
    shelfDepth: number;
}>();

//  
const editorStore = useEditorStore();

const segmentSelected = ref(false);


/**
 * Computed style para o layer baseado em alinhamento e dimensões
 */
const layerStyle = computed(() => {
    // Verificações de segurança para evitar erros de null/undefined
    if (!props.layer?.product) {
        console.warn('Layer.vue: layer.product está null/undefined', props.layer);
        return {
            width: '0px',
            height: '0px',
            zIndex: '0',
        };
    }

    const layerHeight = props.layer.product.height || 0;
    const productWidth = (props.layer.product.width || 0) * props.scaleFactor;
    const quantity = props.layer.quantity || 1;
    let layerWidthFinal = `100%`; // Default para justify ou se não houver gôndola/alinhamento

    const otherStyles: CSSProperties = {
        display: 'flex', 
        justifyContent: 'space-around',
        position: 'relative',
    };

    // Obtém o alinhamento da gôndola atual do editorStore
    const alignment = editorStore.getCurrentGondola?.alignment; 
    // Define a largura final com base no alinhamento
    if (alignment === 'left' || alignment === 'right') {
        // Para alinhamento à esquerda ou direita, usa a largura calculada dos produtos
        layerWidthFinal = `${productWidth * quantity}px`;
    } else if (alignment === 'center') {
        // Para centralizado, ocupa 100% (o CSS tratará a centralização interna)
        layerWidthFinal = `${productWidth * quantity}px`;
    } else if (alignment === 'justify' || !alignment) {
        // Para justificado ou sem alinhamento definido, ocupa 100%
         layerWidthFinal = `100%`;
    }
    return {
        width: layerWidthFinal,
        height: `${layerHeight * props.scaleFactor}px`,
        zIndex: '0',
        ...otherStyles,
    };
});

/**
 * Verifica se o layer está selecionado
 */
const isSelected = computed(() => {
    const layerId = props.layer.id;
    // Usa selectedLayerIds (nome corrigido e agora existente)
    return editorStore.isSelectedLayer(String(layerId));
});

/**
 * Manipula o clique no layer
 */
const handleLayerClick = (event: MouseEvent) => {
    const productId = props.layer.product?.id;
    const layerId = props.layer.id;
    if (!productId) {
        console.error('Layer clicked, but product ID is missing.');
        return;
    }
    const isCtrlOrMetaPressed = event.ctrlKey || event.metaKey;
    handleSelectedLayer(isCtrlOrMetaPressed, productId, layerId);
    // emit('layer-click', props.layer);
};

/**
 * Gerencia a seleção do layer
 */
const handleSelectedLayer = (isCtrlOrMetaPressed: boolean, productId: string, layerId: string) => {
    if (!productId) return;

    const layerIdAsString = String(layerId);
    const compositeId = layerIdAsString;

    if (isCtrlOrMetaPressed) {
        // Alternar seleção para este produto
        segmentSelected.value = !segmentSelected.value;
        // Ação toggleLayerSelection ainda não existe, comentando por enquanto
        editorStore.toggleLayerSelection(layerIdAsString);
    } else {
        // Verifica o estado atual de seleção
        const isCurrentlySelected = editorStore.isSelectedLayer(compositeId); // Usa selectedLayerIds
        const selectionSize = editorStore.getSelectedLayerIds.size; // Usa selectedLayerIds 
        if (isCurrentlySelected && selectionSize === 1) {
            // Desselecionar se já for o único item selecionado
            // Ação clearSelection ainda não existe, comentando por enquanto
            editorStore.clearLayerSelection();
            segmentSelected.value = false;
        } else {
            // Limpar seleção anterior e selecionar apenas este
            // Ações clearSelection e selectLayer ainda não existem, comentando por enquanto
            editorStore.clearLayerSelection();
            editorStore.selectLayer(layerIdAsString);
            segmentSelected.value = true;
        }
    }
};





</script>

<style scoped>
.layer {
    border: 2px solid transparent;
}

.layer--selected {
    border: 2px solid blue;
    box-shadow: 0 0 5px rgba(0, 0, 255, 0.5);
    box-sizing: border-;
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

