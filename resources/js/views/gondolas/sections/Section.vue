<template>
    <ContextMenu>
        <ContextMenuTrigger>
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
                    :sections-container="sectionsContainer"
                    :section-index="sectionIndex"
                    @drop-product="handleProductDropOnShelf"
                    @drop-layer-copy="handleLayerCopy"
                    @drag-shelf="handleShelfDragStart"
                />
            </div>
        </ContextMenuTrigger>
        <ContextMenuContent class="w-64">
            <ContextMenuRadioGroup model-value="modulos">
                <ContextMenuLabel inset> Modulos </ContextMenuLabel>
                <ContextMenuSeparator />
                <ContextMenuItem inset @click="editSection()">
                    Editar
                    <ContextMenuShortcut>⌘E</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem inset @click="(e: MouseEvent) => addShelf(e)">
                    Adicionar prateleira
                    <ContextMenuShortcut>⌘A</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuItem inset @click="inverterModule()">
                    Inverter ordem
                    <ContextMenuShortcut>⌘I</ContextMenuShortcut>
                </ContextMenuItem>
                <ContextMenuSeparator />
                <ContextMenuSub>
                    <ContextMenuSubTrigger inset> Alinhamento </ContextMenuSubTrigger>
                    <ContextMenuSubContent class="w-48">
                        <ContextMenuItem inset @click="justifyModule('left')">
                            à esquerda
                            <ContextMenuShortcut>⌘⇧L</ContextMenuShortcut>
                        </ContextMenuItem>
                        <ContextMenuItem inset @click="justifyModule('justify')">
                            ao centro
                            <ContextMenuShortcut>⌘⇧C</ContextMenuShortcut>
                        </ContextMenuItem>
                        <ContextMenuItem inset @click="justifyModule('right')">
                            à direita
                            <ContextMenuShortcut>⌘⇧R</ContextMenuShortcut>
                        </ContextMenuItem>
                    </ContextMenuSubContent>
                </ContextMenuSub>
                <ContextMenuSeparator />
                <ContextMenuItem inset disabled>
                    Excluir
                    <ContextMenuShortcut>⌘D</ContextMenuShortcut>
                </ContextMenuItem>
            </ContextMenuRadioGroup>
        </ContextMenuContent>
    </ContextMenu>
</template>

<script setup lang="ts">
import { computed, defineEmits, defineProps, onMounted, onUnmounted, ref } from 'vue';
import { useSegmentService } from '../../../services/segmentService';
import { useShelfService } from '../../../services/shelfService'; 
import { useProductStore } from '../../../store/product';
import { useSectionStore } from '../../../store/section';
import { useShelvesStore } from '../../../store/shelves';
import { useEditorStore } from '../../../store/editor';
import { Section } from '../../../types/sections';
import { Layer, Product, Segment } from '../../../types/segment';
import { Shelf as ShelfType } from '../../../types/shelves';
import { useToast } from './../../../components/ui/toast';
import Shelf from './Shelf.vue';

// Definir Props
const props = defineProps<{
    gondolaId: string | undefined;
    section: Section;
    scaleFactor: number;
    selectedCategory: any;
    sectionsContainer: HTMLElement | null;
    sectionIndex: number;
}>();

// Definir Emits
const emit = defineEmits(['update:segments']);

// Stores 
const productStore = useProductStore();
const shelvesStore = useShelvesStore();
const sectionStore = useSectionStore();
const editorStore = useEditorStore();

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

const editSection = () => {
    // Emitir evento para abrir o modal de edição da seção
    sectionStore.setSelectedSection(props.section);
    sectionStore.startEditing();
};

const addShelf = async (event: MouseEvent) => {
    shelvesStore.handleDoubleClick({
        shelf_position: event.offsetY / props.scaleFactor,
        section_id: props.section.id,
    });
    event.stopPropagation();
};

const justifyModule = (alignment: string) => {
    sectionStore.justifyProducts(props.section, alignment);
};

const inverterModule = () => {
    if (!props.gondolaId) {
        console.warn('Não é possível inverter prateleiras: gondolaId não fornecido.');
        return;
    }
    editorStore.invertShelvesInSection(props.gondolaId, props.section.id);
};

// --- Helpers ---
const createSegmentFromProduct = (product: Product, shelf: ShelfType, layerQuantity: number): Segment => {
    const segmentId = `segment-${Date.now()}-${shelf.segments?.length}`;
    return { 
        user_id: null,
        tenant_id: '',
        width: parseInt(props.section.width.toString()),
        ordering: (shelf.segments?.length || 0) + 1,
        quantity: 1,
        shelf_id: shelf.id,
        spacing: 0,
        position: 0,
        alignment: '',
        settings: null,
        status: 'published',
        layer: {
            id: `layer-${Date.now()}`,
            product_id: product.id,
            product: product,
            quantity: layerQuantity,
            status: 'published',
            height: product.height,
            segment_id: segmentId,
            
        }
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
                    shelvesStore.updateShelf(shelf.id, {
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
        shelvesStore.updateShelf(response.data.id, response.data);

        toast({
            title: 'Sucesso',
            description: response.message || 'Produto adicionado com sucesso',
            variant: 'default',
        });
    } catch (error) {
        console.error('Erro ao adicionar produto à prateleira:', error);
        let errorMessage = 'Falha ao adicionar produto à prateleira';
        
        // Tentar extrair mensagem do erro da API
        if (typeof error === 'object' && error !== null && 
            (error as any).response && (error as any).response.data && 
            typeof (error as any).response.data.message === 'string') {
            errorMessage = (error as any).response.data.message;
        } else if (error instanceof Error) {
            // Se não for erro de API, pegar mensagem do Error padrão
            errorMessage = error.message;
        }

        toast({
            title: 'Erro',
            description: errorMessage,
            variant: 'destructive',
        });
    }
};

const handleLayerCopy = async (layer: Layer, shelf: ShelfType, dropPosition: any) => {
    const newSegment = createSegmentFromProduct(layer.product, shelf, layer.quantity);
    try {
        const response = await segmentService.copySegment(shelf.id, newSegment);
        shelvesStore.updateShelf(response.data.id, response.data, false);

        toast({
            title: 'Sucesso',
            description: response.message || 'Camada copiada com sucesso',
            variant: 'default',
        });
    } catch (error) {
        console.error('Erro ao copiar camada para a prateleira:', error);
        let errorMessage = 'Falha ao copiar camada';

        // Tentar extrair mensagem do erro da API
        if (typeof error === 'object' && error !== null && 
            (error as any).response && (error as any).response.data && 
            typeof (error as any).response.data.message === 'string') {
            errorMessage = (error as any).response.data.message;
        } else if (error instanceof Error) {
            // Se não for erro de API, pegar mensagem do Error padrão
            errorMessage = error.message;
        }

        toast({
            title: 'Erro',
            description: errorMessage,
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

    if (!clickedElement.closest('.shelves')) {
        shelvesStore.clearSelection();
        shelvesStore.clearSelectedShelfIds();
    }

    if (!clickedElement.closest('.sections')) {
        sectionStore.setSelectedSection(null);
        sectionStore.clearSelectedSectionIds();
    }
};

const handleDoubleClick = async (event: MouseEvent) => {
    event.stopPropagation();
    shelvesStore.handleDoubleClick({
        shelf_position: event.offsetY / props.scaleFactor,
        section_id: props.section.id,
    });
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
