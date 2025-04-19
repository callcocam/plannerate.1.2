<template>
    <div class="layer group flex cursor-pointer justify-around " :style="layerStyle" @click="handleLayerClick"
        @keydown="handleKeyDown" :class="{ 'layer--selected': isSelected, 'layer--focused': !isSelected }">
        <Product v-for="index in layer.quantity" :key="index" :product="layer.product" :scale-factor="scaleFactor"
            :index="index" :shelf-depth="props.shelfDepth" />
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useGondolaStore } from '@plannerate/store/gondola';
import { useProductStore } from '@plannerate/store/product';
import Product from '@plannerate/views/gondolas/sections/Product.vue';
import { Layer as LayerType, Segment as SegmentType } from '@/types/segment';

const props = defineProps<{
    layer: LayerType;
    segment: SegmentType;
    scaleFactor: number;
    sectionWidth: number;
    shelfDepth: number;
}>();

const emit = defineEmits<{
    (e: 'increase', layer: LayerType): void;
    (e: 'decrease', layer: LayerType): void;
    (e: 'tab-navigation', data: { isLast: boolean, direction: 'next' | 'prev', currentTabIndex: number }): void;
    (e: 'layer-click', layer: LayerType): void;
}>();

// Stores
const productStore = useProductStore();
const gondolaStore = useGondolaStore();

// Refs
const layerSpacing = ref(props.layer.spacing);
const layerQuantity = ref(props.layer.quantity || 1);
const debounceTimer = ref<ReturnType<typeof setTimeout> | null>(null);
const segmentSelected = ref(false);

/**
 * Computed style para o layer baseado em alinhamento e dimensões
 */
const layerStyle = computed(() => {
    const layerHeight = props.layer.product.height;
    const productWidth = props.layer.product.width * props.scaleFactor;
    const quantity = props.layer.quantity || 1;
    let layerWidthFinal = `100%`;

    if (gondolaStore.getAligmentLeft()) {
        layerWidthFinal = `${productWidth * quantity}px`;
    } else if (gondolaStore.getAligmentRight()) {
        layerWidthFinal = `${productWidth * quantity}px`;
    } else if (gondolaStore.getAligmentCenter() || gondolaStore.getAligmentJustify()) {
        layerWidthFinal = `100%`;
    } else {
        layerWidthFinal = `${productWidth * quantity}px`;
    }

    return {
        width: layerWidthFinal,
        height: `${layerHeight * props.scaleFactor}px`,
        zIndex: '2',
    };
});

/**
 * Verifica se o layer está selecionado
 */
const isSelected = computed(() => {
    if (!props.layer.product?.id) return false;
    const productId = props.layer.product.id;
    const layerId = props.layer.id;
    // Concatena IDs para comparação na Set
    return productStore.isSelectedProductIds.has(String(productId).concat('-').concat(layerId));
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

    const productIdAsString = String(productId);
    const compositeId = productIdAsString.concat('-').concat(layerId);

    if (isCtrlOrMetaPressed) {
        // Alternar seleção para este produto
        segmentSelected.value = !segmentSelected.value;
        productStore.toggleProductSelection(productIdAsString);
        productStore.isToggleSelectedProduct(compositeId);
    } else {
        // Verifica o estado atual de seleção
        const isCurrentlySelected = productStore.isSelectedProductIds.has(compositeId);
        const selectionSize = productStore.isSelectedProductIds.size;

        if (isCurrentlySelected && selectionSize === 1) {
            // Desselecionar se já for o único item selecionado
            productStore.clearSelection();
            segmentSelected.value = false;
        } else {
            // Limpar seleção anterior e selecionar apenas este
            productStore.clearSelection();
            productStore.selectProduct(productIdAsString);
            productStore.isSelectedProduct(compositeId);
            segmentSelected.value = true;
        }
    }
};


/**
 * Aumenta a quantidade de produtos no layer
 */
const onIncreaseQuantity = async () => {
    if (productStore.selectedProductIds.size > 1) return;

    emit('increase', {
        ...props.layer,
        quantity: (layerQuantity.value += 1),
    });
};

/**
 * Diminui a quantidade de produtos no layer
 */
const onDecreaseQuantity = async () => {
    if (productStore.selectedProductIds.size > 1) return;
    console.log('layerQuantity.value', layerQuantity.value);
    if (layerQuantity.value <= 1) return;
    const layerQuantityValue = (layerQuantity.value -= 1);
    if (layerQuantityValue === 0) return;
    emit('decrease', {
        ...props.layer,
        quantity: layerQuantityValue,
    });
}


/**
 * Gerencia a navegação por teclado e teclas de ação
 */
const handleKeyDown = (event: KeyboardEvent) => {
    // Gerencia aumento/diminuição com setas quando selecionado
    if (isSelected.value) {
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            onIncreaseQuantity();
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            onDecreaseQuantity();
        }
    }
};
// Lifecycle hooks
onMounted(() => {
    // Não precisamos mais do listener global, pois movemos a lógica para handleKeyDown
    document.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    if (debounceTimer.value) clearTimeout(debounceTimer.value);
    document.removeEventListener('keydown', handleKeyDown);
});
</script>

<style scoped>
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