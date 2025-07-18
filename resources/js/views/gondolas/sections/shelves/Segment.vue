<template>
    <div class="segment drag-segment-handle group relative flex flex-col items-start" :style="outerSegmentStyle"
        @dragstart="onDragStart" draggable="true" :tabindex="segment.tabindex" 
        @dragenter.prevent="handleDragEnter" @dragover.prevent="handleDragOver"
        @dragleave="handleDragLeave" @drop.prevent="handleDrop"
        v-if="segment.layer && segment.layer.product">
        <div :style="innerSegmentStyle">
            <LayerComponent v-for="(_, index) in segmentQuantity" :key="index" :shelf="shelf" :segment="segment"
                :layer="segment.layer" :scale-factor="scaleFactor" :section-width="sectionWidth"
                :shelf-depth="shelf.shelf_depth" @increase="onIncreaseQuantity" @decrease="onDecreaseQuantity"
                @update-layer-quantity="updateLayerQuantity">
                <!-- <template #depth-count>
                    <Label :title="`Profundidade da prateleira: ${depthCount}`"
                        class="product-content-depth absolute -top-2 -left-2 z-10 flex h-3 w-3 cursor-help items-center justify-center rounded-full bg-gray-700 text-xs text-gray-100 dark:bg-gray-300 dark:text-gray-800">
                        {{ depthCount }}</Label>
                </template> -->
            </LayerComponent>
        </div>
    </div>
</template>
<script setup lang="ts">
import { useEditorStore } from '@plannerate/store/editor';
import { Gondola } from '@plannerate/types/gondola';
import type { Layer, Segment, Product } from '@plannerate/types/segment';
import type { Shelf } from '@plannerate/types/shelves';
import { validateShelfWidth } from '@plannerate/utils/validation';
import { computed, defineProps, ref, type CSSProperties } from 'vue';
import { toast } from 'vue-sonner';
import LayerComponent from './Layer.vue';

// Definir Props
const props = defineProps<{
    gondola: Gondola;
    segment: Segment;
    shelf: Shelf;
    scaleFactor: number;
    sectionWidth: number;
}>();

// Definir Emits
const emit = defineEmits(['drop-product', 'drop-products-multiple', 'drop-segment', 'drop-segment-copy']);

const editorStore = useEditorStore();

// Variáveis para drag and drop
const dragEnterCount = ref(0);
const dragSegmentActive = ref(false);

const currentSectionId = computed(() => props.shelf.section_id);

const depthCount = computed(() => {
    // Verificações de segurança para evitar erros de null/undefined
    if (!props.segment?.layer?.product) {
        return 0;
    }
    
    const depth = props.segment.layer.product.depth;
    if (!depth) return 0;
    
    const shelfDepth = props.shelf.shelf_depth || 0;
    return Math.round(shelfDepth / depth);
});

/** Segment quantity (number of layers) */
const segmentQuantity = computed(() => {
    return props.segment?.quantity ?? 0;
});
const alignment = computed(() => editorStore.getCurrentGondola?.alignment);

// Estilo para o container interno (conteúdo visual - Normal Shelf)
const innerSegmentStyle = computed(() => {
    // Verificações de segurança para evitar erros de null/undefined
    if (!props.segment?.layer?.product) {
        return {
            height: '0px',
            width: '100%',
        } as CSSProperties;
    }

    const layerHeight = (props.segment.layer.product.height || 0) * (props.segment.quantity || 0) * props.scaleFactor;
    const selectedStyle = {};
    return {
        height: `${layerHeight}px`,
        width: '100%',
        ...selectedStyle,
    } as CSSProperties;
});

