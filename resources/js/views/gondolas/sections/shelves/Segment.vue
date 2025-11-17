<template>
    <div class="segment drag-segment-handle group relative flex flex-col items-start" :class="{
        'segment--selected': isSelected, 'segment--focused': !isSelected
    }" :style="outerSegmentStyle" @dragstart="onDragStart" @dragend="onDragEnd" draggable="true"
        :tabindex="segment.tabindex" @dragenter.prevent="handleDragEnter" @dragover.prevent="handleDragOver"
        @dragleave="handleDragLeave" @drop.prevent="handleDrop" v-if="segment.layer && segment.layer.product">
        <div :style="innerSegmentStyle">
            <LayerComponent v-for="(_, index) in segmentQuantity" :key="index" :shelf="shelf" :segment="segment"
                :layer="segment.layer" :scale-factor="scaleFactor" :section-width="sectionWidth"
                :shelf-depth="shelf.shelf_depth || null" :is-target-stock-view-active="isTargetStockViewActive"
                @increase="onIncreaseQuantity" @decrease="onDecreaseQuantity"
                @update-layer-quantity="updateLayerQuantity">
            </LayerComponent>
        </div>

        <div v-if="productClassification" class="absolute -top-4 -left-2 m-1 px-1 text-xs font-bold text-white rounded z-50"
            :class="classificationBadgeClass" :title="classificationTitle">
            {{ classificationLabel }}
        </div>
        <StockIndicator :segment="segment" :shelf="shelf" @click="(e) => handleLayerClick(e)" />
    </div>
</template>
<script setup lang="ts">
import { useEditorStore } from '@plannerate/store/editor';
import { Gondola } from '@plannerate/types/gondola';
import type { Layer, Segment } from '@plannerate/types/segment';
import type { Shelf } from '@plannerate/types/shelves';
import { validateShelfWidth } from '@plannerate/utils/validation';
import { computed, defineProps, ref, type CSSProperties } from 'vue';
import { toast } from 'vue-sonner';
import LayerComponent from './Layer.vue';
import StockIndicator from './StockIndicator.vue';
import { useSegmentDragAndDrop } from '@plannerate/composables/useSegmentDragAndDrop';
import { useTargetStockAnalysis } from '@plannerate/composables/useTargetStockAnalysis';
import { useAnalysisResultStore } from '@plannerate/store/editor/analysisResult';

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
const { targetStockResultStore } = useTargetStockAnalysis();

const {
    onDragStart,
    onDragEnd,
    handleDragEnter,
    handleDragOver,
    handleDragLeave,
    handleDrop
} = useSegmentDragAndDrop(props, emit);

const isTargetStockViewActive = computed(() => {
    return !!targetStockResultStore.result && !!props.segment?.layer?.product?.ean;
});


const analysisResultStore = useAnalysisResultStore();

const abcClass = computed(() => {
    if (!props.segment?.layer?.product) return null; // Default para evitar erros
    const productEan = props.segment.layer.product.ean;
    const classificationEntry = analysisResultStore.result?.find((p: any) => p.id === productEan);
    return classificationEntry?.abcClass; // Default para evitar erros
});

/**
 * Determina qual classificação mostrar (BCG tem prioridade sobre ABC)
 */
const productClassification = computed(() => {
    if (!props.segment?.layer?.product) return null;

    const product = props.segment.layer.product as any;

    // Se o produto tiver classificação BCG, usar ela
    if (product.classification) {
        return {
            type: 'BCG',
            value: product.classification
        };
    }

    // Caso contrário, usar ABC se disponível
    if (abcClass.value) {
        return {
            type: 'ABC',
            value: abcClass.value
        };
    }

    return null;
});

/**
 * Classes CSS para o badge baseado no tipo de classificação
 */
const classificationBadgeClass = computed(() => {
    if (!productClassification.value) return '';

    const { type, value } = productClassification.value;

    if (type === 'ABC') {
        // Cores para classificação ABC
        if (value === 'A') return 'bg-green-500';
        if (value === 'B') return 'bg-yellow-500';
        if (value === 'C') return 'bg-red-500';
    }

    if (type === 'BCG') {
        // Cores para classificação BCG (seguindo o padrão dos outros componentes)
        if (value === 'Alto valor - manutenção') return 'bg-green-600';
        if (value === 'Incentivo - volume') return 'bg-blue-500';
        if (value === 'Incentivo - lucro') return 'bg-purple-500';
        if (value === 'Incentivo - valor') return 'bg-orange-500';
        if (value === 'Baixo valor - avaliar') return 'bg-red-600';
    }

    return 'bg-gray-500';
});

