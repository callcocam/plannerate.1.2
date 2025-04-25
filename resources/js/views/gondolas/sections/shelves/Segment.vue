<template>
    <div
        class="segment drag-segment-handle group relative flex flex-col items-start"
        :style="outerSegmentStyle"
        @dragstart="onDragStart"
        draggable="true"
        :tabindex="segment.tabindex"
        v-if="segment.layer"
    >
        <div :style="innerSegmentStyle">
            <LayerComponent
                v-for="(_, index) in segmentQuantity"
                :key="index"
                :shelf="shelf"
                :segment="segment"
                :layer="segment.layer"
                :scale-factor="scaleFactor"
                :section-width="sectionWidth"
                :shelf-depth="shelf.shelf_depth"
                @increase="onIncreaseQuantity"
                @decrease="onDecreaseQuantity"
            >
                <template #depth-count>
                    <Label
                        :title="`Profundidade da prateleira: ${depthCount}`"
                        class="product-content-depth absolute -top-2 -left-2 z-10 flex h-3 w-3 cursor-help items-center justify-center rounded-full bg-gray-700 text-xs text-gray-100 dark:bg-gray-300 dark:text-gray-800"
                    >
                        {{ depthCount }}</Label
                    >
                </template>
            </LayerComponent>
        </div>
    </div>
</template>
<script setup lang="ts">
import { useEditorStore } from '@plannerate/store/editor';
import { Gondola } from '@plannerate/types/gondola';
import type { Segment } from '@plannerate/types/segment';
import type { Shelf } from '@plannerate/types/shelves';
import { validateShelfWidth } from '@plannerate/utils/validation';
import { computed, defineProps, type CSSProperties } from 'vue';
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

const editorStore = useEditorStore();

const currentSectionId = computed(() => props.shelf.section_id);

const depthCount = computed(() => {
    const depth = props.segment.layer.product.depth;
    if (!depth) return 0;
    return Math.round(props.shelf.shelf_depth / depth);
});

/** Segment quantity (number of layers) */
const segmentQuantity = computed(() => {
    console.log('segmentQuantity', props.segment.quantity);
    return props.segment?.quantity ?? 0;
});
const alignment = computed(() => props.gondola.alignment);

// Estilo para o container interno (conteúdo visual - Normal Shelf)
const innerSegmentStyle = computed(() => {
    const layerHeight = props.segment.layer.product.height * props.segment.quantity * props.scaleFactor;
    console.log('layerHeight', layerHeight);
    const selectedStyle = {};
    return {
        height: `${layerHeight}px`,
        width: '100%',
        ...selectedStyle,
    } as CSSProperties;
});

// Estilo para o container externo (manipulado pelo draggable - Normal Shelf)
const outerSegmentStyle = computed(() => {
    const productWidth = props.segment.layer.product.width;
    const productQuantity = props.segment.layer.quantity;
    let layerWidthFinal = 0;
    let currentAlignment = alignment.value;

    if (currentAlignment === 'justify') {
        layerWidthFinal = props.sectionWidth * props.scaleFactor;
        console.log('layerWidthFinal justify', layerWidthFinal);
    } else {
        layerWidthFinal = productWidth * productQuantity * props.scaleFactor;
    }
    const totalWidth = layerWidthFinal;
    const layerHeight = props.segment.layer.product.height * props.segment.quantity * props.scaleFactor;
    const marginBottom = props.shelf.shelf_height * props.scaleFactor;
    return {
        width: `${totalWidth}px`,
        height: `${layerHeight}px`, // Altura explícita
        marginBottom: `${marginBottom}px`,
    } as CSSProperties;
});

// Funções (onIncreaseQuantity, onDecreaseQuantity, onDragStart)
// Mantidas como estavam, pois não dependem do tipo hook/normal
const onIncreaseQuantity = () => {
    if (!props.gondola?.id || !currentSectionId.value || !props.shelf?.id || !props.segment?.id || !props.segment?.layer?.product?.id) {
        console.error('onIncreaseQuantity: IDs faltando para validação/atualização.');
        toast.error('Erro Interno', { description: 'Dados incompletos para aumentar quantidade.' });
        return;
    }
    if (props.sectionWidth === undefined || props.sectionWidth <= 0) {
        console.error('onIncreaseQuantity: Largura da seção (sectionWidth) inválida ou não fornecida.');
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
        props.gondola.id,
        currentSectionId.value,
        props.shelf.id,
        props.segment.id,
        props.segment.layer.product.id,
        newQuantity,
    );
};
const onDecreaseQuantity = () => {
    if (!props.gondola?.id || !currentSectionId.value || !props.shelf?.id || !props.segment?.id || !props.segment?.layer?.product?.id) {
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
            props.gondola.id,
            currentSectionId.value,
            props.shelf.id,
            props.segment.id,
            props.segment.layer.product.id,
            newQuantity,
        );
    }
};
const onDragStart = (event: DragEvent) => {
    if (!event.dataTransfer) return;

    const isCtrlOrMetaPressed = event.ctrlKey || event.metaKey;

    // Incluir explicitamente o shelf_id da origem
    const segmentData = {
        ...props.segment,
    };
    console.log('segmentData', segmentData);
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
</style>