// Estilo para o container externo (manipulado pelo draggable - Normal Shelf)
const outerSegmentStyle = computed(() => {
    // Verificações de segurança para evitar erros de null/undefined
    if (!props.segment?.layer?.product) {
        console.warn('Segment.vue: layer.product está null/undefined', props.segment);
        return {
            width: '0px',
            height: '0px',
            marginBottom: '0px',
        } as CSSProperties;
    }

    const productWidth = props.segment.layer.product.width || 0;
    const productQuantity = props.segment.layer.quantity || 0;
    let layerWidthFinal = 0;
    const currentAlignment = alignment.value;

    if (currentAlignment === 'justify') {
        layerWidthFinal = props.sectionWidth * props.scaleFactor;
    } else {
        layerWidthFinal = productWidth * productQuantity * props.scaleFactor;
    }
    const totalWidth = layerWidthFinal;
    const layerHeight = (props.segment.layer.product.height || 0) * (props.segment.quantity || 0) * props.scaleFactor;
    const marginBottom = (props.shelf.shelf_height || 0) * props.scaleFactor;
    return {
        width: `${totalWidth}px`,
        height: `${layerHeight}px`, // Altura explícita
        marginBottom: `${marginBottom+4}px`,
    } as CSSProperties;
});
const updateLayerQuantity = (layer: Layer) => {
    if (!editorStore.getCurrentGondola?.id || !currentSectionId.value || !props.shelf?.id || !props.segment?.id || !props.segment?.layer?.product?.id) {
        toast.error('Erro Interno', { description: 'Dados incompletos para aumentar quantidade.' });
        return;
    }
    if (props.sectionWidth === undefined || props.sectionWidth <= 0) {
        toast.error('Erro Interno', { description: 'Largura da seção inválida.' });
        return;
    }

    const newQuantity = layer.quantity;


    const validation = validateShelfWidth(props.shelf, props.sectionWidth, props.segment.layer.product.id, newQuantity, null);

    if (!validation.isValid) {
        toast.error('Limite de Largura Excedido', {
            description: `A largura total (${validation.totalWidth.toFixed(1)}cm) excederia a largura da seção (${validation.sectionWidth}cm).`,
        });
        return;
    }

    editorStore.updateLayerQuantity(
        editorStore.getCurrentGondola?.id,
        currentSectionId.value,
        props.shelf.id,
        props.segment.id,
        props.segment.layer.product.id,
        newQuantity,
    );
}
// Funções (onIncreaseQuantity, onDecreaseQuantity, onDragStart)
// Mantidas como estavam, pois não dependem do tipo hook/normal
const onIncreaseQuantity = () => {
    if (!editorStore.getCurrentGondola?.id || !currentSectionId.value || !props.shelf?.id || !props.segment?.id || !props.segment?.layer?.product?.id) {
        toast.error('Erro Interno', { description: 'Dados incompletos para aumentar quantidade.' });
        return;
    }
    if (props.sectionWidth === undefined || props.sectionWidth <= 0) {
        toast.error('Erro Interno', { description: 'Largura da seção inválida.' });
        return;
    }

    const currentQuantity = props.segment.layer?.quantity ?? 0;
    const newQuantity = currentQuantity + 1;

    const validation = validateShelfWidth(props.shelf, props.sectionWidth, props.segment.layer.product.id, newQuantity, null);

    if (!validation.isValid) {
        toast.error('Limite de Largura Excedido', {
            description: `A largura total (${validation.totalWidth.toFixed(1)}cm) excederia a largura da seção (${validation.sectionWidth}cm).`,
        });
        return;
    }

    editorStore.updateLayerQuantity(
        editorStore.getCurrentGondola?.id,
        currentSectionId.value,
        props.shelf.id,
        props.segment.id,
        props.segment.layer.product.id,
        newQuantity,
    );
};
const onDecreaseQuantity = () => {
    if (!editorStore.getCurrentGondola?.id || !currentSectionId.value || !props.shelf?.id || !props.segment?.id || !props.segment?.layer?.product?.id) {
        console.error('onDecreaseQuantity: IDs faltando para validação/atualização.');
        toast.error('Erro Interno', { description: 'Dados incompletos para diminuir quantidade.' });
        return;
    }
    if (props.sectionWidth === undefined || props.sectionWidth <= 0) {
        console.error('onDecreaseQuantity: Largura da seção (sectionWidth) inválida ou não fornecida.');
        toast.error('Erro Interno', { description: 'Largura da seção inválida.' });
        return;
    }

    const currentQuantity = props.segment.layer?.quantity ?? 0;
    if (currentQuantity > 0) {
        const newQuantity = currentQuantity - 1;

        const validation = validateShelfWidth(props.shelf, props.sectionWidth, props.segment.layer.product.id, newQuantity, null);

        if (!validation.isValid) {
            toast.error('Limite de Largura Excedido', {
                description: `Ocorreu um erro ao validar a largura após diminuir. (${validation.totalWidth.toFixed(1)}cm > ${validation.sectionWidth}cm)`,
            });
            return;
        }

        editorStore.updateLayerQuantity(
            editorStore.getCurrentGondola?.id,
            currentSectionId.value,
            props.shelf.id,
            props.segment.id,
            props.segment.layer.product.id,
            newQuantity,
        );
    }
};
// --- Lógica de Drag and Drop (para receber drops) ---

