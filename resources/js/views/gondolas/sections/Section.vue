<template>
    <div
        class="bg-gray-800"
        :style="sectionStyle"
        :data-section-id="section.id"
        @dragover.prevent="handleSectionDragOver"
        @drop.prevent="handleSectionDrop"
        @dragleave="handleSectionDragLeave"
        ref="sectionRef"
    >
        <!-- Conteúdo da Seção (Prateleiras) -->
        <Shelf
            v-for="shelf in section.shelves"
            :key="shelf.id"
            :shelf="shelf"
            :scale-factor="scaleFactor"
            :section-width="props.section.width"
            :section-height="props.section.height"
            :base-height="baseHeight"
            :rack-width="section.rackWidth || section.cremalheira_width || 4"
            :sections-container="sectionsContainer"
            :section-index="sectionIndex"
            @drop-product="handleProductDropOnShelf"
            @drop-layer-copy="handleLayerCopy"
            @drag-shelf="handleShelfDragStart"
        />
    </div>
</template>

<script setup lang="ts">
import { computed, defineEmits, defineProps, onMounted, onUnmounted, ref } from 'vue';
import { useSegmentService } from '../../../services/segmentService';
import { useShelfService } from '../../../services/shelfService';
import { useGondolaStore } from '../../../store/gondola';
import { useProductStore } from '../../../store/product';
import { useShelfStore } from '../../../store/shelf';
import { useToast } from './../../../components/ui/toast';
import Shelf from './Shelf.vue';
import { Layer, Product, Section, Segment, Shelf as ShelfType } from './types';

// Definir Props
const props = defineProps<{
    section: Section;
    scaleFactor: number;
    selectedCategory: any;
    sectionsContainer: HTMLElement | null;
    sectionIndex: number;
}>();

// Definir Emits
const emit = defineEmits(['update:segments']);

// Stores
const gondolaStore = useGondolaStore();
const productStore = useProductStore();
const shelfStore = useShelfStore();

// Services
const { toast } = useToast();
const segmentService = useSegmentService();
const shelfService = useShelfService();

// --- Estado para controle de drag and drop ---
const dropTargetActive = ref(false);
const draggingShelf = ref<ShelfType | null>(null);
const sectionRef = ref<HTMLElement | null>(null);

// --- Computeds para Estilos ---
const baseHeight = computed(() => {
    const baseHeightCm = props.section.base_height || 0;
    if (baseHeightCm <= 0) return 0;
    return baseHeightCm * props.scaleFactor;
});

const sectionStyle = computed(() => {
    return {
        width: `${props.section.width * props.scaleFactor}px`,
        height: `${props.section.height * props.scaleFactor}px`,
        position: 'relative' as const,
        borderWidth: '2px',
        borderStyle: dropTargetActive.value ? 'dashed' : 'solid',
        borderColor: dropTargetActive.value ? 'rgba(59, 130, 246, 0.5)' : 'transparent',
        backgroundColor: dropTargetActive.value ? 'rgba(59, 130, 246, 0.1)' : 'transparent',
        overflow: 'visible' as const,
        transition: 'border-color 0.2s ease-in-out, background-color 0.2s ease-in-out',
    };
});

// --- Helpers ---
const createSegmentFromProduct = (product: Product, shelf: ShelfType, layerQuantity: number): Segment => {
    return {
        gondolaId: gondolaStore.currentGondola.id,
        id: `segment-${Date.now()}-${shelf.segments?.length}`,
        width: parseInt(props.section.width.toString()),
        ordering: (shelf.segments?.length || 0) + 1,
        quantity: 1,
        shelf_id: shelf.id,
        section_id: props.section.id,
        spacing: 0,
        position: 0,
        preserveState: false,
        status: 'published',
        layer: {
            product_id: product.id,
            product: product,
            height: product.height,
            spacing: 0,
            quantity: layerQuantity,
            status: 'published',
        },
    };
};

// --- Lógica de Drag and Drop das Prateleiras ---
const handleShelfDragStart = (shelf: ShelfType) => {
    draggingShelf.value = shelf;
    console.log('Iniciando arrasto da prateleira:', shelf.id);
};

const handleSectionDragOver = (event: DragEvent) => {
    if (!event.dataTransfer) return;
    const isShelf = event.dataTransfer.types.includes('text/shelf');

    if (isShelf) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        dropTargetActive.value = true;
    }
};

const handleSectionDragLeave = () => {
    dropTargetActive.value = false;
};

