<template>
    <div v-if="layer.product" class="layer group flex cursor-pointer justify-between" :style="layerStyle" @click="handleLayerClick"
        @keydown="handleKeyDown" :class="{ 'layer--selected': isSelected, 'layer--focused': !isSelected }">

        <ProductNormal v-for="index in layer.quantity" :key="index" :product="layer.product" :scale-factor="scaleFactor"
            :index="index" :shelf-depth="props.shelfDepth" :layer="layer">
            <template #depth-count v-if="index === 1">
                <slot name="depth-count"></slot>

            </template>
        </ProductNormal>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useEditorStore } from '@plannerate/store/editor';
import ProductNormal from '@plannerate/views/gondolas/sections/shelves/Product.vue';
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

const emit = defineEmits<{
    (e: 'increase', layer: LayerType): void;
    (e: 'decrease', layer: LayerType): void;
    (e: 'tab-navigation', data: { isLast: boolean, direction: 'next' | 'prev', currentTabIndex: number }): void;
    (e: 'layer-click', layer: LayerType): void;
    (e: 'update-layer-quantity', layer: LayerType): void;
}>();

//  
const editorStore = useEditorStore();

// Refs 
const layerQuantity = ref(props.layer.quantity || 1);
const segmentQuantity = ref(props.segment.quantity || 1);
const debounceTimer = ref<ReturnType<typeof setTimeout> | null>(null);
const segmentSelected = ref(false);
const editorGondola = computed(() => editorStore.getCurrentGondola);
const currentSectionId = computed(() => props.shelf.section_id);


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
        if (quantity > 1) {
            layerWidthFinal = `100%`;
        } else {
            layerWidthFinal = `${productWidth}px`;
        }
    }
    return {
        width: layerWidthFinal,
        height: `${layerHeight * props.scaleFactor}px`,
        zIndex: '0',
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


/**
 * Aumenta a quantidade de produtos no layer
 */
const onUpdateQuantity = async (quantity: number) => {
    // Usa selectedLayerIds
    if (editorStore.getSelectedLayerIds.size > 1) return;

    emit('update-layer-quantity', {
        ...props.layer,
        quantity: quantity,
    });
};
/**
 * Aumenta a quantidade de produtos no layer
 */
const onIncreaseQuantity = async () => {
    // Usa selectedLayerIds
    if (editorStore.getSelectedLayerIds.size > 1) return;

    emit('increase', {
        ...props.layer,
        quantity: (layerQuantity.value += 1),
    });
};

/**
 * Diminui a quantidade de produtos no layer
 */
const onDecreaseQuantity = async () => {
    // Usa selectedLayerIds
    if (editorStore.getSelectedLayerIds.size > 1) return;
    if (layerQuantity.value <= 1) return;
    const layerQuantityValue = (layerQuantity.value -= 1);
    if (layerQuantityValue === 0) return;
    emit('decrease', {
        ...props.layer,
        quantity: layerQuantityValue,
    });
}


const onIncreaseSegmentQuantity = () => {
    if (!editorGondola.value?.id || !currentSectionId.value || !props.shelf?.id || !props.segment?.id) {
        console.error("onIncreaseSegmentQuantity: IDs faltando para atualização.");
        return;
    }
    segmentQuantity.value += 1;
    editorStore.updateSegmentQuantity(
        editorGondola.value.id,
        currentSectionId.value,
        props.shelf.id,
        props.segment.id,
        segmentQuantity.value
    );
};

const onDecreaseSegmentQuantity = () => {
    if (!editorGondola.value?.id || !currentSectionId.value || !props.shelf?.id || !props.segment?.id) {
        console.error("onDecreaseSegmentQuantity: IDs faltando para atualização.");
        return;
    }
    if (segmentQuantity.value <= 1) return;
    segmentQuantity.value -= 1;
    editorStore.updateSegmentQuantity(
        editorGondola.value.id,
        currentSectionId.value,
        props.shelf.id,
        props.segment.id,
        segmentQuantity.value
    );
};
/**
 * Gerencia a navegação por teclado e teclas de ação
 */
const handleKeyDown = (event: KeyboardEvent) => {
    // Gerencia aumento/diminuição com setas quando selecionado
    if (isSelected.value) {
        const target = event?.target as HTMLElement;
        const isInput = target.tagName === 'INPUT' || target.tagName === 'TEXTAREA';
        // Vamos verificar se e um numero e passar o numero no incremant da quantity
        if (/^[1-9]$/.test(event.key)) {
            event.preventDefault();
            onUpdateQuantity(parseInt(event.key))
        } else if (event.key === 'ArrowRight' && !isInput) {
            event.preventDefault();
            onIncreaseQuantity();
        } else if (event.key === 'ArrowLeft' && !isInput) {
            event.preventDefault();
            onDecreaseQuantity();
        } else if (event.key === 'ArrowUp' && !isInput) {
            event.preventDefault();
            onIncreaseSegmentQuantity();
        } else if (event.key === 'ArrowDown' && !isInput) {
            event.preventDefault();
            onDecreaseSegmentQuantity();
        } else if (event.key === 'Delete' || event.key === 'Backspace' && !isInput) {
            event.preventDefault();
            if (editorGondola.value) {
                let sectionId = null;
                let shelfId = null;
                let segmentId = null;
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
            }
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