/**
 * Título do tooltip com descrição da classificação
 */
const classificationTitle = computed(() => {
    if (!productClassification.value) return '';

    const { type, value } = productClassification.value;

    if (type === 'ABC') {
        const descriptions: Record<string, string> = {
            'A': 'Classificação ABC: Classe A - Alto valor',
            'B': 'Classificação ABC: Classe B - Valor médio',
            'C': 'Classificação ABC: Classe C - Baixo valor'
        };
        return descriptions[value] || `Classificação ABC: ${value}`;
    }

    if (type === 'BCG') {
        return `Classificação BCG: ${value}`;
    }

    return '';
});

/**
 * Label a ser exibido no badge
 */
const classificationLabel = computed(() => {
    if (!productClassification.value) return '';

    const { type, value } = productClassification.value;

    if (type === 'ABC') {
        return value; // Simplesmente 'A', 'B', ou 'C'
    }

    if (type === 'BCG') {
        // Abreviar os nomes longos da classificação BCG
        const abbreviations: Record<string, string> = {
            'Alto valor - manutenção': 'Alto Valor',
            'Incentivo - volume': 'Inc. Volume',
            'Incentivo - lucro': 'Inc. Lucro',
            'Incentivo - valor': 'Inc. Valor',
            'Baixo valor - avaliar': 'Baixo Valor'
        };
        return abbreviations[value] || value;
    }

    return '';
});

/**
 * Verifica se o layer está selecionado
 */
const isSelected = computed(() => {
    const layerId = props.segment.layer.id;
    // Usa selectedLayerIds (nome corrigido e agora existente)
    return editorStore.isSelectedLayer(String(layerId));
});

const currentSectionId = computed(() => props.shelf.section_id);
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

const segmentQuantity = computed(() => {
    return props.segment?.quantity ?? 0;
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
        marginBottom: `${marginBottom + 4}px`,
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
        // props.segment.layer.quantity -= 1;
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
const onIncreaseQuantity = ({ quantity }: any) => {
    if (!editorStore.getCurrentGondola?.id || !currentSectionId.value || !props.shelf?.id || !props.segment?.id || !props.segment?.layer?.product?.id) {
        toast.error('Erro Interno', { description: 'Dados incompletos para aumentar quantidade.' });
        return;
    }
    if (props.sectionWidth === undefined || props.sectionWidth <= 0) {
        toast.error('Erro Interno', { description: 'Largura da seção inválida.' });
        return;
    }
    const currentQuantity = props.segment.layer.quantity ?? 0;
    const newQuantity = quantity;

    const validation = validateShelfWidth(props.shelf, props.sectionWidth, props.segment.layer.product.id, newQuantity, null);

    if (!validation.isValid) {
        toast.error('Limite de Largura Excedido', {
            description: `A largura total (${validation.totalWidth.toFixed(1)}cm) excederia a largura da seção (${validation.sectionWidth}cm).`,
        });
        props.segment.layer.quantity = currentQuantity; // Reverter para a quantidade anterior
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
const onDecreaseQuantity = ({ quantity }: any) => {
    if (!editorStore.getCurrentGondola?.id || !currentSectionId.value || !props.shelf?.id || !props.segment?.id || !props.segment?.layer?.product?.id) {
        toast.error('Erro Interno', { description: 'Dados incompletos para diminuir quantidade.' });
        return;
    }
    if (props.sectionWidth === undefined || props.sectionWidth <= 0) {
        toast.error('Erro Interno', { description: 'Largura da seção inválida.' });
        return;
    }

    const currentQuantity = quantity ?? 0;
    if (currentQuantity > 0) {
        const newQuantity = currentQuantity;

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
const segmentSelected = ref(false);
/**
 * Manipula o clique no layer
 */
const handleLayerClick = (event: MouseEvent) => {
    event.stopPropagation();
    const productId = props.segment.layer.product?.id;
    const layerId = props.segment.layer.id;
    if (!productId) {
        console.error('Layer clicked, but product ID is missing.');
        return;
    }
    handleSelectedLayer(productId, layerId);
    // emit('layer-click', props.layer);
};

/**
 * Gerencia a seleção do layer
 */
const handleSelectedLayer = (productId: string, layerId: string) => {
    if (!productId) return;

    const layerIdAsString = String(layerId);
    const compositeId = layerIdAsString;

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
};

</script>

<style scoped>
.segment {
    position: relative;
}

.segment--selected {
    border: 2px solid blue;
    box-shadow: 0 0 5px rgba(0, 0, 255, 0.5);
    box-sizing: border-;
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