const handleSectionDrop = async (event: DragEvent) => {
    if (!event.dataTransfer) return;

    const shelfData = event.dataTransfer.getData('text/shelf');

    if (shelfData) {
        try {
            const shelf = JSON.parse(shelfData);
            const mouseY = event.offsetY;
            const newPosition = mouseY / props.scaleFactor;
            const shelfHeight = draggingShelf.value?.shelf_height || 0;

            if (newPosition >= 0 && newPosition <= props.section.height - shelfHeight) {
                try {
                    const response = await shelfService.updateShelfPosition(shelf.id, newPosition);

                    // Atualizar o estado local
                    gondolaStore.updateShelf(shelf.id, {
                        shelf_position: newPosition,
                    });

                    toast({
                        title: 'Sucesso',
                        description: 'Posição da prateleira atualizada',
                        variant: 'default',
                    });
                } catch (error) {
                    console.error('Erro ao atualizar posição da prateleira:', error);
                    toast({
                        title: 'Erro',
                        description: 'Falha ao atualizar posição da prateleira',
                        variant: 'destructive',
                    });
                }
            } else {
                toast({
                    title: 'Aviso',
                    description: 'Posição de prateleira inválida',
                    variant: 'default',
                });
            }
        } catch (e) {
            console.error('Erro ao processar dados da prateleira:', e);
        }

        // Resetar estado
        draggingShelf.value = null;
        dropTargetActive.value = false;
    }
};

// --- Lógica de Eventos para Produtos ---
const handleProductDropOnShelf = async (product: Product, shelf: ShelfType, dropPosition: any) => {
    const newSegment = createSegmentFromProduct(product, shelf, 1);

    try {
        const response = await segmentService.addSegment(shelf.id, newSegment);
        gondolaStore.updateShelf(response.data.id, response.data);

        toast({
            title: 'Sucesso',
            description: response.message || 'Produto adicionado com sucesso',
            variant: 'default',
        });
    } catch (error) {
        console.error('Erro ao adicionar produto à prateleira:', error);
        toast({
            title: 'Erro',
            description: error.response?.data?.message || 'Falha ao adicionar produto à prateleira',
            variant: 'destructive',
        });
    }
};

const handleLayerCopy = async (layer: Layer, shelf: ShelfType, dropPosition: any) => {
    const newSegment = createSegmentFromProduct(layer.product, shelf, layer.quantity);
    try {
        const response = await segmentService.copySegment(shelf.id, newSegment);
        gondolaStore.updateShelf(response.data.id, response.data, false);

        toast({
            title: 'Sucesso',
            description: response.message || 'Camada copiada com sucesso',
            variant: 'default',
        });
    } catch (error) {
        console.error('Erro ao copiar camada para a prateleira:', error);
        toast({
            title: 'Erro',
            description: error.response?.data?.message || 'Falha ao copiar camada',
            variant: 'destructive',
        });
    }
};

// --- Event Handlers for Global Listeners ---
const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        productStore.clearSelection();
    }
};

const handleClickOutside = (event: MouseEvent) => {
    const clickedElement = event.target as HTMLElement;
    if (clickedElement.closest('.border-destructive')) return;
    if (clickedElement.dataset.state) return;
    if (clickedElement.closest('.no-remove-properties')) return;

    if (!clickedElement.closest('.layer')) {
        productStore.clearSelection();
    }
    if (!clickedElement.closest('.shelf')) {
        shelfStore.clearSelection();
        shelfStore.clearShelfSelectedIs();
    }
};

const handleDoubleClick = async (event: MouseEvent) => {
    const newShelf: ShelfType = {
        id: `shelf-${Date.now()}`,
        name: `shelf-${Date.now()}`,
        gondola_id: gondolaStore.currentGondola.id,
        section_id: props.section.id,
        shelf_position: event.offsetY / props.scaleFactor,
        shelf_height: 4,
        quantity: 0,
        spacing: 0,
        ordering: 1,
        status: 'published',
        segments: [],
    } as ShelfType;

    try {
        const response = await shelfService.addShelf(newShelf);
        shelfStore.addShelf(response.data || newShelf);

        toast({
            title: 'Sucesso',
            description: 'Nova prateleira adicionada',
            variant: 'default',
        });
    } catch (error) {
        console.error('Erro ao adicionar prateleira:', error);
        toast({
            title: 'Erro',
            description: 'Falha ao adicionar prateleira',
            variant: 'destructive',
        });
    }

    event.stopPropagation();
};

// --- Lifecycle Hooks for Listeners ---
onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    document.addEventListener('click', handleClickOutside, true);
    if (sectionRef.value) {
        sectionRef.value.addEventListener('dblclick', handleDoubleClick);
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    document.removeEventListener('click', handleClickOutside, true);
    if (sectionRef.value) {
        sectionRef.value.removeEventListener('dblclick', handleDoubleClick);
    }
});
</script>

<style scoped>
.section-container > .absolute.bottom-0 {
    z-index: -1;
}

.section-drag-over {
    background-color: rgba(59, 130, 246, 0.05);
    border: 2px dashed rgba(59, 130, 246, 0.5);
}
</style>
