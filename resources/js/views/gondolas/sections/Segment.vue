<template>
    <div class="segment drag-segment-handle group relative flex items-center " :style="segmentStyle"
        @dragstart="onDragStart" draggable="true" :tabindex="segment.tabindex" v-if="segment.layer">
        <LayerComponent v-for="(_, index) in segmentQuantity" :key="index" :shelf="shelf" :segment="segment"
            :layer="segment.layer" :scale-factor="scaleFactor" :section-width="sectionWidth"
            :shelf-depth="shelf.shelf_depth" @increase="onIncreaseQuantity" @decrease="onDecreaseQuantity">
            <template #depth-count>

                <Label :title="`Profundidade da prateleira: ${depthCount}`"
                    class="product-content-depth absolute -top-2 -left-2 text-xs text-gray-100 bg-gray-700 
                             flex items-center justify-center h-3 w-3 rounded-full  z-10 dark:text-gray-800 dark:bg-gray-300 cursor-help">
                    {{ depthCount }}</Label>
            </template>
        </LayerComponent>
    </div>
</template>
<script setup lang="ts">
import {
    computed,
    defineProps,
    type CSSProperties,
} from 'vue';
import { useEditorStore } from '@plannerate/store/editor'; // <-- ADICIONAR
import type { Segment } from '@plannerate/types/segment';
import type { Shelf } from '@plannerate/types/shelves';
import type { Section } from '@plannerate/types/sections'; // <-- ADICIONAR IMPORT
import LayerComponent from './Layer.vue';
import { Gondola } from '@plannerate/types/gondola';
import { useToast } from '@plannerate/components/ui/toast'; // <-- Importar useToast
import { validateShelfWidth } from '@plannerate/utils/validation'; // <-- Importar validação

// Definir Props
const props = defineProps<{
    gondola: Gondola;
    segment: Segment;
    shelf: Shelf;
    scaleFactor: number;
    sectionWidth: number;
}>();

const editorStore = useEditorStore(); // <-- INSTANCIAR EDITOR STORE
const { toast } = useToast(); // <-- Instanciar toast

const currentSectionId = computed(() => props.shelf.section_id);


const shelfType = computed(() => props.shelf.product_type);

const depthCount = computed(() => {
    const depth = props.segment.layer.product.depth;
    if (!depth) return 0;
    return Math.round(props.shelf.shelf_depth / depth);
});

/** Segment quantity (number of layers) */
const segmentQuantity = computed(() => {
    return props.segment?.quantity ?? 0;
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
    props.gondola.sections.map((section: Section) => {
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
// Corrigido: Determina o alinhamento efetivo do segmento seguindo a hierarquia
const alignment = computed(() => props.gondola.alignment);

const segmentStyle = computed(() => {
    const layerHeight = props.segment.layer.product.height * props.scaleFactor;
    const productWidth = props.segment.layer.product.width;
    const productQuantity = props.segment.layer.quantity;
    let layerWidthFinal = 0;

    let currentAlignment = alignment.value;


    if (currentAlignment === 'justify') {
        layerWidthFinal = productWidth * productQuantity * props.scaleFactor + layerWidth();
    } else {
        layerWidthFinal = productWidth * productQuantity * props.scaleFactor;
    }

    const totalWidth = layerWidthFinal;
    const selectedStyle = {};
  if (shelfType.value === 'hook') {
        return {
        height: `${layerHeight}px`,
        width: `${totalWidth}px`,
        transform: `translateY(100%) !important`,
        ...selectedStyle,
    } as CSSProperties;
    }
 return {
        height: `${layerHeight}px`,
        width: `${totalWidth}px`,
        marginBottom: `${props.shelf.shelf_height * props.scaleFactor}px`,
        ...selectedStyle,
    } as CSSProperties;
   
});

// Funções para ajustar a quantidade
const onIncreaseQuantity = () => {
    if (!props.gondola?.id || !currentSectionId.value || !props.shelf?.id || !props.segment?.id || !props.segment?.layer?.product?.id) {
        console.error("onIncreaseQuantity: IDs faltando para validação/atualização.");
        toast({ title: "Erro Interno", description: "Dados incompletos para aumentar quantidade.", variant: "destructive" });
        return;
    }
    if (props.sectionWidth === undefined || props.sectionWidth <= 0) {
        console.error("onIncreaseQuantity: Largura da seção (sectionWidth) inválida ou não fornecida.");
        toast({ title: "Erro Interno", description: "Largura da seção inválida.", variant: "destructive" });
        return;
    }

    const currentQuantity = props.segment.layer?.quantity ?? 0;
    const newQuantity = currentQuantity + 1;

    const validation = validateShelfWidth(
        props.shelf,
        props.sectionWidth,
        props.segment.layer.product.id,
        newQuantity,
        null
    );

    if (!validation.isValid) {
        toast({
            title: "Limite de Largura Excedido",
            description: `A largura total (${validation.totalWidth.toFixed(1)}cm) excederia a largura da seção (${validation.sectionWidth}cm).`,
            variant: "destructive",
        });
        return;
    }

    editorStore.updateLayerQuantity(
        props.gondola.id,
        currentSectionId.value,
        props.shelf.id,
        props.segment.id,
        props.segment.layer.product.id,
        newQuantity
    );
};

const onDecreaseQuantity = () => {
    if (!props.gondola?.id || !currentSectionId.value || !props.shelf?.id || !props.segment?.id || !props.segment?.layer?.product?.id) {
        console.error("onDecreaseQuantity: IDs faltando para validação/atualização.");
        toast({ title: "Erro Interno", description: "Dados incompletos para diminuir quantidade.", variant: "destructive" });
        return;
    }
    if (props.sectionWidth === undefined || props.sectionWidth <= 0) {
        console.error("onDecreaseQuantity: Largura da seção (sectionWidth) inválida ou não fornecida.");
        toast({ title: "Erro Interno", description: "Largura da seção inválida.", variant: "destructive" });
        return;
    }

    const currentQuantity = props.segment.layer?.quantity ?? 0;
    if (currentQuantity > 0) {
        const newQuantity = currentQuantity - 1;

        const validation = validateShelfWidth(
            props.shelf,
            props.sectionWidth,
            props.segment.layer.product.id,
            newQuantity,
            null
        );

        if (!validation.isValid) {
            toast({
                title: "Erro de Cálculo",
                description: `Ocorreu um erro ao validar a largura após diminuir. (${validation.totalWidth.toFixed(1)}cm > ${validation.sectionWidth}cm)`,
                variant: "destructive",
            });
            return;
        }

        editorStore.updateLayerQuantity(
            props.gondola.id,
            currentSectionId.value,
            props.shelf.id,
            props.segment.id,
            props.segment.layer.product.id,
            newQuantity
        );
    }
};

 

/**
 * Configura dados para arrastar o segmento
 */
const onDragStart = (event: DragEvent) => {
    if (!event.dataTransfer) return;

    const isCtrlOrMetaPressed = event.ctrlKey || event.metaKey;

    // Incluir explicitamente o shelf_id da origem
    const segmentData = {
        ...props.segment
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
</style>
