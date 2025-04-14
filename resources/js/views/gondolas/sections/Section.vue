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
            @drag-shelf="handleShelfDragStart"
        />
    </div>
</template>

<script setup lang="ts">
import { computed, defineEmits, defineProps, onMounted, onUnmounted, ref } from 'vue';
import { apiService } from '../../../services';
import { useGondolaStore } from '../../../store/gondola';
import { useProductStore } from '../../../store/product';
import { useShelfStore } from '../../../store/shelf';
import { useToast } from './../../../components/ui/toast';
import Shelf from './Shelf.vue'; // Importar o componente Shelf
import { Product, Section, Segment, Shelf as ShelfType } from './types';

// Definir Props
const props = defineProps<{
    section: Section;
    scaleFactor: number;
    selectedCategory: any; // Defina o tipo correto para selectedCategory
    sectionsContainer: HTMLElement | null; // Referência ao container das seções
    sectionIndex: number; // Índice da seção atual
}>();

// Definir Emits (se a Section precisar emitir eventos para cima)
const emit = defineEmits(['update:segments']); // Exemplo: se precisar emitir atualizações de segmentos
const gondolaStore = useGondolaStore(); // Instanciar o gondola store
const productStore = useProductStore(); // Instantiate product store
const shelfStore = useShelfStore(); // Instanciar o shelf store
// Services
const { toast } = useToast();

// --- Estado para controle de drag and drop ---
const dropTargetActive = ref(false);
const draggingShelf = ref<ShelfType | null>(null);
const draggingSection = ref(false);
const sectionRef = ref<HTMLElement | null>(null);

// --- Computeds para Estilos ---

// Altura da base em pixels
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

// --- Lógica de Drag and Drop das Prateleiras ---

// Quando uma prateleira começa a ser arrastada
const handleShelfDragStart = (shelf: ShelfType) => {
    draggingShelf.value = shelf;
    console.log('Iniciando arrasto da prateleira:', shelf.id);
};

// Quando algo está sendo arrastado sobre a seção
const handleSectionDragOver = (event: DragEvent) => {
    if (!event.dataTransfer) return;
    // Verificar o tipo de dados sendo arrastado
    const isShelf = event.dataTransfer.types.includes('text/shelf');

    if (isShelf) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';

        // Ativar feedback visual
        dropTargetActive.value = true;
    }
};

// Quando o elemento arrastado sai da área da seção
const handleSectionDragLeave = (event: DragEvent) => {
    dropTargetActive.value = false;
};

// Quando algo é solto na seção
const handleSectionDrop = (event: DragEvent) => {
    if (!event.dataTransfer) return;

    const shelfData = event.dataTransfer.getData('text/shelf');

    if (shelfData) {
        try {
            const shelf = JSON.parse(shelfData);

            // Calcular a nova posição baseada na posição do mouse
            const mouseY = event.offsetY;
            const newPosition = mouseY / props.scaleFactor;

            // Verificar se a posição é válida (dentro dos limites da seção)
            // Obtém a altura da prateleira para garantir que ela não ultrapasse o limite inferior
            const shelfHeight = draggingShelf.value?.shelf_height || 0;

            if (newPosition >= 0 && newPosition <= props.section.height - shelfHeight) {
                // Atualizar a posição da prateleira via API
                try {
                    apiService
                        .patch(`shelves/${shelf.id}`, {
                            shelf_position: newPosition,
                        })
                        .then((response) => {
                            // Atualizar o estado local
                            gondolaStore.updateShelf(shelf.id, {
                                shelf_position: newPosition,
                            });

                            toast({
                                title: 'Success',
                                description: 'Shelf position updated',
                                variant: 'default',
                            });
                        });
                } catch (error) {
                    console.error('Erro ao atualizar posição da prateleira:', error);
                    toast({
                        title: 'Error',
                        description: 'Failed to update shelf position',
                        variant: 'destructive',
                    });
                }
            } else {
                toast({
                    title: 'Warning',
                    description: 'Invalid shelf position',
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

/**
 * Lida com o evento drop-product emitido por um componente Shelf.
 * @param {object} eventData - Dados do evento { product, shelfId, dropPosition }.
 */
const handleProductDropOnShelf = (product: Product, shelf: ShelfType, dropPosition: any) => {
    // Lógica existente para adicionar produtos
    const newSegment: Segment = {
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
        // Cria layer com informações do produto
        layer: {
            product_id: product.id,
            product_name: product.name,
            product_image: product.image,
            product: product,
            height: product.height,
            spacing: 0,
            quantity: 1,
            status: 'published',
        },
    };

    // Adiciona o novo segmento à prateleira
    apiService
        .post(`shelves/${shelf.id}/segments`, {
            segment: newSegment,
        })
        .then((response) => {
            gondolaStore.updateShelf(response.data.id, response.data);

            toast({
                title: 'Success',
                description: response.message,
                variant: 'default',
            });
        })
        .catch((error) => {
            console.error('Erro ao adicionar produto à prateleira:', error);
            toast({
                title: 'Error',
                description: error.response?.data?.message || 'Failed to add product to shelf',
                variant: 'destructive',
            });
        });
};

// --- Event Handlers for Global Listeners ---

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        productStore.clearSelection();
    }
};

const handleClickOutside = (event: MouseEvent) => {
    // Check if the click target or any of its parents has the class 'layer'
    // We assume layers are the selectable elements we want to ignore clicks inside of.
    const clickedElement = event.target as HTMLElement;
    if (clickedElement.closest('.border-destructive')) {
        // If the click was inside an element with the 'layer' class, do nothing.
        return;
    }
    if (clickedElement.dataset.state) {
        // If the click was inside an element with the 'layer' class, do nothing.
        return;
    }
    if (clickedElement.closest('.no-remove-properties')) {
        // If the click was inside an element with the 'no-remove-properties' class, do nothing.
        return;
    }
    if (!clickedElement.closest('.layer')) {
        // If the click was outside any element with the 'layer' class (or its children),
        // clear the selection.
        productStore.clearSelection();
    }
};
const handleDoubleClick = (event: any) => {
    // Emitir evento para o componente pai (Section) lidar com o clique
    shelfStore.addShelf({
        id: `shelf-${Date.now()}`,
        name: `shelf-${Date.now()}`,
        gondola_id: gondolaStore.currentGondola.id,
        section_id: props.section.id,
        shelf_position: event.offsetY / props.scaleFactor,
        shelf_height: 4,
        quantity: 0,
        spacing: 0,
        ordering: 1,
        segments: [],
    } as ShelfType);

    event.stopPropagation(); // Impede que o evento se propague para outros manipuladores
};
// --- Lifecycle Hooks for Listeners ---

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    document.addEventListener('click', handleClickOutside, true); // Use capture phase to intercept clicks early
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
/* Adiciona um z-index para garantir que a base fique atrás do conteúdo */
.section-container > .absolute.bottom-0 {
    z-index: -1;
}

/* Estilos para feedback visual durante arrasto */
.section-drag-over {
    background-color: rgba(59, 130, 246, 0.05);
    border: 2px dashed rgba(59, 130, 246, 0.5);
}
</style>
