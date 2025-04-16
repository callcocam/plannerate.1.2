<template>
    <div
        class="layer group flex cursor-pointer items-center justify-around"
        :style="layerStyle"
        @click="handleLayerClick"
        @dragstart="onDragStart"
        draggable="true"
        :class="{ 'layer--selected': isSelected }"
    >
        <Product v-for="index in layer.quantity" :key="index" :product="layer.product" :scale-factor="scaleFactor" :product-spacing="layerSpacing" />
    </div>
</template>
<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useProductStore } from '../../../store/product'; // Corrected relative path
import Product from './Product.vue'; // Importando o novo componente
import { LayerSegment, Segment } from './types';

const props = defineProps<{
    layer: LayerSegment;
    segment: Segment;
    scaleFactor: number;
    sectionWidth: number;
}>();

const emit = defineEmits<{
    (e: 'increase', layer: LayerSegment): void;
    (e: 'decrease', layer: LayerSegment): void;
    (e: 'spacingIncrease', layer: LayerSegment): void;
    (e: 'spacingDecrease', layer: LayerSegment): void;
}>();

const productStore = useProductStore();

const layerSpacing = ref(props.layer.spacing);
const layerQuantity = ref(props.layer.quantity || 1);
const debounceTimer = ref<ReturnType<typeof setTimeout> | null>(null);
const segmentSelected = ref(false);

const layerStyle = computed(() => {
    const layerHeight = props.layer.product.height;
    // Calculamos a largura total, mas a renderização dos produtos será
    // responsabilidade do componente ProductGroup

    return {
        width: `100%`,
        height: `${layerHeight * props.scaleFactor}px`,
        zIndex: '2',
    };
});

// Computed property to check if this layer's product is selected
const isSelected = computed(() => {
    if (!props.layer.product?.id) return false;
    const productId = props.layer.product?.id ;
    const layerId = props.layer.id;
    // Ensure ID is treated as string for the Set comparison
    return productStore.isSelectedProductIds.has(String(productId).concat('-').concat(layerId));
});

// Click handler function
const handleLayerClick = (event: MouseEvent) => {
    const productId = props.layer.product?.id ;
    const layerId = props.layer.id;
    if (!productId) {
        console.error('Layer clicked, but product ID is missing.');
        return;
    }
    const isCtrlOrMetaPressed = event.ctrlKey || event.metaKey;
    const productIdAsString = String(productId); // Convert ID to string once

    if (isCtrlOrMetaPressed) {
        // Toggle selection for this product (adds if not present, removes if present)
        segmentSelected.value = !segmentSelected.value; // Toggle the segment selection
        productStore.toggleProductSelection(productIdAsString);
        productStore.isToggleSelectedProduct(productIdAsString.concat('-').concat(layerId));
    } else {
        // Check current selection state for the clicked product
        const isCurrentlySelected = productStore.isSelectedProductIds.has(productIdAsString);
        const selectionSize = productStore.isSelectedProductIds.size;

        if (isCurrentlySelected && selectionSize === 1) {
            // Clicked on the item that was already the only selected item -> Deselect it
            productStore.clearSelection();
            segmentSelected.value = false; // Set the segment as selected
        } else {
            // Clicked on an unselected item, or on one of multiple selected items
            // -> Clear previous selection and select only this one
            productStore.clearSelection();
            productStore.selectProduct(productIdAsString);
            productStore.isSelectedProduct(productIdAsString.concat('-').concat(layerId));
        }
    }
};
// Function to handle drag start event
const onDragStart = (event: DragEvent) => {
    // Adicionar lógica para quando a prateleira está sendo arrastada
    if (event.dataTransfer) {
        // Vamos verificar se a tecla Ctrl ou Meta está pressionada
        const isCtrlOrMetaPressed = event.ctrlKey || event.metaKey; 
        if (isCtrlOrMetaPressed) {
            // Se a tecla Ctrl ou Meta estiver pressionada, vamos permitir o movimento
            event.dataTransfer.effectAllowed = 'copy';
            event.dataTransfer.setData(
                'text/layer/copy',
                JSON.stringify({
                    ...props.layer,
                    segment: props.segment,
                }),
            );
        } else {
            // Caso contrário, vamos permitir apenas a cópia
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData(
                'text/layer',
                JSON.stringify({
                    ...props.layer,
                    segment: props.segment,
                }),
            );
        }
    }
};

// Function to increase quantity
const onIncreaseQuantity = async () => {
    layerSpacing.value = props.layer.spacing;
    if (productStore.selectedProductIds.size > 1) {
        return;
    }
    emit('increase', {
        ...props.layer,
        quantity: (layerQuantity.value += 1),
    });
};
// Function to decrease quantity
const onDecreaseQuantity = async () => {
    if (productStore.selectedProductIds.size > 1) {
        return;
    }
    if (layerQuantity.value > 1) {
        layerSpacing.value = props.layer.spacing;
        emit('decrease', {
            ...props.layer,
            quantity: (layerQuantity.value -= 1),
        });
    }
};
// ----------------------------------------------------
// Lifecycle hooks
// ----------------------------------------------------
// Registra o ouvinte de eventos quando o componente é montado
onMounted(() => {
    // Adiciona listener de teclado para o documento inteiro
    document.addEventListener('keydown', async (event) => {
        if (isSelected.value) {
            if (event.key === 'ArrowRight') {
                event.preventDefault();
                await onIncreaseQuantity();
            } else if (event.key === 'ArrowLeft') {
                event.preventDefault();
                await onDecreaseQuantity();
            }
        }
    });
});

// Remove o ouvinte de eventos quando o componente é desmontado
onUnmounted(() => {
    if (debounceTimer.value) clearTimeout(debounceTimer.value);
});
</script>

<style scoped>
.layer--selected {
    /* Add styles for selected layer */
    border: 2px solid blue;
    box-shadow: 0 0 5px rgba(0, 0, 255, 0.5);
    /* Ensure the border doesn't affect layout drastically */
    box-sizing: border-box;
}
</style>
