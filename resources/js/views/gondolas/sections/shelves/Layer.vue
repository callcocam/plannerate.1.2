<template>
    <div v-if="layer.product && getQuantity" class="layer group flex cursor-pointer" :style="layerStyle" @click="handleLayerClick"
        @keydown="handleKeyDown">
        <ProductNormal v-for="index in getQuantity" :key="index" :product="layer.product" :scale-factor="scaleFactor"
            :index="index" :shelf-depth="props.shelfDepth" :layer="layer" />
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
const props = defineProps<{
    layer: LayerType;
    segment: SegmentType;
    shelf: Shelf;
    scaleFactor: number;
    sectionWidth: number;
    shelfDepth: number;
    isTargetStockViewActive?: boolean;
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

const getQuantity = computed(() => {
    return props.layer?.quantity || 1;
});

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
    if (props.layer) {
        if (!props.layer?.product) {
            console.warn('Layer.vue: layer.product está null/undefined', props.layer);
            return {
                width: '0px',
                height: '0px',
                zIndex: '0',
            };
        }
    }

    const layerHeight = props.layer?.product?.height || 0;
    const productWidth = (props.layer?.product?.width || 0) * props.scaleFactor;
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
    const newQuantity = (layerQuantity.value += 1);
    const validation = validateShelfWidth(props.shelf, props.sectionWidth, props.layer?.product?.id, newQuantity, null);
    if (!validation.isValid) {
        toast.error('Limite de Largura Excedido', {
            description: `A largura total (${validation.totalWidth.toFixed(1)}cm) excederia a largura da seção (${validation.sectionWidth}cm).`,
        });
        layerQuantity.value -= 1;
        return;
    }

    emit('increase', {
        ...props.layer,
        quantity: newQuantity,
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
    console.log("Increasing segment quantity to:", segmentQuantity.value);
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
        if (/^[1-9]$/.test(event.key) && !isInput) {
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

.A {
    background-color: #00ff00;
}

.B {
    background-color: #0000ff;
}

.C {
    background-color: #ff0000;
}
</style>