// Verifica apenas se o TIPO de dado arrastado é aceitável
const isAcceptedDataType = (dataTransfer: DataTransfer | null): boolean => {
    if (!dataTransfer) return false;
    const types = dataTransfer.types;
    return types.includes('text/product') || 
           types.includes('text/products-multiple') || 
           types.includes('text/segment') || 
           types.includes('text/segment/copy');
};

const handleDragEnter = (event: DragEvent) => {
    if (!isAcceptedDataType(event.dataTransfer)) {
        return;
    }

    event.preventDefault();
    dragEnterCount.value++;
    dragSegmentActive.value = true;
    
    if (event.currentTarget) {
        (event.currentTarget as HTMLElement).classList.add('drag-over-segment');
    }
};

const handleDragOver = (event: DragEvent) => {
    if (!isAcceptedDataType(event.dataTransfer)) {
        if (event.dataTransfer) event.dataTransfer.dropEffect = 'none';
        if (dragSegmentActive.value) {
            dragSegmentActive.value = false;
            dragEnterCount.value = 0;
            if (event.currentTarget) {
                (event.currentTarget as HTMLElement).classList.remove('drag-over-segment');
            }
        }
        return;
    }

    event.preventDefault();

    if (!dragSegmentActive.value) {
        dragSegmentActive.value = true;
        if (event.currentTarget) {
            (event.currentTarget as HTMLElement).classList.add('drag-over-segment');
        }
    }

    if (event.dataTransfer) {
        let effect: DataTransfer["dropEffect"] = 'move';
        if (event.dataTransfer.types.includes('text/segment/copy') || 
            event.dataTransfer.types.includes('text/product') ||
            event.dataTransfer.types.includes('text/products-multiple')) {
            effect = 'copy';
        }
        event.dataTransfer.dropEffect = effect;
    }
};

const handleDragLeave = (event: DragEvent) => {
    if (dragEnterCount.value > 0) {
        dragEnterCount.value--;
        if (dragEnterCount.value === 0) {
            if (dragSegmentActive.value) {
                dragSegmentActive.value = false;
                if (event.currentTarget) {
                    (event.currentTarget as HTMLElement).classList.remove('drag-over-segment');
                }
            }
        }
    }
};

