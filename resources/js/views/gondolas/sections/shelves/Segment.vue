<template>
    <div class="segment drag-segment-handle group relative flex flex-col items-start" :style="outerSegmentStyle"
        @dragstart="onDragStart" @dragend="onDragEnd" draggable="true" :tabindex="segment.tabindex" 
        @dragenter.prevent="handleDragEnter" @dragover.prevent="handleDragOver"
        @dragleave="handleDragLeave" @drop.prevent="handleDrop"
        v-if="segment.layer && segment.layer.product">
        <div :style="innerSegmentStyle">
            <LayerComponent v-for="(_, index) in segmentQuantity" :key="index" :shelf="shelf" :segment="segment"
                :layer="segment.layer" :scale-factor="scaleFactor" :section-width="sectionWidth"
                :shelf-depth="shelf.shelf_depth" @increase="onIncreaseQuantity" @decrease="onDecreaseQuantity"
                @update-layer-quantity="updateLayerQuantity"> 
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
import { computed, defineProps, ref, type CSSProperties, watch } from 'vue';
import { toast } from 'vue-sonner';
import LayerComponent from './Layer.vue';

// Definir Props
const props = defineProps<{
    gondola: Gondola;
    segment: Segment;
    shelf: Shelf;
    scaleFactor: number;
    sectionWidth: number;
    isSegmentDragging?: boolean;
}>();

// Definir Emits
const emit = defineEmits(['drop-product', 'drop-products-multiple', 'drop-segment', 'drop-segment-copy', 'segment-drag-start', 'segment-drag-end', 'segment-drag-over']);

const editorStore = useEditorStore();

// Variáveis para drag and drop
const dragEnterCount = ref(0);
const dragSegmentActive = ref(false);
const segmentText = ref(`Arrastando Prateleira (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`);
watch(dragSegmentActive, (newValue) => {
  if (newValue) {
    segmentText.value = `Arrastando Prateleira (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`;
  } else {
    segmentText.value = `Arrastando Prateleira (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`;
  }
});

const currentSectionId = computed(() => props.shelf.section_id);
 
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
        return {
            width: '0px',
            height: '0px',
            marginBottom: '0px',
        } as CSSProperties;
    }

    const productWidth = props.segment.layer.product.width || 0;
    const productQuantity = props.segment.layer.quantity || 0;
    let layerWidthFinal = 0;
    // const currentAlignment = alignment.value;

    // if (currentAlignment === 'justify') {
    //     layerWidthFinal = props.sectionWidth * props.scaleFactor;
    // } else {
        layerWidthFinal = productWidth * productQuantity * props.scaleFactor;
    // }
    const totalWidth = layerWidthFinal;
    const layerHeight = (props.segment.layer.product.height || 0) * (props.segment.quantity || 0) * props.scaleFactor;
    const marginBottom = (props.shelf.shelf_height || 0) * props.scaleFactor;
    return {
        width: `${totalWidth}px`,
        height: `${layerHeight}px`, // Altura explícita
        marginBottom: `${marginBottom+4}px`,
        zIndex: props.isSegmentDragging ? -1 : 0, // Z-index baixo quando segment está sendo arrastado
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
        toast.error('Erro Interno', { description: 'Dados incompletos para diminuir quantidade.' });
        return;
    }
    if (props.sectionWidth === undefined || props.sectionWidth <= 0) { 
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

// Verifica especificamente se é um segment sendo arrastado
const isSegmentBeingDragged = (dataTransfer: DataTransfer | null): boolean => {
    if (!dataTransfer) return false;
    const types = dataTransfer.types;
    return types.includes('text/segment') || types.includes('text/segment/copy');
};

const handleDragEnter = (event: DragEvent) => {
    if (!isAcceptedDataType(event.dataTransfer)) {
        return;
    }

    event.preventDefault();
    dragEnterCount.value++;
    dragSegmentActive.value = true;
    segmentText.value = `Arrastando Prateleira (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`;
    
    // Verificar se é um segment sendo arrastado
    if (isSegmentBeingDragged(event.dataTransfer)) { 
        emit('segment-drag-over', props.segment, props.shelf, true);
    }
    
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
        segmentText.value = `Arrastando Prateleira (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`;
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
                // Emitir que o drag saiu do segment
                if (event.dataTransfer && isSegmentBeingDragged(event.dataTransfer)) { 
                    emit('segment-drag-over', props.segment, props.shelf, false);
                }
                if (event.currentTarget) {
                    (event.currentTarget as HTMLElement).classList.remove('drag-over-segment');
                }
            }
        }
    }
};

const handleDrop = (event: DragEvent) => {
    event.preventDefault();
    
    const currentTargetElement = event.currentTarget as HTMLElement | null;

    const resetVisualState = () => {
        dragEnterCount.value = 0;
        if (dragSegmentActive.value) {
            dragSegmentActive.value = false;
        }
        segmentText.value = `Arrastando Prateleira (Pos: ${props.shelf.shelf_position.toFixed(1)}cm)`;
        if (currentTargetElement) {
            currentTargetElement.classList.remove('drag-over-segment');
        }
    };

    if (!isAcceptedDataType(event.dataTransfer) || !event.dataTransfer) {
        resetVisualState();
        return;
    }

    try {
        const types = event.dataTransfer.types;
        const position = { x: event.offsetX, y: event.offsetY };

        if (types.includes('text/products-multiple')) {
            const productsData = event.dataTransfer.getData('text/products-multiple');
            if (!productsData) { console.error('handleDrop: productsData is empty!'); return; }
            const products = JSON.parse(productsData) as Product[];
            emit('drop-products-multiple', products, props.shelf, position);

        } else if (types.includes('text/product')) {
            const productData = event.dataTransfer.getData('text/product');
            if (!productData) { console.error('handleDrop: productData is empty!'); return; }
            const product = JSON.parse(productData) as Product;
            emit('drop-product', product, props.shelf, position);

        } else if (types.includes('text/segment')) {
            const segmentDataString = event.dataTransfer.getData('text/segment');
            if (!segmentDataString) { console.error('handleDrop: segmentData is empty!'); return; }
            const segmentData = JSON.parse(segmentDataString) as Segment;
            const originShelfId = segmentData?.shelf_id;

            if (originShelfId && originShelfId !== props.shelf.id) {
                emit('drop-segment', segmentData, props.shelf, position);
            }

        } else if (types.includes('text/segment/copy')) {
            const segmentDataCopy = event.dataTransfer.getData('text/segment/copy');
            if (!segmentDataCopy) { console.error('handleDrop: segmentDataCopy is empty!'); return; }
            const segment = JSON.parse(segmentDataCopy) as Segment;
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

    // Emitir evento para informar que o segment está sendo arrastado
    console.log('Segment: emitindo segment-drag-start', props.segment.id, props.shelf.id);
    emit('segment-drag-start', props.segment, props.shelf);
};

const onDragEnd = (event: DragEvent) => {
    // Emitir evento para informar que o drag do segment terminou
    console.log('Segment: emitindo segment-drag-end', props.segment.id, props.shelf.id);
    emit('segment-drag-end', props.segment, props.shelf);
};


</script>

<style scoped>
.segment {
    position: relative;
}
 


</style>