const handleDrop = (event: DragEvent) => {
    event.preventDefault();
    console.log('🔵 Segment.vue: handleDrop chamado!');
    
    const currentTargetElement = event.currentTarget as HTMLElement | null;

    const resetVisualState = () => {
        dragEnterCount.value = 0;
        if (dragSegmentActive.value) {
            dragSegmentActive.value = false;
        }
        if (currentTargetElement) {
            currentTargetElement.classList.remove('drag-over-segment');
        }
    };

    if (!isAcceptedDataType(event.dataTransfer) || !event.dataTransfer) {
        console.log('❌ Segment.vue: Tipo de dados não aceito ou dataTransfer vazio');
        resetVisualState();
        return;
    }

    try {
        const types = event.dataTransfer.types;
        const position = { x: event.offsetX, y: event.offsetY };
        
        console.log('🔵 Segment.vue: Tipos detectados:', types);
        console.log('🔵 Segment.vue: Posição do drop:', position);

        if (types.includes('text/products-multiple')) {
            const productsData = event.dataTransfer.getData('text/products-multiple');
            if (!productsData) { console.error('handleDrop: productsData is empty!'); return; }
            const products = JSON.parse(productsData) as Product[];
            console.log('🔵 Segment.vue: Emitindo drop-products-multiple com', products.length, 'produtos');
            emit('drop-products-multiple', products, props.shelf, position);

        } else if (types.includes('text/product')) {
            const productData = event.dataTransfer.getData('text/product');
            if (!productData) { console.error('handleDrop: productData is empty!'); return; }
            const product = JSON.parse(productData) as Product;
            console.log('🔵 Segment.vue: Emitindo drop-product:', product.name || product.id);
            emit('drop-product', product, props.shelf, position);

        } else if (types.includes('text/segment')) {
            const segmentDataString = event.dataTransfer.getData('text/segment');
            if (!segmentDataString) { console.error('handleDrop: segmentData is empty!'); return; }
            const segmentData = JSON.parse(segmentDataString) as Segment;
            const originShelfId = segmentData?.shelf_id;

            console.log('🔵 Segment.vue: Origin shelf ID:', originShelfId, 'Current shelf ID:', props.shelf.id);

            if (originShelfId && originShelfId !== props.shelf.id) {
                console.log('🔵 Segment.vue: Emitindo drop-segment');
                emit('drop-segment', segmentData, props.shelf, position);
            } else {
                console.log('🔵 Segment.vue: Mesma prateleira, ignorando drop');
            }

        } else if (types.includes('text/segment/copy')) {
            const segmentDataCopy = event.dataTransfer.getData('text/segment/copy');
            if (!segmentDataCopy) { console.error('handleDrop: segmentDataCopy is empty!'); return; }
            const segment = JSON.parse(segmentDataCopy) as Segment;
            console.log('🔵 Segment.vue: Emitindo drop-segment-copy');
            emit('drop-segment-copy', segment, props.shelf, position);
        }

    } catch (e) {
        console.error("handleDrop: Error processing dropped data:", e);
    } finally {
        resetVisualState();
    }
};

const onDragStart = (event: DragEvent) => {
    if (!event.dataTransfer) return;

    const isCtrlOrMetaPressed = event.ctrlKey || event.metaKey;

    // Incluir explicitamente o shelf_id da origem
    const segmentData = {
        ...props.segment,
        shelf_id: props.shelf.id, // Garantir que o shelf_id está incluído
    };
    
    console.log('🔵 Segment.vue: onDragStart - segmentData:', segmentData);
    
    if (isCtrlOrMetaPressed) {
        // Copiar (quando Ctrl/Meta está pressionado)
        event.dataTransfer.effectAllowed = 'copy';
        // Use o tipo MIME correto para cópia
        event.dataTransfer.setData('text/segment/copy', JSON.stringify(segmentData));
    } else {
        // Mover (comportamento padrão)
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/segment', JSON.stringify(segmentData));
    }
};
</script>

<style scoped>
.segment {
    position: relative;
}

.segment--selected {
    border: 2px solid blue;
    box-shadow: 0 0 5px rgba(0, 0, 255, 0.5);
    box-sizing: border-box;
}

.segment--focused {
    outline: 1px solid transparent;
    outline-offset: 2px;
}

.segment--focused:focus {
    outline: 1px solid blue;
    outline-offset: 2px;
}

.drag-over-segment {
    background-color: rgba(2, 16, 39, 0.1);
    border-color: rgba(13, 65, 150, 0.5);
    border-width: 2px;
    border-style: dashed;
    border-radius: 4px;
    box-shadow: 0 0 10px rgba(59, 130, 246, 0.5);
    transition:
        border-color 0.2s ease-in-out,
        background-color 0.2s ease-in-out;
    cursor: grab;
    z-index: 9999 !important;
}
</style>
